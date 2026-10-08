<?php

namespace App\Nav;

use App\Enums\SchemePlan;
use App\Enums\SchemeType;
use App\Support\Decimal;
use DateTimeImmutable;
use Generator;

/**
 * Parses AMFI's daily NAVAll.txt.
 *
 * The file is a header line followed by section lines such as
 * "Open Ended Schemes(Equity Scheme - Large Cap Fund)", AMC name lines,
 * blank lines and semicolon-separated data rows:
 * code;isinGrowth;isinReinvestment;name;plan;option;nav;date
 */
final class AmfiNavFileParser
{
    private const HEADER_PREFIX = 'Scheme Code;';

    private const FIELD_COUNT = 8;

    private const NAV_SCALE = 4;

    /**
     * @return Generator<int, AmfiNavRow>
     *
     * @throws AmfiNavFileException when the content is not an AMFI NAV file
     */
    public function parse(string $contents): Generator
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        $headerSeen = false;
        $section = null;
        $amc = null;

        foreach ($lines as $number => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (! $headerSeen) {
                if (! str_starts_with($line, self::HEADER_PREFIX)) {
                    throw new AmfiNavFileException('Missing the "Scheme Code;..." header line.');
                }

                $headerSeen = true;

                continue;
            }

            if (! str_contains($line, ';')) {
                $parsedSection = $this->parseSection($line);

                // A section line is sometimes followed directly by rows of the
                // previous AMC, so the AMC carries over across sections.
                if ($parsedSection !== null) {
                    $section = $parsedSection;
                } else {
                    $amc = $line;
                }

                continue;
            }

            $fields = array_map('trim', explode(';', $line));

            if (count($fields) !== self::FIELD_COUNT || ! ctype_digit($fields[0])) {
                throw new AmfiNavFileException('Unexpected data row on line '.($number + 1).'.');
            }

            if ($section === null || $amc === null) {
                throw new AmfiNavFileException('Data row before a section or AMC line on line '.($number + 1).'.');
            }

            yield $this->makeRow($fields, $section, $amc);
        }

        if (! $headerSeen) {
            throw new AmfiNavFileException('The file is empty.');
        }
    }

    /**
     * @return array{SchemeType, string}|null
     */
    private function parseSection(string $line): ?array
    {
        if (preg_match('/^(Open Ended|Close Ended|Interval Fund) Schemes\s*\((.*)\)$/', $line, $matches) !== 1) {
            return null;
        }

        $type = match ($matches[1]) {
            'Open Ended' => SchemeType::OpenEnded,
            'Close Ended' => SchemeType::CloseEnded,
            'Interval Fund' => SchemeType::Interval,
        };

        // AMFI uses both "Equity Scheme - ..." and "Equity Schemes - ..." for the same category.
        $category = preg_replace('/\bSchemes\b/', 'Scheme', $matches[2]) ?? $matches[2];
        $category = preg_replace(['/\s*\*+/', '/\s+/'], ['', ' '], trim($category)) ?? $category;

        return [$type, $category];
    }

    /**
     * @param  list<string>  $fields
     * @param  array{SchemeType, string}  $section
     */
    private function makeRow(array $fields, array $section, string $amc): AmfiNavRow
    {
        [$code, $isinGrowth, $isinReinvestment, $name, $plan, $option, $nav, $date] = $fields;

        return new AmfiNavRow(
            amfiCode: (int) $code,
            isinGrowth: $this->isin($isinGrowth),
            isinReinvestment: $this->isin($isinReinvestment),
            name: implode(' - ', array_filter([$name, $plan, $option], fn (string $part) => $part !== '')),
            amc: $amc,
            schemeType: $section[0],
            category: $section[1],
            plan: match ($plan) {
                'Direct Plan' => SchemePlan::Direct,
                'Regular Plan' => SchemePlan::Regular,
                default => null,
            },
            nav: $this->nav($nav),
            navDate: $this->date($date),
        );
    }

    private function isin(string $value): ?string
    {
        return preg_match('/^IN[A-Z0-9]{10}$/', $value) === 1 ? $value : null;
    }

    private function nav(string $value): ?string
    {
        if (! Decimal::isNumeric($value) || bccomp($value, '0', self::NAV_SCALE + 4) <= 0) {
            return null;
        }

        return Decimal::round($value, self::NAV_SCALE);
    }

    private function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!d-M-Y', $value);

        return $date !== false && $date->format('d-M-Y') === $value ? $date : null;
    }
}
