<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MerchantSeeder extends Seeder
{
    public function run(): void
    {
        $merchants = [
            [
                'name' => 'TechZone BD',
                'email' => 'merchant1@shipnest.com',
                'phone' => '01710000001',
                'district' => 'Dhaka',
                'description' => 'Your trusted electronics store in Bangladesh.',
            ],
            [
                'name' => 'Fashion Hub',
                'email' => 'merchant2@shipnest.com',
                'phone' => '01710000002',
                'district' => 'Dhaka',
                'description' => 'Trendy fashion for men, women, and kids.',
            ],
            [
                'name' => 'Home Essentials',
                'email' => 'merchant3@shipnest.com',
                'phone' => '01710000003',
                'district' => 'Chittagong',
                'description' => 'Quality home and living products at great prices.',
            ],
            [
                'name' => 'Sports Arena',
                'email' => 'merchant4@shipnest.com',
                'phone' => '01710000004',
                'district' => 'Sylhet',
                'description' => 'Everything you need for fitness and outdoor sports.',
            ],
            [
                'name' => 'Book Paradise',
                'email' => 'merchant5@shipnest.com',
                'phone' => '01710000005',
                'district' => 'Rajshahi',
                'description' => 'Wide collection of books for all ages.',
            ],
        ];

        foreach ($merchants as $index => $data) {
            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'].' Owner',
                    'phone' => $data['phone'],
                    'password' => 'password',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'phone_verified_at' => now(),
                ]
            );

            if (! $user->hasRole('merchant')) {
                $user->assignRole('merchant');
            }

            $slug = Str::slug($data['name']);
            $merchant = Merchant::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'shop_name' => $data['name'],
                    'shop_slug' => $slug,
                    'description' => $data['description'],
                    'phone' => $data['phone'],
                    'address' => fake()->streetAddress(),
                    'district' => $data['district'],
                    'commission_rate' => 10,
                    'rating' => fake()->randomFloat(2, 4, 5),
                    'is_verified' => true,
                    'status' => 'active',
                ]
            );

            MerchantWallet::query()->firstOrCreate(
                ['merchant_id' => $merchant->id],
                [
                    'balance' => fake()->randomFloat(2, 5000, 50000),
                    'pending_balance' => fake()->randomFloat(2, 0, 10000),
                    'total_earned' => fake()->randomFloat(2, 50000, 200000),
                ]
            );
        }
    }
}
