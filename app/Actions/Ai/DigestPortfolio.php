<?php

namespace App\Actions\Ai;

use App\Ai\AiQuotaExceededException;
use App\Ai\AiRequest;
use App\Ai\AiUnavailableException;
use App\Ai\ChangeText;
use App\Ai\Prompts;
use App\Ai\RunAiFeature;
use App\Enums\AiFeature;
use App\Enums\ChangePeriod;
use App\Models\User;
use App\Portfolio\PortfolioPerformance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class DigestPortfolio
{
    public function __construct(
        private readonly RunAiFeature $run,
        private readonly PortfolioPerformance $performance,
    ) {}

    /**
     * A plain-language explanation of how the portfolio's value changed over the
     * period, reused until the portfolio or its NAVs change.
     *
     * @return array{text: string, generated_at: string, cached: bool}|null Null when there's nothing to compare yet
     *
     * @throws AiQuotaExceededException
     * @throws AiUnavailableException
     */
    public function handle(User $user, ChangePeriod $period): ?array
    {
        ['holdings' => $holdings, 'inputs' => $inputs] = $this->performance->forUser($user);
        $change = $this->performance->changes($holdings, $inputs)[$period->value] ?? null;

        if ($change === null || $change->funds === []) {
            return null;
        }

        $key = "ai-digest:{$user->id}:{$period->value}";
        $version = md5($this->performance->version($holdings).'|'.config('ai.model'));
        $cached = Cache::get($key);

        if (is_array($cached) && ($cached['version'] ?? null) === $version) {
            return ['text' => $cached['text'], 'generated_at' => $cached['generated_at'], 'cached' => true];
        }

        $response = $this->run->handle($user, AiFeature::Digest, new AiRequest(
            system: Prompts::SYSTEM,
            messages: [['role' => 'user', 'content' => Prompts::digest(ChangeText::render($change))]],
            maxOutputTokens: config()->integer('ai.max_output_tokens'),
        ));

        $generatedAt = CarbonImmutable::now()->toIso8601String();
        Cache::put($key, ['version' => $version, 'text' => $response->text, 'generated_at' => $generatedAt], now()->addDay());

        return ['text' => $response->text, 'generated_at' => $generatedAt, 'cached' => false];
    }
}
