<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\HoldingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A scheme a user tracks in their portfolio. Transactions and SIPs belong to it.
 *
 * @property int $id
 * @property int $user_id
 * @property int $scheme_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Scheme $scheme
 * @property-read numeric-string|null $units_held Present when loaded with the withUnitsHeld scope
 */
#[Fillable(['scheme_id'])]
class Holding extends Model
{
    /** @use HasFactory<HoldingFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Scheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<Sip, $this>
     */
    public function sips(): HasMany
    {
        return $this->hasMany(Sip::class);
    }

    /**
     * Add a units_held column: units bought minus units redeemed, summed exactly in SQL.
     *
     * @param  Builder<Holding>  $query
     */
    #[Scope]
    protected function withUnitsHeld(Builder $query): void
    {
        $query->addSelect(['units_held' => Transaction::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'redemption' THEN -units ELSE units END), 0)")
            ->whereColumn('transactions.holding_id', 'holdings.id'),
        ]);
    }
}
