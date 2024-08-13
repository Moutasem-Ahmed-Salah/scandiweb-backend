<?php

namespace App\Models;

class Category
{
    public static function getAllCategories($db)
    {
        $query = "SELECT id, name FROM categories";
        $result = $db->query($query);
        
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        
        return $categories;
    }
}
