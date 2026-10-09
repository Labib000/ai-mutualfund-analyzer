<?php

namespace App\Http\Controllers;

use App\Actions\Ai\AskPortfolio;
use App\Actions\Ai\DigestPortfolio;
use App\Actions\Ai\ExplainFund;
use App\Actions\Ai\SummarisePortfolio;
use App\Ai\AiQuota;
use App\Ai\AiQuotaExceededException;
use App\Ai\AiUnavailableException;
use App\Enums\ChangePeriod;
use App\Http\Requests\Ai\AskRequest;
use App\Models\Holding;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * JSON endpoints for the AI features, plus the Ask page. Every failure becomes a
 * friendly message; nothing here ever shows the user a stack trace.
 */
class AiController extends Controller
{
    private const NO_FUNDS = 'Add a fund and some transactions first, then the AI can explain your portfolio.';

    public function __construct(private readonly AiQuota $quota) {}

    public function page(Request $request): Response
    {
        return Inertia::render('ask', [
            'has_funds' => $request->user()->holdings()->exists(),
            'remaining' => $this->quota->remaining($request->user()),
        ]);
    }

    public function summary(Request $request, SummarisePortfolio $summarise): JsonResponse
    {
        return $this->respond($request, function () use ($request, $summarise) {
            $summary = $summarise->handle($request->user(), $request->boolean('fresh'));

            return $summary ?? self::noFunds();
        });
    }

    public function ask(AskRequest $request, AskPortfolio $ask): JsonResponse
    {
        return $this->respond($request, function () use ($request, $ask) {
            $text = $ask->handle($request->user(), $request->question(), $request->history());

            return $text === null ? self::noFunds() : ['text' => $text];
        });
    }

    public function digest(Request $request, DigestPortfolio $digest): JsonResponse
    {
        $validated = $request->validate(['period' => ['required', Rule::enum(ChangePeriod::class)]]);

        return $this->respond($request, function () use ($request, $digest, $validated) {
            return $digest->handle($request->user(), ChangePeriod::from($validated['period']))
                ?? response()->json(['error' => 'There is no change to explain yet. Check back once your funds have NAVs for this period.'], 422);
        });
    }

    public function explain(Request $request, Holding $holding, ExplainFund $explain): JsonResponse
    {
        return $this->respond($request, fn () => $explain->handle($request->user(), $holding->scheme));
    }

    /**
     * @param  Closure(): (array<string, mixed>|JsonResponse)  $action
     */
    private function respond(Request $request, Closure $action): JsonResponse
    {
        try {
            $result = $action();
        } catch (AiQuotaExceededException $e) {
            return $this->error($request, $e->getMessage(), 429);
        } catch (AiUnavailableException) {
            return $this->error($request, 'The AI assistant is busy or unavailable right now. Please try again in a minute.', 503);
        }

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([...$result, 'remaining' => $this->quota->remaining($request->user())]);
    }

    private function error(Request $request, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => $message, 'remaining' => $this->quota->remaining($request->user())], $status);
    }

    private static function noFunds(): JsonResponse
    {
        return response()->json(['error' => self::NO_FUNDS], 422);
    }
}
