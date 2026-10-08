<?php

namespace App\Http\Controllers\Portfolio;

use App\Actions\Portfolio\CreateSip;
use App\Actions\Portfolio\DeleteSip;
use App\Actions\Portfolio\StopSip;
use App\Actions\Portfolio\UpdateSip;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portfolio\StoreSipRequest;
use App\Http\Requests\Portfolio\UpdateSipRequest;
use App\Models\Holding;
use App\Models\Sip;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SipController extends Controller
{
    /**
     * Start a SIP, recording any installments already due.
     */
    public function store(StoreSipRequest $request, Holding $holding, CreateSip $createSip): RedirectResponse
    {
        [$sip, $backfilled] = $createSip->handle(
            $holding,
            $request->amountPaise(),
            $request->integer('day_of_month'),
            $request->startDate(),
            $request->endDate(),
        );

        $count = $sip->transactions()->count();

        Inertia::flash('toast', $backfilled
            ? ['type' => 'success', 'message' => 'SIP added'.($count > 0 ? " with {$count} past installment".($count === 1 ? '' : 's') : '').'.']
            : ['type' => 'warning', 'message' => "SIP added. Past installments couldn't be recorded right now and will be added automatically."]);

        return to_route('holdings.show', $holding);
    }

    /**
     * Change a SIP's amount, day or end date for future installments.
     */
    public function update(UpdateSipRequest $request, Holding $holding, Sip $sip, UpdateSip $updateSip): RedirectResponse
    {
        $updateSip->handle($sip, $request->amountPaise(), $request->integer('day_of_month'), $request->endDate());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'SIP updated. Recorded installments are unchanged.']);

        return to_route('holdings.show', $holding);
    }

    /**
     * Stop a SIP from today.
     */
    public function stop(Holding $holding, Sip $sip, StopSip $stopSip): RedirectResponse
    {
        $stopSip->handle($sip);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'SIP stopped.']);

        return to_route('holdings.show', $holding);
    }

    /**
     * Delete a SIP and its installments.
     */
    public function destroy(Holding $holding, Sip $sip, DeleteSip $deleteSip): RedirectResponse
    {
        $deleteSip->handle($sip);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'SIP and its installments deleted.']);

        return to_route('holdings.show', $holding);
    }
}
