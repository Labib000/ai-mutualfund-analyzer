<?php

namespace Tests\Unit\Portfolio;

use App\Portfolio\SipSchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SipScheduleTest extends TestCase
{
    public function test_monthly_dates_from_the_start_date()
    {
        $this->assertDates(['2026-01-05', '2026-02-05', '2026-03-05'], $this->dueDates(5, '2026-01-01', until: '2026-03-20'));
    }

    public function test_the_first_installment_is_skipped_when_the_start_date_is_after_that_months_day()
    {
        $this->assertDates(['2026-02-05', '2026-03-05'], $this->dueDates(5, '2026-01-10', until: '2026-03-05'));
    }

    public function test_the_start_date_itself_can_be_an_installment()
    {
        $this->assertDates(['2026-01-10'], $this->dueDates(10, '2026-01-10', until: '2026-01-31'));
    }

    public function test_day_31_falls_on_the_last_day_of_shorter_months()
    {
        $this->assertDates(
            ['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'],
            $this->dueDates(31, '2026-01-01', until: '2026-04-30'),
        );
    }

    public function test_day_30_and_29_in_a_leap_year_february()
    {
        $this->assertDates(['2028-01-30', '2028-02-29', '2028-03-30'], $this->dueDates(30, '2028-01-01', until: '2028-03-31'));
        $this->assertDates(['2028-02-29'], $this->dueDates(29, '2028-02-01', until: '2028-02-29'));
    }

    public function test_dates_stop_at_the_end_date()
    {
        $this->assertDates(['2026-01-05', '2026-02-05'], $this->dueDates(5, '2026-01-01', end: '2026-03-04', until: '2026-06-30'));
    }

    public function test_the_end_date_itself_can_be_an_installment()
    {
        $this->assertDates(['2026-01-05', '2026-02-05'], $this->dueDates(5, '2026-01-01', end: '2026-02-05', until: '2026-06-30'));
    }

    public function test_only_dates_after_the_last_generated_one_are_returned()
    {
        $this->assertDates(['2026-03-05', '2026-04-05'], $this->dueDates(5, '2026-01-01', after: '2026-02-05', until: '2026-04-30'));
    }

    public function test_nothing_is_due_when_after_equals_until()
    {
        $this->assertDates([], $this->dueDates(5, '2026-01-01', after: '2026-03-05', until: '2026-03-05'));
    }

    public function test_nothing_is_due_before_the_start_date()
    {
        $this->assertDates([], $this->dueDates(5, '2026-05-01', until: '2026-04-30'));
    }

    public function test_a_changed_day_only_affects_dates_after_the_last_generated_one()
    {
        // Generated on the 5th until March; the user then moved the SIP to the 20th.
        $this->assertDates(['2026-03-20', '2026-04-20'], $this->dueDates(20, '2026-01-01', after: '2026-03-05', until: '2026-04-30'));
    }

    public function test_a_long_running_sip_spans_years()
    {
        $this->assertCount(60, $this->dueDates(1, '2021-01-01', until: '2025-12-31'));
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function dueDates(int $day, string $start, ?string $end = null, ?string $after = null, string $until = '2026-12-31'): array
    {
        return (new SipSchedule)->dueDates(
            $day,
            CarbonImmutable::parse($start),
            $end === null ? null : CarbonImmutable::parse($end),
            $after === null ? null : CarbonImmutable::parse($after),
            CarbonImmutable::parse($until),
        );
    }

    /**
     * @param  list<string>  $expected
     * @param  list<CarbonImmutable>  $dates
     */
    private function assertDates(array $expected, array $dates): void
    {
        $this->assertSame($expected, array_map(fn (CarbonImmutable $date) => $date->toDateString(), $dates));
    }
}
