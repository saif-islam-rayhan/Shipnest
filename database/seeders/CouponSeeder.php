<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'WELCOME10',
                'type' => 'percentage',
                'value' => 10,
                'min_order' => 500,
                'max_discount' => 500,
                'usage_limit' => 1000,
            ],
            [
                'code' => 'FLAT200',
                'type' => 'fixed',
                'value' => 200,
                'min_order' => 1000,
                'max_discount' => null,
                'usage_limit' => 500,
            ],
            [
                'code' => 'MEGA25',
                'type' => 'percentage',
                'value' => 25,
                'min_order' => 2000,
                'max_discount' => 1000,
                'usage_limit' => 200,
            ],
            [
                'code' => 'FREESHIP',
                'type' => 'fixed',
                'value' => 120,
                'min_order' => 1500,
                'max_discount' => 120,
                'usage_limit' => null,
            ],
            [
                'code' => 'VIP15',
                'type' => 'percentage',
                'value' => 15,
                'min_order' => 3000,
                'max_discount' => 750,
                'usage_limit' => 100,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::query()->firstOrCreate(
                ['code' => $coupon['code']],
                array_merge($coupon, [
                    'used_count' => 0,
                    'starts_at' => now()->subWeek(),
                    'expires_at' => now()->addMonths(6),
                    'status' => 'active',
                ])
            );
        }
    }
}
