<?php

use App\Livewire\Inbox\ManageTestimonials;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * A space owned by the given user.
 */
function moderationSpace(?User $owner = null, array $attributes = []): Space
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
function moderationTestimonial(
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
 * Sign in an owner and open the moderation component.
 *
 * Any query parameters left over from an earlier test are cleared first, so
 * a stale pagination page never leaks into the next test.
 */
function moderationComponent(?User $owner = null): Testable
{
    $owner ??= User::factory()->create();

    $owner->forceFill(['email_verified_at' => now()])->save();

    test()->actingAs($owner);

    Livewire::withQueryParams([]);

    return Livewire::test(ManageTestimonials::class);
}

// ---------------------------------------------------------------------------
// 3a. Ownership isolation — 12 cases
// ---------------------------------------------------------------------------

it('refuses to approve a testimonial owned by another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = moderationSpace($other);
    $testimonial = moderationTestimonial($space, $other);

    moderationComponent($owner)
        ->call('approve', $testimonial->id)
        ->assertStatus(404);

    expect($testimonial->fresh()->status)->toBe(Testimonial::STATUS_PENDING);
});

it('refuses to hide a testimonial owned by another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = moderationSpace($other);
    $testimonial = moderationTestimonial($space, $other);

    moderationComponent($owner)
        ->call('hide', $testimonial->id)
        ->assertStatus(404);

    expect($testimonial->fresh()->status)->toBe(Testimonial::STATUS_PENDING);
});

it('refuses to unhide a testimonial owned by another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = moderationSpace($other);
    $testimonial = moderationTestimonial($space, $other, ['status' => Testimonial::STATUS_HIDDEN]);

    moderationComponent($owner)
        ->call('unhide', $testimonial->id)
        ->assertStatus(404);

    expect($testimonial->fresh()->status)->toBe(Testimonial::STATUS_HIDDEN);
});

it('refuses to favourite a testimonial owned by another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = moderationSpace($other);
    $testimonial = moderationTestimonial($space, $other);

    moderationComponent($owner)
        ->call('favorite', $testimonial->id)
        ->assertStatus(404);

    expect($testimonial->fresh()->is_favorite)->toBeFalse();
});

it('refuses to unfavourite a testimonial owned by another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = moderationSpace($other);
    $testimonial = moderationTestimonial($space, $other, ['is_favorite' => true]);

    moderationComponent($owner)
        ->call('unfavorite', $testimonial->id)
        ->assertStatus(404);

    expect($testimonial->fresh()->is_favorite)->toBeTrue();
});

it('refuses to heart a testimonial owned by another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = moderationSpace($other);
    $testimonial = moderationTestimonial($space, $other, ['status' => Testimonial::STATUS_APPROVED]);

    moderationComponent($owner)
        ->call('heart', $testimonial->id)
        ->assertStatus(404);

    expect($testimonial->fresh()->is_heart)->toBeFalse();
});

it('refuses to unheart a testimonial owned by another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = moderationSpace($other);
    $testimonial = moderationTestimonial($space, $other, [
        'status' => Testimonial::STATUS_APPROVED,
        'is_heart' => true,
    ]);

    moderationComponent($owner)
        ->call('unheart', $testimonial->id)
        ->assertStatus(404);

    expect($testimonial->fresh()->is_heart)->toBeTrue();
});

it('refuses to confirm a delete for a testimonial owned by another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = moderationSpace($other);
    $testimonial = moderationTestimonial($space, $other);

    moderationComponent($owner)
        ->call('confirmDelete', $testimonial->id)
        ->assertStatus(404)
        ->assertSet('deletingTestimonialId', null);
});

it('refuses to delete a testimonial owned by another account', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = moderationSpace($other);

    $testimonial = moderationTestimonial($space, $other);

    moderationComponent($owner)
        ->call('delete', $testimonial->id)
        ->assertStatus(404);

    expect(Testimonial::query()->find($testimonial->id))->not->toBeNull();
});

it('never lists another account testimonial and never leaks its details', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $ownerSpace = moderationSpace($owner);
    moderationTestimonial($ownerSpace, $owner, ['name' => 'Grace Hopper']);

    $otherSpace = moderationSpace($other);
    moderationTestimonial($otherSpace, $other, [
        'name' => 'Secret Person',
        'email' => 'secret@example.com',
        'body' => 'A confidential body that belongs to somebody else entirely.',
    ]);

    moderationComponent($owner)
        ->assertSee('Grace Hopper')
        ->assertDontSee('Secret Person')
        ->assertDontSee('secret@example.com')
        ->assertDontSee('A confidential body that belongs to somebody else entirely.');
});

it('cannot reach another account testimonial through the search', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $ownerSpace = moderationSpace($owner);
    moderationTestimonial($ownerSpace, $owner, ['name' => 'Grace Hopper']);

    $otherSpace = moderationSpace($other);
    moderationTestimonial($otherSpace, $other, ['name' => 'Secret Person']);

    moderationComponent($owner)
        ->set('search', 'Secret Person')
        ->assertDontSee('Grace Hopper')
        ->assertDontSee('Secret Person');
});

it('offers only the owner spaces in the space filter', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    moderationSpace($owner, ['name' => 'Owner Space']);
    moderationSpace($other, ['name' => 'Other Space']);

    moderationComponent($owner)
        ->assertSee('Owner Space')
        ->assertDontSee('Other Space');
});

// ---------------------------------------------------------------------------
// 3b. List, search, filter, order, pagination — 10 cases
// ---------------------------------------------------------------------------

it('lists the owner testimonials', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);

    moderationTestimonial($space, $owner, ['name' => 'First Customer']);
    moderationTestimonial($space, $owner, ['name' => 'Second Customer']);

    moderationComponent($owner)
        ->assertSee('First Customer')
        ->assertSee('Second Customer');
});

it('searches the name the email and the body', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);

    moderationTestimonial($space, $owner, [
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'body' => 'A distinctive body sentence for searching.',
    ]);

    moderationComponent($owner)
        ->set('search', 'Grace')
        ->assertSee('Grace Hopper')
        ->set('search', 'grace@example')
        ->assertSee('Grace Hopper')
        ->set('search', 'distinctive')
        ->assertSee('Grace Hopper')
        ->set('search', 'nothing-matches-this')
        ->assertDontSee('Grace Hopper');
});

it('filters by status', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);

    moderationTestimonial($space, $owner, [
        'name' => 'Pending Person',
        'status' => Testimonial::STATUS_PENDING,
    ]);
    moderationTestimonial($space, $owner, [
        'name' => 'Approved Person',
        'status' => Testimonial::STATUS_APPROVED,
    ]);
    moderationTestimonial($space, $owner, [
        'name' => 'Hidden Person',
        'status' => Testimonial::STATUS_HIDDEN,
    ]);

    moderationComponent($owner)
        ->set('status', Testimonial::STATUS_APPROVED)
        ->assertSee('Approved Person')
        ->assertDontSee('Pending Person')
        ->assertDontSee('Hidden Person');
});

it('filters by space', function (): void {
    $owner = User::factory()->create();

    $firstSpace = moderationSpace($owner);
    $secondSpace = moderationSpace($owner);

    moderationTestimonial($firstSpace, $owner, ['name' => 'First Space Person']);
    moderationTestimonial($secondSpace, $owner, ['name' => 'Second Space Person']);

    moderationComponent($owner)
        ->set('spaceId', $secondSpace->id)
        ->assertSee('Second Space Person')
        ->assertDontSee('First Space Person');
});

it('sorts favourites above the rest', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);

    moderationTestimonial($space, $owner, [
        'name' => 'Ordinary Person',
        'is_favorite' => false,
        'created_at' => now()->addDay(),
    ]);
    moderationTestimonial($space, $owner, [
        'name' => 'Favourite Person',
        'is_favorite' => true,
        'created_at' => now()->subWeek(),
    ]);

    $content = moderationComponent($owner)->html();

    expect(strpos($content, 'Favourite Person'))
        ->toBeInt()
        ->toBeLessThan(strpos($content, 'Ordinary Person'));
});

it('sorts the newest first within a group', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);

    moderationTestimonial($space, $owner, [
        'name' => 'Older Person',
        'created_at' => now()->subDays(5),
    ]);
    moderationTestimonial($space, $owner, [
        'name' => 'Newer Person',
        'created_at' => now()->subDay(),
    ]);

    $content = moderationComponent($owner)->html();

    expect(strpos($content, 'Newer Person'))
        ->toBeInt()
        ->toBeLessThan(strpos($content, 'Older Person'));
});

it('paginates the list at twenty five per page', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);

    for ($index = 0; $index < 26; $index++) {
        moderationTestimonial($space, $owner, [
            'name' => 'Customer '.$index,
            'email' => 'customer'.$index.'@example.com',
        ]);
    }

    moderationComponent($owner)
        ->assertSee('Customer 0')
        ->assertSee('Customer 24')
        ->assertDontSee('Customer 25');

    Livewire::withQueryParams(['page' => 2])
        ->test(ManageTestimonials::class)
        ->assertSee('Customer 25')
        ->assertDontSee('Customer 0');

    Livewire::withQueryParams([]);
});

// ---------------------------------------------------------------------------
// 3c. Moderation actions — 8 cases
// ---------------------------------------------------------------------------

it('approves a consented testimonial', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner, ['consent_to_share' => true]);

    moderationComponent($owner)->call('approve', $testimonial->id);

    expect($testimonial->fresh()->status)->toBe(Testimonial::STATUS_APPROVED)
        ->and($testimonial->fresh()->isPubliclyVisible())->toBeTrue();
});

it('holds a testimonial without consent as pending when it is approved', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner, ['consent_to_share' => false]);

    moderationComponent($owner)
        ->call('approve', $testimonial->id)
        ->assertSet('notice', 'Testimonial approved but held as pending because it has no consent.');

    expect($testimonial->fresh()->status)->toBe(Testimonial::STATUS_PENDING)
        ->and($testimonial->fresh()->isPubliclyVisible())->toBeFalse();
});

it('hides a testimonial', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner, ['status' => Testimonial::STATUS_APPROVED]);

    moderationComponent($owner)->call('hide', $testimonial->id);

    expect($testimonial->fresh()->status)->toBe(Testimonial::STATUS_HIDDEN)
        ->and($testimonial->fresh()->isPubliclyVisible())->toBeFalse();
});

it('marks and unmarks a favourite', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner);

    moderationComponent($owner)->call('favorite', $testimonial->id);

    expect($testimonial->fresh()->is_favorite)->toBeTrue();

    moderationComponent($owner)->call('unfavorite', $testimonial->id);

    expect($testimonial->fresh()->is_favorite)->toBeFalse();
});

it('hearts a publicly visible testimonial', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner, ['status' => Testimonial::STATUS_APPROVED]);

    moderationComponent($owner)->call('heart', $testimonial->id);

    expect($testimonial->fresh()->is_heart)->toBeTrue();

    moderationComponent($owner)->call('unheart', $testimonial->id);

    expect($testimonial->fresh()->is_heart)->toBeFalse();
});

it('refuses to heart a testimonial that is not publicly visible', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);

    $pending = moderationTestimonial($space, $owner, [
        'name' => 'Pending Person',
        'status' => Testimonial::STATUS_PENDING,
    ]);
    $hidden = moderationTestimonial($space, $owner, [
        'name' => 'Hidden Person',
        'status' => Testimonial::STATUS_HIDDEN,
    ]);

    moderationComponent($owner)
        ->call('heart', $pending->id)
        ->assertSet('notice', 'Only an approved testimonial with consent can be hearted.')
        ->call('heart', $hidden->id);

    expect($pending->fresh()->is_heart)->toBeFalse()
        ->and($hidden->fresh()->is_heart)->toBeFalse();
});

it('never writes the consent column', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner, ['consent_to_share' => false]);

    $component = moderationComponent($owner);

    $component->call('approve', $testimonial->id)
        ->call('hide', $testimonial->id)
        ->call('unhide', $testimonial->id)
        ->call('favorite', $testimonial->id)
        ->call('unfavorite', $testimonial->id)
        ->call('heart', $testimonial->id)
        ->call('unheart', $testimonial->id);

    expect($testimonial->fresh()->consent_to_share)->toBeFalse();
});

// ---------------------------------------------------------------------------
// 3d. Status restoration — 5 cases
// ---------------------------------------------------------------------------

it('restores a consented pending testimonial to pending exactly', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner, [
        'consent_to_share' => true,
        'status' => Testimonial::STATUS_PENDING,
    ]);

    moderationComponent($owner)->call('hide', $testimonial->id);
    moderationComponent($owner)->call('unhide', $testimonial->id);

    expect($testimonial->fresh()->status)->toBe(Testimonial::STATUS_PENDING)
        ->and($testimonial->fresh()->isPubliclyVisible())->toBeFalse();
});

it('restores a consented approved testimonial to pending because the old status was not stored', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner, [
        'consent_to_share' => true,
        'status' => Testimonial::STATUS_APPROVED,
    ]);

    moderationComponent($owner)->call('hide', $testimonial->id);
    moderationComponent($owner)
        ->call('unhide', $testimonial->id)
        ->assertSet(
            'notice',
            'Testimonial restored to pending. Its previous status was not stored, so approve it again to publish it.'
        );

    expect($testimonial->fresh()->status)->toBe(Testimonial::STATUS_PENDING)
        ->and($testimonial->fresh()->isPubliclyVisible())->toBeFalse();
});

it('restores a testimonial without consent to pending exactly', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner, [
        'consent_to_share' => false,
        'status' => Testimonial::STATUS_PENDING,
    ]);

    moderationComponent($owner)->call('hide', $testimonial->id);
    moderationComponent($owner)->call('unhide', $testimonial->id);

    expect($testimonial->fresh()->status)->toBe(Testimonial::STATUS_PENDING)
        ->and($testimonial->fresh()->isPubliclyVisible())->toBeFalse();
});

it('never publishes a testimonial when it is unhidden', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner, [
        'consent_to_share' => true,
        'status' => Testimonial::STATUS_APPROVED,
    ]);

    moderationComponent($owner)->call('hide', $testimonial->id);
    moderationComponent($owner)->call('unhide', $testimonial->id);

    $restored = $testimonial->fresh();

    expect($restored->isPubliclyVisible())->toBeFalse()
        ->and(
            Testimonial::query()
                ->where('user_id', $owner->id)
                ->get()
                ->filter(fn (Testimonial $testimonial) => $testimonial->isPubliclyVisible())
                ->count()
        )->toBe(0);
});

it('keeps the hide unhide cycle stable and leaves the other columns alone', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner, [
        'consent_to_share' => true,
        'status' => Testimonial::STATUS_APPROVED,
        'is_favorite' => true,
        'is_heart' => true,
    ]);

    moderationComponent($owner)->call('hide', $testimonial->id);

    expect($testimonial->fresh()->status)->toBe(Testimonial::STATUS_HIDDEN);

    moderationComponent($owner)->call('unhide', $testimonial->id);

    expect($testimonial->fresh()->status)->toBe(Testimonial::STATUS_PENDING);

    moderationComponent($owner)->call('hide', $testimonial->id);

    $final = $testimonial->fresh();

    expect($final->status)->toBe(Testimonial::STATUS_HIDDEN)
        ->and($final->consent_to_share)->toBeTrue()
        ->and($final->is_favorite)->toBeTrue()
        ->and($final->is_heart)->toBeTrue()
        ->and($final->body)->toBe('This product made our team genuinely faster every single day.')
        ->and($final->name)->toBe('Ada Lovelace')
        ->and($final->email)->toBe('ada@example.com');
});

// ---------------------------------------------------------------------------
// 3e. Consent enforcement — 3 cases
// ---------------------------------------------------------------------------

it('does not treat a non consented approved testimonial as publicly visible', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner, [
        'consent_to_share' => false,
        'status' => Testimonial::STATUS_APPROVED,
    ]);

    expect($testimonial->fresh()->isPubliclyVisible())->toBeFalse();
});

it('does not let a hidden testimonial be hearted', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner, [
        'consent_to_share' => true,
        'status' => Testimonial::STATUS_HIDDEN,
    ]);

    moderationComponent($owner)
        ->call('heart', $testimonial->id)
        ->assertSet('notice', 'Only an approved testimonial with consent can be hearted.');

    expect($testimonial->fresh()->is_heart)->toBeFalse();
});

it('keeps a testimonial out of the publicly visible set without consent', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);

    $consented = moderationTestimonial($space, $owner, [
        'name' => 'Consented Person',
        'consent_to_share' => true,
        'status' => Testimonial::STATUS_APPROVED,
    ]);
    $notConsented = moderationTestimonial($space, $owner, [
        'name' => 'Unconsented Person',
        'consent_to_share' => false,
        'status' => Testimonial::STATUS_APPROVED,
    ]);

    $visible = Testimonial::query()
        ->where('user_id', $owner->id)
        ->get()
        ->filter(fn (Testimonial $testimonial) => $testimonial->isPubliclyVisible());

    expect($visible)->toHaveCount(1)
        ->and($visible->first()->id)->toBe($consented->id)
        ->and($visible->contains('id', $notConsented->id))->toBeFalse();
});

// ---------------------------------------------------------------------------
// 3f. Delete — 4 cases
// ---------------------------------------------------------------------------

it('deletes a testimonial', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner);

    moderationComponent($owner)->call('delete', $testimonial->id);

    expect(Testimonial::query()->find($testimonial->id))->toBeNull();
});

it('deletes the private photo with the testimonial', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner);

    $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);
    $path = $file->store('testimonials', Testimonial::PHOTO_DISK);

    $testimonial->update(['profile_photo' => $path]);

    expect(Storage::disk(Testimonial::PHOTO_DISK)->exists($path))->toBeTrue();

    moderationComponent($owner)->call('delete', $testimonial->id);

    expect(Testimonial::query()->find($testimonial->id))->toBeNull()
        ->and(Storage::disk(Testimonial::PHOTO_DISK)->exists($path))->toBeFalse();
});

it('keeps the owner other testimonials when one is deleted', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);

    $doomed = moderationTestimonial($space, $owner, ['name' => 'Doomed Person']);
    $kept = moderationTestimonial($space, $owner, ['name' => 'Kept Person']);

    moderationComponent($owner)->call('delete', $doomed->id);

    expect(Testimonial::query()->find($doomed->id))->toBeNull()
        ->and(Testimonial::query()->find($kept->id))->not->toBeNull();
});

it('does not delete a testimonial when the confirmation is cancelled', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner);

    moderationComponent($owner)
        ->call('confirmDelete', $testimonial->id)
        ->assertSet('deletingTestimonialId', $testimonial->id)
        ->call('cancelDelete')
        ->assertSet('deletingTestimonialId', null);

    expect(Testimonial::query()->find($testimonial->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// 3g. Filter reset — 2 cases
// ---------------------------------------------------------------------------

it('clears every filter', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);
    $testimonial = moderationTestimonial($space, $owner);

    moderationComponent($owner)
        ->set('search', 'Ada')
        ->set('status', Testimonial::STATUS_PENDING)
        ->set('spaceId', $space->id)
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('status', '')
        ->assertSet('spaceId', null);
});

it('returns to the first page and drops the filter when the filters are cleared', function (): void {
    $owner = User::factory()->create();
    $space = moderationSpace($owner);

    for ($index = 0; $index < 26; $index++) {
        moderationTestimonial($space, $owner, [
            'name' => 'Customer '.$index,
            'email' => 'customer'.$index.'@example.com',
        ]);
    }

    // The owner must be signed in before the component is mounted, otherwise
    // the owner-scoped query matches nothing and the list is empty.
    moderationComponent($owner);

    Livewire::withQueryParams(['page' => 2])
        ->test(ManageTestimonials::class)
        ->set('status', Testimonial::STATUS_PENDING)
        ->call('clearFilters')
        ->assertSet('paginators.page', 1)
        // Clearing the filters returns the list to the first page, so the
        // first page is shown again and the last record, which lives on the
        // second page, is no longer part of the rendered list.
        ->assertSee('Customer 0')
        ->assertSee('Customer 24')
        ->assertDontSee('Customer 25');

    Livewire::withQueryParams([]);
});

// ---------------------------------------------------------------------------
// 3h. Rendering — 1 case
// ---------------------------------------------------------------------------

it('renders the moderation component', function (): void {
    $owner = User::factory()->create();

    moderationComponent($owner)->assertOk();
});
