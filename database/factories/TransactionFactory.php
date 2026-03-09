<?php

namespace Database\Factories;

use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'stock_id' => Stock::factory(),
            'type' => 'buy',
            'shares' => $this->faker->randomFloat(4, 100, 5000),
            'price_per_share' => $this->faker->randomFloat(4, 10, 2000),
            'transacted_at' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'notes' => null,
        ];
    }

    public function buy(Stock $stock, float $shares, float $price): static
    {
        return $this->state([
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => $shares,
            'price_per_share' => $price,
            'transacted_at' => now()->toDateString(),
        ]);
    }

    public function sell(Stock $stock, float $shares, float $price): static
    {
        return $this->state([
            'stock_id' => $stock->id,
            'type' => 'sell',
            'shares' => $shares,
            'price_per_share' => $price,
            'transacted_at' => now()->toDateString(),
        ]);
    }
}
