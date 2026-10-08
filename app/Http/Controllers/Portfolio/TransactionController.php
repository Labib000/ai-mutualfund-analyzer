<?php

namespace App\Http\Controllers\Portfolio;

use App\Actions\Portfolio\AddPurchase;
use App\Actions\Portfolio\AddRedemption;
use App\Actions\Portfolio\DeleteTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portfolio\StorePurchaseRequest;
use App\Http\Requests\Portfolio\StoreRedemptionRequest;
use App\Models\Holding;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TransactionController extends Controller
{
    /**
     * Record a lump-sum purchase.
     */
    public function storePurchase(StorePurchaseRequest $request, Holding $holding, AddPurchase $addPurchase): RedirectResponse
    {
        $transaction = $addPurchase->handle($holding, $request->txnDate(), $request->amountPaise(), $request->unitsOverride());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Purchase recorded: {$transaction->units} units."]);

        return to_route('holdings.show', $holding);
    }

    /**
     * Record a redemption.
     */
    public function storeRedemption(StoreRedemptionRequest $request, Holding $holding, AddRedemption $addRedemption): RedirectResponse
    {
        $transaction = $addRedemption->handle($holding, $request->txnDate(), $request->units(), $request->amountPaise());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Redemption recorded: {$transaction->units} units."]);

        return to_route('holdings.show', $holding);
    }

    /**
     * Delete a transaction.
     */
    public function destroy(Holding $holding, Transaction $transaction, DeleteTransaction $deleteTransaction): RedirectResponse
    {
        $deleteTransaction->handle($transaction);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction deleted.']);

        return to_route('holdings.show', $holding);
    }
}
