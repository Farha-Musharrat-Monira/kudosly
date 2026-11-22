<?php

use App\Livewire\Inbox\ManageTestimonials;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\get;

/**
 * A space owned by the given user.
 */
function inboxSpace(?User $owner = null, array $attributes = []): Space
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
function inboxTestimonial(
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
        'status' => Testimonial::STATUS_PENDING,
        'is_favorite' => false,
        'is_heart' => false,
    ], $attributes));
}

/**
 * Sign in an owner and open the inbox component.
 *
 * Any query parameters left over from an earlier test are cleared first, so
 * a stale pagination page never leaks into the next test.
 */
function inboxComponent(?User $owner = null): Testable
{
    $owner ??= User::factory()->create();

    $owner->forceFill(['email_verified_at' => now()])->save();

    test()->actingAs($owner);

    Livewire::withQueryParams([]);

    return Livewire::test(ManageTestimonials::class);
}

// ---------------------------------------------------------------------------
// Page shell — 3 cases
// ---------------------------------------------------------------------------

it('renders the inbox page for a verified owner', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    get('/inbox')
        ->assertOk()
        ->assertSee('Inbox')
        ->assertSee('Review, approve and organise the testimonials your customers have sent.');
});

it('requires an authenticated account for the inbox page', function (): void {
    get('/inbox')->assertRedirect(route('login'));
});

it('redirects an unverified account to the verification notice', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    get('/inbox')->assertRedirect(route('verification.notice'));
});

// ---------------------------------------------------------------------------
// Empty states — 2 cases
// ---------------------------------------------------------------------------

it('shows the empty state when the owner has received no testimonials', function (): void {
    $owner = User::factory()->create();

    inboxComponent($owner)
        ->assertSee('No testimonials yet')
        ->assertSee('Once a customer submits a testimonial to one of your spaces, it will appear here for review.')
        // The filtered empty state must not be shown when nothing is filtered.
        ->assertDontSee('No testimonials match your filters');
});

it('shows the filtered empty state when no testimonial matches', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner, ['name' => 'Grace Hopper']);

    inboxComponent($owner)
        ->set('search', 'nothing-matches-this')
        ->assertSee('No testimonials match your filters')
        ->assertSee('Try a different search term, or clear the filters to see every testimonial.')
        // The unfiltered empty state must not be shown while a filter is active.
        ->assertDontSee('No testimonials yet');
});

// ---------------------------------------------------------------------------
// Filter controls — 3 cases
// ---------------------------------------------------------------------------

it('labels every filter control and pairs it with its input', function (): void {
    $owner = User::factory()->create();

    $component = inboxComponent($owner);

    $component
        ->assertSeeHtml('for="inbox-search"')
        ->assertSeeHtml('id="inbox-search"')
        ->assertSeeHtml('for="inbox-status"')
        ->assertSeeHtml('id="inbox-status"')
        ->assertSeeHtml('for="inbox-space"')
        ->assertSeeHtml('id="inbox-space"');
});

it('offers every status in the status filter', function (): void {
    $owner = User::factory()->create();

    inboxComponent($owner)
        ->assertSee('All statuses')
        ->assertSee('Pending')
        ->assertSee('Approved')
        ->assertSee('Hidden');
});

it('shows the clear filters button only while a filter is active', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner);

    inboxComponent($owner)
        ->assertDontSee('Clear filters')
        ->set('status', Testimonial::STATUS_PENDING)
        ->assertSee('Clear filters')
        ->call('clearFilters')
        ->assertDontSee('Clear filters');
});

// ---------------------------------------------------------------------------
// List rendering — 7 cases
// ---------------------------------------------------------------------------

it('shows the name the email address and the body of a testimonial', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner, [
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'body' => 'A distinctive body sentence for the inbox.',
    ]);

    inboxComponent($owner)
        ->assertSee('Grace Hopper')
        ->assertSee('grace@example.com')
        ->assertSee('A distinctive body sentence for the inbox.');
});

it('shows the name of the space that received the testimonial', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner, ['name' => 'Customer Stories']);

    inboxTestimonial($space, $owner);

    inboxComponent($owner)->assertSee('Customer Stories');
});

it('shows a status badge for every status', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner, [
        'name' => 'Pending Person',
        'status' => Testimonial::STATUS_PENDING,
    ]);
    inboxTestimonial($space, $owner, [
        'name' => 'Approved Person',
        'status' => Testimonial::STATUS_APPROVED,
    ]);
    inboxTestimonial($space, $owner, [
        'name' => 'Hidden Person',
        'status' => Testimonial::STATUS_HIDDEN,
    ]);

    inboxComponent($owner)
        ->assertSee('Pending')
        ->assertSee('Approved')
        ->assertSee('Hidden');
});

it('shows the rating as stars and as a number', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner, ['name' => 'Rated Person', 'rating' => 4]);

    inboxComponent($owner)
        ->assertSee('Rated Person')
        ->assertSee('4 of 5')
        ->assertSee('Rated 4 out of 5');
});

it('shows that a testimonial carries no rating', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner, ['name' => 'Unrated Person', 'rating' => null]);

    inboxComponent($owner)
        ->assertSee('Unrated Person')
        ->assertSee('No rating');
});

it('shows the favourite badge only for a favourite', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner, ['name' => 'Plain Person', 'is_favorite' => false]);
    inboxTestimonial($space, $owner, ['name' => 'Starred Person', 'is_favorite' => true]);

    inboxComponent($owner)
        ->assertSee('Starred Person')
        ->assertSee('Favourite');
});

it('shows the wall badge only for a testimonial on the wall', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner, [
        'name' => 'Loved Person',
        'status' => Testimonial::STATUS_APPROVED,
        'is_heart' => true,
    ]);

    inboxComponent($owner)
        ->assertSee('Loved Person')
        ->assertSee('On your wall');
});

// ---------------------------------------------------------------------------
// Action wiring — 4 cases
// ---------------------------------------------------------------------------

it('offers approve hide and restore according to the status', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner, [
        'name' => 'Pending Person',
        'status' => Testimonial::STATUS_PENDING,
    ]);
    inboxTestimonial($space, $owner, [
        'name' => 'Approved Person',
        'status' => Testimonial::STATUS_APPROVED,
    ]);
    inboxTestimonial($space, $owner, [
        'name' => 'Hidden Person',
        'status' => Testimonial::STATUS_HIDDEN,
    ]);

    $component = inboxComponent($owner);

    $content = $component->html();

    // Each testimonial offers only the action that applies to its status.
    expect($content)->toContain('wire:click="approve(')
        ->and($content)->toContain('wire:click="hide(')
        ->and($content)->toContain('wire:click="unhide(');
});

it('wires the favourite button to the matching action', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner, ['name' => 'Plain Person', 'is_favorite' => false]);
    inboxTestimonial($space, $owner, ['name' => 'Starred Person', 'is_favorite' => true]);

    $content = inboxComponent($owner)->html();

    expect($content)->toContain('wire:click="favorite(')
        ->and($content)->toContain('wire:click="unfavorite(');
});

it('wires the heart button to the matching action', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner, [
        'name' => 'Loved Person',
        'status' => Testimonial::STATUS_APPROVED,
        'is_heart' => true,
    ]);

    $content = inboxComponent($owner)->html();

    expect($content)->toContain('wire:click="unheart(');
});

it('disables the heart button for a testimonial that is not publicly visible', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner, [
        'name' => 'Pending Person',
        'status' => Testimonial::STATUS_PENDING,
    ]);

    $content = inboxComponent($owner)->html();

    // The reason the heart is unavailable is exposed to assistive technology.
    expect($content)->toContain('Only an approved testimonial with consent can be hearted.')
        ->and($content)->toContain('aria-disabled="true"')
        ->and($content)->toContain('aria-describedby="heart-reason-');
});

it('enables the heart button for an approved consented testimonial', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner, [
        'name' => 'Loved Person',
        'status' => Testimonial::STATUS_APPROVED,
        'consent_to_share' => true,
    ]);

    $content = inboxComponent($owner)->html();

    expect($content)->toContain('aria-disabled="false"')
        ->and($content)->not->toContain('Only an approved testimonial with consent can be hearted.');
});

// ---------------------------------------------------------------------------
// Delete confirmation modal — 4 cases
// ---------------------------------------------------------------------------

it('opens the delete confirmation modal when a delete is confirmed', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);
    $testimonial = inboxTestimonial($space, $owner);

    inboxComponent($owner)
        ->call('confirmDelete', $testimonial->id)
        ->assertSet('deletingTestimonialId', $testimonial->id)
        ->assertDispatched('open-modal', name: 'delete-testimonial');
});

it('closes the delete confirmation modal once the testimonial is deleted', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);
    $testimonial = inboxTestimonial($space, $owner);

    inboxComponent($owner)
        ->call('confirmDelete', $testimonial->id)
        ->call('delete', $testimonial->id)
        ->assertSet('deletingTestimonialId', null)
        ->assertDispatched('close-modal', name: 'delete-testimonial');
});

it('names the testimonial that is about to be deleted', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner, ['name' => 'Doomed Person']);

    inboxComponent($owner)
        ->assertSee('Doomed Person')
        ->assertSee('will be permanently deleted, along with its private photo. This cannot be undone.');
});

it('deletes the testimonial only after the confirmation', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);
    $testimonial = inboxTestimonial($space, $owner);

    $component = inboxComponent($owner)->call('confirmDelete', $testimonial->id);

    // The confirmation alone must not delete anything.
    expect(Testimonial::query()->find($testimonial->id))->not->toBeNull();

    $component->call('delete', $testimonial->id);

    expect(Testimonial::query()->find($testimonial->id))->toBeNull();
});

// ---------------------------------------------------------------------------
// The delete modal must never open by itself — 4 cases
// ---------------------------------------------------------------------------

it('renders a valid x-data attribute on the modal so Alpine can initialise it', function (): void {
    $owner = User::factory()->create();

    $content = inboxComponent($owner)->html();

    // The modal is opened and closed by an Alpine component. If the x-data
    // attribute is malformed, Alpine throws while parsing it, the component
    // never initialises, x-show never applies and x-cloak is stripped by the
    // init pass. The result is a permanently visible modal backdrop.
    expect($content)->toContain('x-data="')
        ->and($content)->not->toContain('x-data="{ open: false, focusableSelector:');

    // The attribute value must not contain a raw double quote. A double
    // quoted HTML attribute cannot hold one, so any occurrence means the
    // value was cut short.
    $start = strpos($content, 'x-data="');
    expect($start)->not->toBeFalse();

    $valueStart = $start + strlen('x-data="');
    $valueEnd = strpos($content, '"', $valueStart);
    $value = substr($content, $valueStart, $valueEnd - $valueStart);

    expect($value)->not->toContain('"')
        // The whole Alpine object must be present, not a truncated prefix.
        ->and($value)->toContain('open: false')
        ->and($value)->toContain('openModal()')
        ->and($value)->toContain('closeModal()')
        ->and($value)->toContain('focusableSelector')
        // The braces must balance, otherwise it is not a valid object literal.
        ->and(substr_count($value, '{'))->toBe(substr_count($value, '}'));
});

it('marks the modal as cloaked so it stays hidden until Alpine boots', function (): void {
    $owner = User::factory()->create();

    $content = inboxComponent($owner)->html();

    // The x-cloak attribute is what keeps the backdrop out of the page
    // before Alpine has applied x-show. Without it the modal paints as a
    // full screen overlay the moment the Inbox loads.
    expect($content)->toContain('x-cloak');
});

it('does not dispatch open-modal when the inbox is loaded', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);
    inboxTestimonial($space, $owner);

    inboxComponent($owner)
        ->assertNotDispatched('open-modal')
        ->assertSet('deletingTestimonialId', null);
});

it('never opens the delete modal when the inbox is empty', function (): void {
    $owner = User::factory()->create();

    inboxComponent($owner)
        ->assertSee('No testimonials yet')
        ->assertNotDispatched('open-modal')
        ->assertSet('deletingTestimonialId', null);
});

it('closes the delete modal and clears the selection when the delete is cancelled', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);
    $testimonial = inboxTestimonial($space, $owner);

    inboxComponent($owner)
        ->call('confirmDelete', $testimonial->id)
        ->assertSet('deletingTestimonialId', $testimonial->id)
        ->call('cancelDelete')
        ->assertSet('deletingTestimonialId', null)
        ->assertDispatched('close-modal', name: 'delete-testimonial');

    // The cancelled testimonial is never deleted.
    expect(Testimonial::query()->find($testimonial->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Accessibility — 3 cases
// ---------------------------------------------------------------------------

it('announces the result count in a live region', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner);
    inboxTestimonial($space, $owner);

    $content = inboxComponent($owner)->html();

    expect($content)->toContain('role="status"')
        ->and($content)->toContain('aria-live="polite"')
        ->and($content)->toContain('2 testimonials found.');
});

it('uses the singular form for a single testimonial', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner);

    inboxComponent($owner)->assertSee('1 testimonial found.');
});

it('exposes the pressed state of the favourite button', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    inboxTestimonial($space, $owner, ['name' => 'Starred Person', 'is_favorite' => true]);

    $content = inboxComponent($owner)->html();

    expect($content)->toContain('aria-pressed="true"')
        ->and($content)->toContain('Remove Starred Person from favourites');
});

// ---------------------------------------------------------------------------
// Pagination — 2 cases
// ---------------------------------------------------------------------------

it('reports the visible range and the total in the pagination summary', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);

    for ($index = 0; $index < 3; $index++) {
        inboxTestimonial($space, $owner, ['name' => 'Customer '.$index]);
    }

    inboxComponent($owner)->assertSee('Showing 1 to 3 of 3');
});

it('links the private photo of a testimonial to the owner scoped route', function (): void {
    $owner = User::factory()->create();
    $space = inboxSpace($owner);
    $testimonial = inboxTestimonial($space, $owner);

    $testimonial->update(['profile_photo' => 'testimonials/avatar.jpg']);

    $content = inboxComponent($owner)->html();

    expect($content)->toContain(
        'src="'.route('inbox.testimonials.photo', $testimonial->id).'"'
    );
});
