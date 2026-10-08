<?php

namespace App\Actions\Ai;

use App\Ai\AiQuotaExceededException;
use App\Ai\AiRequest;
use App\Ai\AiUnavailableException;
use App\Ai\PortfolioContext;
use App\Ai\Prompts;
use App\Ai\RunAiFeature;
use App\Enums\AiFeature;
use App\Models\Sip;
use App\Models\User;
use App\Portfolio\PortfolioPerformance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class SummarisePortfolio
{
    public function __construct(
        private readonly RunAiFeature $run,
        private readonly PortfolioContext $context,
        private readonly PortfolioPerformance $performance,
    ) {}

    /**
     * A plain-language summary of the user's portfolio, reused until the portfolio
     * changes (or for 24 hours) unless a fresh one is asked for.
     *
     * @return array{text: string, generated_at: string, cached: bool}|null Null when the user holds no funds
     *
     * @throws AiQuotaExceededException
     * @throws AiUnavailableException
     */
    public function handle(User $user, bool $fresh = false): ?array
    {
        $holdings = $user->holdings()->with(['scheme', 'transactions', 'sips'])->get();

        if ($holdings->isEmpty()) {
            return null;
        }

        $key = "ai-summary:{$user->id}";
        $version = md5($this->performance->version($holdings).'|'.json_encode(
            $holdings->flatMap->sips->sortBy('id')->map(fn (Sip $sip) => [$sip->id, $sip->updated_at?->toIso8601String()])->values(),
        ).'|'.config('ai.model'));

        $cached = Cache::get($key);

        if (! $fresh && is_array($cached) && ($cached['version'] ?? null) === $version) {
            return ['text' => $cached['text'], 'generated_at' => $cached['generated_at'], 'cached' => true];
        }

        $context = (string) $this->context->forUser($user);

        $response = $this->run->handle($user, AiFeature::Summary, new AiRequest(
            system: Prompts::SYSTEM,
            messages: [['role' => 'user', 'content' => Prompts::summary($context)]],
            maxOutputTokens: config()->integer('ai.max_output_tokens'),
        ));

        $generatedAt = CarbonImmutable::now()->toIso8601String();
        Cache::put($key, ['version' => $version, 'text' => $response->text, 'generated_at' => $generatedAt], now()->addDay());

        return ['text' => $response->text, 'generated_at' => $generatedAt, 'cached' => false];
    }
}
