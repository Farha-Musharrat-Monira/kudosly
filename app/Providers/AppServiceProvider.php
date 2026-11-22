<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters used by the application.
     *
     * A public testimonial submission is limited per space and per address.
     * The key combines both so that one busy space cannot exhaust another
     * space's allowance, and a single address cannot exhaust a space's
     * allowance either.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('testimonial-submit', function (Request $request): Limit {
            $slug = (string) $request->route('slug');

            $key = Str::transliterate(Str::lower($slug)).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });
    }
}
