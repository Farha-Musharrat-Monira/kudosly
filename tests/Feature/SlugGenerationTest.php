<?php

use App\Models\Space;
use App\Models\User;

it('turns a name into a slug', function (): void {
    expect(Space::generateUniqueSlug('Customer Stories'))->toBe('customer-stories');
});

it('lowercases and hyphenates a name', function (): void {
    expect(Space::generateUniqueSlug('Ada Lovelace Reviews'))
        ->toBe('ada-lovelace-reviews');
});

it('removes characters that cannot appear in a url', function (): void {
    expect(Space::generateUniqueSlug('Hello, World! & Friends'))
        ->toBe('hello-world-friends');
});

it('falls back to a default when the name has no usable characters', function (): void {
    expect(Space::generateUniqueSlug('!!!'))->toBe('space');
});

it('appends a suffix when the slug is already taken', function (): void {
    $user = User::factory()->create();

    Space::factory()->create([
        'user_id' => $user->id,
        'slug' => 'customer-stories',
    ]);

    expect(Space::generateUniqueSlug('Customer Stories'))->toBe('customer-stories-2');
});

it('increments the suffix until the slug is free', function (): void {
    $user = User::factory()->create();

    Space::factory()->create([
        'user_id' => $user->id,
        'slug' => 'customer-stories',
    ]);

    Space::factory()->create([
        'user_id' => $user->id,
        'slug' => 'customer-stories-2',
    ]);

    expect(Space::generateUniqueSlug('Customer Stories'))->toBe('customer-stories-3');
});

it('keeps the slug unique when the name is different', function (): void {
    $user = User::factory()->create();

    Space::factory()->create([
        'user_id' => $user->id,
        'slug' => 'customer-stories',
    ]);

    expect(Space::generateUniqueSlug('Customer Feedback'))->toBe('customer-feedback');
});
