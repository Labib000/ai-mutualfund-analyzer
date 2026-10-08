<?php

namespace App\Actions\Portfolio;

use App\Models\Sip;
use App\Portfolio\UnitLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteSip
{
    /**
     * Delete a SIP together with its installments, unless that would leave a
     * redemption selling more units than were held.
     *
     * @throws ValidationException
     */
    public function handle(Sip $sip): void
    {
        DB::transaction(function () use ($sip) {
            LockHolding::handle($sip->holding);

            $remaining = $sip->holding->transactions()
                ->where(fn ($query) => $query->whereNull('sip_id')->orWhere('sip_id', '!=', $sip->getKey()))
                ->get();
            $overdrawn = UnitLedger::fromTransactions($remaining)->firstOverdrawnDate();

            if ($overdrawn !== null) {
                throw ValidationException::withMessages([
                    'sip' => 'Deleting this SIP and its installments would leave the redemption on '
                        .CarbonImmutable::parse($overdrawn)->format('j M Y').' with more units than you held.',
                ]);
            }

            $sip->delete();
        });
    }
}
