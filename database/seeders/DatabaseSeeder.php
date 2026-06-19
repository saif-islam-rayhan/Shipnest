<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            MerchantSeeder::class,
            CustomerSeeder::class,
            ProductSeeder::class,
            CouponSeeder::class,
            OrderSeeder::class,
            BannerSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
