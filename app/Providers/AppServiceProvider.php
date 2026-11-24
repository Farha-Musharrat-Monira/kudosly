<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Cashier's own routes are replaced by the ones in routes/web.php so
         * the webhook endpoint is served by Kudosly's controller, which applies
         * the subscription from the signed payload alone. Cashier's routes are
         * registered during boot, so this has to be set beforehand.
         */
        Cashier::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureTrustedProxies();
    }

    /**
     * Trust the forwarded headers of the proxies named in the configuration.
     *
     * Behind a load balancer or a CDN the application sees the proxy's
     * address rather than the visitor's, which breaks the rate limiters,
     * because every visitor would share one limit, and it makes the
     * application build links with the wrong scheme.
     *
     * Trusting a proxy that is not really in front of the application is
     * worse than the problem it solves, because a visitor could then forge
     * the address the rate limiters key on. So nothing is trusted until a
     * deployment says exactly which proxy to trust.
     *
     * The configuration is read here rather than in the middleware
     * configuration, because that callback also runs for the console
     * kernel, which is resolved before the configuration has been loaded.
     */
    protected function configureTrustedProxies(): void
    {
        $proxies = static::trustedProxyAddresses(
            (string) config('kudosly.trusted_proxies', '')
        );

        if ($proxies === null) {
            return;
        }

        TrustProxies::at($proxies);
    }

    /**
     * The proxy addresses that may be trusted, or null when none are.
     *
     * The configuration is a comma separated list of proxy addresses. A
     * wildcard is not accepted: trusting every proxy is the same as
     * trusting no proxy at all, because anyone could then send the
     * forwarded headers.
     *
     * @return array<int, string>|null
     */
    public static function trustedProxyAddresses(?string $configured): ?array
    {
        $configured = trim((string) $configured);

        if ($configured === '') {
            return null;
        }

        $proxies = array_values(array_filter(
            array_map('trim', explode(',', $configured)),
            static fn (string $proxy): bool => $proxy !== '',
        ));

        if ($proxies === []) {
            return null;
        }

        if (in_array('*', $proxies, true)) {
            return null;
        }

        return $proxies;
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
