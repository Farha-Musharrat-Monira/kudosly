<?php

use App\Livewire\Spaces\ManageSpaces;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

it('allows the owner to view, update and delete a space', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    expect(Gate::forUser($user)->allows('view', $space))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $space))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $space))->toBeTrue();
});

it('denies a non-owner from viewing, updating or deleting a space', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = Space::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    expect(Gate::forUser($other)->allows('view', $space))->toBeFalse()
        ->and(Gate::forUser($other)->allows('update', $space))->toBeFalse()
        ->and(Gate::forUser($other)->allows('delete', $space))->toBeFalse();
});

it('denies every space ability to a guest', function (): void {
    $owner = User::factory()->create();
    $space = Space::factory()->create(['user_id' => $owner->id]);

    expect(Gate::allows('view', $space))->toBeFalse()
        ->and(Gate::allows('update', $space))->toBeFalse()
        ->and(Gate::allows('delete', $space))->toBeFalse()
        ->and(Gate::allows('create', Space::class))->toBeFalse();
});

it('allows a user to create a space while within the plan limit', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    expect($user->canCreateSpace())->toBeTrue()
        ->and(Gate::forUser($user)->allows('create', Space::class))->toBeTrue();
});

it('denies space creation once the plan limit is reached', function (): void {
    $user = User::factory()->create();

    Space::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    expect($user->spacesUsed())->toBe(3)
        ->and($user->spaceQuota())->toBe(3)
        ->and($user->canCreateSpace())->toBeFalse()
        ->and(Gate::forUser($user)->allows('create', Space::class))->toBeFalse();
});

it('does not let one account reach another account spaces through the component', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = Space::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    // The component scopes every query to the authenticated account, so a
    // foreign space id is not found rather than merely unauthorised.
    Livewire::test(ManageSpaces::class)
        ->call('edit', $space->id)
        ->assertStatus(404);
});

it('lists only the authenticated account spaces', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    Space::factory()->create(['user_id' => $owner->id, 'name' => 'Owned Space']);
    Space::factory()->create(['user_id' => $other->id, 'name' => 'Foreign Space']);

    $this->actingAs($owner);

    // The rendered list must not expose another account's spaces.
    Livewire::test(ManageSpaces::class)
        ->assertSee('Owned Space')
        ->assertDontSee('Foreign Space');
});
