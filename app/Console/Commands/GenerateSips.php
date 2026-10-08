<?php

namespace App\Console\Commands;

use App\Actions\Portfolio\GenerateSipInstallments;
use App\Models\Sip;
use App\Nav\NavProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('sips:generate')]
#[Description('Record SIP installments that are due and whose NAV is published')]
class GenerateSips extends Command
{
    public function handle(GenerateSipInstallments $generate): int
    {
        $today = CarbonImmutable::today();
        $created = 0;
        $failed = 0;

        Sip::query()
            ->dueBy($today)
            ->with('holding.scheme')
            ->chunkById(100, function (Collection $sips) use ($generate, $today, &$created, &$failed) {
                foreach ($sips as $sip) {
                    try {
                        $created += $generate->handle($sip, $today);
                    } catch (NavProviderException $e) {
                        // One unreachable scheme shouldn't block the others; the next run retries it.
                        report($e);
                        $failed++;
                    }
                }
            });

        $this->info("Recorded {$created} SIP installment(s).");

        if ($failed > 0) {
            $this->warn("{$failed} SIP(s) could not fetch NAV history and will be retried.");
        }

        return self::SUCCESS;
    }
}
