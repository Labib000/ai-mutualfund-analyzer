<?php

namespace Tests\Support;

use App\Ai\AiProvider;
use App\Ai\AiRequest;
use App\Ai\AiResponse;
use App\Ai\AiUnavailableException;

/**
 * Records every request and answers with canned text, or fails on demand.
 */
class FakeAiProvider implements AiProvider
{
    /** @var list<AiRequest> */
    public array $requests = [];

    public bool $failing = false;

    public function __construct(public string $answer = 'Your portfolio is worth more than you invested.') {}

    public function name(): string
    {
        return 'fake';
    }

    public function complete(AiRequest $request): AiResponse
    {
        $this->requests[] = $request;

        if ($this->failing) {
            throw new AiUnavailableException('Provider is down.');
        }

        return new AiResponse($this->answer, 'fake-model', 1200, 300);
    }

    public function lastUserMessage(): string
    {
        $request = end($this->requests);

        if ($request === false) {
            return '';
        }

        $messages = $request->messages;

        return end($messages)['content'] ?? '';
    }
}
