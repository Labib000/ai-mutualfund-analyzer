<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Portfolio\HoldingController;
use App\Http\Controllers\Portfolio\SipController;
use App\Http\Controllers\Portfolio\TransactionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('ask', [AiController::class, 'page'])->name('ask');
    Route::middleware('throttle:ai')->prefix('ai')->name('ai.')->group(function () {
        Route::post('summary', [AiController::class, 'summary'])->name('summary');
        Route::post('ask', [AiController::class, 'ask'])->name('ask');
        Route::post('digest', [AiController::class, 'digest'])->name('digest');
    });

    Route::get('holdings', [HoldingController::class, 'index'])->name('holdings.index');
    Route::get('holdings/create', [HoldingController::class, 'create'])->name('holdings.create');
    Route::post('holdings', [HoldingController::class, 'store'])->name('holdings.store');

    // Everything inside a holding is limited to its owner, and child records
    // must belong to the holding in the URL.
    Route::middleware('can:manage,holding')->scopeBindings()->prefix('holdings/{holding}')->name('holdings.')->group(function () {
        Route::get('/', [HoldingController::class, 'show'])->name('show');
        Route::delete('/', [HoldingController::class, 'destroy'])->name('destroy');

        Route::post('purchases', [TransactionController::class, 'storePurchase'])->name('purchases.store');
        Route::post('redemptions', [TransactionController::class, 'storeRedemption'])->name('redemptions.store');
        Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

        Route::post('sips', [SipController::class, 'store'])->name('sips.store');
        Route::patch('sips/{sip}', [SipController::class, 'update'])->name('sips.update');
        Route::post('sips/{sip}/stop', [SipController::class, 'stop'])->name('sips.stop');
        Route::delete('sips/{sip}', [SipController::class, 'destroy'])->name('sips.destroy');

        Route::post('explain', [AiController::class, 'explain'])->middleware('throttle:ai')->name('explain');
    });
});

require __DIR__.'/settings.php';
