<?php

namespace App\Portfolio;

use App\Enums\AssetClass;

/**
 * How current value splits across asset classes and AMFI sub-categories.
 */
final class Allocation
{
    /**
     * @param  list<array{category: string, value_paise: int}>  $holdings
     * @return array{
     *     classes: list<array{key: string, label: string, value_paise: int, pct: float}>,
     *     categories: list<array{label: string, asset_class: string, value_paise: int, pct: float}>
     * }
     */
    public function of(array $holdings): array
    {
        $holdings = array_values(array_filter($holdings, fn (array $holding) => $holding['value_paise'] > 0));
        $total = array_sum(array_column($holdings, 'value_paise'));

        if ($total === 0) {
            return ['classes' => [], 'categories' => []];
        }

        $byClass = [];
        $byCategory = [];

        foreach ($holdings as $holding) {
            $class = AssetClass::fromCategory($holding['category']);
            $byClass[$class->value] = ($byClass[$class->value] ?? 0) + $holding['value_paise'];

            $key = $holding['category'];
            $byCategory[$key] ??= ['label' => self::subCategory($key), 'asset_class' => $class->value, 'value_paise' => 0];
            $byCategory[$key]['value_paise'] += $holding['value_paise'];
        }

        $classes = [];
        foreach (AssetClass::cases() as $class) {
            if (isset($byClass[$class->value])) {
                $classes[] = [
                    'key' => $class->value,
                    'label' => $class->label(),
                    'value_paise' => $byClass[$class->value],
                    'pct' => round($byClass[$class->value] / $total * 100, 2),
                ];
            }
        }

        $categories = array_values($byCategory);
        usort($categories, fn (array $a, array $b) => [$b['value_paise'], $a['label']] <=> [$a['value_paise'], $b['label']]);

        return [
            'classes' => $classes,
            'categories' => array_map(fn (array $category) => [
                ...$category,
                'pct' => round($category['value_paise'] / $total * 100, 2),
            ], $categories),
        ];
    }

    /**
     * "Equity Scheme - Large Cap Fund" → "Large Cap Fund"; legacy categories without a group stay as they are.
     */
    private static function subCategory(string $category): string
    {
        $parts = explode(' - ', $category, 2);

        return $parts[1] ?? $parts[0];
    }
}
