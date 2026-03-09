<?php

namespace Database\Factories;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockPriceHistory>
 */
class StockPriceHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stock_id' => Stock::factory(),
            'date' => $this->faker->unique()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'close_price' => $this->faker->randomFloat(4, 10, 2000),
        ];
    }
}
