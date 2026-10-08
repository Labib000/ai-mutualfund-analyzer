<?php

namespace App\Http\Requests\Ai;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AskRequest extends FormRequest
{
    public const MAX_QUESTION = 500;

    public const MAX_HISTORY_TURNS = 6;

    public const MAX_TURN_LENGTH = 2000;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:'.self::MAX_QUESTION],
            'history' => ['array', 'max:'.self::MAX_HISTORY_TURNS],
            'history.*' => ['array:role,content'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:'.self::MAX_TURN_LENGTH],
        ];
    }

    public function question(): string
    {
        return trim($this->string('question')->value());
    }

    /**
     * @return list<array{role: 'user'|'assistant', content: string}>
     */
    public function history(): array
    {
        /** @var list<array{role: 'user'|'assistant', content: string}> $history */
        $history = array_values($this->validated('history', []));

        return $history;
    }
}
