<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('starts a new account unverified', function (): void {
    $user = User::factory()->unverified()->create();

    expect($user->email_verified_at)->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeFalse();
});

it('reaches the verification notice while unverified', function (): void {
    $user = User::factory()->unverified()->create();

    actingAs($user);

    get('/email/verify')->assertOk()->assertSee('Verify your email address');
});

it('redirects an unverified user away from the dashboard', function (): void {
    $user = User::factory()->unverified()->create();

    actingAs($user);

    get('/dashboard')->assertRedirect(route('verification.notice'));
});

it('verifies the account with a valid signed link', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    actingAs($user);

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    get($url)->assertRedirect(route('dashboard').'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('dispatches the verified event', function (): void {
    Notification::fake();

    Event::fake();

    $user = User::factory()->unverified()->create();

    actingAs($user);

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    get($url);

    Event::assertDispatched(Verified::class);
});

it('rejects an invalid signature', function (): void {
    $user = User::factory()->unverified()->create();

    actingAs($user);

    get('/email/verify/'.$user->getKey().'/'.sha1($user->getEmailForVerification()))
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects an expired signature', function (): void {
    $user = User::factory()->unverified()->create();

    actingAs($user);

    $url = URL::temporarySignedRoute('verification.verify', now()->subMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    get($url)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects a hash that does not match the email address', function (): void {
    $user = User::factory()->unverified()->create();

    actingAs($user);

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1('wrong@example.com'),
    ]);

    get($url)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects a link that belongs to a different user', function (): void {
    $user = User::factory()->unverified()->create();
    $other = User::factory()->unverified()->create();

    actingAs($user);

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $other->getKey(),
        'hash' => sha1($other->getEmailForVerification()),
    ]);

    get($url)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse()
        ->and($other->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification email', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    actingAs($user);

    post('/email/verification-notification')->assertRedirect();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('lets a verified user reach the dashboard', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    actingAs($user);

    get('/dashboard')->assertOk();
});
