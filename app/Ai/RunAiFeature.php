<?php

namespace App\Ai;

use App\Enums\AiFeature;
use App\Models\AiUsage;
use App\Models\User;

/**
 * The one path to the AI provider: checks the quota, calls the provider and
 * logs every call, successful or not.
 */
class RunAiFeature
{
    public function __construct(
        private readonly AiProvider $provider,
        private readonly AiQuota $quota,
    ) {}

    /**
     * @throws AiQuotaExceededException
     * @throws AiUnavailableException
     */
    public function handle(User $user, AiFeature $feature, AiRequest $request): AiResponse
    {
        $this->quota->ensureAvailable($user);

        try {
            $response = $this->provider->complete($request);
        } catch (AiUnavailableException $e) {
            $this->log($user, $feature, config()->string('ai.model'), 0, 0, false);
            report($e);

            throw $e;
        }

        $this->log($user, $feature, $response->model, $response->inputTokens, $response->outputTokens, true);

        return $response;
    }

    private function log(User $user, AiFeature $feature, string $model, int $input, int $output, bool $succeeded): void
    {
        AiUsage::query()->create([
            'user_id' => $user->id,
            'feature' => $feature,
            'provider' => $this->provider->name(),
            'model' => $model,
            'input_tokens' => $input,
            'output_tokens' => $output,
            'succeeded' => $succeeded,
            'created_at' => now(),
        ]);
    }
}
