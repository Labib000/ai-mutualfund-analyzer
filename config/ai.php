<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI provider
    |--------------------------------------------------------------------------
    |
    | The AI features talk to a provider through App\Ai\AiProvider. "groq" uses
    | Groq's OpenAI-compatible chat API (free tier). The key lives only in .env.
    |
    */

    'provider' => env('AI_PROVIDER', 'groq'),

    'api_key' => env('AI_API_KEY'),

    'model' => env('AI_MODEL', 'openai/gpt-oss-120b'),

    // Reasoning models (gpt-oss) think before answering; low keeps answers quick.
    // Leave empty for models that don't support it.
    'reasoning_effort' => env('AI_REASONING_EFFORT', 'low'),

    // Room for the model's reasoning plus a ~200-word answer.
    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 2000),

    'timeout' => (int) env('AI_TIMEOUT_SECONDS', 20),

    'groq_base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),

    // Successful AI requests each user may make per calendar month (IST).
    'monthly_requests_per_user' => (int) env('AI_MONTHLY_REQUESTS_PER_USER', 20),

];
