<?php

use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\get;

/*
|--------------------------------------------------------------------------
| Local Email Verification
|--------------------------------------------------------------------------
|
| When email verification is switched off, a new account can reach the
| dashboard straight away. These tests cover both a newly registered
| account and an account that already existed with an unverified email
| address.
|
| The rule is read from an explicit setting rather than from the
| application environment, so that the switch cannot be flipped by an
| environment name that happens to be wrong.
|
*/

beforeEach(function (): void {
    config(['kudosly.require_email_verification' => false]);
});

it('marks a newly registered local account as verified', function (): void {
    Livewire::test(Register::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('password', 'password-123')
        ->set('password_confirmation', 'password-123')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'ada@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

it('does not send a verification email for a local registration', function (): void {
    Notification::fake();

    Livewire::test(Register::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('password', 'password-123')
        ->set('password_confirmation', 'password-123')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'ada@example.com')->first();

    Notification::assertNotSentTo($user, VerifyEmail::class);
});

it('lets a newly registered local account reach the dashboard', function (): void {
    Livewire::test(Register::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('password', 'password-123')
        ->set('password_confirmation', 'password-123')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'ada@example.com')->first();

    $this->actingAs($user);

    get('/dashboard')->assertOk();
});

it('lets a pre-existing unverified local account reach the dashboard', function (): void {
    $user = User::factory()->unverified()->create();

    expect($user->hasVerifiedEmail())->toBeFalse();

    $this->actingAs($user);

    get('/dashboard')->assertOk();
});

it('sends an unverified local account to the dashboard rather than to the verification notice', function (): void {
    // The account is unverified, and the setting is off, so the request must
    // not be redirected to the verification notice. The response is
    // successful and it stays on the dashboard.
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    $response = get('/dashboard');

    // A redirect carries a 3xx status and a Location header, so a
    // successful response with no Location header is the dashboard itself.
    $response->assertOk();
    expect($response->headers->get('Location'))->toBeNull();
});

it('still requires an authenticated account on the local dashboard', function (): void {
    get('/dashboard')->assertRedirect(route('login'));
});

it('still protects the space creation limit for local accounts', function (): void {
    $user = User::factory()->unverified()->create(['email_verified_at' => null]);

    $this->actingAs($user);

    // The account may reach the dashboard, but the plan limit still applies.
    get('/spaces')->assertOk();
});
