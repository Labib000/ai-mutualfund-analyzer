<?php

namespace App\Actions\Ai;

use App\Ai\AiQuotaExceededException;
use App\Ai\AiRequest;
use App\Ai\AiUnavailableException;
use App\Ai\Prompts;
use App\Ai\RunAiFeature;
use App\Enums\AiFeature;
use App\Enums\AssetClass;
use App\Models\Scheme;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ExplainFund
{
    /** Explanations aren't personal, so one per scheme is shared by everyone for a month. */
    private const CACHE_DAYS = 30;

    public function __construct(private readonly RunAiFeature $run) {}

    /**
     * @return array{text: string, cached: bool}
     *
     * @throws AiQuotaExceededException
     * @throws AiUnavailableException
     */
    public function handle(User $user, Scheme $scheme): array
    {
        $key = 'ai-explain:'.$scheme->id.':'.md5(config()->string('ai.model').Prompts::EXPLAIN_TASK);
        $cached = Cache::get($key);

        if (is_string($cached)) {
            return ['text' => $cached, 'cached' => true];
        }

        $text = $this->run->handle($user, AiFeature::Explain, new AiRequest(
            system: Prompts::SYSTEM,
            messages: [['role' => 'user', 'content' => Prompts::explain(self::facts($scheme))]],
            maxOutputTokens: config()->integer('ai.max_output_tokens'),
        ))->text;

        Cache::put($key, $text, now()->addDays(self::CACHE_DAYS));

        return ['text' => $text, 'cached' => false];
    }

    private static function facts(Scheme $scheme): string
    {
        $lines = [
            "Scheme: {$scheme->name}",
            "Fund house: {$scheme->amc}",
            "AMFI category: {$scheme->category}",
            'Asset class: '.AssetClass::fromCategory($scheme->category)->label(),
            'Plan: '.match ($scheme->plan?->value) {
                'direct' => 'Direct',
                'regular' => 'Regular',
                default => 'not stated',
            },
        ];

        if ($scheme->latest_nav !== null && $scheme->latest_nav_date !== null) {
            $lines[] = "Latest NAV: ₹{$scheme->latest_nav} on ".$scheme->latest_nav_date->format('j M Y');
        }

        return implode("\n", $lines);
    }
}
