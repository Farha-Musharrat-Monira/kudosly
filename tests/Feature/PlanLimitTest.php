<?php

use App\Livewire\Spaces\ManageSpaces;
use App\Models\Space;
use App\Models\User;
use Livewire\Livewire;

it('gives a free account a quota of three spaces', function (): void {
    $user = User::factory()->create();

    expect($user->onFreePlan())->toBeTrue()
        ->and($user->spaceQuota())->toBe(3)
        ->and($user->testimonialQuota())->toBe(100)
        ->and($user->spacesUsed())->toBe(0)
        ->and($user->remainingSpaces())->toBe(3)
        ->and($user->canCreateSpace())->toBeTrue();
});

it('allows a free account to create up to three spaces', function (): void {
    $user = User::factory()->create();

    Space::factory()->count(2)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    expect($user->canCreateSpace())->toBeTrue()
        ->and($user->remainingSpaces())->toBe(1);

    Livewire::test(ManageSpaces::class)
        ->call('create')
        ->set('name', 'Third Space')
        ->call('save')
        ->assertHasNoErrors();

    expect(Space::query()->where('user_id', $user->id)->count())->toBe(3);
});

it('blocks a free account from creating a fourth space', function (): void {
    $user = User::factory()->create();

    Space::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    expect($user->canCreateSpace())->toBeFalse()
        ->and($user->remainingSpaces())->toBe(0);

    Livewire::test(ManageSpaces::class)
        ->call('create')
        ->set('name', 'Fourth Space')
        ->call('save')
        ->assertHasErrors('name');

    // The limit is enforced server-side, so nothing was written.
    expect(Space::query()->where('user_id', $user->id)->count())->toBe(3)
        ->and(Space::query()->where('name', 'Fourth Space')->exists())->toBeFalse();
});

it('shows the limit warning and the usage counter at the limit', function (): void {
    $user = User::factory()->create();

    Space::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->assertSee('You have reached the space limit for your plan. Upgrade to Pro to create more spaces.')
        ->assertSee('3 / 3 used');
});

it('gives a pro account a quota of twenty five spaces', function (): void {
    $user = User::factory()->proPlan()->create();

    expect($user->onFreePlan())->toBeFalse()
        ->and($user->plan->slug)->toBe('pro')
        ->and($user->spaceQuota())->toBe(25)
        ->and($user->testimonialQuota())->toBe(1000)
        ->and($user->canCreateSpace())->toBeTrue();
});

it('allows a pro account to create beyond the free limit', function (): void {
    $user = User::factory()->proPlan()->create();

    Space::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ManageSpaces::class)
        ->call('create')
        ->set('name', 'Fourth Space')
        ->call('save')
        ->assertHasNoErrors();

    expect(Space::query()->where('user_id', $user->id)->count())->toBe(4);
});

it('blocks a pro account only at twenty five spaces', function (): void {
    $user = User::factory()->proPlan()->create();

    Space::factory()->count(25)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    expect($user->canCreateSpace())->toBeFalse()
        ->and($user->remainingSpaces())->toBe(0);

    Livewire::test(ManageSpaces::class)
        ->call('create')
        ->set('name', 'Twenty Sixth Space')
        ->call('save')
        ->assertHasErrors('name');

    expect(Space::query()->where('user_id', $user->id)->count())->toBe(25);
});

it('keeps each account quota independent', function (): void {
    $free = User::factory()->create();
    $pro = User::factory()->proPlan()->create();

    Space::factory()->count(3)->create(['user_id' => $free->id]);

    $this->actingAs($free);

    expect($free->canCreateSpace())->toBeFalse()
        ->and($pro->canCreateSpace())->toBeTrue()
        ->and($pro->spacesUsed())->toBe(0);
});
