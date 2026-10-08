<?php

namespace App\Actions\Portfolio;

use App\Models\Holding;
use App\Models\Sip;
use App\Nav\NavProviderException;
use Carbon\CarbonImmutable;

class CreateSip
{
    public function __construct(private readonly GenerateSipInstallments $generate) {}

    /**
     * Create a SIP and record its past installments straight away.
     *
     * @return array{Sip, bool} The SIP, and whether its past installments were recorded
     */
    public function handle(Holding $holding, int $amountPaise, int $dayOfMonth, CarbonImmutable $startDate, ?CarbonImmutable $endDate): array
    {
        $sip = $holding->sips()->create([
            'amount_paise' => $amountPaise,
            'day_of_month' => $dayOfMonth,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        try {
            $this->generate->handle($sip);
        } catch (NavProviderException $e) {
            // The scheduled run will backfill once the NAV source is reachable.
            report($e);

            return [$sip, false];
        }

        return [$sip, true];
    }
}
