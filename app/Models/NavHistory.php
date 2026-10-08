<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\NavHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One published NAV for a scheme on a business day.
 *
 * @property int $id
 * @property int $scheme_id
 * @property CarbonImmutable $nav_date
 * @property numeric-string $nav
 */
#[Table('nav_history', timestamps: false)]
#[Fillable(['scheme_id', 'nav_date', 'nav'])]
class NavHistory extends Model
{
    /** @use HasFactory<NavHistoryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Scheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nav_date' => 'date',
            'nav' => 'decimal:4',
        ];
    }
}
