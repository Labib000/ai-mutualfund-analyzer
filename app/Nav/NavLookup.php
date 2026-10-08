<?php

namespace App\Nav;

use App\Models\NavHistory;
use App\Models\Scheme;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finds the NAV that applies to a scheme on a date, fetching and caching history on demand.
 */
class NavLookup
{
    /** A purchase NAV further than this from the transaction date is treated as not yet published. */
    private const MAX_PURCHASE_LAG_DAYS = 7;

    private const CHUNK_SIZE = 500;

    public function __construct(private readonly NavProvider $provider) {}

    /**
     * The NAV units are allotted at: the transaction date's NAV, or the next
     * business day's when the date is a weekend or holiday. Null when that NAV
     * hasn't been published yet.
     *
     * @throws NavProviderException when the scheme's history can't be fetched
     */
    public function forPurchase(Scheme $scheme, DateTimeInterface $date): ?NavPoint
    {
        $this->ensureHistory($scheme);

        $from = CarbonImmutable::instance($date)->startOfDay();

        $row = $scheme->navHistory()
            ->whereBetween('nav_date', [$from->toDateString(), $from->addDays(self::MAX_PURCHASE_LAG_DAYS)->toDateString()])
            ->orderBy('nav_date')
            ->first();

        return $row === null ? null : new NavPoint($row->nav_date, $row->nav);
    }

    /**
     * The NAV a holding is worth on a date: the latest published on or before it.
     *
     * @throws NavProviderException when the scheme's history can't be fetched
     */
    public function forValuation(Scheme $scheme, DateTimeInterface $date): ?NavPoint
    {
        $this->ensureHistory($scheme);

        $row = $scheme->navHistory()
            ->where('nav_date', '<=', CarbonImmutable::instance($date)->toDateString())
            ->orderByDesc('nav_date')
            ->first();

        return $row === null ? null : new NavPoint($row->nav_date, $row->nav);
    }

    /**
     * Fetch the full history the first time a scheme is used, then only the
     * missing tail whenever the nightly import has seen a newer NAV than we hold.
     *
     * @throws NavProviderException when the first fetch for a scheme fails
     */
    public function ensureHistory(Scheme $scheme): void
    {
        if ($scheme->history_synced_at === null) {
            $this->store($scheme, $this->provider->history($scheme->amfi_code));

            return;
        }

        /** @var string|null $newestStored */
        $newestStored = $scheme->navHistory()->max('nav_date');

        if ($scheme->latest_nav_date === null || ($newestStored !== null && $newestStored >= $scheme->latest_nav_date->toDateString())) {
            return;
        }

        $from = $newestStored === null ? null : CarbonImmutable::parse($newestStored)->addDay();

        try {
            $this->store($scheme, $this->provider->history($scheme->amfi_code, $from));
        } catch (NavProviderException $e) {
            // Cached history still answers most lookups; the next call retries the gap.
            Log::warning('Could not refresh NAV history', ['amfi_code' => $scheme->amfi_code, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, string>  $navs
     */
    private function store(Scheme $scheme, array $navs): void
    {
        $rows = [];
        foreach ($navs as $date => $nav) {
            $rows[] = ['scheme_id' => $scheme->id, 'nav_date' => $date, 'nav' => $nav];
        }

        DB::transaction(function () use ($scheme, $rows) {
            foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
                NavHistory::query()->upsert($chunk, ['scheme_id', 'nav_date'], ['nav']);
            }

            $scheme->forceFill(['history_synced_at' => now()])->save();
        });
    }
}
