<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get categories
        $fruitsVeg = Category::where('name', 'Fruits & Vegetables')->first();
        $meat = Category::where('name', 'Meat & Poultry')->first();
        $dairy = Category::where('name', 'Dairy & Eggs')->first();
        $bakery = Category::where('name', 'Bakery')->first();
        $pantry = Category::where('name', 'Pantry Staples')->first();
        $beverages = Category::where('name', 'Beverages')->first();
        $snacks = Category::where('name', 'Snacks & Candy')->first();
        $frozen = Category::where('name', 'Frozen Foods')->first();
        $health = Category::where('name', 'Health & Beauty')->first();
        $cleaning = Category::where('name', 'Cleaning Supplies')->first();

        $products = [
            // Fruits & Vegetables
            ['name' => 'Bananas', 'category_id' => $fruitsVeg->id, 'price' => 1.29],
            ['name' => 'Apples', 'category_id' => $fruitsVeg->id, 'price' => 2.99],
            ['name' => 'Carrots', 'category_id' => $fruitsVeg->id, 'price' => 1.49],
            ['name' => 'Spinach', 'category_id' => $fruitsVeg->id, 'price' => 2.49],
            ['name' => 'Tomatoes', 'category_id' => $fruitsVeg->id, 'price' => 3.99],

            // Meat & Poultry
            ['name' => 'Chicken Breast', 'category_id' => $meat->id, 'price' => 8.99],
            ['name' => 'Ground Beef', 'category_id' => $meat->id, 'price' => 6.99],
            ['name' => 'Salmon Fillet', 'category_id' => $meat->id, 'price' => 12.99],

            // Dairy & Eggs
            ['name' => 'Milk (2%)', 'category_id' => $dairy->id, 'price' => 3.49],
            ['name' => 'Large Eggs', 'category_id' => $dairy->id, 'price' => 4.29],
            ['name' => 'Cheddar Cheese', 'category_id' => $dairy->id, 'price' => 5.99],
            ['name' => 'Greek Yogurt', 'category_id' => $dairy->id, 'price' => 1.99],

            // Bakery
            ['name' => 'Whole Wheat Bread', 'category_id' => $bakery->id, 'price' => 2.99],
            ['name' => 'Croissants', 'category_id' => $bakery->id, 'price' => 4.99],

            // Pantry Staples
            ['name' => 'Pasta', 'category_id' => $pantry->id, 'price' => 1.99],
            ['name' => 'Rice', 'category_id' => $pantry->id, 'price' => 3.49],
            ['name' => 'Canned Tomatoes', 'category_id' => $pantry->id, 'price' => 1.29],
            ['name' => 'Olive Oil', 'category_id' => $pantry->id, 'price' => 8.99],

            // Beverages
            ['name' => 'Orange Juice', 'category_id' => $beverages->id, 'price' => 4.49],
            ['name' => 'Coffee', 'category_id' => $beverages->id, 'price' => 12.99],

            // Snacks & Candy
            ['name' => 'Potato Chips', 'category_id' => $snacks->id, 'price' => 3.99],
            ['name' => 'Chocolate Bar', 'category_id' => $snacks->id, 'price' => 1.99],

            // Frozen Foods
            ['name' => 'Frozen Pizza', 'category_id' => $frozen->id, 'price' => 5.99],
            ['name' => 'Ice Cream', 'category_id' => $frozen->id, 'price' => 6.99],

            // Health & Beauty
            ['name' => 'Toothpaste', 'category_id' => $health->id, 'price' => 4.99],
            ['name' => 'Shampoo', 'category_id' => $health->id, 'price' => 7.99],

            // Cleaning Supplies
            ['name' => 'Dish Soap', 'category_id' => $cleaning->id, 'price' => 2.99],
            ['name' => 'Laundry Detergent', 'category_id' => $cleaning->id, 'price' => 9.99],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(
                ['name' => $product['name']],
                $product
            );
        }
    }
}