<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductAndCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed categories
        $categories = [
            ['name' => 'Beverages', 'description' => 'Drinks and refreshments', 'color' => '#1E90FF'],
            ['name' => 'Bakery', 'description' => 'Bread and baked goods', 'color' => '#F59E42'],
            ['name' => 'Dairy', 'description' => 'Milk, cheese, and eggs', 'color' => '#FFFACD'],
            ['name' => 'Produce', 'description' => 'Fruits and vegetables', 'color' => '#34D399'],
            ['name' => 'Meat', 'description' => 'Meat and poultry', 'color' => '#EF4444'],
        ];
        foreach ($categories as $category) {
            DB::table('categories')->insert([
                'name' => $category['name'],
                'description' => $category['description'],
                'color' => $category['color'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Get category IDs
        $categoryIds = DB::table('categories')->pluck('id', 'name');

        // Seed products
        $products = [
            [
                'name' => 'Coca Cola',
                'category' => 'Beverages',
                'description' => 'Classic soft drink',
                'barcode' => '5449000000996',
                'price' => 2.50,
                'brand' => 'Coca Cola',
            ],
            [
                'name' => 'Orange Juice',
                'category' => 'Beverages',
                'description' => 'Fresh orange juice',
                'barcode' => '1234567890123',
                'price' => 3.00,
                'brand' => 'Tropicana',
            ],
            [
                'name' => 'White Bread',
                'category' => 'Bakery',
                'description' => 'Soft white bread loaf',
                'barcode' => '2345678901234',
                'price' => 1.80,
                'brand' => 'Wonder',
            ],
            [
                'name' => 'Croissant',
                'category' => 'Bakery',
                'description' => 'Buttery French pastry',
                'barcode' => '3456789012345',
                'price' => 2.20,
                'brand' => 'Bakery Fresh',
            ],
            [
                'name' => 'Milk',
                'category' => 'Dairy',
                'description' => 'Whole milk 1L',
                'barcode' => '4567890123456',
                'price' => 1.50,
                'brand' => 'DairyPure',
            ],
            [
                'name' => 'Cheddar Cheese',
                'category' => 'Dairy',
                'description' => 'Aged cheddar cheese',
                'barcode' => '5678901234567',
                'price' => 4.00,
                'brand' => 'Kraft',
            ],
            [
                'name' => 'Apple',
                'category' => 'Produce',
                'description' => 'Fresh red apple',
                'barcode' => '6789012345678',
                'price' => 0.80,
                'brand' => 'Farm Fresh',
            ],
            [
                'name' => 'Banana',
                'category' => 'Produce',
                'description' => 'Ripe yellow banana',
                'barcode' => '7890123456789',
                'price' => 0.60,
                'brand' => 'Chiquita',
            ],
            [
                'name' => 'Chicken Breast',
                'category' => 'Meat',
                'description' => 'Boneless chicken breast',
                'barcode' => '8901234567890',
                'price' => 5.50,
                'brand' => 'Tyson',
            ],
            [
                'name' => 'Beef Steak',
                'category' => 'Meat',
                'description' => 'Premium beef steak',
                'barcode' => '9012345678901',
                'price' => 8.00,
                'brand' => 'Certified Angus',
            ],
        ];
        foreach ($products as $product) {
            DB::table('products')->insert([
                'name' => $product['name'],
                'category_id' => $categoryIds[$product['category']],
                'description' => $product['description'],
                'barcode' => $product['barcode'],
                'price' => $product['price'],
                'brand' => $product['brand'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
