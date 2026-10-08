<?php

namespace Database\Factories;

use App\Models\NavHistory;
use App\Models\Scheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NavHistory>
 */
class NavHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scheme_id' => Scheme::factory(),
            'nav_date' => today()->subDay(),
            'nav' => fake()->numerify('##.####'),
        ];
    }
}
