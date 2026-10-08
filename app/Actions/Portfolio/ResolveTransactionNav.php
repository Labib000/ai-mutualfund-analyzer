<?php

namespace App\Actions\Portfolio;

use App\Models\Scheme;
use App\Nav\NavLookup;
use App\Nav\NavPoint;
use App\Nav\NavProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Finds the NAV a transaction is processed at, turning "not available" into a
 * validation error on the date field.
 */
class ResolveTransactionNav
{
    public function __construct(private readonly NavLookup $navs) {}

    /**
     * @throws ValidationException
     */
    public function handle(Scheme $scheme, CarbonImmutable $date, string $field = 'txn_date'): NavPoint
    {
        try {
            $point = $this->navs->forTransaction($scheme, $date);
        } catch (NavProviderException $e) {
            report($e);

            throw ValidationException::withMessages([
                $field => "Couldn't fetch NAV history right now, please try again.",
            ]);
        }

        if ($point === null) {
            throw ValidationException::withMessages([
                $field => $date->isToday()
                    ? "Today's NAV isn't published yet. Add this after 11 PM IST or tomorrow."
                    : "No NAV is available for {$date->format('j M Y')}.",
            ]);
        }

        return $point;
    }
}
