<?php

namespace Database\Factories;

use App\Enums\SchemePlan;
use App\Enums\SchemeType;
use App\Models\Scheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scheme>
 */
class SchemeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amfi_code' => fake()->unique()->numberBetween(100000, 199999),
            'isin_growth' => 'INF'.strtoupper(fake()->unique()->bothify('###?##?##')),
            'isin_reinvestment' => null,
            'name' => fake()->company().' Flexi Cap Fund - Direct Plan - Growth',
            'amc' => fake()->company().' Mutual Fund',
            'scheme_type' => SchemeType::OpenEnded,
            'category' => 'Equity Scheme - Flexi Cap Fund',
            'plan' => SchemePlan::Direct,
            'latest_nav' => fake()->numerify('##.####'),
            'latest_nav_date' => today()->subDay(),
            'is_active' => true,
            'history_synced_at' => null,
        ];
    }

    /**
     * Indicate that the scheme's NAV history is being tracked.
     */
    public function tracked(): static
    {
        return $this->state(fn (array $attributes) => [
            'history_synced_at' => now(),
        ]);
    }
}
