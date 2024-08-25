<?php

namespace App\Controller;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\GraphQL;
use Dotenv\Dotenv;
use mysqli;
use App\Models\Category;
use App\Models\Product;
use App\Models\Cart;

class GraphQLController
{
    private $db;
    private $categoryType;
    private $attributeType;
    private $productType;
    private $productAttributesType;
    private $cartItemType;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $this->db = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);

        if ($this->db->connect_error) {
            die('Database connection failed: ' . $this->db->connect_error);
        }

        $this->initTypes();
    }

    private function initTypes()
    {
        $this->categoryType = new ObjectType([
            'name' => 'Category',
            'fields' => [
                'id' => Type::nonNull(Type::int()),
                'name' => Type::string(),
            ],
        ]);

        $this->attributeType = new ObjectType([
            'name' => 'Attribute',
            'fields' => [
                'name' => Type::string(),
                'values' => Type::listOf(Type::string()),
            ],
        ]);

        $this->productAttributesType = new ObjectType([
            'name' => 'ProductAttributes',
            'fields' => [
                'product_id' => Type::nonNull(Type::string()),
                'attributes' => Type::listOf($this->attributeType),
            ],
        ]);

        $this->productType = new ObjectType([
            'name' => 'Product',
            'fields' => [
                'product_id' => Type::nonNull(Type::string()),
                'in_stock' => Type::boolean(),
                'name' => Type::string(),
                'price' => Type::float(),
                'currency_label' => Type::string(),
                'currency_symbol' => Type::string(),
                'description' => Type::string(),
                'first_image' => Type::string(),
                'category' => [
                    'type' => $this->categoryType,
                    'resolve' => function ($product) {
                        return $product['category'];
                    }
                ],
                'images' => [
                    'type' => Type::listOf(Type::string()),
                    'resolve' => function ($product) {
                        return $product['images'];
                    }
                ],
                'attributes' => [
                    'type' => Type::listOf($this->attributeType),
                    'resolve' => function ($product) {
                        $attributes = [];
                        foreach ($product['attributes'] as $name => $values) {
                            $attributes[] = [
                                'name' => $name,
                                'values' => $values,
                            ];
                        }
                        return $attributes;
                    }
                ],
            ],
        ]);

        $this->cartItemType = new ObjectType([
            'name' => 'CartItem',
            'fields' => [
                'cartitemID' => Type::nonNull(Type::int()),
                'productID' => Type::string(),
                'name' => Type::string(),
                'price_per_unit' => Type::float(),
                'quantity' => Type::int(),
                'currency_symbol' => Type::string(),
                'color' => Type::string(),
                'size' => Type::string(),
                'capacity' => Type::string(),
                'first_image' => Type::string(),
                'usb_port' => Type::string(),
                'touch_id' => Type::string(),
            ],
        ]);
    }

    public function getQueryType()
    {
        return new ObjectType([
            'name' => 'Query',
            'fields' => [
                'categories' => [
                    'type' => Type::listOf($this->categoryType),
                    'resolve' => function () {
                        return Category::getAllCategories($this->db);
                    }
                ],
                'products' => [
                    'type' => Type::listOf($this->productType),
                    'args' => [
                        'categoryName' => Type::string(),
                    ],
                    'resolve' => function ($root, $args) {
                        $categoryName = $args['categoryName'] ?? null;
                        return Product::getAllProducts($this->db, $categoryName);
                    }
                ],
                'productDetails' => [
                    'type' => $this->productType,
                    'args' => [
                        'id' => Type::nonNull(Type::string()),
                    ],
                    'resolve' => function ($root, $args) {
                        return Product::getProductDetails($this->db, $args['id']);
                    }
                ],
                'cart' => [
                    'type' => Type::listOf($this->cartItemType),
                    'resolve' => function () {
                        return Cart::viewCart($this->db);
                    }
                ],
                'getAllAttributes' => [
                    'type' => Type::listOf($this->productAttributesType),
                    'resolve' => function () {
                        return Product::getAllProductAttributes($this->db);
                    }
                ],
            ],
        ]);
    }

    public function getMutationType()
    {
        return new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'addToCart' => [
                    'type' => $this->cartItemType,
                    'args' => [
                        'productID' => Type::nonNull(Type::string()),
                        'quantity' => Type::nonNull(Type::int()),
                        'color' => Type::string(),
                        'size' => Type::string(),
                        'capacity' => Type::string(),
                        'usb_port' => Type::string(),
                        'touch_id' => Type::string(),
                    ],
                    'resolve' => function ($root, $args) {
                        try {
                            $productID = $args['productID'];
                            $quantity = $args['quantity'];
                            $color = $args['color'] ?? null;
                            $size = $args['size'] ?? null;
                            $capacity = $args['capacity'] ?? null;
                            $usb_port = $args['usb_port'] ?? null;
                            $touch_id = $args['touch_id'] ?? null;

                            $cartitemID = Cart::addToCart(
                                $this->db,
                                $productID,
                                $quantity,
                                $color,
                                $size,
                                $capacity,
                                $usb_port,
                                $touch_id
                            );

                            if ($cartitemID === null) {
                                throw new \GraphQL\Error\Error('Failed to add to cart.');
                            }

                            return [
                                'cartitemID' => $cartitemID,
                                'productID' => $productID,
                                'quantity' => $quantity,
                                'color' => $color,
                                'size' => $size,
                                'capacity' => $capacity,
                                'usb_port' => $usb_port,
                                'touch_id' => $touch_id,
                            ];
                        } catch (\GraphQL\Error\Error $e) {
                            throw new \GraphQL\Error\Error($e->getMessage());
                        }
                    },
                ],
                'quickAddToCart' => [
                    'type' => Type::nonNull(Type::int()),
                    'args' => [
                        'productID' => Type::nonNull(Type::string()),
                    ],
                    'resolve' => function ($root, $args) {
                        try {
                            $productID = $args['productID'];

                            $cartitemID = Cart::QuickAddToCart($this->db, $productID);

                            if ($cartitemID === null) {
                                throw new \GraphQL\Error\Error('Failed to add to cart quickly.');
                            }

                            return $cartitemID;
                        } catch (\GraphQL\Error\Error $e) {
                            throw new \GraphQL\Error\Error($e->getMessage());
                        }
                    },
                ],
                'DeleteCartItem' => [
                    'type' => Type::nonNull(Type::int()),
                    'args' => [
                        'cartitemID' => Type::nonNull(Type::int()),
                    ],
                    'resolve' => function ($root, $args) {
                        try {
                            $cartitemID = $args['cartitemID'];

                            $result = Cart::DeleteCartItem($this->db, $cartitemID);

                            if ($result === null) {
                                throw new \GraphQL\Error\Error('Failed to delete cart item.');
                            }

                            return $result;
                        } catch (\GraphQL\Error\Error $e) {
                            throw new \GraphQL\Error\Error($e->getMessage());
                        }
                    },
                ],
                'UpdateCartItem' => [
                    'type' => Type::nonNull(Type::int()),
                    'args' => [
                        'cartitemID' => Type::nonNull(Type::int()),
                        'quantity' => Type::nonNull(Type::int()),
                    ],
                    'resolve' => function ($root, $args) {
                        try {
                            $cartitemID = $args['cartitemID'];
                            $quantity = $args['quantity'];

                            $result = Cart::updateCartItem(
                                $this->db,
                                $cartitemID,
                                $quantity
                            );

                            if ($result === null) {
                                throw new \GraphQL\Error\Error('Failed to update cart item.');
                            }

                            return $result;
                        } catch (\GraphQL\Error\Error $e) {
                            throw new \GraphQL\Error\Error($e->getMessage());
                        }
                    },
                ],
                'placeOrder' => [
                    'type' => Type::nonNull(Type::int()), // Return the order ID
                    'args' => [
                        'total' => Type::nonNull(Type::float()), // Total amount for the order
                    ],
                    'resolve' => function ($root, $args) {
                        try {
                            $total = $args['total'];
    
                            // Place the order and get the order ID
                            $orderID = Cart::placeOrder($this->db, $total);
    
                            return $orderID;
                        } catch (\GraphQL\Error\Error $e) {
                            throw new \GraphQL\Error\Error($e->getMessage());
                        }
                    },
                ],
            ],
        ]);
    }
}
