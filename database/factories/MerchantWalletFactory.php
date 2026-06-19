<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\MerchantWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantWallet>
 */
class MerchantWalletFactory extends Factory
{
    protected $model = MerchantWallet::class;

    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'balance' => fake()->randomFloat(2, 0, 100000),
            'pending_balance' => fake()->randomFloat(2, 0, 20000),
            'total_earned' => fake()->randomFloat(2, 0, 500000),
            'total_withdrawn' => fake()->randomFloat(2, 0, 100000),
        ];
    }
}
