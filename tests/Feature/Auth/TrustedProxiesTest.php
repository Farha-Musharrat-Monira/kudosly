<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;

use function Pest\Laravel\get;

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
|
| Behind a load balancer or a CDN the application sees the proxy's
| address rather than the visitor's. That breaks the rate limiters,
| because every visitor would share one limit, and it makes the
| application build links with the wrong scheme.
|
| Trusting a proxy that is not really in front of the application is
| worse than the problem it solves, because a visitor could then forge
| the address the rate limiters key on. So nothing is trusted until a
| deployment says exactly which proxy to trust, and a wildcard is
| refused outright: trusting every proxy is the same as trusting none.
|
| The trusted addresses are held in Laravel's own TrustProxies state,
| which the global middleware reads. That state is static, so it is
| reset after every test here, otherwise one test's proxy would be
| trusted by the next.
|
*/

afterEach(function (): void {
    TrustProxies::flushState();
});

it('trusts no proxy when nothing is configured', function (): void {
    expect(AppServiceProvider::trustedProxyAddresses(''))->toBeNull();
});

it('trusts no proxy when the configuration holds only separators', function (): void {
    expect(AppServiceProvider::trustedProxyAddresses(' , , '))->toBeNull();
});

it('trusts no proxy when the configuration is null', function (): void {
    expect(AppServiceProvider::trustedProxyAddresses(null))->toBeNull();
});

it('trusts the configured proxy addresses', function (): void {
    expect(AppServiceProvider::trustedProxyAddresses('10.0.0.1,10.0.0.2'))
        ->toBe(['10.0.0.1', '10.0.0.2']);
});

it('trims whitespace around the configured proxy addresses', function (): void {
    expect(AppServiceProvider::trustedProxyAddresses(' 10.0.0.1 , 10.0.0.2 '))
        ->toBe(['10.0.0.1', '10.0.0.2']);
});

it('ignores an empty entry in the configured proxy list', function (): void {
    expect(AppServiceProvider::trustedProxyAddresses('10.0.0.1,,10.0.0.2'))
        ->toBe(['10.0.0.1', '10.0.0.2']);
});

it('refuses to trust every proxy', function (): void {
    // Trusting a wildcard is the same as trusting nobody, because anyone
    // could then send the forwarded headers.
    expect(AppServiceProvider::trustedProxyAddresses('*'))->toBeNull();
});

it('refuses to trust a wildcard mixed with real addresses', function (): void {
    expect(AppServiceProvider::trustedProxyAddresses('10.0.0.1,*'))->toBeNull();
});

it('does not honour a forwarded address from an untrusted client', function (): void {
    // With nothing trusted the forwarded header is ignored, so the
    // request keeps the address it actually came from. A visitor cannot
    // forge the address the rate limiters key on.
    get('/up', [
        'X-Forwarded-For' => '203.0.113.9',
        'X-Forwarded-Proto' => 'https',
    ])->assertOk();

    expect(request()->ip())->not->toBe('203.0.113.9');
});

it('does not build a secure url from a forged forwarded scheme', function (): void {
    get('/up', [
        'X-Forwarded-For' => '203.0.113.9',
        'X-Forwarded-Proto' => 'https',
    ])->assertOk();

    expect(request()->getScheme())->toBe('http');
});

it('honours a forwarded address from a configured trusted proxy', function (): void {
    // The request arrives from the proxy itself, which is on the list, so
    // the forwarded headers it sends are believed. Without this the
    // application would rate limit every visitor as one proxy.
    TrustProxies::at(['10.0.0.1']);

    get('/up', [
        'REMOTE_ADDR' => '10.0.0.1',
        'X-Forwarded-For' => '203.0.113.9',
    ])->assertOk();

    expect(request()->ip())->toBe('203.0.113.9');
});

it('builds a secure url from a forwarded scheme sent by a trusted proxy', function (): void {
    TrustProxies::at(['10.0.0.1']);

    get('/up', [
        'REMOTE_ADDR' => '10.0.0.1',
        'X-Forwarded-For' => '203.0.113.9',
        'X-Forwarded-Proto' => 'https',
    ])->assertOk();

    expect(request()->getScheme())->toBe('https');
});

it('still ignores a forwarded address when another proxy is trusted', function (): void {
    // The proxy that actually sent the request is not on the list, so its
    // forwarded headers must not be believed.
    TrustProxies::at(['10.0.0.9']);

    get('/up', [
        'REMOTE_ADDR' => '10.0.0.1',
        'X-Forwarded-For' => '203.0.113.9',
    ])->assertOk();

    expect(request()->ip())->not->toBe('203.0.113.9');
});

it('does not trust a proxy when the configuration is empty', function (): void {
    // The provider leaves the trusted state alone when nothing is
    // configured, so the forwarded headers are ignored.
    config(['kudosly.trusted_proxies' => '']);

    (new AppServiceProvider(app()))->boot();

    get('/up', [
        'REMOTE_ADDR' => '10.0.0.1',
        'X-Forwarded-For' => '203.0.113.9',
    ])->assertOk();

    expect(request()->ip())->not->toBe('203.0.113.9');
});

it('trusts a configured proxy through the provider', function (): void {
    config(['kudosly.trusted_proxies' => '10.0.0.1']);

    (new AppServiceProvider(app()))->boot();

    get('/up', [
        'REMOTE_ADDR' => '10.0.0.1',
        'X-Forwarded-For' => '203.0.113.9',
    ])->assertOk();

    expect(request()->ip())->toBe('203.0.113.9');
});

it('does not trust a wildcard through the provider', function (): void {
    config(['kudosly.trusted_proxies' => '*']);

    (new AppServiceProvider(app()))->boot();

    get('/up', [
        'REMOTE_ADDR' => '10.0.0.1',
        'X-Forwarded-For' => '203.0.113.9',
    ])->assertOk();

    expect(request()->ip())->not->toBe('203.0.113.9');
});
