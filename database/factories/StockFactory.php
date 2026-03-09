<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stock>
 */
class StockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'symbol' => strtoupper($this->faker->unique()->lexify('????')),
            'name' => $this->faker->company(),
            'current_price' => $this->faker->randomFloat(4, 1, 5000),
            'previous_close' => $this->faker->randomFloat(4, 1, 5000),
            'change_percent' => $this->faker->randomFloat(4, -10, 10),
            'last_fetched_at' => null,
        ];
    }
}
