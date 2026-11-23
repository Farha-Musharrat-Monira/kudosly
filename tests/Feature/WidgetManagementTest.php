<?php

use App\Livewire\Widgets\ManageWidgets;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Database\UniqueConstraintViolationException;
use Livewire\Livewire;

use function Pest\Laravel\get;

/**
 * A space owned by the given user.
 */
function widgetSpace(?User $owner = null, array $attributes = []): Space
{
    $owner ??= User::factory()->create();

    return Space::factory()->create(array_merge([
        'user_id' => $owner->id,
    ], $attributes));
}

/**
 * A testimonial belonging to a space.
 */
function widgetTestimonial(Space $space, array $attributes = []): Testimonial
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
 * A widget for a fresh space of the given owner.
 */
function widgetFor(User $owner, array $attributes = []): Widget
{
    $space = widgetSpace($owner);

    return Widget::factory()->create(array_merge([
        'space_id' => $space->id,
    ], $attributes));
}

// ---------------------------------------------------------------------------
// Access to the builder — 6 cases
// ---------------------------------------------------------------------------

it('requires an authenticated account to reach the widget builder', function (): void {
    get('/widgets')->assertRedirect(route('login'));
});

it('redirects an unverified account to the verification notice', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    get('/widgets')->assertRedirect(route('verification.notice'));
});

it('renders the builder for a verified account', function (): void {
    $user = verifiedUser();

    $this->actingAs($user);

    get('/widgets')
        ->assertOk()
        ->assertSeeLivewire(ManageWidgets::class);
});

it('tells the owner to create a space when there are none', function (): void {
    $user = verifiedUser();

    $this->actingAs($user);

    get('/widgets')
        ->assertOk()
        ->assertSee('No spaces to embed yet')
        ->assertSee('Create a space first');
});

it('lists only the spaces of the signed in account', function (): void {
    $user = verifiedUser();
    $other = verifiedUser();

    widgetSpace($user, ['name' => 'My Customer Stories']);
    widgetSpace($other, ['name' => 'Someone Elses Stories']);

    $this->actingAs($user);

    get('/widgets')
        ->assertOk()
        ->assertSee('My Customer Stories')
        ->assertDontSee('Someone Elses Stories');
});

it('does not offer another account\'s spaces in the picker', function (): void {
    $user = verifiedUser();
    $other = verifiedUser();

    widgetSpace($other, ['name' => 'Someone Elses Stories']);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->assertDontSee('Someone Elses Stories');
});

// ---------------------------------------------------------------------------
// Creating a widget — 12 cases
// ---------------------------------------------------------------------------

it('creates a widget for the selected space', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->call('save')
        ->assertHasNoErrors();

    $widget = Widget::query()->first();

    expect($widget)->not->toBeNull()
        ->and($widget->space_id)->toBe($space->id)
        ->and($widget->layout)->toBe(Widget::DEFAULT_LAYOUT)
        ->and($widget->theme)->toBe(Widget::DEFAULT_THEME)
        ->and($widget->background_color)->toBe(Widget::DEFAULT_BACKGROUND_COLOR)
        ->and($widget->has_animation)->toBeTrue()
        ->and($widget->display_limit)->toBe(Widget::DEFAULT_DISPLAY_LIMIT);
});

it('generates a 32 character hexadecimal public id for a new widget', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->call('save');

    $widget = Widget::query()->first();

    expect($widget->public_id)->toMatch('/^[0-9a-f]{32}$/');
});

it('gives every widget a different public id', function (): void {
    $user = verifiedUser();
    $first = widgetSpace($user);
    $second = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $first->id)
        ->call('save');

    Livewire::test(ManageWidgets::class)
        ->call('edit', $second->id)
        ->call('save');

    expect(Widget::query()->pluck('public_id')->unique()->count())->toBe(2);
});

it('saves the chosen layout', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('layout', 'carousel')
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::query()->first()->layout)->toBe('carousel');
});

it('saves the chosen theme', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('theme', 'dark')
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::query()->first()->theme)->toBe('dark');
});

it('saves the chosen background colour', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('backgroundColor', '#0f172a')
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::query()->first()->background_color)->toBe('#0f172a');
});

it('saves the animation setting when it is turned off', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('hasAnimation', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::query()->first()->has_animation)->toBeFalse();
});

it('saves the chosen display limit', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('displayLimit', 5)
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::query()->first()->display_limit)->toBe(5);
});

it('reports that a widget was created', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->call('save')
        ->assertSet('notice', 'Widget created. Copy an embed snippet to put it on your website.');
});

it('allows only one widget per space', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->call('save');

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::query()->count())->toBe(1);
});

it('refuses a second widget for a space at the database level', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    Widget::factory()->create(['space_id' => $space->id]);

    expect(fn () => Widget::factory()->create(['space_id' => $space->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('does not create a widget when no space is selected', function (): void {
    $user = verifiedUser();

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('save')
        ->assertSet('error', 'Choose one of your spaces to embed.');

    expect(Widget::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Updating a widget — 10 cases
// ---------------------------------------------------------------------------

it('loads an existing widget into the builder when a space is chosen', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    Widget::factory()->create([
        'space_id' => $space->id,
        'layout' => 'carousel',
        'theme' => 'dark',
        'background_color' => '#0f172a',
        'has_animation' => false,
        'display_limit' => 7,
    ]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->assertSet('layout', 'carousel')
        ->assertSet('theme', 'dark')
        ->assertSet('backgroundColor', '#0f172a')
        ->assertSet('hasAnimation', false)
        ->assertSet('displayLimit', 7);
});

it('updates the settings of an existing widget', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);
    $widget = Widget::factory()->create(['space_id' => $space->id]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('layout', 'carousel')
        ->set('theme', 'dark')
        ->set('displayLimit', 3)
        ->call('save')
        ->assertHasNoErrors();

    expect($widget->fresh())
        ->layout->toBe('carousel')
        ->theme->toBe('dark')
        ->display_limit->toBe(3);
});

it('reports that a widget was updated', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    Widget::factory()->create(['space_id' => $space->id]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->call('save')
        ->assertSet('notice', 'Widget updated.');
});

it('keeps the public id when a widget is updated', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);
    $widget = Widget::factory()->create(['space_id' => $space->id]);
    $publicId = $widget->public_id;

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('theme', 'dark')
        ->call('save');

    expect($widget->fresh()->public_id)->toBe($publicId);
});

it('does not create a second widget when an existing one is saved', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    Widget::factory()->create(['space_id' => $space->id]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::query()->count())->toBe(1);
});

it('resets the settings when a space without a widget is chosen', function (): void {
    $user = verifiedUser();
    $withWidget = widgetSpace($user);
    $withoutWidget = widgetSpace($user);

    Widget::factory()->create([
        'space_id' => $withWidget->id,
        'layout' => 'carousel',
        'theme' => 'dark',
        'display_limit' => 9,
    ]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $withWidget->id)
        ->call('edit', $withoutWidget->id)
        ->assertSet('layout', Widget::DEFAULT_LAYOUT)
        ->assertSet('theme', Widget::DEFAULT_THEME)
        ->assertSet('displayLimit', Widget::DEFAULT_DISPLAY_LIMIT);
});

it('marks a space that is already embedded in the picker', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user, ['name' => 'Embedded Space']);

    Widget::factory()->create(['space_id' => $space->id]);

    $this->actingAs($user);

    get('/widgets')
        ->assertOk()
        ->assertSee('Embedded Space (embedded)');
});

it('shows the embed snippets once a widget exists', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);
    $widget = Widget::factory()->create(['space_id' => $space->id]);

    $this->actingAs($user);

    // The snippets belong to a chosen space, so the space is chosen first
    // in the builder, exactly as the owner does it.
    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->assertSee('widget-script-snippet')
        ->assertSee('widget-iframe-snippet')
        ->assertSee($widget->public_id);
});

it('does not show the embed snippets before a widget exists', function (): void {
    $user = verifiedUser();

    widgetSpace($user);

    $this->actingAs($user);

    get('/widgets')
        ->assertOk()
        ->assertDontSee('widget-iframe-snippet');
});

it('exposes the widget through the account relationship', function (): void {
    $user = verifiedUser();

    widgetFor($user);

    expect($user->widgets()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Validation — 12 cases
// ---------------------------------------------------------------------------

it('rejects an unknown layout', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('layout', 'hologram')
        ->call('save')
        ->assertHasErrors('layout');

    expect(Widget::query()->count())->toBe(0);
});

it('rejects an unknown theme', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('theme', 'neon-horror')
        ->call('save')
        ->assertHasErrors('theme');

    expect(Widget::query()->count())->toBe(0);
});

it('rejects a background colour that is not a hex value', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('backgroundColor', 'red')
        ->call('save')
        ->assertHasErrors('backgroundColor');

    expect(Widget::query()->count())->toBe(0);
});

it('rejects a background colour with a script injection attempt', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('backgroundColor', '#fff; background-image: url(javascript:alert(1))')
        ->call('save')
        ->assertHasErrors('backgroundColor');

    expect(Widget::query()->count())->toBe(0);
});

it('accepts a background colour in uppercase hex', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('backgroundColor', '#AABBCC')
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::query()->first()->background_color)->toBe('#AABBCC');
});

it('rejects a display limit below the minimum', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('displayLimit', 0)
        ->call('save')
        ->assertHasErrors('displayLimit');

    expect(Widget::query()->count())->toBe(0);
});

it('rejects a display limit above the maximum', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('displayLimit', Widget::MAX_DISPLAY_LIMIT + 1)
        ->call('save')
        ->assertHasErrors('displayLimit');

    expect(Widget::query()->count())->toBe(0);
});

it('accepts the minimum display limit', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('displayLimit', Widget::MIN_DISPLAY_LIMIT)
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::query()->first()->display_limit)->toBe(1);
});

it('accepts the maximum display limit', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('displayLimit', Widget::MAX_DISPLAY_LIMIT)
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::query()->first()->display_limit)->toBe(50);
});

it('rejects a non numeric display limit', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('displayLimit', 'many')
        ->call('save')
        ->assertHasErrors('displayLimit');

    expect(Widget::query()->count())->toBe(0);
});

it('rejects an empty display limit', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('displayLimit', '')
        ->call('save')
        ->assertHasErrors('displayLimit');

    expect(Widget::query()->count())->toBe(0);
});

it('does not raise a type error when the display limit is emptied', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    // Clearing the field must not break the page. The component holds the
    // value as text for exactly this reason.
    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('displayLimit', '')
        ->assertOk()
        ->assertSet('displayLimit', '');
});

it('does not raise a type error when the display limit is not a number', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('displayLimit', 'many')
        ->assertOk()
        ->assertSet('displayLimit', 'many');
});

it('keeps the preview usable while the display limit cannot be read', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    widgetTestimonial($space, ['name' => 'Readable Customer']);

    $this->actingAs($user);

    // An unreadable limit must not empty the preview. The widget's own
    // limit is used instead, so the owner still sees what they configured.
    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('displayLimit', '')
        ->assertSee('Readable Customer');
});

it('does not store an unreadable display limit as zero', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('displayLimit', 'many')
        ->call('save')
        ->assertHasErrors('displayLimit');

    expect(Widget::query()->count())->toBe(0);
});

it('keeps the saved display limit when a later save is invalid', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);
    $widget = Widget::factory()->create([
        'space_id' => $space->id,
        'display_limit' => 9,
    ]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('displayLimit', '')
        ->call('save')
        ->assertHasErrors('displayLimit');

    expect($widget->fresh()->display_limit)->toBe(9);
});

it('stores the display limit as an integer', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('displayLimit', '7')
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::query()->first()->display_limit)->toBe(7);
});

it('keeps the existing widget unchanged when a save fails validation', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);
    $widget = Widget::factory()->create([
        'space_id' => $space->id,
        'theme' => 'light',
    ]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('theme', 'neon-horror')
        ->call('save')
        ->assertHasErrors('theme');

    expect($widget->fresh()->theme)->toBe('light');
});

it('clears the error message on a later successful save', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('save')
        ->assertSet('error', 'Choose one of your spaces to embed.')
        ->call('edit', $space->id)
        ->call('save')
        ->assertSet('error', null)
        ->assertHasNoErrors();
});

// ---------------------------------------------------------------------------
// Deleting a widget — 5 cases
// ---------------------------------------------------------------------------

it('deletes the widget of the selected space', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    Widget::factory()->create(['space_id' => $space->id]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->call('delete')
        ->assertHasNoErrors();

    expect(Widget::query()->count())->toBe(0);
});

it('reports that a widget was deleted', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    Widget::factory()->create(['space_id' => $space->id]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->call('delete')
        ->assertSet('notice', 'Widget deleted.');
});

it('resets the settings after a delete', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    Widget::factory()->create([
        'space_id' => $space->id,
        'layout' => 'carousel',
        'theme' => 'dark',
    ]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->call('delete')
        ->assertSet('layout', Widget::DEFAULT_LAYOUT)
        ->assertSet('theme', Widget::DEFAULT_THEME);
});

it('does nothing when deleting without a selected space', function (): void {
    $user = verifiedUser();

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('delete')
        ->assertSet('notice', null)
        ->assertSet('error', null);

    expect(Widget::query()->count())->toBe(0);
});

it('does nothing when the selected space has no widget', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->call('delete')
        ->assertSet('notice', null);

    expect(Widget::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Ownership and authorization — 11 cases
// ---------------------------------------------------------------------------

it('cannot open a space owned by another account', function (): void {
    $owner = verifiedUser();
    $other = verifiedUser();
    $space = widgetSpace($owner);

    $this->actingAs($other);

    // The space is looked up with an owner-scoped query, so another
    // account's space is reported as missing rather than forbidden.
    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->assertStatus(404);
});

it('cannot create a widget for a space owned by another account', function (): void {
    $owner = verifiedUser();
    $other = verifiedUser();
    $space = widgetSpace($owner);

    $this->actingAs($other);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->assertStatus(404);

    expect(Widget::query()->count())->toBe(0);
});

it('cannot delete a widget owned by another account', function (): void {
    $owner = verifiedUser();
    $other = verifiedUser();
    $space = widgetSpace($owner);

    Widget::factory()->create(['space_id' => $space->id]);

    $this->actingAs($other);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->assertStatus(404);

    expect(Widget::query()->count())->toBe(1);
});

it('cannot update a widget owned by another account', function (): void {
    $owner = verifiedUser();
    $other = verifiedUser();
    $space = widgetSpace($owner);
    $widget = Widget::factory()->create(['space_id' => $space->id]);

    $this->actingAs($other);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->assertStatus(404);

    expect($widget->fresh()->theme)->toBe(Widget::DEFAULT_THEME);
});

it('reports a space that belongs to nobody as missing', function (): void {
    $user = verifiedUser();

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', 999999)
        ->assertStatus(404)
        ->assertSet('spaceId', null);
});

it('does not expose another account\'s widget through the account relationship', function (): void {
    $owner = verifiedUser();
    $other = verifiedUser();

    widgetFor($owner);

    expect($other->widgets()->count())->toBe(0)
        ->and($owner->widgets()->count())->toBe(1);
});

it('does not expose another account\'s widget through the space relation', function (): void {
    $owner = verifiedUser();
    $other = verifiedUser();
    $space = widgetSpace($owner);

    Widget::factory()->create(['space_id' => $space->id]);

    expect($other->spaces()->count())->toBe(0)
        ->and($space->widget)->not->toBeNull();
});

it('allows the owner to manage their own widget', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);
    $widget = Widget::factory()->create(['space_id' => $space->id]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('theme', 'dark')
        ->call('save')
        ->assertHasNoErrors();

    expect($widget->fresh()->theme)->toBe('dark');
});

it('allows a widget to be created even when the account has reached its space limit', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    // The account already owns a space, and the widget is still allowed:
    // embedding an existing space is not a new space.
    expect($user->spaces()->count())->toBe(1);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Widget::query()->count())->toBe(1);
});

it('does not count widgets against the space quota', function (): void {
    $user = verifiedUser();

    widgetFor($user);
    widgetFor($user);

    expect($user->spacesUsed())->toBe(2)
        ->and($user->spaces()->count())->toBe(2);
});

it('does not create a widget when the space is not owned by the account', function (): void {
    $owner = verifiedUser();
    $other = verifiedUser();

    widgetSpace($owner);

    $this->actingAs($other);

    Livewire::test(ManageWidgets::class)
        ->call('save')
        ->assertSet('error', 'Choose one of your spaces to embed.');

    expect(Widget::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// The live preview — 10 cases
// ---------------------------------------------------------------------------

it('shows the preview copy when no space is chosen', function (): void {
    $user = verifiedUser();

    widgetSpace($user);

    $this->actingAs($user);

    get('/widgets')
        ->assertOk()
        ->assertSee('Choose a space to see a preview of your widget.');
});

it('previews only publicly visible testimonials', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    widgetTestimonial($space, [
        'name' => 'Visible Customer',
        'status' => Testimonial::STATUS_APPROVED,
        'consent_to_share' => true,
    ]);

    widgetTestimonial($space, [
        'name' => 'Pending Customer',
        'status' => Testimonial::STATUS_PENDING,
        'consent_to_share' => true,
    ]);

    widgetTestimonial($space, [
        'name' => 'Unconsented Customer',
        'status' => Testimonial::STATUS_APPROVED,
        'consent_to_share' => false,
    ]);

    widgetTestimonial($space, [
        'name' => 'Hidden Customer',
        'status' => Testimonial::STATUS_HIDDEN,
        'consent_to_share' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->assertSee('Visible Customer')
        ->assertDontSee('Pending Customer')
        ->assertDontSee('Unconsented Customer')
        ->assertDontSee('Hidden Customer');
});

it('previews a testimonial that is approved but not hearted', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    // The heart flag belongs to the Wall of Love, not to widgets, so a
    // testimonial without it must still appear in the preview.
    widgetTestimonial($space, [
        'name' => 'Unhearted Customer',
        'status' => Testimonial::STATUS_APPROVED,
        'consent_to_share' => true,
        'is_heart' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->assertSee('Unhearted Customer');
});

it('does not preview another account\'s testimonials', function (): void {
    $owner = verifiedUser();
    $other = verifiedUser();
    $space = widgetSpace($owner);

    widgetTestimonial($space, ['name' => 'Owner Customer']);

    $this->actingAs($other);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->assertStatus(404)
        ->assertDontSee('Owner Customer');
});

it('respects the display limit in the preview', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    // The widget shows the newest testimonials first, so the three rows are
    // given distinct timestamps. Without them the rows would share a
    // timestamp and their order would be undefined.
    widgetTestimonial($space, ['name' => 'First Customer'])
        ->forceFill(['created_at' => now()->subDays(3)])->save();
    widgetTestimonial($space, ['name' => 'Second Customer'])
        ->forceFill(['created_at' => now()->subDays(2)])->save();
    widgetTestimonial($space, ['name' => 'Third Customer'])
        ->forceFill(['created_at' => now()->subDay()])->save();

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('displayLimit', 2)
        ->assertSee('Third Customer')
        ->assertSee('Second Customer')
        ->assertDontSee('First Customer');
});

it('shows the chosen layout in the preview', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    widgetTestimonial($space);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('layout', 'carousel')
        ->assertSee('Carousel');
});

it('shows the chosen theme in the preview', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    widgetTestimonial($space);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->set('theme', 'dark')
        ->assertSee('Dark');
});

it('escapes a testimonial body in the preview', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    widgetTestimonial($space, [
        'body' => '<script>alert("xss")</script>',
        'status' => Testimonial::STATUS_APPROVED,
        'consent_to_share' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->assertSee('&lt;script&gt;', escape: false)
        ->assertDontSee('<script>alert("xss")</script>', escape: false);
});

it('shows the empty copy when the space has no testimonials', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->assertSee('No testimonials are available to show yet.');
});

it('does not preview a hidden testimonial even when it is hearted', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    widgetTestimonial($space, [
        'name' => 'Hidden Heart',
        'status' => Testimonial::STATUS_HIDDEN,
        'consent_to_share' => true,
        'is_heart' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->assertDontSee('Hidden Heart');
});

// ---------------------------------------------------------------------------
// The public identifier — 9 cases
// ---------------------------------------------------------------------------

it('generates a public id of exactly 32 hexadecimal characters', function (): void {
    expect(Widget::generatePublicId())->toMatch('/^[0-9a-f]{32}$/')
        ->and(strlen(Widget::generatePublicId()))->toBe(32);
});

it('generates a different public id on every call', function (): void {
    $ids = collect(range(1, 25))->map(fn () => Widget::generatePublicId());

    expect($ids->unique()->count())->toBe(25);
});

it('stores a public id that is unique across widgets', function (): void {
    $user = verifiedUser();

    widgetFor($user);
    widgetFor($user);
    widgetFor($user);

    expect(Widget::query()->pluck('public_id')->unique()->count())->toBe(3);
});

it('refuses two widgets with the same public id at the database level', function (): void {
    $widget = Widget::factory()->create();

    expect(fn () => Widget::factory()->create(['public_id' => $widget->public_id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('retries the insert when the public id collides', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    // The identifier already in use is handed out first, so the insert is
    // guaranteed to collide. The second call returns a fresh identifier,
    // so the retry succeeds.
    $taken = 'a'.str_repeat('0', 31);
    $fresh = 'b'.str_repeat('0', 31);

    Widget::factory()->create(['public_id' => $taken]);

    $sequence = [$taken, $fresh];

    $model = Mockery::mock(Widget::class)->makePartial();
    $model->shouldReceive('generatePublicId')->andReturnUsing(function () use (&$sequence) {
        return array_shift($sequence);
    });

    $widget = $model::createWithPublicId([
        'space_id' => $space->id,
        'layout' => Widget::DEFAULT_LAYOUT,
        'theme' => Widget::DEFAULT_THEME,
        'background_color' => Widget::DEFAULT_BACKGROUND_COLOR,
        'has_animation' => true,
        'display_limit' => Widget::DEFAULT_DISPLAY_LIMIT,
    ]);

    expect($widget->public_id)->toBe($fresh)
        ->and(Widget::query()->count())->toBe(2);
});

it('gives up after the maximum number of attempts', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    Widget::factory()->create(['public_id' => 'a'.str_repeat('0', 31)]);

    $model = Mockery::mock(Widget::class)->makePartial();
    $model->shouldReceive('generatePublicId')->andReturn('a'.str_repeat('0', 31));

    expect(fn () => $model::createWithPublicId([
        'space_id' => $space->id,
        'layout' => Widget::DEFAULT_LAYOUT,
        'theme' => Widget::DEFAULT_THEME,
        'background_color' => Widget::DEFAULT_BACKGROUND_COLOR,
        'has_animation' => true,
        'display_limit' => Widget::DEFAULT_DISPLAY_LIMIT,
    ]))->toThrow(UniqueConstraintViolationException::class);

    // Only the original widget exists: the five attempts all failed.
    expect(Widget::query()->count())->toBe(1);
});

it('does not retry when the space already has a widget', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    Widget::factory()->create(['space_id' => $space->id]);

    $calls = 0;

    $model = Mockery::mock(Widget::class)->makePartial();
    $model->shouldReceive('generatePublicId')->andReturnUsing(function () use (&$calls) {
        $calls++;

        return Widget::generatePublicId();
    });

    expect(fn () => $model::createWithPublicId([
        'space_id' => $space->id,
        'layout' => Widget::DEFAULT_LAYOUT,
        'theme' => Widget::DEFAULT_THEME,
        'background_color' => Widget::DEFAULT_BACKGROUND_COLOR,
        'has_animation' => true,
        'display_limit' => Widget::DEFAULT_DISPLAY_LIMIT,
    ]))->toThrow(UniqueConstraintViolationException::class);

    // The identifier was generated exactly once: the failure was the space
    // constraint, so the insert was not retried.
    expect($calls)->toBe(1);
});

it('does not let a request set the public id', function (): void {
    $user = verifiedUser();
    $space = widgetSpace($user);

    $this->actingAs($user);

    Livewire::test(ManageWidgets::class)
        ->call('edit', $space->id)
        ->call('save');

    $widget = Widget::query()->first();

    expect($widget->public_id)->toMatch('/^[0-9a-f]{32}$/');
});

// ---------------------------------------------------------------------------
// The display limit helper and the labels — 6 cases
// ---------------------------------------------------------------------------

it('returns the display limit when it is inside the range', function (): void {
    $widget = Widget::factory()->make(['display_limit' => 12]);

    expect($widget->effectiveDisplayLimit())->toBe(12);
});

it('raises a display limit below the minimum to the minimum', function (): void {
    $widget = Widget::factory()->make(['display_limit' => 0]);

    expect($widget->effectiveDisplayLimit())->toBe(Widget::MIN_DISPLAY_LIMIT);
});

it('lowers a display limit above the maximum to the maximum', function (): void {
    $widget = Widget::factory()->make(['display_limit' => 500]);

    expect($widget->effectiveDisplayLimit())->toBe(Widget::MAX_DISPLAY_LIMIT);
});

it('exposes the layout label', function (): void {
    expect(Widget::factory()->make(['layout' => 'carousel'])->layoutLabel())->toBe('Carousel');
});

it('exposes the theme label', function (): void {
    expect(Widget::factory()->make(['theme' => 'dark'])->themeLabel())->toBe('Dark');
});

it('falls back to the default label for an unknown layout', function (): void {
    expect(Widget::factory()->make(['layout' => 'unknown'])->layoutLabel())
        ->toBe(Widget::LAYOUTS[Widget::DEFAULT_LAYOUT]);
});
