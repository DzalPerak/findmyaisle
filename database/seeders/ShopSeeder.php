<?php

namespace Database\Seeders;

use App\Models\Shop;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shops = [
            [
                'name' => 'SuperMart Downtown',
                'location' => '123 Main Street, Downtown',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'description' => 'Large supermarket in the heart of downtown',
                'dxf_file_path' => null, // We'll add a simple DXF later
            ],
            [
                'name' => 'FreshMart Mall',
                'location' => '456 Shopping Mall Drive',
                'latitude' => 40.7589,
                'longitude' => -73.9851,
                'description' => 'Fresh grocery store in the shopping mall',
                'dxf_file_path' => null,
            ],
        ];

        foreach ($shops as $shop) {
            Shop::firstOrCreate(
                ['name' => $shop['name']],
                $shop
            );
        }
    }
}