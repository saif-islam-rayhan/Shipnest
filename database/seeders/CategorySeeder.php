<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Electronics' => ['icon' => '📱', 'children' => ['Mobile Phones', 'Laptops & Computers', 'TV & Audio', 'Cameras', 'Accessories']],
            'Fashion' => ['icon' => '👗', 'children' => ['Men\'s Clothing', 'Women\'s Clothing', 'Kids\' Fashion', 'Shoes', 'Bags & Luggage']],
            'Home' => ['icon' => '🏠', 'children' => ['Furniture', 'Kitchen & Dining', 'Home Decor', 'Bedding', 'Lighting']],
            'Beauty' => ['icon' => '💄', 'children' => ['Skincare', 'Makeup', 'Hair Care', 'Fragrances', 'Personal Care']],
            'Sports' => ['icon' => '⚽', 'children' => ['Fitness Equipment', 'Outdoor Sports', 'Team Sports', 'Sportswear', 'Cycling']],
            'Books' => ['icon' => '📚', 'children' => ['Fiction', 'Non-Fiction', 'Academic', 'Children\'s Books', 'Comics & Manga']],
            'Food' => ['icon' => '🍔', 'children' => ['Snacks', 'Beverages', 'Cooking Essentials', 'Organic Food', 'Frozen Food']],
            'Toys' => ['icon' => '🧸', 'children' => ['Action Figures', 'Board Games', 'Educational Toys', 'Dolls & Plush', 'Remote Control']],
        ];

        $sortOrder = 0;
        foreach ($categories as $name => $data) {
            $parent = Category::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'icon' => $data['icon'],
                    'is_featured' => true,
                    'sort_order' => $sortOrder++,
                    'status' => 'active',
                ]
            );

            foreach ($data['children'] as $childIndex => $childName) {
                Category::query()->firstOrCreate(
                    ['slug' => Str::slug($childName.'-'.$parent->id)],
                    [
                        'parent_id' => $parent->id,
                        'name' => $childName,
                        'sort_order' => $childIndex,
                        'status' => 'active',
                    ]
                );
            }
        }
    }
}
