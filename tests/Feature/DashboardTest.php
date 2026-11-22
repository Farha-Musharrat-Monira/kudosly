<?php

use App\Livewire\Dashboard\Overview;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\get;

it('requires an authenticated account', function (): void {
    get('/dashboard')->assertRedirect(route('login'));
});

it('redirects an unverified account to the verification notice', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    get('/dashboard')->assertRedirect(route('verification.notice'));
});

it('lets a verified account reach the dashboard', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    get('/dashboard')->assertOk();
});

it('reports zero for every statistic on a fresh account', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    $component = Livewire::test(Overview::class);

    expect($component->instance()->totalSpaces())->toBe(0)
        ->and($component->instance()->totalTestimonials())->toBe(0)
        ->and($component->instance()->uniqueCustomers())->toBe(0)
        ->and($component->instance()->thisMonth())->toBe(0)
        ->and($component->instance()->averageRating())->toBeNull();
});

it('counts the spaces and testimonials owned by the account', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create();

    $space = Space::factory()->create(['user_id' => $user->id]);

    Testimonial::factory()->count(2)->create([
        'space_id' => $space->id,
        'user_id' => $user->id,
    ]);

    // Another account's data must never be counted.
    $foreignSpace = Space::factory()->create(['user_id' => $other->id]);

    Testimonial::factory()->create([
        'space_id' => $foreignSpace->id,
        'user_id' => $other->id,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Overview::class);

    expect($component->instance()->totalSpaces())->toBe(1)
        ->and($component->instance()->totalTestimonials())->toBe(2);
});

it('renders the real counts in the statistic cards', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $space = Space::factory()->create(['user_id' => $user->id]);

    Testimonial::factory()->count(2)->create([
        'space_id' => $space->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::test(Overview::class)
        ->assertSee('Total Spaces')
        ->assertSee('Total Testimonials')
        ->assertSee('Unique Customers')
        ->assertSee('This Month')
        ->assertSee('Average Rating')
        // The value markup is matched exactly, so a stray "1" elsewhere on
        // the page cannot satisfy the assertion.
        ->assertSeeHtml('<p class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">2</p>');
});

it('counts unique customers by email address', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $space = Space::factory()->create(['user_id' => $user->id]);

    Testimonial::factory()->create([
        'space_id' => $space->id,
        'user_id' => $user->id,
        'email' => 'ada@example.com',
    ]);

    Testimonial::factory()->create([
        'space_id' => $space->id,
        'user_id' => $user->id,
        'email' => 'ada@example.com',
    ]);

    Testimonial::factory()->create([
        'space_id' => $space->id,
        'user_id' => $user->id,
        'email' => 'grace@example.com',
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Overview::class);

    expect($component->instance()->totalTestimonials())->toBe(3)
        ->and($component->instance()->uniqueCustomers())->toBe(2);
});

it('counts the testimonials submitted this month', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $space = Space::factory()->create(['user_id' => $user->id]);

    Testimonial::factory()->create([
        'space_id' => $space->id,
        'user_id' => $user->id,
        'created_at' => Carbon::today(),
    ]);

    Testimonial::factory()->create([
        'space_id' => $space->id,
        'user_id' => $user->id,
        'created_at' => Carbon::now()->subMonths(2),
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Overview::class);

    expect($component->instance()->totalTestimonials())->toBe(2)
        ->and($component->instance()->thisMonth())->toBe(1);
});

it('shows an em dash instead of a zero when there are no ratings', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $space = Space::factory()->create(['user_id' => $user->id]);

    Testimonial::factory()->create([
        'space_id' => $space->id,
        'user_id' => $user->id,
        'rating' => null,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Overview::class);

    expect($component->instance()->averageRating())->toBeNull();

    // The missing average is shown as an em dash with an explicit hint, never
    // as a fabricated zero.
    $component
        ->assertSee('No ratings yet')
        ->assertSee('—')
        ->assertDontSeeHtml('<p class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">0.0</p>');
});

it('averages the ratings that exist', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $space = Space::factory()->create(['user_id' => $user->id]);

    Testimonial::factory()->create([
        'space_id' => $space->id,
        'user_id' => $user->id,
        'rating' => 4,
    ]);

    Testimonial::factory()->create([
        'space_id' => $space->id,
        'user_id' => $user->id,
        'rating' => 5,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Overview::class);

    expect($component->instance()->averageRating())->toBe(4.5);
});

it('shows an empty state when there are no submissions', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test(Overview::class)
        ->assertSee('No submissions yet');
});

it('builds a chart series covering the selected range', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    $component = Livewire::test(Overview::class)
        ->set('range', 7);

    $series = $component->instance()->chartSeries();

    expect($series)->toHaveCount(7)
        ->and($series->first()['date'])->toBe(Carbon::today()->subDays(6)->format('Y-m-d'))
        ->and($series->last()['date'])->toBe(Carbon::today()->format('Y-m-d'));
});

it('rejects a tampered chart range', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    $component = Livewire::test(Overview::class)
        ->set('range', 9999);

    // The range is clamped back to a supported window on the server.
    expect($component->get('range'))->toBe(30)
        ->and($component->instance()->chartSeries())->toHaveCount(30);
});

it('counts only the submissions inside the chart range', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $space = Space::factory()->create(['user_id' => $user->id]);

    Testimonial::factory()->create([
        'space_id' => $space->id,
        'user_id' => $user->id,
        'created_at' => Carbon::today(),
    ]);

    Testimonial::factory()->create([
        'space_id' => $space->id,
        'user_id' => $user->id,
        'created_at' => Carbon::today()->subDays(10),
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Overview::class)
        ->set('range', 7);

    $series = $component->instance()->chartSeries();

    expect($series->sum('count'))->toBe(1);
});

it('lists the five most recent submissions', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $space = Space::factory()->create(['user_id' => $user->id]);

    Testimonial::factory()->count(7)->create([
        'space_id' => $space->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Overview::class);

    expect($component->instance()->recentSubmissions())->toHaveCount(5);
});

it('shows the free plan banner and the current plan', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test(Overview::class)
        // The banner is literal markup in the view, so its apostrophe is not
        // HTML-escaped and the needle must not be escaped either.
        ->assertSee("You're on the Free plan", escape: false)
        ->assertSeeHtml('<p class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Free</p>');
});

it('measures each space against the per-space testimonial quota', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $first = Space::factory()->create(['user_id' => $user->id, 'name' => 'First Space']);
    $second = Space::factory()->create(['user_id' => $user->id, 'name' => 'Second Space']);

    Testimonial::factory()->count(4)->create([
        'space_id' => $first->id,
        'user_id' => $user->id,
    ]);

    Testimonial::factory()->count(2)->create([
        'space_id' => $second->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Overview::class);

    $usage = $component->instance()->spaceUsage();

    expect($usage)->toHaveCount(2);

    $rows = $usage->keyBy(fn (array $row) => $row['space']->name);

    // Each space is measured against the per-space quota, never the aggregate.
    expect($rows['First Space']['count'])->toBe(4)
        ->and($rows['First Space']['quota'])->toBe(100)
        ->and($rows['First Space']['percent'])->toBe(4)
        ->and($rows['Second Space']['count'])->toBe(2)
        ->and($rows['Second Space']['quota'])->toBe(100)
        ->and($rows['Second Space']['percent'])->toBe(2);
});
