<?php

namespace App\Nav;

use App\Models\Scheme;
use App\Portfolio\FundStats;
use App\Portfolio\FundStatsResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A scheme's past returns and risk from its stored NAV history, cached per
 * scheme until a newer NAV arrives.
 */
class SchemeStats
{
    public function __construct(private readonly FundStats $stats) {}

    public function for(Scheme $scheme): ?FundStatsResult
    {
        // One entry per scheme, replaced when the NAV history grows.
        $key = "scheme-stats:{$scheme->id}";
        $version = $scheme->latest_nav_date?->toDateString().'|'.$scheme->history_synced_at?->toIso8601String();
        $cached = Cache::get($key);

        if (is_array($cached) && ($cached['version'] ?? null) === $version) {
            return $cached['stats'] === null ? null : FundStatsResult::fromArray($cached['stats']);
        }

        // Plain rows, not models: a decade-old fund has ~2,700 NAVs.
        $navs = DB::table('nav_history')
            ->where('scheme_id', $scheme->id)
            ->orderBy('nav_date')
            ->pluck('nav', 'nav_date')
            ->map(fn (mixed $nav) => (string) $nav)
            ->all();

        $stats = $this->stats->of($navs);
        Cache::put($key, ['version' => $version, 'stats' => $stats?->toArray()], now()->addDays(7));

        return $stats;
    }
}
