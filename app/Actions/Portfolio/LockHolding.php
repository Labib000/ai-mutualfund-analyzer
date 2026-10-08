<?php

namespace App\Actions\Portfolio;

use App\Models\Holding;

/**
 * Serializes unit-changing writes on one holding. Call inside a DB transaction:
 * the row lock is held until it commits, so two concurrent redemptions can't
 * both pass the balance check.
 */
final class LockHolding
{
    public static function handle(Holding $holding): void
    {
        Holding::query()->whereKey($holding->getKey())->lockForUpdate()->first();
    }
}
