<?php

namespace App\Actions\Portfolio;

use App\Models\Sip;
use App\Nav\NavProviderException;
use Carbon\CarbonImmutable;

class UpdateSip
{
    public function __construct(private readonly GenerateSipInstallments $generate) {}

    /**
     * Change a SIP's amount, day or end date. Installments already recorded are
     * kept as they are; only future installments use the new values.
     */
    public function handle(Sip $sip, int $amountPaise, int $dayOfMonth, ?CarbonImmutable $endDate): void
    {
        $sip->update([
            'amount_paise' => $amountPaise,
            'day_of_month' => $dayOfMonth,
            'end_date' => $endDate,
        ]);

        try {
            $this->generate->handle($sip);
        } catch (NavProviderException $e) {
            report($e);
        }
    }
}
