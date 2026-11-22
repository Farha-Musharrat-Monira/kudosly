<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * The throttle key the Login component uses.
 */
function loginThrottleKey(string $email): string
{
    return Str::transliterate(Str::lower($email).'|'.request()->ip());
}

beforeEach(function (): void {
    // The limiter uses the array cache store in the test environment, so the
    // state must be cleared between tests to stop them interfering.
    RateLimiter::clear(loginThrottleKey('ada@example.com'));
    RateLimiter::clear(loginThrottleKey('nobody@example.com'));
});

it('logs a user in with valid credentials', function (): void {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('password-123'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'ada@example.com')
        ->set('password', 'password-123')
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($user->id);
});

it('rejects an incorrect password', function (): void {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('password-123'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'ada@example.com')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

it('rejects an unknown email address', function (): void {
    Livewire::test(Login::class)
        ->set('email', 'nobody@example.com')
        ->set('password', 'password-123')
        ->call('authenticate')
        ->assertHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

it('rate limits repeated failed logins', function (): void {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('password-123'),
    ]);

    $component = Livewire::test(Login::class)
        ->set('email', 'ada@example.com')
        ->set('password', 'wrong-password');

    // The first five attempts fail with the generic authentication error.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $component->call('authenticate')
            ->assertHasErrors(['email' => trans('auth.failed')]);
    }

    // The sixth attempt is rejected by the limiter before the credentials are
    // ever checked, so the throttle message is returned instead.
    $component->call('authenticate')
        ->assertHasErrors(['email' => trans('auth.throttle', [
            'seconds' => RateLimiter::availableIn(loginThrottleKey('ada@example.com')),
            'minutes' => ceil(RateLimiter::availableIn(loginThrottleKey('ada@example.com')) / 60),
        ])]);

    expect(Auth::check())->toBeFalse();
});

it('fires a lockout event once the throttle is reached', function (): void {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('password-123'),
    ]);

    Event::fake();

    $component = Livewire::test(Login::class)
        ->set('email', 'ada@example.com')
        ->set('password', 'wrong-password');

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $component->call('authenticate');
    }

    $component->call('authenticate');

    Event::assertDispatched(Lockout::class);
});

it('clears the rate limit after a successful login', function (): void {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('password-123'),
    ]);

    $key = loginThrottleKey('ada@example.com');

    $component = Livewire::test(Login::class)
        ->set('email', 'ada@example.com')
        ->set('password', 'wrong-password');

    // Fewer attempts than the threshold, so the login is not throttled.
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $component->call('authenticate');
    }

    expect(RateLimiter::attempts($key))->toBe(3);

    Livewire::test(Login::class)
        ->set('email', 'ada@example.com')
        ->set('password', 'password-123')
        ->call('authenticate')
        ->assertHasNoErrors();

    // The successful login clears the limiter for that key.
    expect(RateLimiter::attempts($key))->toBe(0)
        ->and(Auth::check())->toBeTrue();
});

it('regenerates the session on a successful login', function (): void {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('password-123'),
    ]);

    // Start a session so an id exists before the login.
    $this->get('/login');

    $before = session()->getId();

    Livewire::test(Login::class)
        ->set('email', 'ada@example.com')
        ->set('password', 'password-123')
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(session()->getId())->not->toBe($before);
});

it('logs a user out', function (): void {
    $user = User::factory()->create();

    actingAs($user);

    expect(Auth::check())->toBeTrue();

    post('/logout');

    expect(Auth::check())->toBeFalse();
});

it('redirects guests to the login page', function (): void {
    get('/dashboard')->assertRedirect(route('login'));
});
