<?php

use App\Livewire\Settings\UpdateProfileInformationForm;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    // The password is set explicitly on every user so the current-password
    // assertions never depend on the factory's default value.
    $this->currentPassword = 'original-password-123';
});

it('updates the name without requiring the current password', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => Hash::make($this->currentPassword),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(UpdateProfileInformationForm::class)
        ->set('name', 'Ada King')
        ->set('email', 'ada@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $user->fresh();

    expect($fresh->name)->toBe('Ada King')
        ->and($fresh->email)->toBe('ada@example.com')
        ->and($fresh->email_verified_at)->not->toBeNull()
        ->and($fresh->hasVerifiedEmail())->toBeTrue();

    // A name-only update neither asks for the current password nor resends
    // the verification email.
    Notification::assertNothingSent();
});

it('updates the email and resets the verification state', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => Hash::make($this->currentPassword),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(UpdateProfileInformationForm::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada.king@example.com')
        ->set('current_password', $this->currentPassword)
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $user->fresh();

    expect($fresh->email)->toBe('ada.king@example.com')
        ->and($fresh->email_verified_at)->toBeNull()
        ->and($fresh->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($fresh, VerifyEmail::class);
});

it('updates the name and the email together', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => Hash::make($this->currentPassword),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(UpdateProfileInformationForm::class)
        ->set('name', 'Ada King')
        ->set('email', 'ada.king@example.com')
        ->set('current_password', $this->currentPassword)
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $user->fresh();

    // Both attributes are written by the same save.
    expect($fresh->name)->toBe('Ada King')
        ->and($fresh->email)->toBe('ada.king@example.com')
        ->and($fresh->email_verified_at)->toBeNull()
        ->and($fresh->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($fresh, VerifyEmail::class);
});

it('requires the current password when the email changes', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => Hash::make($this->currentPassword),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(UpdateProfileInformationForm::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada.king@example.com')
        ->call('save')
        ->assertHasErrors('current_password');

    $fresh = $user->fresh();

    // The account is untouched after a rejected update.
    expect($fresh->email)->toBe('ada@example.com')
        ->and($fresh->name)->toBe('Ada Lovelace')
        ->and($fresh->email_verified_at)->not->toBeNull()
        ->and($fresh->hasVerifiedEmail())->toBeTrue();

    Notification::assertNothingSent();
});

it('rejects an incorrect current password and leaves the account unchanged', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => Hash::make($this->currentPassword),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(UpdateProfileInformationForm::class)
        ->set('name', 'Ada King')
        ->set('email', 'ada.king@example.com')
        ->set('current_password', 'wrong-password')
        ->call('save')
        ->assertHasErrors('current_password');

    $fresh = $user->fresh();

    expect($fresh->name)->toBe('Ada Lovelace')
        ->and($fresh->email)->toBe('ada@example.com')
        ->and($fresh->email_verified_at)->not->toBeNull()
        ->and($fresh->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check($this->currentPassword, $fresh->password))->toBeTrue();

    Notification::assertNothingSent();
});
