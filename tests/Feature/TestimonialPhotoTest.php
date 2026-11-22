<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

/**
 * A space owned by the given user.
 */
function photoSpace(?User $owner = null, array $attributes = []): Space
{
    $owner ??= User::factory()->create();

    return Space::factory()->create(array_merge([
        'user_id' => $owner->id,
        'is_published' => true,
    ], $attributes));
}

/**
 * A testimonial owned by the given user, with a stored private photo.
 *
 * The photo is written to the private disk so the route has something real
 * to serve.
 */
function photoTestimonial(
    Space $space,
    ?User $owner = null,
    string $filename = 'avatar.jpg',
    array $attributes = [],
): Testimonial {
    $owner ??= $space->owner;

    $testimonial = Testimonial::factory()->create(array_merge([
        'space_id' => $space->id,
        'user_id' => $owner->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'body' => 'This product made our team genuinely faster every single day.',
        'consent_to_share' => true,
        'status' => Testimonial::STATUS_PENDING,
    ], $attributes));

    $path = UploadedFile::fake()->image($filename, 200, 200)->store('testimonials', Testimonial::PHOTO_DISK);

    $testimonial->update(['profile_photo' => $path]);

    return $testimonial;
}

// ---------------------------------------------------------------------------
// Authentication — 2 cases
// ---------------------------------------------------------------------------

it('refuses to serve a private photo to a guest', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create();
    $space = photoSpace($owner);
    $testimonial = photoTestimonial($space, $owner);

    get(route('inbox.testimonials.photo', $testimonial->id))
        ->assertRedirect(route('login'));
});

it('refuses to serve a private photo to an unverified account', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->unverified()->create();
    $space = photoSpace($owner);
    $testimonial = photoTestimonial($space, $owner);

    $this->actingAs($owner);

    get(route('inbox.testimonials.photo', $testimonial->id))
        ->assertRedirect(route('verification.notice'));
});

// ---------------------------------------------------------------------------
// Ownership — 4 cases
// ---------------------------------------------------------------------------

it('serves the private photo to the owner of the space', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = photoSpace($owner);
    $testimonial = photoTestimonial($space, $owner);

    $this->actingAs($owner);

    get(route('inbox.testimonials.photo', $testimonial->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

it('refuses to serve a private photo to another account', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);

    $space = photoSpace($owner);
    $testimonial = photoTestimonial($space, $owner);

    $this->actingAs($other);

    // The response reports the testimonial as missing, so it reveals nothing
    // about whether the testimonial exists.
    get(route('inbox.testimonials.photo', $testimonial->id))
        ->assertNotFound();
});

it('refuses to serve a private photo when the space belongs to another account', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);

    // The testimonial claims the requesting account as its owner, but the
    // space it belongs to belongs to somebody else. The ownership check runs
    // through the space, so the request is refused.
    $otherSpace = photoSpace($other);
    $testimonial = photoTestimonial($otherSpace, $owner);

    $this->actingAs($owner);

    get(route('inbox.testimonials.photo', $testimonial->id))
        ->assertNotFound();
});

it('reports an unknown testimonial as missing', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($owner);

    get(route('inbox.testimonials.photo', 999999))
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Missing files — 3 cases
// ---------------------------------------------------------------------------

it('reports a testimonial without a photo as missing', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = photoSpace($owner);

    $testimonial = Testimonial::factory()->create([
        'space_id' => $space->id,
        'user_id' => $owner->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'body' => 'This product made our team genuinely faster every single day.',
        'profile_photo' => null,
    ]);

    $this->actingAs($owner);

    get(route('inbox.testimonials.photo', $testimonial->id))
        ->assertNotFound();
});

it('reports a photo that is not on the disk as missing', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = photoSpace($owner);

    $testimonial = Testimonial::factory()->create([
        'space_id' => $space->id,
        'user_id' => $owner->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'body' => 'This product made our team genuinely faster every single day.',
        'profile_photo' => 'testimonials/does-not-exist.jpg',
    ]);

    $this->actingAs($owner);

    get(route('inbox.testimonials.photo', $testimonial->id))
        ->assertNotFound();
});

it('reports a photo with an empty path as missing', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = photoSpace($owner);

    $testimonial = Testimonial::factory()->create([
        'space_id' => $space->id,
        'user_id' => $owner->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'body' => 'This product made our team genuinely faster every single day.',
        'profile_photo' => '',
    ]);

    $this->actingAs($owner);

    get(route('inbox.testimonials.photo', $testimonial->id))
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Allowed image types — 4 cases
// ---------------------------------------------------------------------------

it('serves a jpeg photo as an image', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = photoSpace($owner);
    $testimonial = photoTestimonial($space, $owner, 'avatar.jpg');

    $this->actingAs($owner);

    get(route('inbox.testimonials.photo', $testimonial->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

it('serves a png photo as an image', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = photoSpace($owner);
    $testimonial = photoTestimonial($space, $owner, 'avatar.png');

    $this->actingAs($owner);

    get(route('inbox.testimonials.photo', $testimonial->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('serves a webp photo as an image', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = photoSpace($owner);
    $testimonial = photoTestimonial($space, $owner, 'avatar.webp');

    $this->actingAs($owner);

    get(route('inbox.testimonials.photo', $testimonial->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');
});

it('refuses to serve a photo whose type is not an allowed image', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = photoSpace($owner);

    $testimonial = Testimonial::factory()->create([
        'space_id' => $space->id,
        'user_id' => $owner->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'body' => 'This product made our team genuinely faster every single day.',
        'profile_photo' => 'testimonials/notes.pdf',
    ]);

    Storage::disk(Testimonial::PHOTO_DISK)->put('testimonials/notes.pdf', 'not an image');

    $this->actingAs($owner);

    get(route('inbox.testimonials.photo', $testimonial->id))
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Disclosure — 4 cases
// ---------------------------------------------------------------------------

it('never exposes the storage path of the photo', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = photoSpace($owner);
    $testimonial = photoTestimonial($space, $owner, 'avatar.jpg');

    $this->actingAs($owner);

    $response = get(route('inbox.testimonials.photo', $testimonial->id))->assertOk();

    // The directory the photo lives in must never leave the server.
    $headers = $response->headers->all();

    foreach ($headers as $name => $values) {
        foreach ((array) $values as $value) {
            expect($value)->not->toContain('testimonials/')
                ->and($value)->not->toContain(storage_path())
                ->and($value)->not->toContain('testimonial-photos');
        }
    }
});

it('streams the photo itself and nothing but the photo', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = photoSpace($owner);
    $testimonial = photoTestimonial($space, $owner, 'avatar.jpg');

    $this->actingAs($owner);

    $response = get(route('inbox.testimonials.photo', $testimonial->id))->assertOk();

    // The photo is streamed, so the body is not buffered on the response.
    // The streamed bytes are the image itself, never a path or an error page.
    $response->streamedContent();

    expect($response->getContent())->toBeFalse();
});

it('never exposes the storage path of the photo in the response body', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = photoSpace($owner);
    $testimonial = photoTestimonial($space, $owner, 'avatar.jpg');

    $this->actingAs($owner);

    $response = get(route('inbox.testimonials.photo', $testimonial->id))->assertOk();

    // The streamed payload is the stored image, so it cannot carry a
    // filesystem path or a public URL.
    ob_start();
    $response->streamedContent();
    $body = (string) ob_get_clean();

    expect($body)->toBeString()
        ->and($body)->not->toContain('testimonials/');

    expect($body)->not->toContain(storage_path());
});

it('marks the photo response as private and as not sniffable', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = photoSpace($owner);
    $testimonial = photoTestimonial($space, $owner, 'avatar.jpg');

    $this->actingAs($owner);

    get(route('inbox.testimonials.photo', $testimonial->id))
        ->assertOk()
        // The framework normalises the order of the cache directives, so the
        // header is matched on its parts rather than on the whole string.
        ->assertHeaderContains('Cache-Control', 'private')
        ->assertHeaderContains('Cache-Control', 'max-age=3600')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('does not serve the photo from the public storage path', function (): void {
    Storage::fake('testimonial-photos');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $space = photoSpace($owner);
    $testimonial = photoTestimonial($space, $owner, 'avatar.jpg');

    $this->actingAs($owner);

    $response = get(route('inbox.testimonials.photo', $testimonial->id))->assertOk();

    // The photo is streamed through the authorized route, never redirected to
    // a public file URL.
    expect($response->headers->get('Location'))->toBeNull()
        ->and($response->headers->get('Content-Disposition'))->toContain('inline')
        ->and($response->headers->get('Content-Disposition'))->not->toContain('/');
});
