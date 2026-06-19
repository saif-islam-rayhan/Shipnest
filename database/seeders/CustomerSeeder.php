<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $districts = ['Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi', 'Khulna', 'Barishal'];

        for ($i = 1; $i <= 30; $i++) {
            $user = User::query()->firstOrCreate(
                ['email' => "customer{$i}@shipnest.com"],
                [
                    'name' => fake()->name(),
                    'phone' => '0172'.str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                    'password' => 'password',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'phone_verified_at' => now(),
                ]
            );

            if (! $user->hasRole('customer')) {
                $user->assignRole('customer');
            }

            UserAddress::query()->firstOrCreate(
                ['user_id' => $user->id, 'is_default' => true],
                [
                    'label' => 'Home',
                    'recipient_name' => $user->name,
                    'phone' => $user->phone,
                    'address_line1' => fake()->streetAddress(),
                    'city' => fake()->randomElement($districts),
                    'district' => fake()->randomElement($districts),
                    'thana' => fake()->citySuffix(),
                    'postal_code' => fake()->numerify('####'),
                ]
            );
        }

        $demoCustomer = User::query()->firstOrCreate(
            ['email' => 'customer@shipnest.com'],
            [
                'name' => 'Demo Customer',
                'phone' => '01700000003',
                'password' => 'password',
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

        if (! $demoCustomer->hasRole('customer')) {
            $demoCustomer->assignRole('customer');
        }

        UserAddress::query()->firstOrCreate(
            ['user_id' => $demoCustomer->id, 'is_default' => true],
            [
                'label' => 'Home',
                'recipient_name' => $demoCustomer->name,
                'phone' => $demoCustomer->phone,
                'address_line1' => '45 Dhanmondi Road 27',
                'city' => 'Dhaka',
                'district' => 'Dhaka',
                'postal_code' => '1209',
            ]
        );
    }
}
