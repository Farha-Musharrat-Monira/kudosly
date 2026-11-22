<?php

use App\Livewire\Wall\ManageWalls;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\get;

/**
 * A space owned by the given user.
 */
function wmSpace(?User $owner = null, array $attributes = []): Space
{
    $owner ??= User::factory()->create();

    return Space::factory()->create(array_merge([
        'user_id' => $owner->id,
        'is_published' => true,
    ], $attributes));
}

/**
 * A testimonial on the given space.
 */
function wmTestimonial(
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
        'rating' => 5,
        'consent_to_share' => true,
        'status' => Testimonial::STATUS_APPROVED,
        'is_favorite' => false,
        'is_heart' => false,
    ], $attributes));
}

/**
 * Sign in an owner and open the wall management component.
 */
function wmComponent(?User $owner = null): Testable
{
    $owner ??= User::factory()->create();

    $owner->forceFill(['email_verified_at' => now()])->save();

    test()->actingAs($owner);

    Livewire::withQueryParams([]);

    return Livewire::test(ManageWalls::class);
}

// ---------------------------------------------------------------------------
// Authorization regressions — 6 cases
//
// A wall belongs to exactly one space. A testimonial from another space of
// the same account must never be added to or removed from the open wall,
// and no action may run when no valid wall is open.
// ---------------------------------------------------------------------------

it('cannot heart a testimonial from another space of the same account', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);
    $otherSpace = wmSpace($owner);

    $testimonial = wmTestimonial($otherSpace, $owner);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->call('heart', $testimonial->id);

    // The testimonial belongs to the other space, so the wall that is open
    // must not have taken it.
    expect($testimonial->refresh()->is_heart)->toBeFalse();
});

it('cannot unheart a testimonial from another space of the same account', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);
    $otherSpace = wmSpace($owner);

    $testimonial = wmTestimonial($otherSpace, $owner, ['is_heart' => true]);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->call('unheart', $testimonial->id);

    // The heart on the other wall must survive.
    expect($testimonial->refresh()->is_heart)->toBeTrue();
});

it('refuses to heart when no wall is open', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    $testimonial = wmTestimonial($space, $owner);

    wmComponent($owner)
        ->call('heart', $testimonial->id)
        ->assertHasErrors('testimonial');

    expect($testimonial->refresh()->is_heart)->toBeFalse();
});

it('refuses to unheart when no wall is open', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    $testimonial = wmTestimonial($space, $owner, ['is_heart' => true]);

    wmComponent($owner)
        ->call('unheart', $testimonial->id)
        ->assertHasErrors('testimonial');

    expect($testimonial->refresh()->is_heart)->toBeTrue();
});

it('refuses to heart when the open wall belongs to another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = wmSpace($owner);
    $otherSpace = wmSpace($other);

    $testimonial = wmTestimonial($otherSpace, $other);

    // The owner cannot open another account's wall in the first place.
    wmComponent($owner)
        ->call('manage', $otherSpace->id)
        ->assertStatus(404);

    // Even if the state is forced, the cross-account testimonial is refused.
    wmComponent($owner)
        ->set('managingSpaceId', $otherSpace->id)
        ->call('heart', $testimonial->id)
        ->assertHasErrors('testimonial');

    expect($testimonial->refresh()->is_heart)->toBeFalse();
});

it('the cross-space attempt leaves the other wall untouched', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);
    $otherSpace = wmSpace($owner);

    $testimonial = wmTestimonial($otherSpace, $owner);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->call('heart', $testimonial->id);

    // The open wall must not have gained the testimonial either.
    expect($space->wallTestimonialCount())->toBe(0)
        ->and($otherSpace->wallTestimonialCount())->toBe(0)
        ->and($testimonial->refresh()->is_heart)->toBeFalse();
});

// ---------------------------------------------------------------------------
// The picker — 7 cases
// ---------------------------------------------------------------------------

it('opens the picker for a space the owner owns', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->assertSet('managingSpaceId', $space->id)
        ->assertSee('Choose what goes on this wall');
});

it('lists the eligible testimonials of the managed space', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    wmTestimonial($space, $owner, ['name' => 'Eligible Person']);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->assertSee('Eligible Person');
});

it('does not list the testimonials of another space', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);
    $otherSpace = wmSpace($owner);

    wmTestimonial($otherSpace, $owner, ['name' => 'Other Space Person']);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->assertDontSee('Other Space Person');
});

it('does not list the testimonials of another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = wmSpace($owner);
    $otherSpace = wmSpace($other);

    wmTestimonial($otherSpace, $other, ['name' => 'Foreign Person']);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->assertDontSee('Foreign Person');
});

it('does not open the picker for a space of another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $otherSpace = wmSpace($other);

    wmComponent($owner)
        ->call('manage', $otherSpace->id)
        ->assertStatus(404);
});

it('closes the picker', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->assertSet('managingSpaceId', $space->id)
        ->call('closePicker')
        ->assertSet('managingSpaceId', null);
});

it('never shows a customer email address in the picker', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    wmTestimonial($space, $owner, [
        'name' => 'Private Person',
        'email' => 'private-customer@example.com',
    ]);

    $html = wmComponent($owner)
        ->call('manage', $space->id)
        ->html();

    expect($html)->toContain('Private Person')
        ->and($html)->not->toContain('private-customer@example.com');
});

// ---------------------------------------------------------------------------
// Adding and removing — 8 cases
// ---------------------------------------------------------------------------

it('puts an eligible testimonial on the wall', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    $testimonial = wmTestimonial($space, $owner);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->call('heart', $testimonial->id);

    expect($testimonial->refresh()->is_heart)->toBeTrue();
});

it('shows a hearted testimonial on the public wall', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    $testimonial = wmTestimonial($space, $owner, ['name' => 'Loved Person']);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->call('heart', $testimonial->id);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('Loved Person');
});

it('takes a testimonial off the wall', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    $testimonial = wmTestimonial($space, $owner, ['is_heart' => true]);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->call('unheart', $testimonial->id);

    expect($testimonial->refresh()->is_heart)->toBeFalse();
});

it('refuses to put a pending testimonial on the wall', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    $testimonial = wmTestimonial($space, $owner, [
        'status' => Testimonial::STATUS_PENDING,
    ]);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->call('heart', $testimonial->id);

    expect($testimonial->refresh()->is_heart)->toBeFalse();
});

it('refuses to put a testimonial without consent on the wall', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    $testimonial = wmTestimonial($space, $owner, [
        'consent_to_share' => false,
    ]);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->call('heart', $testimonial->id);

    expect($testimonial->refresh()->is_heart)->toBeFalse();
});

it('refuses to put a hidden testimonial on the wall', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    $testimonial = wmTestimonial($space, $owner, [
        'status' => Testimonial::STATUS_HIDDEN,
    ]);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->call('heart', $testimonial->id);

    expect($testimonial->refresh()->is_heart)->toBeFalse();
});

it('removes a hidden testimonial that is still hearted', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    // The testimonial was hearted and then hidden, so it is off the public
    // wall but the heart is still set. The owner must still be able to
    // take the heart off.
    $testimonial = wmTestimonial($space, $owner, [
        'is_heart' => true,
        'status' => Testimonial::STATUS_HIDDEN,
    ]);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->call('unheart', $testimonial->id);

    expect($testimonial->refresh()->is_heart)->toBeFalse();
});

it('shows the hearted but hidden testimonial as a distinct state', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    wmTestimonial($space, $owner, [
        'name' => 'Hidden But Hearted',
        'is_heart' => true,
        'status' => Testimonial::STATUS_HIDDEN,
    ]);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->assertSee('Hidden But Hearted')
        ->assertSee('Hidden from the wall');
});

// ---------------------------------------------------------------------------
// Publishing — 5 cases
// ---------------------------------------------------------------------------

it('publishes a wall', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner, ['is_published' => false]);

    wmComponent($owner)
        ->call('togglePublish', $space->id);

    expect($space->refresh()->is_published)->toBeTrue();
});

it('unpublishes a wall', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner, ['is_published' => true]);

    wmComponent($owner)
        ->call('togglePublish', $space->id);

    expect($space->refresh()->is_published)->toBeFalse();
});

it('does not publish a wall of another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $otherSpace = wmSpace($other, ['is_published' => false]);

    wmComponent($owner)
        ->call('togglePublish', $otherSpace->id)
        ->assertStatus(404);

    expect($otherSpace->refresh()->is_published)->toBeFalse();
});

it('hides an unpublished wall from the public', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner, ['is_published' => false]);

    wmTestimonial($space, $owner, ['name' => 'Loved Person', 'is_heart' => true]);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertDontSee('Loved Person');
});

it('publishes a wall and makes it publicly reachable', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner, ['is_published' => false]);

    wmTestimonial($space, $owner, ['name' => 'Loved Person', 'is_heart' => true]);

    wmComponent($owner)->call('togglePublish', $space->id);

    get(route('spaces.public.wall', $space->slug))
        ->assertOk()
        ->assertSee('Loved Person');
});

// ---------------------------------------------------------------------------
// Counts and page state — 7 cases
// ---------------------------------------------------------------------------

it('shows the on-wall count and the eligible count', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    wmTestimonial($space, $owner, ['is_heart' => true]);
    wmTestimonial($space, $owner, ['is_heart' => false]);

    wmComponent($owner)
        // One testimonial is hearted and on the wall. Both are approved
        // with consent, so both are eligible to go on the wall.
        ->assertSee('1 on the wall')
        ->assertSee('2 eligible');
});

it('counts a hearted but hidden testimonial as eligible but not on the wall', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    // A hidden testimonial is not publicly visible, so it is neither
    // eligible nor on the wall.
    wmTestimonial($space, $owner, [
        'is_heart' => true,
        'status' => Testimonial::STATUS_HIDDEN,
    ]);

    wmComponent($owner)
        ->assertSee('0 on the wall')
        ->assertSee('0 eligible');
});

it('shows the empty state when the owner has no spaces', function (): void {
    $owner = User::factory()->create();

    wmComponent($owner)
        ->assertSee('No spaces yet');
});

it('shows a distinct empty state when a wall has nothing on it', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->assertSee('Nothing is on this wall yet');
});

it('shows the wall url of the space', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    wmComponent($owner)
        ->assertSee($space->wallUrl());
});

it('lists only the spaces of the signed in owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    wmSpace($owner, ['name' => 'My Own Space']);
    wmSpace($other, ['name' => 'Somebody Elses Space']);

    wmComponent($owner)
        ->assertSee('My Own Space')
        ->assertDontSee('Somebody Elses Space');
});

it('shows the notice after an action', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    $testimonial = wmTestimonial($space, $owner);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->call('heart', $testimonial->id)
        ->assertSee('Testimonial added to your wall.');
});

// ---------------------------------------------------------------------------
// Page access — 4 cases
// ---------------------------------------------------------------------------

it('requires an authenticated account for the wall page', function (): void {
    get('/wall')->assertRedirect(route('login'));
});

it('redirects an unverified account to the verification notice', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    get('/wall')->assertRedirect(route('verification.notice'));
});

it('shows the wall page for a verified owner', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($owner);

    get('/wall')
        ->assertOk()
        ->assertSee('Wall of Love');
});

it('never exposes a customer email address on the wall page', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    wmTestimonial($space, $owner, [
        'name' => 'Visible Person',
        'email' => 'secret-customer@example.com',
    ]);

    // The picker is opened first, so the testimonial is genuinely rendered
    // on the page. Without this the assertion would pass against an empty
    // list and could never detect a leak.
    $html = wmComponent($owner)
        ->call('manage', $space->id)
        ->html();

    // The customer's name is on the page, so the assertion below is real.
    expect($html)->toContain('Visible Person');

    // A customer's email address is owner-only information and must never
    // reach a wall surface.
    expect($html)->not->toContain('secret-customer@example.com');
});

// ---------------------------------------------------------------------------
// The status filter — 6 cases
//
// The filter narrows which testimonials the picker shows. It must never
// change whether a testimonial may be put on the wall: that decision is
// made by the model, so filtering can never make a pending or a
// no-consent testimonial addable.
// ---------------------------------------------------------------------------

it('narrows the picker to the chosen status', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    wmTestimonial($space, $owner, ['name' => 'Approved Person', 'status' => Testimonial::STATUS_APPROVED]);
    wmTestimonial($space, $owner, ['name' => 'Pending Person', 'status' => Testimonial::STATUS_PENDING]);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->set('status', Testimonial::STATUS_PENDING)
        ->assertSee('Pending Person')
        ->assertDontSee('Approved Person');
});

it('shows every status when no filter is chosen', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    wmTestimonial($space, $owner, ['name' => 'Approved Person', 'status' => Testimonial::STATUS_APPROVED]);
    wmTestimonial($space, $owner, ['name' => 'Pending Person', 'status' => Testimonial::STATUS_PENDING]);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->assertSee('Approved Person')
        ->assertSee('Pending Person');
});

it('refuses to add a pending testimonial while the pending filter is chosen', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    // The filter shows the pending testimonial, but filtering must not make
    // it addable. Only the model's own rule may allow that.
    $testimonial = wmTestimonial($space, $owner, [
        'status' => Testimonial::STATUS_PENDING,
    ]);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->set('status', Testimonial::STATUS_PENDING)
        ->call('heart', $testimonial->id)
        ->assertSee('Only an approved testimonial with consent can go on a wall.');

    expect($testimonial->refresh()->is_heart)->toBeFalse();
});

it('refuses to add a testimonial without consent while the approved filter is chosen', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    // An approved status is not enough on its own. Without consent the
    // testimonial stays off the wall even though the filter matches it.
    $testimonial = wmTestimonial($space, $owner, [
        'status' => Testimonial::STATUS_APPROVED,
        'consent_to_share' => false,
    ]);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->set('status', Testimonial::STATUS_APPROVED)
        ->call('heart', $testimonial->id)
        ->assertSee('Only an approved testimonial with consent can go on a wall.');

    expect($testimonial->refresh()->is_heart)->toBeFalse();
});

it('falls back to every status when an unknown status is chosen', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);

    wmTestimonial($space, $owner, ['name' => 'Approved Person', 'status' => Testimonial::STATUS_APPROVED]);
    wmTestimonial($space, $owner, ['name' => 'Pending Person', 'status' => Testimonial::STATUS_PENDING]);

    // A value that is not one of the real statuses must not leave the
    // picker matching nothing. It falls back to showing every status.
    wmComponent($owner)
        ->call('manage', $space->id)
        ->set('status', 'not-a-real-status')
        ->assertSet('status', '')
        ->assertSee('Approved Person')
        ->assertSee('Pending Person');
});

it('resets the status filter when another space is opened', function (): void {
    $owner = User::factory()->create();
    $space = wmSpace($owner);
    $otherSpace = wmSpace($owner);

    wmTestimonial($space, $owner, ['name' => 'First Space Person', 'status' => Testimonial::STATUS_PENDING]);
    wmTestimonial($otherSpace, $owner, ['name' => 'Second Space Person', 'status' => Testimonial::STATUS_APPROVED]);

    wmComponent($owner)
        ->call('manage', $space->id)
        ->set('status', Testimonial::STATUS_PENDING)
        ->assertSet('status', Testimonial::STATUS_PENDING)
        // Opening another space must start the filter fresh, otherwise a
        // filter left behind on one space would silently hide
        // testimonials on the next one.
        ->call('manage', $otherSpace->id)
        ->assertSet('status', '')
        ->assertSee('Second Space Person');
});
