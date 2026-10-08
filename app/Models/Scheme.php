<?php

namespace App\Models;

use App\Enums\SchemePlan;
use App\Enums\SchemeType;
use Carbon\CarbonImmutable;
use Database\Factories\SchemeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A mutual fund scheme from the AMFI master list.
 *
 * @property int $id
 * @property int $amfi_code
 * @property string|null $isin_growth
 * @property string|null $isin_reinvestment
 * @property string $name
 * @property string $amc
 * @property SchemeType $scheme_type
 * @property string $category
 * @property SchemePlan|null $plan
 * @property string|null $latest_nav
 * @property CarbonImmutable|null $latest_nav_date
 * @property bool $is_active
 * @property CarbonImmutable|null $history_synced_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'amfi_code', 'isin_growth', 'isin_reinvestment', 'name', 'amc', 'scheme_type', 'category',
    'plan', 'latest_nav', 'latest_nav_date', 'is_active', 'history_synced_at',
])]
class Scheme extends Model
{
    /** @use HasFactory<SchemeFactory> */
    use HasFactory;

    /**
     * @return HasMany<NavHistory, $this>
     */
    public function navHistory(): HasMany
    {
        return $this->hasMany(NavHistory::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheme_type' => SchemeType::class,
            'plan' => SchemePlan::class,
            'latest_nav' => 'decimal:4',
            'latest_nav_date' => 'date',
            'is_active' => 'boolean',
            'history_synced_at' => 'datetime',
        ];
    }
}
