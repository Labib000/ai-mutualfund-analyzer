<?php

namespace App\Providers;

use App\Nav\MfApiNavProvider;
use App\Nav\NavProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
