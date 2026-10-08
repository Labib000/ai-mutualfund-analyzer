<?php

namespace Tests\Unit\Nav;

use App\Enums\SchemePlan;
use App\Enums\SchemeType;
use App\Nav\AmfiNavFileException;
use App\Nav\AmfiNavFileParser;
use App\Nav\AmfiNavRow;
use PHPUnit\Framework\TestCase;

class AmfiNavFileParserTest extends TestCase
{
    /** @var array<int, AmfiNavRow> */
    private array $rows;

    protected function setUp(): void
    {
        parent::setUp();

        $contents = (string) file_get_contents(__DIR__.'/../../Fixtures/amfi/NAVAll-sample.txt');

        $this->rows = [];
        foreach ((new AmfiNavFileParser)->parse($contents) as $row) {
            $this->rows[$row->amfiCode] = $row;
        }
    }

    public function test_it_parses_every_data_row()
    {
        $this->assertCount(12, $this->rows);
    }

    public function test_it_parses_a_current_format_row()
    {
        $row = $this->rows[135762];

        $this->assertSame("Axis Children's Fund - Direct Plan - Growth Option", $row->name);
        $this->assertSame('Axis Mutual Fund', $row->amc);
        $this->assertSame(SchemeType::OpenEnded, $row->schemeType);
        $this->assertSame("Children’s Fund - Childrens' Fund", $row->category);
        $this->assertSame(SchemePlan::Direct, $row->plan);
        $this->assertSame('INF846K01WO1', $row->isinGrowth);
        $this->assertNull($row->isinReinvestment);
        $this->assertSame('29.2304', $row->nav);
        $this->assertSame('2026-10-07', $row->navDate?->format('Y-m-d'));
    }

    public function test_it_reads_the_reinvestment_isin()
    {
        $this->assertSame('INF846K01WQ6', $this->rows[135763]->isinReinvestment);
    }

    public function test_it_normalizes_plural_section_names()
    {
        $this->assertSame('Equity Scheme - Large Cap Fund', $this->rows[119133]->category);
        $this->assertSame('Equity Scheme - Large Cap Fund', $this->rows[119527]->category);
        $this->assertSame('Solution Oriented Scheme - Retirement Fund', $this->rows[147825]->category);
    }

    public function test_older_rows_without_plan_keep_the_bare_name()
    {
        $row = $this->rows[119527];

        $this->assertSame('Aditya Birla Sun Life Large Cap Fund', $row->name);
        $this->assertNull($row->plan);
    }

    public function test_navs_are_padded_to_four_decimals()
    {
        $this->assertSame('49.5600', $this->rows[119133]->nav);
        $this->assertSame('88.1100', $this->rows[119527]->nav);
    }

    public function test_navs_with_more_than_four_decimals_are_rounded_half_up()
    {
        $this->assertSame('11.2128', $this->rows[134568]->nav);
    }

    public function test_zero_and_unpublished_navs_become_null()
    {
        $this->assertNull($this->rows[148239]->nav);
        $this->assertNull($this->rows[100026]->nav);
        $this->assertSame('2026-10-07', $this->rows[100026]->navDate?->format('Y-m-d'));
    }

    public function test_invalid_isins_become_null_and_valid_ones_are_trimmed()
    {
        $this->assertNull($this->rows[152713]->isinReinvestment);
        $this->assertNull($this->rows[133786]->isinGrowth);
        $this->assertSame('INF178L01BT0', $this->rows[133786]->isinReinvestment);
    }

    public function test_the_amc_carries_over_when_a_section_has_no_amc_line()
    {
        $this->assertSame('Aditya Birla Sun Life Mutual Fund', $this->rows[151533]->amc);
    }

    public function test_it_detects_etfs()
    {
        $this->assertTrue($this->rows[151533]->isEtf());
        $this->assertFalse($this->rows[152713]->isEtf());
    }

    public function test_it_reads_interval_and_close_ended_sections()
    {
        $this->assertSame(SchemeType::Interval, $this->rows[105689]->schemeType);
        $this->assertSame('Income', $this->rows[105689]->category);
        $this->assertSame(SchemePlan::Regular, $this->rows[105689]->plan);

        $this->assertSame(SchemeType::CloseEnded, $this->rows[134568]->schemeType);
        $this->assertSame('2018-05-07', $this->rows[134568]->navDate?->format('Y-m-d'));
    }

    public function test_it_handles_lf_line_endings_and_a_byte_order_mark()
    {
        $contents = "\xEF\xBB\xBFScheme Code;a;b;c;d;e;f;g\n\nOpen Ended Schemes(Gilt)\n\nSBI Mutual Fund\n\n"
            ."101206;INF200K01180;-;SBI Gilt Fund;;;65.2;07-Oct-2026\n";

        $rows = iterator_to_array((new AmfiNavFileParser)->parse($contents), false);

        $this->assertCount(1, $rows);
        $this->assertSame('Gilt', $rows[0]->category);
        $this->assertSame('65.2000', $rows[0]->nav);
    }

    public function test_an_invalid_date_becomes_null()
    {
        $contents = "Scheme Code;a;b;c;d;e;f;g\nOpen Ended Schemes(Gilt)\nSBI Mutual Fund\n"
            ."101206;INF200K01180;-;SBI Gilt Fund;;;65.2;31-Feb-2026\n";

        $rows = iterator_to_array((new AmfiNavFileParser)->parse($contents), false);

        $this->assertNull($rows[0]->navDate);
    }

    public function test_it_rejects_content_without_the_header()
    {
        $this->expectException(AmfiNavFileException::class);

        iterator_to_array((new AmfiNavFileParser)->parse('<html>Service unavailable</html>'));
    }

    public function test_it_rejects_empty_content()
    {
        $this->expectException(AmfiNavFileException::class);

        iterator_to_array((new AmfiNavFileParser)->parse(''));
    }

    public function test_it_rejects_rows_with_an_unexpected_column_count()
    {
        $this->expectException(AmfiNavFileException::class);

        iterator_to_array((new AmfiNavFileParser)->parse(
            "Scheme Code;a;b;c;d;e;f;g\nOpen Ended Schemes(Gilt)\nSBI Mutual Fund\n101206;INF200K01180;SBI Gilt Fund;65.2;07-Oct-2026\n",
        ));
    }

    public function test_it_rejects_rows_before_any_section()
    {
        $this->expectException(AmfiNavFileException::class);

        iterator_to_array((new AmfiNavFileParser)->parse(
            "Scheme Code;a;b;c;d;e;f;g\n101206;INF200K01180;-;SBI Gilt Fund;;;65.2;07-Oct-2026\n",
        ));
    }
}
