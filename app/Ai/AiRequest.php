<?php

namespace App\Ai;

final readonly class AiRequest
{
    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages  Oldest first, ending with the user's turn
     */
    public function __construct(
        public string $system,
        public array $messages,
        public int $maxOutputTokens,
    ) {}
}
