<?php

use App\Livewire\Spaces\ManageSpaces;
use App\Models\Space;
use App\Models\SpaceField;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\get;

it('creates a space for the authenticated account', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('create')
        ->set('name', 'Customer Stories')
        ->set('title', 'What our customers say')
        ->set('subtitle', 'Real feedback from real people')
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasNoErrors();

    $space = Space::query()->where('name', 'Customer Stories')->first();

    expect($space)->not->toBeNull()
        ->and($space->user_id)->toBe($user->id)
        ->and($space->slug)->toBe('customer-stories')
        ->and($space->theme)->toBe('minimal_light')
        ->and($space->title)->toBe('What our customers say');
});

it('shows the collection url after creating a space', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('create')
        ->set('name', 'Customer Stories')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee(url('/s/customer-stories/submit'));
});

it('requires a name', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('create')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name');

    expect(Space::query()->count())->toBe(0);
});

it('rejects an unknown theme', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('create')
        ->set('name', 'Customer Stories')
        ->set('theme', 'neon-horror')
        ->call('save')
        ->assertHasErrors('theme');

    expect(Space::query()->count())->toBe(0);
});

it('creates the default fields and always keeps name and email enabled', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('create')
        ->set('name', 'Customer Stories')
        ->call('save')
        ->assertHasNoErrors();

    $space = Space::query()->where('name', 'Customer Stories')->first();

    expect($space->fields()->count())->toBe(count(SpaceField::KEYS));

    foreach (SpaceField::ALWAYS_ON as $key) {
        $field = $space->fields()->where('key', $key)->first();

        expect($field)->not->toBeNull()
            ->and($field->enabled)->toBeTrue()
            ->and($field->required)->toBeTrue();
    }
});

it('enables an optional field when it is selected', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('create')
        ->set('name', 'Customer Stories')
        ->set('enabledFields', ['company_name'])
        ->call('save')
        ->assertHasNoErrors();

    $space = Space::query()->where('name', 'Customer Stories')->first();

    $company = $space->fields()->where('key', 'company_name')->first();

    expect($company->enabled)->toBeTrue()
        ->and($space->fields()->where('key', 'social_url')->first()->enabled)->toBeFalse();
});

it('never disables the always-on fields', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('create')
        ->set('name', 'Customer Stories')
        ->set('enabledFields', ['company_name'])
        ->call('save')
        ->assertHasNoErrors();

    $space = Space::query()->where('name', 'Customer Stories')->first();

    expect($space->fields()->where('key', 'name')->first()->enabled)->toBeTrue()
        ->and($space->fields()->where('key', 'email')->first()->enabled)->toBeTrue();
});

it('generates a unique slug when the name is already taken', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('create')
        ->set('name', 'Customer Stories')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(ManageSpaces::class)
        ->call('create')
        ->set('name', 'Customer Stories')
        ->call('save')
        ->assertHasNoErrors();

    $slugs = Space::query()->orderBy('id')->pluck('slug')->all();

    expect($slugs)->toBe(['customer-stories', 'customer-stories-2']);
});

it('updates a space owned by the account', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('edit', $space->id)
        ->set('name', 'New Name')
        ->set('title', 'Updated headline')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $space->fresh();

    expect($fresh->name)->toBe('New Name')
        ->and($fresh->title)->toBe('Updated headline')
        // The slug is not regenerated on an update, so the public URL is stable.
        ->and($fresh->slug)->toBe($space->slug);
});

it('deletes a space owned by the account', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('confirmDelete', $space->id)
        ->call('delete')
        ->assertHasNoErrors();

    expect(Space::query()->whereKey($space->id)->exists())->toBeFalse();
});

it('keeps spaces belonging to other accounts isolated', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $foreign = Space::factory()->create(['user_id' => $owner->id, 'name' => 'Foreign Space']);

    $this->actingAs($other);

    Livewire::test(ManageSpaces::class)
        ->call('confirmDelete', $foreign->id)
        ->assertStatus(404);

    expect(Space::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('resolves the reserved public page for a space', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'name' => 'Customer Stories',
        'is_published' => true,
    ]);

    get('/s/'.$space->slug)
        ->assertOk()
        // The heading is literal markup in the view, so its apostrophe is not
        // HTML-escaped and the needle must not be escaped either.
        ->assertSee('This space\'s public page is reserved', escape: false)
        ->assertSee($space->name);
});

it('shows the reserved page instead of a collection form', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'is_published' => true,
    ]);

    get('/s/'.$space->slug)
        ->assertOk()
        ->assertSee('Testimonial submission is not available yet')
        ->assertDontSee('<form', false);
});

it('returns a not found response for an unknown public slug', function (): void {
    get('/s/there-is-no-such-space')->assertNotFound();
});

it('shows the collection url in the spaces list for the owner', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'name' => 'Customer Stories',
    ]);

    $this->actingAs($user);

    get(route('spaces.index'))
        ->assertOk()
        ->assertSee($space->collectionUrl())
        // The copy button copies the code element holding the url.
        ->assertSee('data-copy-target="space-url-'.$space->id.'"', escape: false)
        ->assertSee('id="space-url-'.$space->id.'"', escape: false);
});

it('offers the collection url in the spaces list for the owner', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'name' => 'Customer Stories',
        'is_published' => true,
    ]);

    $this->actingAs($user);

    // The "View page" link points at the collection form, because that is
    // where a customer actually sends a testimonial.
    get(route('spaces.index'))
        ->assertOk()
        ->assertSee('href="'.$space->collectionUrl().'"', escape: false);
});

it('hides the view page link in the spaces list for an unpublished space', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'name' => 'Customer Stories',
        'is_published' => false,
    ]);

    $this->actingAs($user);

    // An unpublished space has no working collection page, so the link is
    // withheld rather than offered as a dead end.
    get(route('spaces.index'))
        ->assertOk()
        ->assertDontSee('href="'.$space->collectionUrl().'"', escape: false)
        ->assertDontSee('View page');
});

it('keeps the copyable collection url of an unpublished space in the spaces list', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'name' => 'Customer Stories',
        'is_published' => false,
    ]);

    $this->actingAs($user);

    // The url is still shown so the owner can copy it and share it once the
    // space is published, and the unpublished badge is still shown.
    get(route('spaces.index'))
        ->assertOk()
        ->assertSee($space->collectionUrl())
        ->assertSee('data-copy-target="space-url-'.$space->id.'"', escape: false)
        ->assertSee('Unpublished');
});

it('shows a collection url that a customer can use to send a testimonial', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'name' => 'Customer Stories',
        'is_published' => true,
    ]);

    $this->actingAs($user);

    // The url the owner is given must be the working collection form.
    get(route('spaces.index'))
        ->assertOk()
        ->assertSee($space->collectionUrl());

    get($space->collectionUrl())
        ->assertOk()
        ->assertSee('Send my testimonial');
});

it('never links the reserved url as the shareable url in the spaces list', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'name' => 'Customer Stories',
    ]);

    $this->actingAs($user);

    // The reserved url is a prefix of the collection url, so it cannot be
    // ruled out with a plain substring assertion. The href is checked
    // instead, because it holds the whole url.
    get(route('spaces.index'))
        ->assertOk()
        ->assertDontSee('href="'.url('/s/'.$space->slug).'"', escape: false);
});

it('keeps the reserved url serving the reserved notice after the collection url fix', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'name' => 'Customer Stories',
        'is_published' => true,
    ]);

    $this->actingAs($user);

    // The reserved page is unchanged by the collection url fix.
    get($space->publicUrl())
        ->assertOk()
        ->assertSee('This space\'s public page is reserved', escape: false)
        ->assertSee('Testimonial submission is not available yet');
});
