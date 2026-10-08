<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A systematic investment plan: a fixed amount invested on a day of every month.
 *
 * @property int $id
 * @property int $holding_id
 * @property int $amount_paise
 * @property int $day_of_month
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $end_date
 * @property CarbonImmutable|null $generated_until Date of the last installment created
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Holding $holding
 */
#[Fillable(['amount_paise', 'day_of_month', 'start_date', 'end_date'])]
class Sip extends Model
{
    /** @use HasFactory<SipFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Holding, $this>
     */
    public function holding(): BelongsTo
    {
        return $this->belongsTo(Holding::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Whether installments are still due on or after the given date.
     */
    public function isRunningOn(CarbonImmutable $date): bool
    {
        return $this->end_date === null || $this->end_date->greaterThanOrEqualTo($date->startOfDay());
    }

    /**
     * SIPs that may still have installments to generate up to the given date.
     *
     * @param  Builder<Sip>  $query
     */
    #[Scope]
    protected function dueBy(Builder $query, CarbonImmutable $date): void
    {
        $query->where('start_date', '<=', $date->toDateString())
            ->where(fn (Builder $query) => $query
                ->whereNull('generated_until')
                ->orWhere(fn (Builder $query) => $query
                    ->where('generated_until', '<', $date->toDateString())
                    ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereColumn('generated_until', '<', 'end_date'))));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'day_of_month' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'generated_until' => 'date',
        ];
    }
}
