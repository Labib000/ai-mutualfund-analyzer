<?php

namespace Tests\Support;

use App\Models\Holding;
use App\Models\Scheme;
use App\Models\User;
use App\Nav\NavProvider;
use Carbon\CarbonImmutable;

/**
 * A user holding one scheme, with NAVs published on business days up to
 * Wed 7 Oct 2026 and "today" frozen at Thu 8 Oct 2026, before that day's NAV.
 * Fri 2 Oct 2026 is a holiday.
 */
trait BuildsPortfolio
{
    protected const AMFI_CODE = 135762;

    protected User $user;

    protected Scheme $scheme;

    protected Holding $holding;

    protected FakeNavProvider $navProvider;

    protected function buildPortfolio(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 10:00:00'));

        $navs = self::businessDayNavs('2025-01-01', '2026-10-07', holidays: ['2026-10-02']);
        $navs['2026-10-05'] = '29.1100';

        $this->navProvider = new FakeNavProvider([self::AMFI_CODE => $navs]);
        $this->app->instance(NavProvider::class, $this->navProvider);

        $this->user = User::factory()->create();
        $this->scheme = Scheme::factory()->create([
            'amfi_code' => self::AMFI_CODE,
            'name' => "Axis Children's Fund - Direct Plan - Growth Option",
            'latest_nav' => $navs['2026-10-07'],
            'latest_nav_date' => '2026-10-07',
        ]);
        $this->holding = Holding::factory()->for($this->user)->for($this->scheme)->create();
    }

    /**
     * NAVs for every weekday in the range, rising by 0.01 a day from 10.0000.
     *
     * @param  list<string>  $holidays
     * @return array<string, string>
     */
    protected static function businessDayNavs(string $from, string $to, array $holidays = []): array
    {
        $navs = [];
        $nav = '10.0000';

        for ($date = CarbonImmutable::parse($from); $date->lessThanOrEqualTo(CarbonImmutable::parse($to)); $date = $date->addDay()) {
            if ($date->isWeekday() && ! in_array($date->toDateString(), $holidays, true)) {
                $navs[$date->toDateString()] = $nav;
                $nav = bcadd($nav, '0.0100', 4);
            }
        }

        return $navs;
    }

    protected function navOn(string $date): string
    {
        return $this->navProvider->navs[self::AMFI_CODE][$date];
    }
}
