<?php

use App\Livewire\Wall\ManageWalls;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\get;

/**
 * A space owned by the given user.
 */
function wallSpace(?User $owner = null, array $attributes = []): Space
{
    $owner ??= User::factory()->create();

    return Space::factory()->create(array_merge([
        'user_id' => $owner->id,
        'is_published' => true,
    ], $attributes));
}

/**
 * A testimonial owned by the given user.
 */
function wallTestimonial(
    Space $space,
    ?User $owner = null,
    array $attributes = [],
): Testimonial {
    $owner ??= $space->owner;

    return Testimonial::factory()->create(array_merge([
        'space_id' => $space->id,
        'user_id' => $owner->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'body' => 'This product made our team genuinely faster every single day.',
        'consent_to_share' => true,
        'status' => Testimonial::STATUS_APPROVED,
        'is_favorite' => false,
        'is_heart' => false,
    ], $attributes));
}

/**
 * A testimonial that is on the public wall: hearted, approved and
 * consented.
 */
function wallHeartedTestimonial(Space $space, ?User $owner = null, array $attributes = []): Testimonial
{
    return wallTestimonial($space, $owner, array_merge([
        'is_heart' => true,
    ], $attributes));
}

// ---------------------------------------------------------------------------
// Public access — 5 cases
// ---------------------------------------------------------------------------

it('shows the public wall to a guest', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, ['name' => 'Grace Hopper']);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('Grace Hopper');
});

it('shows the public wall to an unverified account', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, ['name' => 'Grace Hopper']);

    $this->actingAs(User::factory()->unverified()->create());

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('Grace Hopper');
});

it('returns a not found response for an unknown wall slug', function (): void {
    get(route('spaces.public.wall', 'no-such-wall'))->assertNotFound();
});

it('shows the wall of a published space', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner, ['name' => 'Customer Stories']);

    wallHeartedTestimonial($space, $owner);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('Customer Stories');
});

it('serves the generic notice for an unpublished space', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner, ['is_published' => false, 'name' => 'Secret Space']);

    wallHeartedTestimonial($space, $owner, ['name' => 'Hidden Person']);

    $response = get(route('spaces.public.wall', $space->slug));

    $response->assertOk()
        ->assertSee('This page is not available')
        // Nothing about the space or its wall may leak.
        ->assertDontSee('Secret Space')
        ->assertDontSee('Hidden Person');
});

// ---------------------------------------------------------------------------
// Visibility — 6 cases
// ---------------------------------------------------------------------------

it('shows a hearted testimonial on the wall', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, ['name' => 'Loved Person']);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('Loved Person');
});

it('does not show a testimonial that is not hearted', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallTestimonial($space, $owner, ['name' => 'Unloved Person', 'is_heart' => false]);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertDontSee('Unloved Person');
});

it('does not show a hearted testimonial that is not approved', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, [
        'name' => 'Pending Person',
        'status' => Testimonial::STATUS_PENDING,
    ]);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertDontSee('Pending Person');
});

it('does not show a hearted testimonial that is hidden', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, [
        'name' => 'Hidden Person',
        'status' => Testimonial::STATUS_HIDDEN,
    ]);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertDontSee('Hidden Person');
});

it('does not show a hearted testimonial without consent', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, [
        'name' => 'Unconsented Person',
        'consent_to_share' => false,
    ]);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertDontSee('Unconsented Person');
});

it('drops a testimonial from the wall once it is hidden after being hearted', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    $testimonial = wallHeartedTestimonial($space, $owner, ['name' => 'Loved Person']);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('Loved Person');

    // The owner hides the testimonial after it was hearted.
    $testimonial->update(['status' => Testimonial::STATUS_HIDDEN]);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertDontSee('Loved Person');
});

// ---------------------------------------------------------------------------
// Space isolation — 2 cases
// ---------------------------------------------------------------------------

it('does not show the testimonials of another space', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);
    $otherSpace = wallSpace($owner);

    wallHeartedTestimonial($otherSpace, $owner, ['name' => 'Other Space Person']);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertDontSee('Other Space Person');
});

it('does not show the testimonials of another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = wallSpace($owner);
    $otherSpace = wallSpace($other);

    wallHeartedTestimonial($otherSpace, $other, ['name' => 'Foreign Person']);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertDontSee('Foreign Person');
});

// ---------------------------------------------------------------------------
// Disclosure — 4 cases
// ---------------------------------------------------------------------------

it('never shows the customer email address on the wall', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, [
        'name' => 'Grace Hopper',
        'email' => 'grace-private@example.com',
    ]);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('Grace Hopper')
        ->assertDontSee('grace-private@example.com');
});

it('never shows any customer email address on the wall', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, ['email' => 'first-private@example.com']);
    wallHeartedTestimonial($space, $owner, ['email' => 'second-private@example.com']);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertDontSee('first-private@example.com')
        ->assertDontSee('second-private@example.com')
        ->assertDontSee('@example.com');
});

it('never shows a customer photo on the wall', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, [
        'name' => 'Photographed Person',
        'profile_photo' => 'testimonials/avatar.jpg',
    ]);

    $content = get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->getContent();

    // A customer's photo is private data. The wall never renders an image
    // and never exposes a path that could point at one.
    expect($content)->not->toContain('<img')
        ->and($content)->not->toContain('testimonials/');
});

it('never exposes the storage path of a customer photo on the wall', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, [
        'profile_photo' => 'testimonials/avatar.jpg',
    ]);

    $content = get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->getContent();

    expect($content)->not->toContain(storage_path())
        ->and($content)->not->toContain('testimonial-photos');
});

// ---------------------------------------------------------------------------
// Escaping and social URL safety — 5 cases
// ---------------------------------------------------------------------------

it('escapes a customer name that carries html', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, [
        'name' => '<script>alert("x")</script>',
    ]);

    $content = get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->getContent();

    expect($content)->not->toContain('<script>alert("x")</script>')
        ->and($content)->toContain('&lt;script&gt;');
});

it('escapes a testimonial body that carries html', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, [
        'body' => '<script>alert("body")</script>',
    ]);

    $content = get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->getContent();

    expect($content)->not->toContain('<script>alert("body")</script>')
        ->and($content)->toContain('&lt;script&gt;');
});

it('links a social url that uses an http or https scheme', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, [
        'social_url' => 'https://example.com/me',
    ]);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('View profile')
        ->assertSee('https://example.com/me');
});

it('never links a social url that uses an unsafe scheme', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, [
        'social_url' => 'javascript:alert(1)',
    ]);

    $content = get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->getContent();

    // The link is dropped entirely rather than rendered with a bad scheme.
    expect($content)->not->toContain('javascript:')
        ->and($content)->not->toContain('View profile');
});

it('never links a social url that uses a data scheme', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, [
        'social_url' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    ]);

    $content = get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->getContent();

    expect($content)->not->toContain('data:text/html')
        ->and($content)->not->toContain('View profile');
});

// ---------------------------------------------------------------------------
// Theme — 3 cases
// ---------------------------------------------------------------------------

it('applies the theme of the space to the public wall', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner, ['theme' => 'modern_dark']);

    wallHeartedTestimonial($space, $owner);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('bg-slate-950', escape: false);
});

it('uses the soft gradient theme classes when the space asks for them', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner, ['theme' => 'soft_gradient']);

    wallHeartedTestimonial($space, $owner);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('bg-[#f5f3ff]', escape: false);
});

it('falls back to the default theme for an unknown theme key', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner, ['theme' => 'no_such_theme']);

    wallHeartedTestimonial($space, $owner);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('bg-slate-50', escape: false);
});

// ---------------------------------------------------------------------------
// Empty state and content — 3 cases
// ---------------------------------------------------------------------------

it('shows the empty state when nothing is on the wall', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('No testimonials on the wall yet');
});

it('shows the empty state when the space has no testimonials at all', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('No testimonials on the wall yet');
});

it('shows the name the body and the rating of a wall testimonial', function (): void {
    $owner = User::factory()->create();
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner, [
        'name' => 'Grace Hopper',
        'body' => 'A distinctive wall sentence for the public page.',
        'rating' => 4,
    ]);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('Grace Hopper')
        ->assertSee('A distinctive wall sentence for the public page.')
        ->assertSee('Rated 4 out of 5');
});

// ---------------------------------------------------------------------------
// Dashboard management page — 7 cases
// ---------------------------------------------------------------------------

it('requires an authenticated account for the wall management page', function (): void {
    get('/wall')->assertRedirect(route('login'));
});

it('redirects an unverified account to the verification notice', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    get('/wall')->assertRedirect(route('verification.notice'));
});

it('shows the wall management page for a verified owner', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    get('/wall')
        ->assertOk()
        ->assertSee('Wall of Love')
        ->assertSee('Share your best testimonials on a page you can send to anyone.');
});

it('lists the wall url of every space the owner owns', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = wallSpace($owner, ['name' => 'Customer Stories']);

    $this->actingAs($owner);

    get('/wall')
        ->assertOk()
        ->assertSee('Customer Stories')
        ->assertSee($space->wallUrl());
});

it('lists only the spaces of the signed in owner', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);

    wallSpace($owner, ['name' => 'My Own Space']);
    wallSpace($other, ['name' => 'Somebody Elses Space']);

    $this->actingAs($owner);

    get('/wall')
        ->assertOk()
        ->assertSee('My Own Space')
        ->assertDontSee('Somebody Elses Space');
});

it('shows how many testimonials are on each wall', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = wallSpace($owner);

    wallHeartedTestimonial($space, $owner);
    wallHeartedTestimonial($space, $owner);

    // A hearted testimonial that is hidden is not on the wall, so it is
    // not counted either.
    wallHeartedTestimonial($space, $owner, [
        'status' => Testimonial::STATUS_HIDDEN,
    ]);

    $this->actingAs($owner);

    get('/wall')
        ->assertOk()
        ->assertSee('2 on the wall');
});

it('shows the empty state on the management page when the owner has no spaces', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($owner);

    get('/wall')
        ->assertOk()
        ->assertSee('No spaces yet');
});

// ---------------------------------------------------------------------------
// Management component behaviour — 2 cases
// ---------------------------------------------------------------------------

it('renders the wall management component', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($owner);

    Livewire::test(ManageWalls::class)
        ->assertOk()
        ->assertSee('Wall of Love');
});

it('never lists another account space in the management component', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);

    wallSpace($owner, ['name' => 'My Own Space']);
    wallSpace($other, ['name' => 'Somebody Elses Space']);

    $this->actingAs($owner);

    Livewire::test(ManageWalls::class)
        ->assertSee('My Own Space')
        ->assertDontSee('Somebody Elses Space');
});
