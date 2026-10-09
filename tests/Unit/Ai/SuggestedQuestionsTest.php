<?php

namespace Tests\Unit\Ai;

use App\Ai\SuggestedQuestions;
use App\Portfolio\Insight;
use PHPUnit\Framework\TestCase;

class SuggestedQuestionsTest extends TestCase
{
    public function test_insight_questions_come_first_and_general_ones_fill_the_rest()
    {
        $questions = SuggestedQuestions::for([
            new Insight('loss', 'attention', 'A is below its cost', '', 'Why is A below its cost?'),
            new Insight('idle', 'info', 'No new money', ''),
            new Insight('regular_plan', 'info', 'Regular', '', 'What is the difference between Direct and Regular plans?'),
            new Insight('short_history', 'info', 'Short', '', 'Why is my XIRR not reliable yet?'),
        ]);

        $this->assertSame([
            'Why is A below its cost?',
            'What is the difference between Direct and Regular plans?',
            'How did my portfolio change in the last 30 days?',
            'Which of my funds has the best XIRR so far?',
        ], $questions);
    }

    public function test_without_insights_the_general_questions_are_used()
    {
        $this->assertCount(SuggestedQuestions::LIMIT, SuggestedQuestions::for([]));
        $this->assertContains('How much have I invested in each financial year?', SuggestedQuestions::for([]));
    }
}
