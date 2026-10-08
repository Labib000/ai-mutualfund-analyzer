<?php

namespace App\Providers;

use App\Ai\AiProvider;
use App\Ai\GroqProvider;
use App\Nav\MfApiNavProvider;
use App\Nav\NavProvider;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NavProvider::class, fn (): NavProvider => match (config('services.nav_history.provider')) {
            'mfapi' => new MfApiNavProvider(
                baseUrl: config()->string('services.mfapi.base_url'),
                timeoutSeconds: config()->integer('services.mfapi.timeout'),
            ),
            default => throw new InvalidArgumentException('Unknown NAV_HISTORY_PROVIDER ['.config()->string('services.nav_history.provider').'].'),
        });

        $this->app->bind(AiProvider::class, fn (): AiProvider => match (config('ai.provider')) {
            'groq' => new GroqProvider(
                apiKey: (string) config('ai.api_key'),
                model: config()->string('ai.model'),
                reasoningEffort: is_string($effort = config('ai.reasoning_effort')) ? $effort : null,
                timeoutSeconds: config()->integer('ai.timeout'),
                baseUrl: config()->string('ai.groq_base_url'),
            ),
            default => throw new InvalidArgumentException('Unknown AI_PROVIDER ['.config()->string('ai.provider').'].'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // AI calls are slow and rate-limited upstream; keep each user to a few a minute.
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(5)->by((string) $request->user()?->id ?: $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
