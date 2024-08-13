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

class GraphQLController
{
    private $db;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $this->db = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
        
        if ($this->db->connect_error) {
            die('Database connection failed: ' . $this->db->connect_error);
        }
    }

    public function getQueryType()
    {
        $categoryType = new ObjectType([
            'name' => 'Category',
            'fields' => [
                'id' => Type::nonNull(Type::int()),
                'name' => Type::string(),
            ],
        ]);

        $attributeType = new ObjectType([
            'name' => 'Attribute',
            'fields' => [
                'name' => Type::string(),
                'values' => Type::listOf(Type::string()),
            ],
        ]);

        $productType = new ObjectType([
            'name' => 'Product',
            'fields' => [
                'product_id' => Type::nonNull(Type::string()),
                'name' => Type::string(),
                'price' => Type::float(),
                'currency_label' => Type::string(),
                'currency_symbol' => Type::string(),
                'description' => Type::string(),
                'category' => [
                    'type' => $categoryType,
                    'resolve' => function($product) {
                        return $product['category'];
                    }
                ],
                'images' => [
                    'type' => Type::listOf(Type::string()),
                    'resolve' => function($product) {
                        return $product['images'];
                    }
                ],
                'attributes' => [
                    'type' => Type::listOf($attributeType),
                    'resolve' => function($product) {
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

        return new ObjectType([
            'name' => 'Query',
            'fields' => [
                'categories' => [
                    'type' => Type::listOf($categoryType),
                    'resolve' => function () {
                        return Category::getAllCategories($this->db);
                    }
                ],
                'products' => [
                    'type' => Type::listOf($productType),
                    'args' => [
                        'categoryName' => Type::string(),
                    ],
                    'resolve' => function ($root, $args) {
                        $categoryName = isset($args['categoryName']) ? $args['categoryName'] : null;
                        return Product::getAllProducts($this->db, $categoryName);
                    }
                ],
                'productDetails' => [
                    'type' => $productType,
                    'args' => [
                        'id' => Type::nonNull(Type::string()),
                    ],
                    'resolve' => function ($root, $args) {
                        return Product::getProductDetails($this->db, $args['id']);
                    }
                ],
            ],
        ]);
    }

    public function getMutationType()
    {
        return null;
    }
}