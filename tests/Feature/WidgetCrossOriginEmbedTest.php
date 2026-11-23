<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Widget;

use function Pest\Laravel\get;

/**
 * A published space owned by the given user.
 */
function corsSpace(?User $owner = null, array $attributes = []): Space
{
    $owner ??= User::factory()->create();

    return Space::factory()->create(array_merge([
        'user_id' => $owner->id,
        'is_published' => true,
    ], $attributes));
}

/**
 * A testimonial belonging to a space.
 */
function corsTestimonial(Space $space, array $attributes = []): Testimonial
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
 * A widget for a space.
 */
function corsWidget(Space $space, array $attributes = []): Widget
{
    return Widget::factory()->create(array_merge([
        'space_id' => $space->id,
    ], $attributes));
}

// ---------------------------------------------------------------------------
// The cross-origin policy — 8 cases
// ---------------------------------------------------------------------------

it('allows a cross-origin read of the embeddable fragment', function (): void {
    $space = corsSpace();
    $widget = corsWidget($space);

    $response = get(route('widgets.embed', $widget->public_id), [
        'Origin' => 'https://customer-example.com',
    ]);

    $response->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*');
});

it('allows a cross-origin read from any host', function (): void {
    $space = corsSpace();
    $widget = corsWidget($space);

    foreach (['https://one.example.com', 'https://two.example.org', 'http://localhost:3000'] as $origin) {
        get(route('widgets.embed', $widget->public_id), ['Origin' => $origin])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }
});

it('answers a cross-origin preflight request', function (): void {
    $space = corsSpace();
    $widget = corsWidget($space);

    $response = $this->call(
        'OPTIONS',
        route('widgets.embed', $widget->public_id),
        [],
        [],
        [],
        [
            'HTTP_ORIGIN' => 'https://customer-example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ],
    );

    $response->assertSuccessful()
        ->assertHeader('Access-Control-Allow-Origin', '*');
});

it('does not allow a cross-origin write to the embeddable fragment', function (): void {
    $space = corsSpace();
    $widget = corsWidget($space);

    $response = $this->call(
        'OPTIONS',
        route('widgets.embed', $widget->public_id),
        [],
        [],
        [],
        [
            'HTTP_ORIGIN' => 'https://customer-example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ],
    );

    $allowed = $response->headers->get('Access-Control-Allow-Methods', '');

    expect($allowed)->not->toContain('POST')
        ->and($allowed)->not->toContain('PUT')
        ->and($allowed)->not->toContain('DELETE');
});

it('does not allow credentials on a cross-origin read', function (): void {
    $space = corsSpace();
    $widget = corsWidget($space);

    $response = get(route('widgets.embed', $widget->public_id), [
        'Origin' => 'https://customer-example.com',
    ]);

    $response->assertOk();

    expect($response->headers->get('Access-Control-Allow-Credentials'))->not->toBe('true');
});

it('grants no more than the wildcard to a same-origin request', function (): void {
    $space = corsSpace();
    $widget = corsWidget($space);

    $response = get(route('widgets.embed', $widget->public_id));

    $response->assertOk();

    // The endpoint carries a wildcard policy, so the header is sent either
    // way. What matters is that it is the wildcard and that no credential
    // permission rides along with it.
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('*')
        ->and($response->headers->get('Access-Control-Allow-Credentials'))->not->toBe('true');
});

it('does not allow a cross-origin read of the standalone widget page', function (): void {
    $space = corsSpace();
    $widget = corsWidget($space);

    $response = get(route('widgets.public', $widget->public_id), [
        'Origin' => 'https://customer-example.com',
    ]);

    $response->assertOk()
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('does not allow a cross-origin read of the dashboard', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard', [
        'Origin' => 'https://customer-example.com',
    ]);

    $response->assertOk()
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

// ---------------------------------------------------------------------------
// The embeddable fragment is cacheable and read-only — 5 cases
// ---------------------------------------------------------------------------

it('marks the fragment as public and cacheable', function (): void {
    $space = corsSpace();
    $widget = corsWidget($space);

    $response = get(route('widgets.embed', $widget->public_id))->assertOk();

    // The directives are checked rather than the whole header, because the
    // server is free to order them as it likes.
    $directives = array_map(
        'trim',
        explode(',', (string) $response->headers->get('Cache-Control')),
    );

    expect($directives)->toContain('public')
        ->and($directives)->toContain('max-age=300');
});

it('tells the browser not to guess the fragment\'s content type', function (): void {
    $space = corsSpace();
    $widget = corsWidget($space);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('serves the fragment as html', function (): void {
    $space = corsSpace();
    $widget = corsWidget($space);

    get(route('widgets.embed', $widget->public_id))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
});

it('serves the fragment content cross-origin as well', function (): void {
    $space = corsSpace();
    $widget = corsWidget($space);

    corsTestimonial($space, ['name' => 'Grace Hopper']);

    get(route('widgets.embed', $widget->public_id), [
        'Origin' => 'https://customer-example.com',
    ])
        ->assertOk()
        ->assertSee('Grace Hopper')
        ->assertHeader('Access-Control-Allow-Origin', '*');
});

it('does not leak the owner\'s session on a cross-origin read', function (): void {
    $owner = User::factory()->create();
    $space = corsSpace($owner);
    $widget = corsWidget($space);

    $response = $this->actingAs($owner)->get(route('widgets.embed', $widget->public_id), [
        'Origin' => 'https://customer-example.com',
    ]);

    $response->assertOk()
        ->assertHeaderMissing('Access-Control-Allow-Credentials');
});

// ---------------------------------------------------------------------------
// The widget loader — 4 cases
// ---------------------------------------------------------------------------

it('ships the widget loader as a static file on the application origin', function (): void {
    // The loader is a static asset served straight from the public
    // directory by the web server, so it is not routed by the application.
    expect(public_path('widget.js'))->toBeFile()
        ->and(file_get_contents(public_path('widget.js')))->toContain('data-widget');
});

it('builds the fragment url from the loader\'s own origin', function (): void {
    $loader = file_get_contents(public_path('widget.js'));

    // The request must go back to the server that served the loader, never
    // to an origin the host page chooses.
    expect($loader)->toContain('script.src')
        ->and($loader)->not->toContain('document.location.origin');
});

it('never sends the visitor\'s credentials with the fragment request', function (): void {
    $loader = file_get_contents(public_path('widget.js'));

    expect($loader)->toContain("credentials: 'omit'");
});

it('refuses a public identifier that is not a known hexadecimal one', function (): void {
    $loader = file_get_contents(public_path('widget.js'));

    // A tampered attribute must not be able to smuggle a path, a query
    // string or a protocol-relative url into the request.
    expect($loader)->toContain('/^[0-9a-f]{32}$/');
});
