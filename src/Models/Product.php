<?php

namespace App\Models;

class Product
{
    public static function getAllProducts($db, $categoryName = null)
    {
        $query = "
            SELECT DISTINCT
                p.id AS product_id,
                p.in_stock,
                p.name,
                pp.amount AS price,
                pp.currency_label,
                pp.currency_symbol,
                c.id AS category_id,
                c.name AS category_name,
                pi.image_url AS first_image
            FROM
                products p
            LEFT JOIN
                product_prices pp ON p.id = pp.product_id
            LEFT JOIN
                categories c ON p.category_id = c.id
            LEFT JOIN
                product_images pi ON p.id = pi.product_id
            WHERE
                pi.id = (
                    SELECT MIN(pi_sub.id)
                    FROM product_images pi_sub
                    WHERE pi_sub.product_id = p.id
                )
        ";

        if ($categoryName) {
            $query .= " AND c.name = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param('s', $categoryName);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();
        }

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = [
                'product_id' => $row['product_id'],
                'in_stock' => $row['in_stock'],
                'name' => $row['name'],
                'price' => $row['price'],
                'currency_label' => $row['currency_label'],
                'currency_symbol' => $row['currency_symbol'],
                'category' => [
                    'id' => $row['category_id'],
                    'name' => $row['category_name'],
                ],
                'first_image' => $row['first_image'],
            ];
        }

        return $products;
    }

    public static function getAllProductAttributes($db)
    {
        $query = "
    SELECT
    p.id AS product_id,
    pa.attribute_name,
    GROUP_CONCAT(DISTINCT pa.attribute_value ORDER BY 
        CASE
            -- Custom order for size: Small, Medium, Large, Extra Large
            WHEN pa.attribute_name = 'Size' THEN
                CASE pa.attribute_value
                    WHEN 'Small' THEN 1
                    WHEN 'Medium' THEN 2
                    WHEN 'Large' THEN 3
                    WHEN 'Extra Large' THEN 4
                    ELSE 5
                END
            -- Custom order for capacity: 256GB, 512GB, 1TB
            WHEN pa.attribute_name = 'capacity' THEN
                CASE pa.attribute_value
                    WHEN '256GB' THEN 1
                    WHEN '512GB' THEN 2
                    WHEN '1TB' THEN 3
                    ELSE 4
                END
            ELSE 0
        END ASC, pa.attribute_value ASC
        SEPARATOR ','
    ) AS attribute_values
FROM
    products p
LEFT JOIN
    product_attributes pa ON p.id = pa.product_id
GROUP BY
    p.id, pa.attribute_name;
    ";

        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();

        $attributesByProduct = [];
        while ($row = $result->fetch_assoc()) {
            $product_id = $row['product_id'];

            if (!isset($attributesByProduct[$product_id])) {
                $attributesByProduct[$product_id] = [
                    'product_id' => $product_id,
                    'attributes' => []
                ];
            }

            // Ensure attribute values are strings and handle empty values properly
            $values = explode(',', $row['attribute_values']);
            $values = array_filter($values, function ($value) {
                return !empty($value);
            });

            $attributesByProduct[$product_id]['attributes'][] = [
                'name' => $row['attribute_name'],
                'values' => $values
            ];
        }

        return array_values($attributesByProduct);
    }





    public static function getProductDetails($db, $productId)
    {
        $query = "
         SELECT
    p.id AS product_id,
    p.name,
    p.in_stock,
    p.description AS description,
    pp.amount AS price,
    pp.currency_label AS currency_label,
    pp.currency_symbol AS currency_symbol,
    c.id AS category_id,
    c.name AS category_name,
    GROUP_CONCAT(DISTINCT pi.image_url ORDER BY pi.id ASC SEPARATOR ';') AS image_urls,
    pa.attribute_name,
    GROUP_CONCAT(
        DISTINCT pa.attribute_value 
        ORDER BY 
            CASE 
                -- Custom order for Size: Small, Medium, Large, Extra Large
                WHEN pa.attribute_name = 'Size' THEN
                    CASE pa.attribute_value
                        WHEN 'Small' THEN 1
                        WHEN 'Medium' THEN 2
                        WHEN 'Large' THEN 3
                        WHEN 'Extra Large' THEN 4
                        ELSE 5
                    END
                -- Custom order for Capacity: 256GB, 512GB, 1TB
                WHEN pa.attribute_name = 'capacity' THEN
                    CASE pa.attribute_value
                        WHEN '256GB' THEN 1
                        WHEN '512GB' THEN 2
                        WHEN '1TB' THEN 3
                        ELSE 4
                    END
                ELSE 0
            END ASC, 
            pa.attribute_value ASC
        SEPARATOR ','
    ) AS attribute_values
FROM
    products p
INNER JOIN
    product_prices pp ON p.id = pp.product_id
INNER JOIN
    categories c ON p.category_id = c.id
LEFT JOIN
    product_images pi ON p.id = pi.product_id
LEFT JOIN
    product_attributes pa ON p.id = pa.product_id
WHERE
    p.id = ?
GROUP BY
    p.id, pa.attribute_name;
        ";

        $stmt = $db->prepare($query);
        $stmt->bind_param('s', $productId);
        $stmt->execute();
        $result = $stmt->get_result();

        $productDetails = [
            'product_id' => null,
            'name' => null,
            'in_stock' => null,
            'description' => null,
            'price' => null,
            'currency_label' => null,
            'currency_symbol' => null,
            'category' => [
                'id' => null,
                'name' => null
            ],
            'images' => [],
            'attributes' => []
        ];

        while ($row = $result->fetch_assoc()) {
            // Populate basic product details (only once)
            if (!$productDetails['product_id']) {
                $productDetails['product_id'] = $row['product_id'];
                $productDetails['name'] = $row['name'];
                $productDetails['in_stock'] = $row['in_stock'];
                $productDetails['description'] = $row['description'];
                $productDetails['price'] = $row['price'];
                $productDetails['currency_label'] = $row['currency_label'];
                $productDetails['currency_symbol'] = $row['currency_symbol'];
                $productDetails['category'] = [
                    'id' => $row['category_id'],
                    'name' => $row['category_name']
                ];

                // Handle images
                if ($row['image_urls']) {
                    $productDetails['images'] = explode(';', $row['image_urls']);
                }
            }

            // Handle attributes
            if ($row['attribute_name'] && $row['attribute_values']) {
                $productDetails['attributes'][$row['attribute_name']] = explode(',', $row['attribute_values']);
            }
        }

        return $productDetails;
    }
}
