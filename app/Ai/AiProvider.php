<?php

namespace App\Ai;

/**
 * A large language model behind a single completion call.
 */
interface AiProvider
{
    /**
     * @throws AiUnavailableException when the provider can't answer right now
     */
    public function complete(AiRequest $request): AiResponse;

    /** Short provider name for usage logs, e.g. "groq". */
    public function name(): string;
}
