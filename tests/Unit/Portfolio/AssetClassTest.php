<?php

namespace Tests\Unit\Portfolio;

use App\Enums\AssetClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AssetClassTest extends TestCase
{
    /**
     * Every category in a real AMFI file, so new AMFI wording shows up here.
     *
     * @return array<string, array{string, string}>
     */
    public static function realCategories(): array
    {
        $cases = [];
        $lines = file(__DIR__.'/../../Fixtures/amfi/categories.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            if (! str_starts_with($line, '#')) {
                [$category, $class] = explode('|', $line);
                $cases[$category] = [$category, $class];
            }
        }

        return $cases;
    }

    #[DataProvider('realCategories')]
    public function test_every_real_category_maps_to_the_expected_class(string $category, string $expected)
    {
        $this->assertSame($expected, AssetClass::fromCategory($category)->value);
    }

    public function test_the_fixture_covers_all_77_categories()
    {
        $this->assertCount(77, self::realCategories());
    }

    public function test_unknown_categories_are_other()
    {
        $this->assertSame(AssetClass::Other, AssetClass::fromCategory('Something New - Brand New Fund'));
        $this->assertSame(AssetClass::Other, AssetClass::fromCategory(''));
    }
}
