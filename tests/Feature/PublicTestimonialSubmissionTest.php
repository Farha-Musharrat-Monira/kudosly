<?php

use App\Models\Space;
use App\Models\SpaceField;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Create a published space with its default field configuration.
 *
 * The default fields are always Name and Email. The optional fields are
 * opt-in, so a test that needs them enables them explicitly.
 */
function publishedSpace(array $attributes = [], ?User $owner = null): Space
{
    $owner ??= User::factory()->create();

    $space = Space::factory()->create(array_merge([
        'user_id' => $owner->id,
        'is_published' => true,
    ], $attributes));

    SpaceField::defaultsFor($space);

    return $space;
}

/**
 * A valid submission payload for a space.
 *
 * @return array<string, mixed>
 */
function submission(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'body' => 'This product made our team genuinely faster every single day.',
        'consent_to_share' => '1',
        'website' => '',
    ], $overrides);
}

it('shows the live collection page for a published space', function (): void {
    $space = publishedSpace([
        'name' => 'Customer Stories',
        'title' => 'What our customers say',
        'subtitle' => 'A few words from the team',
        'testimonial_request' => 'Please tell us what you think.',
    ]);

    get('/s/'.$space->slug.'/submit')
        ->assertOk()
        ->assertSee($space->name)
        ->assertSee($space->title)
        ->assertSee($space->subtitle)
        ->assertSee($space->testimonial_request);
});

it('shows the collection form with the always on fields', function (): void {
    $space = publishedSpace();

    get('/s/'.$space->slug.'/submit')
        ->assertOk()
        ->assertSee('name="name"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="body"', false)
        ->assertSee('Send my testimonial');
});

it('posts the collection form to the submission endpoint with csrf', function (): void {
    $space = publishedSpace();

    get('/s/'.$space->slug.'/submit')
        ->assertOk()
        ->assertSee('name="_token"', false)
        ->assertSee(route('spaces.public.submit', $space->slug), false);
});

it('does not render a star rating when the space does not collect one', function (): void {
    $space = publishedSpace(['collect_rating' => false]);

    get('/s/'.$space->slug.'/submit')
        ->assertOk()
        ->assertDontSee('name="rating"', false);
});

it('renders a star rating when the space collects one', function (): void {
    $space = publishedSpace(['collect_rating' => true]);

    get('/s/'.$space->slug.'/submit')
        ->assertOk()
        ->assertSee('name="rating"', false)
        ->assertSee('value="5"', false);
});

it('renders the enabled custom fields in their saved order', function (): void {
    $space = publishedSpace();

    // Enable the optional fields and put them in a deliberate order.
    $space->fields()->where('key', 'company_name')->update(['enabled' => true, 'position' => 2]);
    $space->fields()->where('key', 'social_url')->update(['enabled' => true, 'position' => 3]);

    $response = get('/s/'.$space->slug.'/submit')->assertOk();

    $content = $response->getContent();

    $companyPosition = strpos($content, 'name="company_name"');
    $socialPosition = strpos($content, 'name="social_url"');
    $emailPosition = strpos($content, 'name="email"');

    expect($companyPosition)->toBeInt()
        ->and($socialPosition)->toBeInt()
        ->and($emailPosition)->toBeInt()
        ->and($emailPosition)->toBeLessThan($companyPosition)
        ->and($companyPosition)->toBeLessThan($socialPosition);
});

it('does not render a custom field that is not enabled', function (): void {
    $space = publishedSpace();

    get('/s/'.$space->slug.'/submit')
        ->assertOk()
        ->assertDontSee('name="company_name"', false)
        ->assertDontSee('name="social_url"', false)
        ->assertDontSee('name="profile_photo"', false);
});

it('applies the theme of the space to the public page', function (): void {
    $space = publishedSpace(['theme' => 'modern_dark']);

    get('/s/'.$space->slug.'/submit')
        ->assertOk()
        ->assertSee('bg-slate-950', false)
        ->assertSee('bg-slate-900', false);
});

it('uses the soft gradient theme classes when the space asks for them', function (): void {
    $space = publishedSpace(['theme' => 'soft_gradient']);

    get('/s/'.$space->slug.'/submit')
        ->assertOk()
        ->assertSee('bg-[#f5f3ff]', false);
});

it('falls back to the default theme for an unknown theme key', function (): void {
    $space = publishedSpace(['theme' => 'not-a-real-theme']);

    get('/s/'.$space->slug.'/submit')
        ->assertOk()
        ->assertSee('bg-slate-50', false);
});

it('stores a valid submission against the space', function (): void {
    $owner = User::factory()->create();
    $space = publishedSpace([], $owner);

    post('/s/'.$space->slug.'/submit', submission(['rating' => 5]))
        ->assertRedirect(route('spaces.public.thank-you', $space->slug));

    $testimonial = Testimonial::query()->sole();

    expect($testimonial->space_id)->toBe($space->id)
        ->and($testimonial->user_id)->toBe($owner->id)
        ->and($testimonial->name)->toBe('Ada Lovelace')
        ->and($testimonial->email)->toBe('ada@example.com')
        ->and($testimonial->body)->toContain('genuinely faster')
        ->and($testimonial->rating)->toBe(5)
        ->and($testimonial->consent_to_share)->toBeTrue();
});

it('stores every new testimonial as pending', function (): void {
    $space = publishedSpace();

    post('/s/'.$space->slug.'/submit', submission())
        ->assertRedirect();

    expect(Testimonial::query()->sole()->status)->toBe(Testimonial::STATUS_PENDING);
});

it('does not let a submission approve or favourite itself', function (): void {
    $space = publishedSpace();

    post('/s/'.$space->slug.'/submit', submission([
        'status' => Testimonial::STATUS_APPROVED,
        'is_favorite' => '1',
        'is_heart' => '1',
    ]))
        ->assertRedirect();

    $testimonial = Testimonial::query()->sole();

    expect($testimonial->status)->toBe(Testimonial::STATUS_PENDING)
        ->and($testimonial->is_favorite)->toBeFalse()
        ->and($testimonial->is_heart)->toBeFalse();
});

it('does not let a submission claim another account', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $space = publishedSpace([], $owner);

    post('/s/'.$space->slug.'/submit', submission(['user_id' => $other->id]))
        ->assertRedirect();

    expect(Testimonial::query()->sole()->user_id)->toBe($owner->id);
});

it('rejects a submission without a name', function (): void {
    $space = publishedSpace();

    post('/s/'.$space->slug.'/submit', submission(['name' => '']))
        ->assertSessionHasErrors('name');

    expect(Testimonial::query()->count())->toBe(0);
});

it('rejects a submission without an email', function (): void {
    $space = publishedSpace();

    post('/s/'.$space->slug.'/submit', submission(['email' => '']))
        ->assertSessionHasErrors('email');

    expect(Testimonial::query()->count())->toBe(0);
});

it('rejects a submission with an invalid email', function (): void {
    $space = publishedSpace();

    post('/s/'.$space->slug.'/submit', submission(['email' => 'not-an-email']))
        ->assertSessionHasErrors('email');

    expect(Testimonial::query()->count())->toBe(0);
});

it('rejects a submission without a body', function (): void {
    $space = publishedSpace();

    post('/s/'.$space->slug.'/submit', submission(['body' => '']))
        ->assertSessionHasErrors('body');

    expect(Testimonial::query()->count())->toBe(0);
});

it('rejects a body that is too short', function (): void {
    $space = publishedSpace();

    post('/s/'.$space->slug.'/submit', submission(['body' => 'Too short']))
        ->assertSessionHasErrors('body');

    expect(Testimonial::query()->count())->toBe(0);
});

it('rejects a submission without a rating when the space collects one', function (): void {
    $space = publishedSpace(['collect_rating' => true]);

    post('/s/'.$space->slug.'/submit', submission(['rating' => null]))
        ->assertSessionHasErrors('rating');

    expect(Testimonial::query()->count())->toBe(0);
});

it('rejects a rating outside the one to five range', function (): void {
    $space = publishedSpace(['collect_rating' => true]);

    post('/s/'.$space->slug.'/submit', submission(['rating' => 9]))
        ->assertSessionHasErrors('rating');

    expect(Testimonial::query()->count())->toBe(0);
});

it('accepts a submission without a rating when the space does not collect one', function (): void {
    $space = publishedSpace(['collect_rating' => false]);

    post('/s/'.$space->slug.'/submit', submission(['rating' => null]))
        ->assertRedirect();

    expect(Testimonial::query()->sole()->rating)->toBeNull();
});

it('requires a field that the space marks as required', function (): void {
    $space = publishedSpace();

    $space->fields()->where('key', 'company_name')->update([
        'enabled' => true,
        'required' => true,
    ]);

    post('/s/'.$space->slug.'/submit', submission(['company_name' => '']))
        ->assertSessionHasErrors('company_name');

    expect(Testimonial::query()->count())->toBe(0);
});

it('accepts an optional field that the space leaves optional', function (): void {
    $space = publishedSpace();

    $space->fields()->where('key', 'company_name')->update([
        'enabled' => true,
        'required' => false,
    ]);

    post('/s/'.$space->slug.'/submit', submission(['company_name' => 'Acme Ltd']))
        ->assertRedirect();

    expect(Testimonial::query()->sole()->company_name)->toBe('Acme Ltd');
});

it('stores the optional fields when they are enabled', function (): void {
    $space = publishedSpace();

    $space->fields()->where('key', 'company_name')->update(['enabled' => true]);
    $space->fields()->where('key', 'social_url')->update(['enabled' => true]);

    post('/s/'.$space->slug.'/submit', submission([
        'company_name' => 'Acme Ltd',
        'social_url' => 'https://example.com/ada',
    ]))
        ->assertRedirect();

    $testimonial = Testimonial::query()->sole();

    expect($testimonial->company_name)->toBe('Acme Ltd')
        ->and($testimonial->social_url)->toBe('https://example.com/ada');
});

it('rejects a social url that is not a url', function (): void {
    $space = publishedSpace();

    $space->fields()->where('key', 'social_url')->update(['enabled' => true]);

    post('/s/'.$space->slug.'/submit', submission(['social_url' => 'not a url']))
        ->assertSessionHasErrors('social_url');

    expect(Testimonial::query()->count())->toBe(0);
});

it('ignores an optional field that the space has not enabled', function (): void {
    $space = publishedSpace();

    post('/s/'.$space->slug.'/submit', submission([
        'company_name' => 'Acme Ltd',
        'social_url' => 'https://example.com/ada',
    ]))
        ->assertRedirect();

    $testimonial = Testimonial::query()->sole();

    expect($testimonial->company_name)->toBeNull()
        ->and($testimonial->social_url)->toBeNull();
});

it('stores an uploaded profile photo on the private disk', function (): void {
    Storage::fake('testimonial-photos');

    $space = publishedSpace();

    $space->fields()->where('key', 'profile_photo')->update(['enabled' => true]);

    post('/s/'.$space->slug.'/submit', submission([
        'profile_photo' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
    ]))
        ->assertRedirect();

    $testimonial = Testimonial::query()->sole();

    expect($testimonial->profile_photo)->toBeString()
        ->and($testimonial->profile_photo)->toStartWith('testimonials/')
        ->and(Storage::disk('testimonial-photos')->exists($testimonial->profile_photo))->toBeTrue();
});

it('rejects a profile photo that is not an image', function (): void {
    Storage::fake('testimonial-photos');

    $space = publishedSpace();

    $space->fields()->where('key', 'profile_photo')->update(['enabled' => true]);

    post('/s/'.$space->slug.'/submit', submission([
        'profile_photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ]))
        ->assertSessionHasErrors('profile_photo');

    expect(Testimonial::query()->count())->toBe(0);
});

it('does not expose an unpublished space through the collection page', function (): void {
    $space = publishedSpace([
        'name' => 'Secret Customer Stories',
        'title' => 'Our private headline',
        'is_published' => false,
    ]);

    get('/s/'.$space->slug.'/submit')->assertNotFound();
});

it('does not accept a submission for an unpublished space', function (): void {
    $space = publishedSpace(['is_published' => false]);

    post('/s/'.$space->slug.'/submit', submission())->assertNotFound();

    expect(Testimonial::query()->count())->toBe(0);
});

it('returns a not found response for an unknown slug', function (): void {
    get('/s/there-is-no-such-space/submit')->assertNotFound();

    post('/s/there-is-no-such-space/submit', submission())->assertNotFound();
});

it('discards a submission that fills the honeypot without telling the sender', function (): void {
    $space = publishedSpace();

    post('/s/'.$space->slug.'/submit', submission(['website' => 'https://spam.example']))
        ->assertRedirect(route('spaces.public.thank-you', $space->slug));

    expect(Testimonial::query()->count())->toBe(0);
});

it('throttles repeated submissions from the same address', function (): void {
    $space = publishedSpace();

    // The limiter allows five submissions a minute from one address.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        post('/s/'.$space->slug.'/submit', submission())->assertRedirect();
    }

    post('/s/'.$space->slug.'/submit', submission())->assertStatus(429);
});

it('shows the thank you page after a submission', function (): void {
    $space = publishedSpace();

    get('/s/'.$space->slug.'/thank-you')
        ->assertOk()
        ->assertSee('officially awesome!', false)
        ->assertSee('Your kind words just made someone');
});

it('does not show customer details on the thank you page', function (): void {
    $space = publishedSpace();

    post('/s/'.$space->slug.'/submit', submission())->assertRedirect();

    get('/s/'.$space->slug.'/thank-you')
        ->assertOk()
        ->assertDontSee('ada@example.com')
        ->assertDontSee('Ada Lovelace')
        ->assertDontSee('genuinely faster');
});

it('does not show the thank you page for an unpublished space', function (): void {
    $space = publishedSpace(['is_published' => false]);

    get('/s/'.$space->slug.'/thank-you')->assertNotFound();
});

it('keeps the reserved url serving the reserved notice', function (): void {
    $space = publishedSpace();

    get('/s/'.$space->slug)
        ->assertOk()
        ->assertSee('This space\'s public page is reserved', escape: false)
        ->assertSee('Testimonial submission is not available yet');
});

it('does not expose customer data on the public collection page', function (): void {
    $space = publishedSpace();

    post('/s/'.$space->slug.'/submit', submission())->assertRedirect();

    // The collection page never lists submitted testimonials.
    get('/s/'.$space->slug.'/submit')
        ->assertOk()
        ->assertDontSee('ada@example.com')
        ->assertDontSee('Ada Lovelace')
        ->assertDontSee('genuinely faster');
});

it('rejects a submission once the space has reached its quota', function (): void {
    $owner = User::factory()->create();
    $space = publishedSpace([], $owner);

    // The Free plan allows a hundred testimonials per space.
    for ($index = 0; $index < $owner->testimonialQuota(); $index++) {
        Testimonial::query()->create([
            'space_id' => $space->id,
            'user_id' => $owner->id,
            'name' => 'Customer '.$index,
            'email' => 'customer'.$index.'@example.com',
            'body' => 'A testimonial body that is long enough to be valid.',
            'status' => Testimonial::STATUS_PENDING,
        ]);
    }

    post('/s/'.$space->slug.'/submit', submission())
        ->assertSessionHasErrors('body');

    expect(Testimonial::query()->count())->toBe($owner->testimonialQuota());
});
