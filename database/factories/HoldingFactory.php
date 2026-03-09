<?php

namespace Database\Factories;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Holding>
 */
class HoldingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stock_id' => Stock::factory(),
            'shares' => $this->faker->randomFloat(4, 1, 1000),
            'average_cost' => $this->faker->randomFloat(4, 1, 5000),
        ];
    }
}
