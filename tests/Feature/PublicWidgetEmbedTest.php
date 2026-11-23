<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Widget;

use function Pest\Laravel\get;

/**
 * A published space owned by the given user.
 */
function embedSpace(?User $owner = null, array $attributes = []): Space
{
    $owner ??= User::factory()->create();

    return Space::factory()->create(array_merge([
        'user_id' => $owner->id,
        'is_published' => true,
    ], $attributes));
}

/**
 * A testimonial belonging to a space.
 */
function embedTestimonial(Space $space, array $attributes = []): Testimonial
{
    return Testimonial::factory()->create(array_merge([
        'space_id' => $space->id,
        'user_id' => $space->user_id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'body' => 'This product made our team genuinely faster every single day.',
        'consent_to_share' => true,
        'status' => Testimonial::STATUS_APPROVED,
    ], $attributes));
}

/**
 * A widget for a space.
 */
function embedWidget(Space $space, array $attributes = []): Widget
{
    return Widget::factory()->create(array_merge([
        'space_id' => $space->id,
    ], $attributes));
}

// ---------------------------------------------------------------------------
// Public access — 6 cases
// ---------------------------------------------------------------------------

it('shows the standalone widget page to a guest', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    get(route('widgets.public', $widget->public_id))
        ->assertOk()
        ->assertSee($space->name);
});

it('shows the embeddable fragment to a guest', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    embedTestimonial($space, ['name' => 'Grace Hopper']);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertSee('Grace Hopper');
});

it('does not require an account to reach the widget page', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    $this->assertGuest();

    get(route('widgets.public', $widget->public_id))->assertOk();
});

it('does not require a verified account to reach the widget page', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    $this->actingAs(User::factory()->unverified()->create());

    get(route('widgets.public', $widget->public_id))->assertOk();
});

it('does not require an account to reach the embeddable fragment', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    $this->assertGuest();

    get(route('widgets.embed', $widget->public_id))->assertOk();
});

it('reports an unknown public identifier as missing', function (): void {
    get(route('widgets.public', str_repeat('0', 32)))->assertNotFound();
});

// ---------------------------------------------------------------------------
// Unpublished spaces — 4 cases
// ---------------------------------------------------------------------------

it('does not show the widget page of an unpublished space', function (): void {
    $space = embedSpace(null, ['is_published' => false]);
    $widget = embedWidget($space);

    get(route('widgets.public', $widget->public_id))->assertNotFound();
});

it('does not show the embeddable fragment of an unpublished space', function (): void {
    $space = embedSpace(null, ['is_published' => false]);
    $widget = embedWidget($space);

    get(route('widgets.embed', $widget->public_id))->assertNotFound();
});

it('does not leak the testimonials of an unpublished space', function (): void {
    $space = embedSpace(null, ['is_published' => false]);
    $widget = embedWidget($space);

    embedTestimonial($space, ['name' => 'Hidden Customer']);

    get(route('widgets.embed', $widget->public_id))
        ->assertNotFound()
        ->assertDontSee('Hidden Customer');
});

it('does not expose the internal identifier of a widget', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    get(route('widgets.public', $widget->public_id))
        ->assertOk()
        ->assertDontSee('Widget '.$widget->id);
});

// ---------------------------------------------------------------------------
// The testimonials the widget shows — 8 cases
// ---------------------------------------------------------------------------

it('shows an approved and consented testimonial', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    embedTestimonial($space, [
        'name' => 'Grace Hopper',
        'status' => Testimonial::STATUS_APPROVED,
        'consent_to_share' => true,
    ]);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertSee('Grace Hopper');
});

it('does not show a pending testimonial', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    embedTestimonial($space, [
        'name' => 'Pending Customer',
        'status' => Testimonial::STATUS_PENDING,
        'consent_to_share' => true,
    ]);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertDontSee('Pending Customer');
});

it('does not show a hidden testimonial', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    embedTestimonial($space, [
        'name' => 'Hidden Customer',
        'status' => Testimonial::STATUS_HIDDEN,
        'consent_to_share' => true,
    ]);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertDontSee('Hidden Customer');
});

it('does not show a testimonial that did not consent to being shared', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    embedTestimonial($space, [
        'name' => 'Unconsented Customer',
        'status' => Testimonial::STATUS_APPROVED,
        'consent_to_share' => false,
    ]);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertDontSee('Unconsented Customer');
});

it('does not require the heart flag to show a testimonial', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    embedTestimonial($space, [
        'name' => 'Unhearted Customer',
        'status' => Testimonial::STATUS_APPROVED,
        'consent_to_share' => true,
        'is_heart' => false,
    ]);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertSee('Unhearted Customer');
});

it('does not show another space\'s testimonials', function (): void {
    $space = embedSpace();
    $otherSpace = embedSpace();
    $widget = embedWidget($space);

    embedTestimonial($otherSpace, ['name' => 'Other Space Customer']);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertDontSee('Other Space Customer');
});

it('shows the empty copy when there is nothing to show', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertSee('No testimonials are available to show yet.');
});

it('shows the empty copy when every testimonial is hidden', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    embedTestimonial($space, [
        'name' => 'Pending Customer',
        'status' => Testimonial::STATUS_PENDING,
        'consent_to_share' => true,
    ]);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertSee('No testimonials are available to show yet.')
        ->assertDontSee('Pending Customer');
});

// ---------------------------------------------------------------------------
// The display limit — 4 cases
// ---------------------------------------------------------------------------

it('honours the display limit', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space, ['display_limit' => 1]);

    embedTestimonial($space, ['name' => 'First Customer'])
        ->forceFill(['created_at' => now()->subDays(2)])->save();

    embedTestimonial($space, ['name' => 'Second Customer'])
        ->forceFill(['created_at' => now()->subDay()])->save();

    $response = get(route('widgets.embed', $widget->public_id))->assertOk();

    $response
        ->assertSee('Second Customer')
        ->assertDontSee('First Customer');

    // Exactly one card element is rendered, so only one testimonial
    // reached the page. The card element is counted rather than the class
    // name, because the class name also appears in the stylesheet.
    expect(substr_count($response->getContent(), '<figure class="kw-card">'))->toBe(1);
});

it('shows every testimonial when the limit allows it', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space, ['display_limit' => 10]);

    embedTestimonial($space, ['name' => 'First Customer']);
    embedTestimonial($space, ['name' => 'Second Customer']);

    $response = get(route('widgets.embed', $widget->public_id))->assertOk();

    $response->assertSee('First Customer')
        ->assertSee('Second Customer');

    expect(substr_count($response->getContent(), '<figure class="kw-card">'))->toBe(2);
});

it('shows the newest testimonials first', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space, ['display_limit' => 1]);

    embedTestimonial($space, ['name' => 'Older Customer'])
        ->forceFill(['created_at' => now()->subDays(5)])->save();
    embedTestimonial($space, ['name' => 'Newer Customer'])
        ->forceFill(['created_at' => now()->subDay()])->save();

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertSee('Newer Customer')
        ->assertDontSee('Older Customer');
});

it('holds an out of range display limit inside its bounds', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space, ['display_limit' => 0]);

    embedTestimonial($space, ['name' => 'First Customer']);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertSee('First Customer');
});

// ---------------------------------------------------------------------------
// The fragment itself — 8 cases
// ---------------------------------------------------------------------------

it('marks the fragment up with the widget\'s own identifier', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertSee('id="kw-'.$widget->public_id.'"', escape: false);
});

it('carries the chosen layout on the fragment', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space, ['layout' => 'carousel']);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertSee('kw-layout-carousel', escape: false)
        ->assertSee('data-layout="carousel"', escape: false);
});

it('carries the chosen theme on the fragment', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space, ['theme' => 'dark']);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertSee('kw-theme-dark', escape: false)
        ->assertSee('data-theme="dark"', escape: false);
});

it('carries the chosen background colour on the fragment', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space, ['background_color' => '#0f172a']);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertSee('background-color: #0f172a;', escape: false);
});

it('scopes the stylesheet to the widget\'s own identifier', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertSee('#kw-'.$widget->public_id.' .kw-card', escape: false);
});

it('does not include the application shell in the fragment', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    $response = get(route('widgets.embed', $widget->public_id))->assertOk();

    $response->assertDontSee('<html', escape: false)
        ->assertDontSee('<body', escape: false)
        ->assertDontSee('livewire.js', escape: false);
});

it('includes the widget stylesheet in the fragment', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertSee('<style>', escape: false);
});

it('escapes a testimonial body in the fragment', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    embedTestimonial($space, [
        'body' => '<script>alert("xss")</script>',
        'status' => Testimonial::STATUS_APPROVED,
        'consent_to_share' => true,
    ]);

    $response = get(route('widgets.embed', $widget->public_id))->assertOk();

    $response->assertSee('&lt;script&gt;', escape: false)
        ->assertDontSee('<script>alert("xss")</script>', escape: false);
});

// ---------------------------------------------------------------------------
// The standalone page — 5 cases
// ---------------------------------------------------------------------------

it('shows the widget layout and theme on the standalone page', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space, ['layout' => 'carousel', 'theme' => 'dark']);

    get(route('widgets.public', $widget->public_id))
        ->assertOk()
        ->assertSee('Carousel widget')
        ->assertSee('Dark');
});

it('shows the testimonials on the standalone page', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    embedTestimonial($space, ['name' => 'Grace Hopper']);

    get(route('widgets.public', $widget->public_id))
        ->assertOk()
        ->assertSee('Grace Hopper');
});

it('does not ask a search engine to index the standalone page', function (): void {
    $space = embedSpace();
    $widget = embedWidget($space);

    get(route('widgets.public', $widget->public_id))
        ->assertOk()
        ->assertSee('noindex', escape: false);
});

it('does not leak the testimonials of an unpublished space on the standalone page', function (): void {
    $space = embedSpace(null, ['is_published' => false]);
    $widget = embedWidget($space);

    embedTestimonial($space, ['name' => 'Hidden Customer']);

    get(route('widgets.public', $widget->public_id))
        ->assertNotFound()
        ->assertDontSee('Hidden Customer');
});

it('does not show the owner\'s account details on the standalone page', function (): void {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $space = embedSpace($owner);
    $widget = embedWidget($space);

    get(route('widgets.public', $widget->public_id))
        ->assertOk()
        ->assertDontSee('owner@example.com');
});
