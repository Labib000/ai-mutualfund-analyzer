<?php

namespace Database\Factories;

use App\Models\Holding;
use App\Models\Sip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sip>
 */
class SipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'holding_id' => Holding::factory(),
            'amount_paise' => 500000,
            'day_of_month' => 5,
            'start_date' => today()->subMonths(3)->startOfMonth(),
            'end_date' => null,
            'generated_until' => null,
        ];
    }
}
