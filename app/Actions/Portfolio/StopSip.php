<?php

namespace App\Actions\Portfolio;

use App\Models\Sip;
use Carbon\CarbonImmutable;

class StopSip
{
    /**
     * End the SIP today. Installments up to today stay; none are created after.
     */
    public function handle(Sip $sip): void
    {
        $today = CarbonImmutable::today();

        if ($sip->end_date === null || $sip->end_date->greaterThan($today)) {
            $sip->update(['end_date' => $today->max($sip->start_date)]);
        }
    }
}
