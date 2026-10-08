<?php

namespace App\Models;

use App\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A purchase, SIP installment or redemption in a holding.
 *
 * @property int $id
 * @property int $holding_id
 * @property int|null $sip_id
 * @property TransactionType $type
 * @property CarbonImmutable $txn_date The date the user transacted
 * @property CarbonImmutable $nav_date The date whose NAV was applied (next business day for holidays)
 * @property numeric-string $nav
 * @property int $amount_paise Invested for purchases, received for redemptions
 * @property int $stamp_duty_paise
 * @property numeric-string $units
 * @property bool $units_overridden
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Holding $holding
 * @property-read Sip|null $sip
 */
#[Fillable([
    'sip_id', 'type', 'txn_date', 'nav_date', 'nav', 'amount_paise', 'stamp_duty_paise', 'units', 'units_overridden',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Holding, $this>
     */
    public function holding(): BelongsTo
    {
        return $this->belongsTo(Holding::class);
    }

    /**
     * @return BelongsTo<Sip, $this>
     */
    public function sip(): BelongsTo
    {
        return $this->belongsTo(Sip::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'txn_date' => 'date',
            'nav_date' => 'date',
            'nav' => 'decimal:4',
            'amount_paise' => 'integer',
            'stamp_duty_paise' => 'integer',
            'units' => 'decimal:3',
            'units_overridden' => 'boolean',
        ];
    }
}
