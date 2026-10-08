<?php

namespace Tests\Feature\Ai;

use App\Ai\AiRequest;
use App\Ai\AiUnavailableException;
use App\Ai\GroqProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqProviderTest extends TestCase
{
    private const URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function test_it_sends_an_openai_compatible_request_and_reads_the_answer()
    {
        Http::fake([self::URL => Http::response([
            'model' => 'openai/gpt-oss-120b',
            'choices' => [['message' => ['role' => 'assistant', 'content' => "  Your portfolio grew.\n"], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 812, 'completion_tokens' => 240],
        ])]);

        $response = $this->provider()->complete(new AiRequest('Be helpful.', [
            ['role' => 'user', 'content' => 'Earlier question'],
            ['role' => 'assistant', 'content' => 'Earlier answer'],
            ['role' => 'user', 'content' => 'How am I doing?'],
        ], 1500));

        $this->assertSame('Your portfolio grew.', $response->text);
        $this->assertSame('openai/gpt-oss-120b', $response->model);
        $this->assertSame(812, $response->inputTokens);
        $this->assertSame(240, $response->outputTokens);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'openai/gpt-oss-120b'
            && $request['messages'][0] === ['role' => 'system', 'content' => 'Be helpful.']
            && $request['messages'][3] === ['role' => 'user', 'content' => 'How am I doing?']
            && $request['max_completion_tokens'] === 1500
            && $request['reasoning_effort'] === 'low'
            && $request['include_reasoning'] === false);
    }

    public function test_reasoning_parameters_are_left_out_for_other_models()
    {
        Http::fake([self::URL => Http::response(['choices' => [['message' => ['content' => 'Hi']]]])]);

        $this->provider(reasoningEffort: null)->complete(new AiRequest('S', [['role' => 'user', 'content' => 'Q']], 500));

        Http::assertSent(fn (Request $request) => ! isset($request['reasoning_effort']) && ! isset($request['include_reasoning']));
    }

    public function test_a_rate_limit_carries_the_retry_after_delay()
    {
        Http::fake([self::URL => Http::response(['error' => ['message' => 'Rate limit']], 429, ['retry-after' => '7.5'])]);

        try {
            $this->complete();
            $this->fail('Expected AiUnavailableException.');
        } catch (AiUnavailableException $e) {
            $this->assertSame(8, $e->retryAfterSeconds);
        }
    }

    public function test_server_errors_are_unavailable()
    {
        Http::fake([self::URL => Http::response('Bad gateway', 502)]);

        $this->expectException(AiUnavailableException::class);
        $this->complete();
    }

    public function test_connection_failures_are_unavailable()
    {
        Http::fake([self::URL => Http::failedConnection()]);

        $this->expectException(AiUnavailableException::class);
        $this->complete();
    }

    public function test_an_answer_cut_off_during_reasoning_is_unavailable()
    {
        Http::fake([self::URL => Http::response(['choices' => [['message' => ['content' => ''], 'finish_reason' => 'length']]])]);

        $this->expectException(AiUnavailableException::class);
        $this->expectExceptionMessage('finish reason: length');
        $this->complete();
    }

    public function test_a_malformed_body_is_unavailable()
    {
        Http::fake([self::URL => Http::response('<html>oops</html>')]);

        $this->expectException(AiUnavailableException::class);
        $this->complete();
    }

    public function test_a_missing_api_key_fails_without_calling_groq()
    {
        Http::preventStrayRequests();

        $this->expectException(AiUnavailableException::class);
        $this->expectExceptionMessage('No AI API key');
        (new GroqProvider('', 'openai/gpt-oss-120b', 'low', 20, 'https://api.groq.com/openai/v1'))
            ->complete(new AiRequest('S', [['role' => 'user', 'content' => 'Q']], 500));
    }

    private function complete(): void
    {
        $this->provider()->complete(new AiRequest('S', [['role' => 'user', 'content' => 'Q']], 500));
    }

    private function provider(?string $reasoningEffort = 'low'): GroqProvider
    {
        return new GroqProvider('test-key', 'openai/gpt-oss-120b', $reasoningEffort, 20, 'https://api.groq.com/openai/v1');
    }
}
