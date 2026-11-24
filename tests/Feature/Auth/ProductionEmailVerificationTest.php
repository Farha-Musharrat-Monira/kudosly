<?php

use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\get;

/*
|--------------------------------------------------------------------------
| Production Email Verification
|--------------------------------------------------------------------------
|
| When email verification is switched on, it stays mandatory: a new
| account is unverified, the verification email is sent, and an unverified
| account cannot reach the dashboard.
|
| The rule is read from an explicit setting rather than from the
| application environment, so that the switch cannot be flipped by an
| environment name that happens to be wrong.
|
*/

beforeEach(function (): void {
    config(['kudosly.require_email_verification' => true]);
});

it('leaves a newly registered account unverified', function (): void {
    Livewire::test(Register::class)
        ->set('name', 'Grace Hopper')
        ->set('email', 'grace@example.com')
        ->set('password', 'password-123')
        ->set('password_confirmation', 'password-123')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'grace@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeFalse();
});

it('sends the verification email for a new account', function (): void {
    Notification::fake();

    Livewire::test(Register::class)
        ->set('name', 'Grace Hopper')
        ->set('email', 'grace@example.com')
        ->set('password', 'password-123')
        ->set('password_confirmation', 'password-123')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'grace@example.com')->first();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('redirects an unverified account away from the dashboard', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    get('/dashboard')->assertRedirect(route('verification.notice'));
});

it('does not let an unverified account reach the dashboard when verification is required', function (): void {
    // The counterpart to the local rule: with verification on, an unverified
    // account is sent to the verification notice and never sees the
    // dashboard.
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    $response = get('/dashboard');

    $response->assertRedirect(route('verification.notice'));
    $response->assertDontSee('Verify your email address');
});

it('redirects an unverified account away from the other dashboard pages', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    get('/spaces')->assertRedirect(route('verification.notice'));
    get('/billing')->assertRedirect(route('verification.notice'));
});

it('lets a verified account reach the dashboard', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    get('/dashboard')->assertOk();
});

it('still requires an authenticated account', function (): void {
    get('/dashboard')->assertRedirect(route('login'));
});
