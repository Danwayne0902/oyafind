<?php

namespace App\Providers;

use App\Contracts\ContactVerificationPolicy;
use App\Services\SelectedContactVerificationPolicy;
use Illuminate\Support\ServiceProvider;

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
        //
    }
}
