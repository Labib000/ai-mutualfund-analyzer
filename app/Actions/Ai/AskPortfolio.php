<?php

namespace App\Actions\Ai;

use App\Ai\AiQuotaExceededException;
use App\Ai\AiRequest;
use App\Ai\AiUnavailableException;
use App\Ai\PortfolioContext;
use App\Ai\Prompts;
use App\Ai\RunAiFeature;
use App\Enums\AiFeature;
use App\Models\User;

class AskPortfolio
{
    public function __construct(
        private readonly RunAiFeature $run,
        private readonly PortfolioContext $context,
    ) {}

    /**
     * Answer a question about the user's portfolio. Earlier turns come from the
     * browser; the current portfolio data rides along with the newest question.
     *
     * @param  list<array{role: 'user'|'assistant', content: string}>  $history
     * @return string|null Null when the user holds no funds
     *
     * @throws AiQuotaExceededException
     * @throws AiUnavailableException
     */
    public function handle(User $user, string $question, array $history = []): ?string
    {
        $context = $this->context->forUser($user);

        if ($context === null) {
            return null;
        }

        return $this->run->handle($user, AiFeature::Ask, new AiRequest(
            system: Prompts::SYSTEM,
            messages: [
                ...$history,
                ['role' => 'user', 'content' => Prompts::question($context, $question)],
            ],
            maxOutputTokens: config()->integer('ai.max_output_tokens'),
        ))->text;
    }
}
