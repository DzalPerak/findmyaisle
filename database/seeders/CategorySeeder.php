<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Fruits & Vegetables',
                'description' => 'Fresh fruits and vegetables',
                'color' => '#10B981', // green-500
            ],
            [
                'name' => 'Meat & Poultry',
                'description' => 'Fresh and frozen meat products',
                'color' => '#EF4444', // red-500
            ],
            [
                'name' => 'Dairy & Eggs',
                'description' => 'Milk, cheese, yogurt, eggs',
                'color' => '#F59E0B', // amber-500
            ],
            [
                'name' => 'Bakery',
                'description' => 'Bread, pastries, cakes',
                'color' => '#D97706', // amber-600
            ],
            [
                'name' => 'Pantry Staples',
                'description' => 'Rice, pasta, canned goods',
                'color' => '#8B5CF6', // violet-500
            ],
            [
                'name' => 'Beverages',
                'description' => 'Water, juice, soda, alcohol',
                'color' => '#3B82F6', // blue-500
            ],
            [
                'name' => 'Snacks & Candy',
                'description' => 'Chips, cookies, candy',
                'color' => '#EC4899', // pink-500
            ],
            [
                'name' => 'Frozen Foods',
                'description' => 'Frozen meals, ice cream, vegetables',
                'color' => '#06B6D4', // cyan-500
            ],
            [
                'name' => 'Health & Beauty',
                'description' => 'Personal care, vitamins, cosmetics',
                'color' => '#84CC16', // lime-500
            ],
            [
                'name' => 'Cleaning Supplies',
                'description' => 'Detergent, soap, household cleaners',
                'color' => '#6B7280', // gray-500
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}