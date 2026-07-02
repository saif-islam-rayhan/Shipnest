<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['name' => 'Samsung', 'featured' => true],
            ['name' => 'Apple', 'featured' => true],
            ['name' => 'Xiaomi', 'featured' => true],
            ['name' => 'Nike', 'featured' => true],
            ['name' => 'Adidas', 'featured' => true],
            ['name' => 'Sony', 'featured' => true],
            ['name' => 'LG', 'featured' => false],
            ['name' => 'HP', 'featured' => false],
            ['name' => 'Dell', 'featured' => false],
            ['name' => 'Walton', 'featured' => true],
            ['name' => 'Vision', 'featured' => true],
            ['name' => 'Unilever', 'featured' => false],
            ['name' => 'Puma', 'featured' => false],
            ['name' => 'Canon', 'featured' => false],
            ['name' => 'Philips', 'featured' => false],
            ['name' => 'Panasonic', 'featured' => false],
            ['name' => 'Realme', 'featured' => true],
            ['name' => 'OnePlus', 'featured' => false],
            ['name' => 'H&M', 'featured' => false],
            ['name' => 'Zara', 'featured' => false],
            ['name' => 'Fastrack', 'featured' => true],
            ['name' => 'Awei', 'featured' => false],
            ['name' => 'Remax', 'featured' => false],
            ['name' => 'Baseus', 'featured' => false],
            ['name' => 'Havit', 'featured' => false],
            ['name' => 'Lakme', 'featured' => true],
            ['name' => 'Richman', 'featured' => false],
            ['name' => 'Aarong', 'featured' => true],
            ['name' => 'Pran', 'featured' => false],
            ['name' => 'ACI', 'featured' => false],
            ['name' => 'Anker', 'featured' => true],
            ['name' => 'Boat', 'featured' => false],
            ['name' => 'Titan', 'featured' => false],
            ['name' => 'Levi\'s', 'featured' => false],
        ];

        foreach ($brands as $brand) {
            Brand::query()->firstOrCreate(
                ['slug' => Str::slug($brand['name'])],
                [
                    'name' => $brand['name'],
                    'is_featured' => $brand['featured'],
                    'status' => 'active',
                    'description' => "Official {$brand['name']} products on ShipNest.",
                ]
            );
        }
    }
}
