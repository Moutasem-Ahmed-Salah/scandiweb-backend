<?php

namespace App\Models;

class Cart
{
    public static function viewCart($db)
    {
        $query = "
            SELECT 
                c.cartitemID, 
                c.productID, 
                pr.name, 
                p.amount AS price_per_unit, 
                c.quantity, 
                p.currency_symbol, 
                c.color, 
                c.size, 
                c.capacity,
                c.touch_id,
                c.usb_port,
                pi.image_url AS first_image 
            FROM 
                cart c 
            JOIN 
                products pr ON c.productID = pr.id 
            JOIN 
                product_prices p ON c.priceID = p.id 
            LEFT JOIN 
                product_images pi ON pr.id = pi.product_id 
            AND pi.id = (
                SELECT MIN(id) 
                FROM product_images 
                WHERE product_id = pr.id
            )
        ";

        $result = $db->query($query);
        $cartItems = [];

        while ($row = $result->fetch_assoc()) {
            $cartItems[] = [
                'cartitemID' => $row['cartitemID'],
                'productID' => $row['productID'],
                'name' => $row['name'],
                'price_per_unit' => $row['price_per_unit'],
                'quantity' => $row['quantity'],
                'currency_symbol' => $row['currency_symbol'],
                'color' => $row['color'],
                'size' => $row['size'],
                'capacity' => $row['capacity'],
                'first_image' => $row['first_image'],
                'touch_id' => $row['touch_id'],
                'usb_port' => $row['usb_port'],
            ];
        }

        return $cartItems;
    }

    public static function addToCart($db, $productID, $quantity, $color = null, $size = null, $capacity = null, $usb_port = null, $touchID = null)
    {
        $query = "
            INSERT INTO cart (productID, priceID, quantity, color, size, capacity, usb_port, touch_id) 
            VALUES (?, (SELECT id FROM product_prices WHERE product_id = ?), ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ";

        $stmt = $db->prepare($query);

        if ($stmt === false) {
            error_log('Prepare failed: ' . $db->error);
            return null;
        }

        $stmt->bind_param('ssisssss', $productID, $productID, $quantity, $color, $size, $capacity, $usb_port, $touchID);

        $execute = $stmt->execute();

        if ($execute === false) {
            error_log('Execute failed: ' . $stmt->error);
            return null;
        }

        return $db->insert_id;
    }

    public static function QuickAddToCart($db, $productID)
    {
        $query = "
            INSERT INTO cart (productID, priceID, quantity, size, capacity, color, touch_id, usb_port)
            VALUES (
                ?,
                (SELECT id FROM product_prices WHERE product_id = ? limit 1),
                1,
                (SELECT attribute_value FROM product_attributes WHERE product_id = ? AND attribute_name = 'Size'  LIMIT 1),
                (SELECT attribute_value FROM product_attributes WHERE product_id = ? AND attribute_name = 'Capacity'  LIMIT 1),  
                (SELECT attribute_value FROM product_attributes WHERE product_id = ? AND attribute_name = 'Color' ORDER BY attribute_value ASC LIMIT 1),
                (SELECT attribute_value FROM product_attributes WHERE product_id = ? AND attribute_name = 'Touch ID in keyboard' ORDER BY attribute_value ASC LIMIT 1),
                (SELECT attribute_value FROM product_attributes WHERE product_id = ? AND attribute_name = 'With USB 3 ports' ORDER BY attribute_value ASC LIMIT 1)
            )
            ON DUPLICATE KEY UPDATE quantity = quantity + 1
        ";

        $stmt = $db->prepare($query);

        if ($stmt === false) {
            error_log('Prepare failed: ' . $db->error);
            return null;
        }

        $stmt->bind_param('sssssss', $productID, $productID, $productID, $productID, $productID, $productID, $productID);

        $execute = $stmt->execute();

        if ($execute === false) {
            error_log('Execute failed: ' . $stmt->error);
            return null;
        }

        return $db->insert_id;
    }

    public static function DeleteCartItem($db, $cartitemID)
    {
        $query = "
            DELETE FROM cart WHERE cartitemID = ?
        ";

        $stmt = $db->prepare($query);

        if ($stmt === false) {
            error_log('Prepare failed: ' . $db->error);
            return null;
        }

        $stmt->bind_param('i', $cartitemID);

        $execute = $stmt->execute();

        if ($execute === false) {
            error_log('Execute failed: ' . $stmt->error);
            return null;
        }

        return $execute;
    }

    public static function updateCartItem($db, $cartitemID, $quantity)
    {
        $query = "
            UPDATE cart SET quantity = ? WHERE cartitemID = ?
        ";

        $stmt = $db->prepare($query);

        if ($stmt === false) {
            error_log('Prepare failed: ' . $db->error);
            return null;
        }

        $stmt->bind_param('ii', $quantity, $cartitemID);

        $execute = $stmt->execute();

        if ($execute === false) {
            error_log('Execute failed: ' . $stmt->error);
            return null;
        }

        return $execute;
    }

    public static function placeOrder($db, $total)
    {
        // Fetch all cart items
        $cartItems = self::viewCart($db);

        $orderDetails = json_encode($cartItems); // Convert cart items to JSON

        $query = "
            INSERT INTO orders (total, orderDetails) 
            VALUES (?, ?)
        ";

        $stmt = $db->prepare($query);

        if ($stmt === false) {
            error_log('Prepare failed: ' . $db->error);
            return null;
        }

        $stmt->bind_param('ds', $total, $orderDetails);

        $execute = $stmt->execute();

        if ($execute === false) {
            error_log('Execute failed: ' . $stmt->error);
            return null;
        }

        // Empty the cart after placing the order
        self::emptyCart($db);

        return $db->insert_id;
    }

    public static function emptyCart($db)
    {
        $query = "
            DELETE FROM cart
        ";

        $execute = $db->query($query);

        if ($execute === false) {
            error_log('Execute failed: ' . $db->error);
            return null;
        }

        return $execute;
    }
}
