<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Holding;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
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
            'sip_id' => null,
            'type' => TransactionType::Purchase,
            'txn_date' => today()->subMonth(),
            'nav_date' => today()->subMonth(),
            'nav' => '10.0000',
            'amount_paise' => 100000,
            'stamp_duty_paise' => 5,
            'units' => '99.995',
            'units_overridden' => false,
        ];
    }

    /**
     * A redemption of the given units at the factory NAV.
     *
     * @param  numeric-string  $units
     */
    public function redemption(string $units = '10.000'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Redemption,
            'units' => $units,
            'amount_paise' => (int) bcmul($units, '1000', 0),
            'stamp_duty_paise' => 0,
        ]);
    }
}
