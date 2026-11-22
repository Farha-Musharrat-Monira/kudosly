<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;

use function Pest\Laravel\get;

it('requires an authenticated account for the placeholder pages', function (): void {
    get('/inbox')->assertRedirect(route('login'));
    get('/wall')->assertRedirect(route('login'));
    get('/widgets')->assertRedirect(route('login'));
    get('/billing')->assertRedirect(route('login'));
    get('/settings')->assertRedirect(route('login'));
});

it('redirects an unverified account to the verification notice', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    get('/inbox')->assertRedirect(route('verification.notice'));
    get('/settings')->assertRedirect(route('verification.notice'));
});

it('shows the inbox', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    get('/inbox')
        ->assertOk()
        ->assertSee('Inbox')
        ->assertSee('Review, approve and organise the testimonials your customers have sent.')
        ->assertSee('No testimonials yet')
        // The placeholder copy is gone now that the inbox is real, so it must
        // not linger anywhere on the page.
        ->assertDontSee('Every testimonial submitted to your spaces will land here for review')
        ->assertDontSee('Testimonial collection arrives in the next release of Kudosly.');
});

it('shows the wall of love management page', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    get('/wall')
        ->assertOk()
        ->assertSee('Wall of Love')
        ->assertSee('Share your best testimonials on a page you can send to anyone.')
        // The wall is real now, so the placeholder copy must not linger.
        ->assertDontSee('The Wall of Love arrives in the next release of Kudosly.');
});

it('shows the widgets placeholder', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    get('/widgets')
        ->assertOk()
        ->assertSee('Widgets')
        ->assertSee('Embed your testimonials on your own website with a lightweight widget and live filtering.')
        ->assertSee('Embeddable widgets arrive in a later release of Kudosly.');
});

it('shows the billing page with the real current plan', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    get('/billing')
        ->assertOk()
        ->assertSee('Billing')
        ->assertSee('Current plan')
        ->assertSee('Your current plan and how much of it you are using.');
});

it('disables the checkout button because the Stripe secret is not configured', function (): void {
    // The Stripe secret is deliberately absent from the test environment, so
    // checkout cannot start and the button must be inert.
    config()->set('cashier.secret', null);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    get('/billing')
        ->assertOk()
        ->assertSee('Checkout unavailable')
        ->assertSee('Billing is not configured on this server yet.')
        // The checkout button is rendered with the disabled attribute, so it
        // cannot be pressed even though the markup is present. The attribute
        // is emitted after the merged class list.
        ->assertSeeHtml('disabled="disabled"');
});

it('shows the settings page with the profile and password forms', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    get('/settings')
        ->assertOk()
        ->assertSee('Settings')
        ->assertSee('Profile information')
        ->assertSee('Update your name and email address.')
        ->assertSee('Choose a new password for your account.');
});

it('shows the account summary on the settings page', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Space::factory()->count(2)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    get('/settings')
        ->assertOk()
        ->assertSee('Current plan')
        ->assertSee('Email verified')
        ->assertSee('Verified')
        ->assertSee('Spaces used');
});

it('shows the real space usage on the billing page', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $space = Space::factory()->create(['user_id' => $user->id, 'name' => 'Customer Stories']);

    Testimonial::factory()->count(3)->create([
        'space_id' => $space->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user);

    get('/billing')
        ->assertOk()
        ->assertSee('Customer Stories')
        ->assertSee('3 / 100')
        ->assertSee('1 / 3');
});

it('redirects the root path to the dashboard', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    get('/')->assertRedirect('/dashboard');
});
