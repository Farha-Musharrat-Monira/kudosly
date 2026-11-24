<?php

use App\Livewire\Auth\Register;
use App\Models\User;
use App\Support\EmailVerification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\get;

/*
|--------------------------------------------------------------------------
| The email verification setting
|--------------------------------------------------------------------------
|
| Whether email verification is required is decided by an explicit
| configuration setting, not by the name of the application environment.
|
| The reason is that a security decision should not hinge on a string
| that is easy to get wrong. An environment named "local" that reached
| production would silently turn verification off, and one named
| "productionn" would turn it on where it was not expected. Reading the
| rule from a setting makes that impossible: the setting is on unless
| somebody deliberately turns it off.
|
*/

it('requires email verification by default', function (): void {
    // The setting is read from the environment with a default of true, so
    // a deployment that does not configure it keeps verification on.
    config(['kudosly.require_email_verification' => true]);

    expect(EmailVerification::isRequired())->toBeTrue();
});

it('keeps verification on whatever the application environment is called', function (): void {
    // The environment name must have no influence on the rule at all.
    foreach (['local', 'production', 'productionn', 'staging', 'testing', ''] as $environment) {
        config([
            'app.env' => $environment,
            'kudosly.require_email_verification' => true,
        ]);

        expect(EmailVerification::isRequired())->toBeTrue();
    }
});

it('does not require email verification when the setting is turned off', function (): void {
    config(['kudosly.require_email_verification' => false]);

    expect(EmailVerification::isRequired())->toBeFalse();
});

it('does not require email verification when the setting is turned off in any environment', function (): void {
    // The switch works the same way everywhere, so a deployment that
    // deliberately relaxes the rule is not overridden by its environment
    // name either.
    foreach (['local', 'production', 'staging'] as $environment) {
        config([
            'app.env' => $environment,
            'kudosly.require_email_verification' => false,
        ]);

        expect(EmailVerification::isRequired())->toBeFalse();
    }
});

it('leaves a newly registered account unverified when the setting is on', function (): void {
    config(['kudosly.require_email_verification' => true]);

    Livewire::test(Register::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('password', 'password-123')
        ->set('password_confirmation', 'password-123')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'ada@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeFalse();
});

it('sends the verification email for a new account when the setting is on', function (): void {
    config(['kudosly.require_email_verification' => true]);

    Notification::fake();

    Livewire::test(Register::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('password', 'password-123')
        ->set('password_confirmation', 'password-123')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'ada@example.com')->first();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('blocks an unverified account when the setting is on', function (): void {
    config(['kudosly.require_email_verification' => true]);

    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    get('/dashboard')->assertRedirect(route('verification.notice'));
});

it('lets an unverified account through when the setting is off', function (): void {
    config(['kudosly.require_email_verification' => false]);

    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    get('/dashboard')->assertOk();
});

it('still requires an authenticated account when the setting is off', function (): void {
    config(['kudosly.require_email_verification' => false]);

    // Turning verification off must never turn authentication off.
    get('/dashboard')->assertRedirect(route('login'));
});

it('still protects the space limit when the setting is off', function (): void {
    config(['kudosly.require_email_verification' => false]);

    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    get('/spaces')->assertOk();
});
