<?php

namespace App\Nav;

use App\Models\NavHistory;
use App\Models\Scheme;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Writes parsed AMFI rows into the scheme master and, for tracked schemes, NAV history.
 */
class NavImporter
{
    private const CHUNK_SIZE = 500;

    /** Schemes whose latest NAV is older than this, relative to the file date, are inactive. */
    private const ACTIVE_WITHIN_DAYS = 30;

    /**
     * @param  iterable<AmfiNavRow>  $rows
     *
     * @throws AmfiNavFileException when there are no scheme rows to import
     */
    public function import(iterable $rows): NavImportResult
    {
        [$rows, $skippedEtfs] = $this->withoutEtfs($rows);

        $fileDate = $this->fileDate($rows);
        $activeSince = $fileDate->modify('-'.self::ACTIVE_WITHIN_DAYS.' days');

        $existingCodes = Scheme::query()->pluck('amfi_code')->flip();
        $created = 0;
        $active = 0;

        $records = [];
        foreach ($rows as $row) {
            $isActive = $row->navDate !== null && $row->navDate >= $activeSince;
            $active += (int) $isActive;
            $created += (int) ! $existingCodes->has($row->amfiCode);

            $records[] = [
                'amfi_code' => $row->amfiCode,
                'isin_growth' => $row->isinGrowth,
                'isin_reinvestment' => $row->isinReinvestment,
                'name' => $row->name,
                'amc' => $row->amc,
                'scheme_type' => $row->schemeType->value,
                'category' => $row->category,
                'plan' => $row->plan?->value,
                'latest_nav' => $row->nav,
                'latest_nav_date' => $row->nav !== null ? $row->navDate?->format('Y-m-d') : null,
                'is_active' => $isActive,
            ];
        }

        $historyAppended = DB::transaction(function () use ($records, $rows) {
            foreach (array_chunk($records, self::CHUNK_SIZE) as $chunk) {
                Scheme::query()->upsert($chunk, ['amfi_code'], [
                    'isin_growth', 'isin_reinvestment', 'name', 'amc', 'scheme_type', 'category', 'plan', 'is_active',
                    // Keep the last known NAV when AMFI publishes "N.A." for a day.
                    'latest_nav' => DB::raw('COALESCE(VALUES(latest_nav), latest_nav)'),
                    'latest_nav_date' => DB::raw('COALESCE(VALUES(latest_nav_date), latest_nav_date)'),
                ]);
            }

            return $this->appendTrackedHistory($rows);
        });

        return new NavImportResult(
            fileDate: $fileDate,
            created: $created,
            updated: count($rows) - $created,
            active: $active,
            inactive: count($rows) - $active,
            skippedEtfs: $skippedEtfs,
            historyAppended: $historyAppended,
        );
    }

    /**
     * @param  iterable<AmfiNavRow>  $rows
     * @return array{list<AmfiNavRow>, int}
     */
    private function withoutEtfs(iterable $rows): array
    {
        $kept = [];
        $skipped = 0;

        foreach ($rows as $row) {
            if ($row->isEtf()) {
                $skipped++;
            } else {
                $kept[] = $row;
            }
        }

        return [$kept, $skipped];
    }

    /**
     * The newest NAV date in the file. Activity is measured against it rather than
     * today, so a delayed or stale file doesn't mark every scheme inactive.
     *
     * @param  list<AmfiNavRow>  $rows
     */
    private function fileDate(array $rows): DateTimeImmutable
    {
        $dates = array_filter(array_map(fn (AmfiNavRow $row) => $row->navDate, $rows));

        if ($dates === []) {
            throw new AmfiNavFileException('The file has no scheme rows with a NAV date.');
        }

        return max($dates);
    }

    /**
     * Record today's NAV for schemes whose history we keep.
     *
     * @param  list<AmfiNavRow>  $rows
     */
    private function appendTrackedHistory(array $rows): int
    {
        $trackedIds = Scheme::query()->whereNotNull('history_synced_at')->pluck('id', 'amfi_code');

        $history = [];
        foreach ($rows as $row) {
            if ($row->nav !== null && $row->navDate !== null && $trackedIds->has($row->amfiCode)) {
                $history[] = [
                    'scheme_id' => $trackedIds[$row->amfiCode],
                    'nav_date' => $row->navDate->format('Y-m-d'),
                    'nav' => $row->nav,
                ];
            }
        }

        $appended = 0;
        foreach (array_chunk($history, self::CHUNK_SIZE) as $chunk) {
            $appended += NavHistory::query()->insertOrIgnore($chunk);
        }

        return $appended;
    }
}
