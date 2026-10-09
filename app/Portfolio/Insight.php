<?php

namespace App\Portfolio;

/**
 * One factual observation about a portfolio. "attention" marks something the
 * user may want to look at (such as a loss), never a recommendation.
 */
final readonly class Insight
{
    /**
     * @param  'attention'|'info'  $tone
     */
    public function __construct(
        public string $code,
        public string $tone,
        public string $title,
        public string $detail,
    ) {}

    /**
     * @return array{code: string, tone: 'attention'|'info', title: string, detail: string}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'tone' => $this->tone, 'title' => $this->title, 'detail' => $this->detail];
    }
}
