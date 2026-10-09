<?php

namespace App\Ai;

use App\Portfolio\Insight;

/**
 * Starter questions for the Ask page, led by the portfolio's own insights.
 * Built without AI, so they cost nothing.
 */
final class SuggestedQuestions
{
    public const LIMIT = 4;

    private const GENERAL = [
        'How did my portfolio change in the last 30 days?',
        'Which of my funds has the best XIRR so far?',
        'How much have I invested in each financial year?',
        'How is my money split across asset classes?',
    ];

    /**
     * @param  list<Insight>  $insights
     * @return list<string>
     */
    public static function for(array $insights): array
    {
        $fromInsights = array_filter(array_map(fn (Insight $insight) => $insight->question, $insights));

        // At most two from insights, so the general ones still show.
        $questions = [...array_slice(array_values(array_unique($fromInsights)), 0, 2), ...self::GENERAL];

        return array_slice(array_values(array_unique($questions)), 0, self::LIMIT);
    }
}
