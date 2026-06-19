<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\MerchantWalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantWalletTransaction>
 */
class MerchantWalletTransactionFactory extends Factory
{
    protected $model = MerchantWalletTransaction::class;

    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'type' => fake()->randomElement(['credit', 'debit', 'withdrawal', 'commission']),
            'amount' => fake()->randomFloat(2, 50, 10000),
            'description' => fake()->sentence(),
            'reference_id' => fake()->optional()->numberBetween(1, 1000),
        ];
    }
}
