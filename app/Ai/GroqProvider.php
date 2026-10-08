<?php

namespace App\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Groq's OpenAI-compatible chat completions API.
 *
 * @see https://console.groq.com/docs/reasoning for the gpt-oss reasoning parameters
 */
class GroqProvider implements AiProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?string $reasoningEffort,
        private readonly int $timeoutSeconds,
        private readonly string $baseUrl,
    ) {}

    public function name(): string
    {
        return 'groq';
    }

    public function complete(AiRequest $request): AiResponse
    {
        if ($this->apiKey === '') {
            throw new AiUnavailableException('No AI API key is configured.');
        }

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $request->system],
                ...$request->messages,
            ],
            'max_completion_tokens' => $request->maxOutputTokens,
        ];

        if ($this->reasoningEffort !== null && $this->reasoningEffort !== '') {
            // Reasoning text would otherwise be returned alongside the answer.
            $payload['reasoning_effort'] = $this->reasoningEffort;
            $payload['include_reasoning'] = false;
        }

        try {
            // No automatic retries: a user is waiting on this request.
            $response = Http::baseUrl($this->baseUrl)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->post('/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw new AiUnavailableException("Groq request failed: {$e->getMessage()}", previous: $e);
        }

        if ($response->status() === 429) {
            $retryAfter = $response->header('retry-after');

            throw new AiUnavailableException(
                'Groq rate limit reached.',
                is_numeric($retryAfter) ? (int) ceil((float) $retryAfter) : null,
            );
        }

        if ($response->failed()) {
            throw new AiUnavailableException("Groq returned HTTP {$response->status()}.");
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            // Typically the output limit was spent on reasoning (finish_reason "length").
            $reason = $response->json('choices.0.finish_reason');

            throw new AiUnavailableException('Groq returned no answer (finish reason: '.(is_string($reason) ? $reason : 'unknown').').');
        }

        return new AiResponse(
            text: trim($text),
            model: is_string($model = $response->json('model')) ? $model : $this->model,
            inputTokens: (int) $response->json('usage.prompt_tokens', 0),
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
        );
    }
}
