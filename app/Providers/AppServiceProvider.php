<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * How many calls one user may start per minute, so nobody can be rung over and over.
     */
    public const CALL_STARTS_PER_MINUTE = 10;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
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

    /**
     * Limit how often a user can start calls (applied to POST /calls as `throttle:call-starts`).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('call-starts', fn (Request $request): Limit => Limit::perMinute(self::CALL_STARTS_PER_MINUTE)
            ->by($request->user()->id)
            ->response(fn () => response()->json(['message' => __('You are starting calls too quickly. Please wait a moment.')], 429)));
    }
}
