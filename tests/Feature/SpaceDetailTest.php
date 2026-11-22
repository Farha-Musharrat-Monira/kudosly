<?php

use App\Models\Space;
use App\Models\SpaceField;
use App\Models\Testimonial;
use App\Models\User;

use function Pest\Laravel\get;

it('shows the space detail page to its owner', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'name' => 'Customer Stories',
        'title' => 'What our customers say',
    ]);

    $this->actingAs($user);

    get(route('spaces.show', $space->id))
        ->assertOk()
        ->assertSee('Customer Stories')
        ->assertSee('What our customers say');
});

it('shows the copyable collection url on the detail page', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    get(route('spaces.show', $space->id))
        ->assertOk()
        ->assertSee($space->collectionUrl())
        // The copy button targets the readonly input holding the url.
        ->assertSee('data-copy-target="space-detail-url-'.$space->id.'"', escape: false)
        ->assertSee('id="space-detail-url-'.$space->id.'"', escape: false);
});

it('shows the theme preview on the detail page', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'theme' => 'modern_dark',
    ]);

    $this->actingAs($user);

    get(route('spaces.show', $space->id))
        ->assertOk()
        ->assertSee('Modern Dark')
        ->assertSee('Modern Dark theme preview');
});

it('shows the field configuration on the detail page', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create(['user_id' => $user->id]);

    // A space always has the default field set. Name and email are always
    // collected and always required.
    SpaceField::defaultsFor($space);

    // Company Name is collected but optional.
    $space->fields()->where('key', 'company_name')->update([
        'enabled' => true,
        'required' => false,
    ]);

    // The social profile url is not collected at all.
    $space->fields()->where('key', 'social_url')->update([
        'enabled' => false,
        'required' => false,
    ]);

    $this->actingAs($user);

    get(route('spaces.show', $space->id))
        ->assertOk()
        ->assertSee('Company Name')
        ->assertSee('Social Profile URL')
        // The always-on fields and the enabled optional field are shown as
        // required, the disabled one as not collected.
        ->assertSee('Required')
        ->assertSee('Optional')
        ->assertSee('Not collected');
});

it('shows the publishing status on the detail page', function (): void {
    $user = User::factory()->create();

    $published = Space::factory()->create([
        'user_id' => $user->id,
        'is_published' => true,
    ]);

    $this->actingAs($user);

    get(route('spaces.show', $published->id))
        ->assertOk()
        ->assertSee('Published');

    $unpublished = Space::factory()->create([
        'user_id' => $user->id,
        'is_published' => false,
    ]);

    get(route('spaces.show', $unpublished->id))
        ->assertOk()
        ->assertSee('Unpublished');
});

it('shows the actual testimonial count on the detail page', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    // With no testimonials the count is a real zero, not a blank.
    get(route('spaces.show', $space->id))
        ->assertOk()
        ->assertSee('Testimonials')
        ->assertSee('Collected on this space so far.');

    Testimonial::factory()->count(3)->create(['space_id' => $space->id, 'user_id' => $user->id]);

    get(route('spaces.show', $space->id))
        ->assertOk()
        ->assertSee('Collected on this space so far.')
        ->assertSeeInOrder(['Testimonials', '3']);
});

it('does not let a non-owner view the space detail page', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $space = Space::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Foreign Space',
    ]);

    $this->actingAs($other);

    get(route('spaces.show', $space->id))
        ->assertNotFound()
        ->assertDontSee('Foreign Space');
});

it('redirects a guest to the login page', function (): void {
    $owner = User::factory()->create();
    $space = Space::factory()->create(['user_id' => $owner->id]);

    get(route('spaces.show', $space->id))
        ->assertRedirect(route('login'));
});

it('returns a not found response for an unknown space', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    get(route('spaces.show', 999999))
        ->assertNotFound();
});

it('does not render a public collection form on the detail page', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'is_published' => true,
    ]);

    $this->actingAs($user);

    // The dashboard shell has its own logout form, so the collection form is
    // identified by the fields it would collect rather than by a bare form
    // tag. Testimonial submission is not available yet.
    get(route('spaces.show', $space->id))
        ->assertOk()
        ->assertDontSee('Testimonial submission is not available yet')
        ->assertDontSee('name="body"', false)
        ->assertDontSee('name="testimonial_request"', false);
});

it('does not theme the public page from the detail page', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'theme' => 'modern_dark',
        'is_published' => true,
    ]);

    $this->actingAs($user);

    // The detail page shows the theme preview, but the public page keeps its
    // own reserved look. Group E theming is not implemented yet.
    get(route('spaces.show', $space->id))
        ->assertOk()
        ->assertSee('Modern Dark theme preview');

    get('/s/'.$space->slug)
        ->assertOk()
        ->assertSee('This space\'s public page is reserved', escape: false)
        ->assertDontSee('Modern Dark theme preview');
});

it('offers the collection url on the detail page', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    // The "View page" link points at the collection form, because that is
    // where a customer actually sends a testimonial.
    get(route('spaces.show', $space->id))
        ->assertOk()
        ->assertSee('Collection page')
        ->assertSee('href="'.$space->collectionUrl().'"', escape: false);
});

it('shows the collection url that a customer can use to send a testimonial', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'is_published' => true,
    ]);

    $this->actingAs($user);

    // The url the owner is given must be the working collection form.
    get(route('spaces.show', $space->id))
        ->assertOk()
        ->assertSee($space->collectionUrl());

    get($space->collectionUrl())
        ->assertOk()
        ->assertSee('Send my testimonial');
});

it('keeps the reserved url serving the reserved notice', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->create([
        'user_id' => $user->id,
        'is_published' => true,
    ]);

    $this->actingAs($user);

    // The reserved page is unchanged by the collection url fix.
    get($space->publicUrl())
        ->assertOk()
        ->assertSee('This space\'s public page is reserved', escape: false)
        ->assertSee('Testimonial submission is not available yet');
});
