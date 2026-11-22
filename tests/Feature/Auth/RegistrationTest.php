<?php

use App\Livewire\Auth\Register;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use RuntimeException;

it('registers a new account', function (): void {
    Notification::fake();

    Livewire::test(Register::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('password', 'password-123')
        ->set('password_confirmation', 'password-123')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::where('email', 'ada@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Ada Lovelace')
        ->and(Hash::check('password-123', $user->password))->toBeTrue();
});

it('assigns the persisted free plan to a new account', function (): void {
    Notification::fake();

    Livewire::test(Register::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('password', 'password-123')
        ->set('password_confirmation', 'password-123')
        ->call('register');

    $user = User::where('email', 'ada@example.com')->first();

    expect($user->plan_id)->not->toBeNull();

    $plan = Plan::find($user->plan_id);

    expect($plan)->not->toBeNull()
        ->and($plan->slug)->toBe('free')
        ->and($user->onFreePlan())->toBeTrue()
        ->and($user->spaceQuota())->toBe(3)
        ->and($user->testimonialQuota())->toBe(100);
});

it('leaves the new account unverified and sends the verification email', function (): void {
    Notification::fake();

    Livewire::test(Register::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('password', 'password-123')
        ->set('password_confirmation', 'password-123')
        ->call('register');

    $user = User::where('email', 'ada@example.com')->first();

    expect($user->email_verified_at)->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects a duplicate email address', function (): void {
    Notification::fake();

    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test(Register::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'taken@example.com')
        ->set('password', 'password-123')
        ->set('password_confirmation', 'password-123')
        ->call('register')
        ->assertHasErrors('email');

    expect(User::where('email', 'taken@example.com')->count())->toBe(1);
});

it('requires the password confirmation to match', function (): void {
    Notification::fake();

    Livewire::test(Register::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('password', 'password-123')
        ->set('password_confirmation', 'different-password')
        ->call('register')
        ->assertHasErrors('password');

    expect(User::where('email', 'ada@example.com')->exists())->toBeFalse();
});

it('rolls back the account when the free plan cannot be assigned', function (): void {
    Notification::fake();

    // Remove the Free plan so the assignment inside CreateNewUser cannot
    // succeed. The action throws a RuntimeException, which the database
    // transaction rolls back.
    Plan::query()->where('slug', 'free')->delete();

    expect(fn () => Livewire::test(Register::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('password', 'password-123')
        ->set('password_confirmation', 'password-123')
        ->call('register')
    )->toThrow(RuntimeException::class, 'The Free plan could not be assigned');

    // The transaction rolled back, so no orphaned account is left behind.
    expect(User::where('email', 'ada@example.com')->exists())->toBeFalse()
        ->and(User::count())->toBe(0);
});
