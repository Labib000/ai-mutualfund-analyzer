<?php

namespace App\Enums;

/**
 * Broad asset classes for allocation, derived from AMFI's scheme categories.
 * The case order is the fixed display (and colour) order.
 */
enum AssetClass: string
{
    case Equity = 'equity';
    case Debt = 'debt';
    case Hybrid = 'hybrid';
    case SolutionOriented = 'solution_oriented';
    case Other = 'other';

    /**
     * Map an AMFI category such as "Equity Scheme - Large Cap Fund", including
     * legacy ones like "Income" or "Growth", to its asset class.
     */
    public static function fromCategory(string $category): self
    {
        [$group, $type] = array_pad(explode(' - ', $category, 2), 2, '');

        return match (true) {
            in_array($group, ['Equity Scheme', 'ELSS', 'Growth'], true) => self::Equity,
            in_array($group, ['Debt Scheme', 'Income/Debt Oriented Scheme', 'Income', 'Gilt', 'Money Market'], true) => self::Debt,
            $group === 'Hybrid Scheme' => self::Hybrid,
            $group === 'Index Funds' => match ($type) {
                'Equity Funds' => self::Equity,
                'Debt Funds' => self::Debt,
                'Hybrid Fund' => self::Hybrid,
                default => self::Other,
            },
            in_array($group, ['Solution Oriented Scheme', 'Life Cycle Funds'], true),
            str_starts_with($group, 'Children') => self::SolutionOriented,
            default => self::Other,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Equity => 'Equity',
            self::Debt => 'Debt',
            self::Hybrid => 'Hybrid',
            self::SolutionOriented => 'Solution oriented',
            self::Other => 'Other',
        };
    }
}
