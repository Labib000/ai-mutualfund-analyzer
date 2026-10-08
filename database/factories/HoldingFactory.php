<?php

namespace Database\Factories;

use App\Models\Holding;
use App\Models\Scheme;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holding>
 */
class HoldingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'scheme_id' => Scheme::factory(),
        ];
    }
}
