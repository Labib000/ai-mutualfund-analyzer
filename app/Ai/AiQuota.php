<?php

namespace App\Ai;

use App\Models\AiUsage;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Monthly allowance of successful AI requests per user, counted in IST
 * calendar months. Failed calls and cached answers don't count.
 */
class AiQuota
{
    public function limit(User $user): int
    {
        return config()->integer('ai.monthly_requests_per_user');
    }

    public function used(User $user): int
    {
        return AiUsage::query()
            ->where('user_id', $user->id)
            ->where('succeeded', true)
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth())
            ->count();
    }

    public function remaining(User $user): int
    {
        return max(0, $this->limit($user) - $this->used($user));
    }

    /**
     * @throws AiQuotaExceededException
     */
    public function ensureAvailable(User $user): void
    {
        if ($this->remaining($user) === 0) {
            throw new AiQuotaExceededException("You've used all {$this->limit($user)} AI requests for this month. They reset on the 1st.");
        }
    }
}
