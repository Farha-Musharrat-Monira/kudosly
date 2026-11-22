<?php

use App\Livewire\Spaces\ManageSpaces;
use App\Models\Space;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\get;

it('publishes an unpublished space owned by the account', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'is_published' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('togglePublish', $space->id)
        ->assertHasNoErrors();

    expect($space->fresh()->is_published)->toBeTrue();
});

it('unpublishes a published space owned by the account', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'is_published' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('togglePublish', $space->id)
        ->assertHasNoErrors();

    expect($space->fresh()->is_published)->toBeFalse();
});

it('shows the published and unpublished status on the space card', function (): void {
    $user = User::factory()->create();

    $published = Space::factory()->create([
        'user_id' => $user->id,
        'name' => 'Published Space',
        'is_published' => true,
    ]);

    $unpublished = Space::factory()->create([
        'user_id' => $user->id,
        'name' => 'Unpublished Space',
        'is_published' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->assertSee('Published')
        ->assertSee('Unpublished')
        // The toggle carries the space id so the change event identifies it.
        ->assertSee('wire:change="togglePublish('.$published->id.')"', escape: false)
        ->assertSee('wire:change="togglePublish('.$unpublished->id.')"', escape: false);
});

it('does not let a non-owner publish a space', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = Space::factory()->create([
        'user_id' => $owner->id,
        'is_published' => false,
    ]);

    $this->actingAs($other);

    Livewire::test(ManageSpaces::class)
        ->call('togglePublish', $space->id)
        ->assertStatus(404);

    expect($space->fresh()->is_published)->toBeFalse();
});

it('does not let a non-owner unpublish a space', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = Space::factory()->create([
        'user_id' => $owner->id,
        'is_published' => true,
    ]);

    $this->actingAs($other);

    Livewire::test(ManageSpaces::class)
        ->call('togglePublish', $space->id)
        ->assertStatus(404);

    expect($space->fresh()->is_published)->toBeTrue();
});

it('does not let a guest publish a space', function (): void {
    $owner = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $owner->id,
        'is_published' => false,
    ]);

    // The spaces page requires an authenticated account, so a guest is
    // redirected before the component is ever reached.
    get('/spaces')
        ->assertRedirect(route('login'));

    expect($space->fresh()->is_published)->toBeFalse();
});

it('publishing one space leaves the other spaces untouched', function (): void {
    $user = User::factory()->create();

    $first = Space::factory()->create([
        'user_id' => $user->id,
        'is_published' => false,
    ]);

    $second = Space::factory()->create([
        'user_id' => $user->id,
        'is_published' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('togglePublish', $first->id)
        ->assertHasNoErrors();

    expect($first->fresh()->is_published)->toBeTrue()
        ->and($second->fresh()->is_published)->toBeFalse();
});

it('returns the generic notice for an unpublished space', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'name' => 'Secret Customer Stories',
        'title' => 'Our private headline',
        'subtitle' => 'Our private sub-headline',
        'is_published' => false,
    ]);

    get('/s/'.$space->slug)
        ->assertOk()
        ->assertSee('This page is not available')
        ->assertSee('This page has not been published yet');
});

it('does not expose the space details of an unpublished space', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'name' => 'Secret Customer Stories',
        'title' => 'Our private headline',
        'subtitle' => 'Our private sub-headline',
        'testimonial_request' => 'Please tell us what you think.',
        'is_published' => false,
    ]);

    get('/s/'.$space->slug)
        ->assertOk()
        ->assertDontSee($space->name)
        ->assertDontSee($space->title)
        ->assertDontSee($space->subtitle)
        ->assertDontSee($space->testimonial_request)
        ->assertDontSee('<form', false)
        // The reserved-page copy is not shown for an unpublished space.
        ->assertDontSee('This space\'s public page is reserved', escape: false)
        ->assertDontSee('Testimonial submission is not available yet');
});

it('shows the reserved page once the space is published', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'name' => 'Customer Stories',
        'title' => 'What our customers say',
        'is_published' => false,
    ]);

    // The space is unpublished, so its details are withheld.
    get('/s/'.$space->slug)
        ->assertOk()
        ->assertDontSee($space->name);

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('togglePublish', $space->id)
        ->assertHasNoErrors();

    // Once published the reserved page and the space details are shown.
    get('/s/'.$space->slug)
        ->assertOk()
        ->assertSee('This space\'s public page is reserved', escape: false)
        ->assertSee($space->name)
        ->assertSee($space->title)
        ->assertSee('Testimonial submission is not available yet')
        ->assertDontSee('<form', false);
});

it('keeps the reserved url working for an unpublished space', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'is_published' => false,
    ]);

    // The slug is still claimed: the route resolves it rather than 404ing.
    get('/s/'.$space->slug)->assertOk();

    expect(Space::query()->where('slug', $space->slug)->exists())->toBeTrue();
});

it('still returns a not found response for an unknown public slug', function (): void {
    get('/s/there-is-no-such-space')->assertNotFound();
});
