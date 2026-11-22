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
| Outside local development email verification stays mandatory: a new account
| is unverified, the verification email is sent, and an unverified account
| cannot reach the dashboard.
|
*/

beforeEach(function (): void {
    config(['app.env' => 'production']);
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
