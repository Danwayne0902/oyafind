<?php

namespace App\Providers;

use App\Contracts\ContactVerificationPolicy;
use App\Services\SelectedContactVerificationPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            ContactVerificationPolicy::class,
            SelectedContactVerificationPolicy::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Sign-in is limited twice at the edge: once per client IP so one host
         * cannot spray many accounts, and once per IP + identifier so a single
         * account cannot be guessed quickly. The persistent account lockout in
         * LoginLockoutService then covers the case where an attacker rotates
         * IPs; the two controls are deliberate layers, not duplicates.
         */
        RateLimiter::for('login', function (Request $request): array {
            $identifier = Str::lower(trim((string) $request->input('identifier')));

            return [
                Limit::perMinute(max(1, (int) config('oyafind.auth.ip_attempts_per_minute', 10)))
                    ->by('login-ip:'.$request->ip()),
                Limit::perMinute(max(1, (int) config('oyafind.auth.identifier_attempts_per_minute', 5)))
                    ->by('login-identifier:'.$request->ip().'|'.hash('sha256', $identifier)),
            ];
        });
    }
}
