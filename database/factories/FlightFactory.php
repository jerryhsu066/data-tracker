<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlightFactory extends Factory
{
    public function definition(): array
    {
        $airports = ['TPE', 'NRT', 'LAX', 'SFO', 'ICN', 'HKG', 'SIN', 'BKK', 'KIX', 'CDG'];

        return [
            'user_id'           => User::factory(),
            'flight_date'       => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'airline'           => $this->faker->randomElement(['China Airlines', 'EVA Air', 'Peach', 'ANA', 'JAL']),
            'flight_number'     => strtoupper($this->faker->lexify('??')) . $this->faker->numberBetween(100, 999),
            'departure_airport' => $this->faker->randomElement($airports),
            'arrival_airport'   => $this->faker->randomElement($airports),
            'aircraft_type'     => $this->faker->optional()->randomElement(['A330-300', 'B777-300ER', 'A321neo', 'B787-9']),
            'seat_class'        => $this->faker->optional()->randomElement(['economy', 'business', 'first']),
            'ticket_price'      => $this->faker->optional()->randomFloat(2, 3000, 50000),
        ];
    }
}
