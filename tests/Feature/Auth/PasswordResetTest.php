<?php

use App\Livewire\Auth\ForgotPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

use function Pest\Laravel\get;

it('requests a password reset link for an existing email address', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'ada@example.com']);

    Livewire::test(ForgotPassword::class)
        ->set('email', 'ada@example.com')
        ->call('sendResetLink')
        ->assertHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('sends no notification for an unknown email address', function (): void {
    Notification::fake();

    Livewire::test(ForgotPassword::class)
        ->set('email', 'nobody@example.com')
        ->call('sendResetLink')
        ->assertHasNoErrors();

    Notification::assertNothingSent();
});

it('does not reveal whether an email address exists', function (): void {
    Notification::fake();

    User::factory()->create(['email' => 'ada@example.com']);

    $existing = Livewire::test(ForgotPassword::class)
        ->set('email', 'ada@example.com')
        ->call('sendResetLink');

    $unknown = Livewire::test(ForgotPassword::class)
        ->set('email', 'nobody@example.com')
        ->call('sendResetLink');

    $expected = 'If an account exists for this email, a password reset link has been sent.';

    $existing->assertSee($expected);
    $unknown->assertSee($expected);
});

it('rejects an invalid email address', function (): void {
    Livewire::test(ForgotPassword::class)
        ->set('email', 'not-an-email')
        ->call('sendResetLink')
        ->assertHasErrors('email');
});

it('resets the password with a valid token', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('original-password-123'),
    ]);

    $token = Password::createToken($user);

    Livewire::test(App\Livewire\Auth\ResetPassword::class, [
        'token' => $token,
        'email' => 'ada@example.com',
    ])
        ->set('password', 'new-password-456')
        ->set('password_confirmation', 'new-password-456')
        ->call('resetPassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password-456', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('original-password-123', $user->fresh()->password))->toBeFalse();
});

it('rejects an invalid reset token', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('original-password-123'),
    ]);

    Password::createToken($user);

    Livewire::test(App\Livewire\Auth\ResetPassword::class, [
        'token' => 'invalid-token',
        'email' => 'ada@example.com',
    ])
        ->set('password', 'new-password-456')
        ->set('password_confirmation', 'new-password-456')
        ->call('resetPassword')
        ->assertHasErrors('email');

    // The original password is untouched after a failed reset.
    expect(Hash::check('original-password-123', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('new-password-456', $user->fresh()->password))->toBeFalse();
});

it('rejects a reset when the passwords do not match', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('original-password-123'),
    ]);

    $token = Password::createToken($user);

    Livewire::test(App\Livewire\Auth\ResetPassword::class, [
        'token' => $token,
        'email' => 'ada@example.com',
    ])
        ->set('password', 'new-password-456')
        ->set('password_confirmation', 'different-password')
        ->call('resetPassword')
        ->assertHasErrors('password');

    // The original password is untouched after a failed reset.
    expect(Hash::check('original-password-123', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('new-password-456', $user->fresh()->password))->toBeFalse();
});

it('cannot reuse a reset token after it has been used', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('original-password-123'),
    ]);

    $token = Password::createToken($user);

    Livewire::test(App\Livewire\Auth\ResetPassword::class, [
        'token' => $token,
        'email' => 'ada@example.com',
    ])
        ->set('password', 'new-password-456')
        ->set('password_confirmation', 'new-password-456')
        ->call('resetPassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password-456', $user->fresh()->password))->toBeTrue();

    // The same token cannot be used a second time.
    Livewire::test(App\Livewire\Auth\ResetPassword::class, [
        'token' => $token,
        'email' => 'ada@example.com',
    ])
        ->set('password', 'another-password-789')
        ->set('password_confirmation', 'another-password-789')
        ->call('resetPassword')
        ->assertHasErrors('email');

    expect(Hash::check('new-password-456', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('another-password-789', $user->fresh()->password))->toBeFalse();
});

it('shows the forgot password and reset password pages', function (): void {
    get('/forgot-password')->assertOk();
    get('/reset-password/some-token?email=ada@example.com')->assertOk();
});
