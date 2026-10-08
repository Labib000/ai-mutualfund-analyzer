<?php

namespace App\Actions\Portfolio;

use App\Models\Transaction;
use App\Portfolio\UnitLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteTransaction
{
    /**
     * Delete a transaction, unless removing it would leave a later redemption
     * selling more units than were held.
     *
     * @throws ValidationException
     */
    public function handle(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            LockHolding::handle($transaction->holding);

            $remaining = $transaction->holding->transactions()->whereKeyNot($transaction->getKey())->get();
            $overdrawn = UnitLedger::fromTransactions($remaining)->firstOverdrawnDate();

            if ($overdrawn !== null) {
                throw ValidationException::withMessages([
                    'transaction' => 'Deleting this would leave the redemption on '
                        .CarbonImmutable::parse($overdrawn)->format('j M Y').' with more units than you held.',
                ]);
            }

            $transaction->delete();
        });
    }
}
