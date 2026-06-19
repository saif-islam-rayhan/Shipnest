<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $banners = [
            [
                'title' => 'Mega Sale - Up to 70% Off',
                'image' => 'banners/mega-sale.jpg',
                'link' => '/products?sale=1',
                'position' => 'top',
                'sort_order' => 1,
            ],
            [
                'title' => 'New Arrivals in Electronics',
                'image' => 'banners/electronics.jpg',
                'link' => '/categories/electronics',
                'position' => 'top',
                'sort_order' => 2,
            ],
            [
                'title' => 'Fashion Week Special',
                'image' => 'banners/fashion.jpg',
                'link' => '/categories/fashion',
                'position' => 'middle',
                'sort_order' => 1,
            ],
            [
                'title' => 'Free Shipping on Orders Over ৳500',
                'image' => 'banners/free-shipping.jpg',
                'link' => '/products',
                'position' => 'middle',
                'sort_order' => 2,
            ],
            [
                'title' => 'Download ShipNest App',
                'image' => 'banners/app-download.jpg',
                'link' => '#',
                'position' => 'bottom',
                'sort_order' => 1,
            ],
        ];

        foreach ($banners as $banner) {
            Banner::query()->firstOrCreate(
                ['title' => $banner['title']],
                array_merge($banner, [
                    'type' => 'home',
                    'status' => 'active',
                ])
            );
        }
    }
}
