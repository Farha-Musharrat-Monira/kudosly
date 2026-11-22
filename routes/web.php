<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlaceholderController;
use App\Http\Controllers\PublicSpaceController;
use App\Http\Controllers\PublicWallController;
use App\Http\Controllers\SpacesController;
use App\Http\Controllers\TestimonialPhotoController;
use App\Http\Controllers\TestimonialSubmissionController;
use App\Models\Space;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
|
| The dashboard group requires an authenticated and verified account. The
| "verified.environment" middleware only guards this group, so an unverified
| user can always reach the email verification notice without a redirect loop.
| It enforces verification everywhere except local development, where the
| check is intentionally skipped.
|
*/

Route::middleware(['auth', 'verified.environment'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/spaces', [SpacesController::class, 'index'])->name('spaces.index');

    Route::get('/spaces/{spaceId}', [SpacesController::class, 'show'])->name('spaces.show');

    Route::get('/inbox', [PlaceholderController::class, 'inbox'])->name('inbox');

    /*
     * A customer's profile photo is private data held on a disk that is never
     * served over the web. This route streams it to the space owner only,
     * and the request carries nothing but a numeric testimonial id.
     */
    Route::get('/inbox/testimonials/{testimonial}/photo', [TestimonialPhotoController::class, 'show'])
        ->name('inbox.testimonials.photo');

    /*
     * The Wall of Love management page. The owner finds the public URL of
     * each space's wall here and shares it. The wall itself is public and
     * lives at /wall/{slug} below.
     */
    Route::view('/wall', 'pages.wall')->name('wall');

    Route::get('/widgets', [PlaceholderController::class, 'widgets'])->name('widgets');
    Route::get('/billing', [PlaceholderController::class, 'billing'])->name('billing');

    Route::get('/settings', [PlaceholderController::class, 'settings'])->name('settings');
});

/*
|--------------------------------------------------------------------------
| Public space page (reserved)
|--------------------------------------------------------------------------
|
| The /s/{slug} URL is reserved for each space. The route resolves the
| space and renders an honest reserved notice rather than a collection
| form. An unpublished space is served a generic notice that carries no
| information about the space.
|
*/

Route::get('/s/{slug}', [PublicSpaceController::class, 'show'])->name('spaces.public');

/*
|--------------------------------------------------------------------------
| Public testimonial collection
|--------------------------------------------------------------------------
|
| The live collection page and the submission endpoint for a published
| space. Both are public: a customer reaches them without an account.
|
| The submission is protected by CSRF, a honeypot field and rate limiting.
| An unpublished space is reported as missing on every route, so nothing
| about it is exposed publicly.
|
*/

Route::get('/s/{slug}/submit', [PublicSpaceController::class, 'collection'])
    ->name('spaces.public.collection');

Route::post('/s/{slug}/submit', [TestimonialSubmissionController::class, 'store'])
    ->middleware('throttle:testimonial-submit')
    ->name('spaces.public.submit');

Route::get('/s/{slug}/thank-you', [PublicSpaceController::class, 'thankYou'])
    ->name('spaces.public.thank-you');

/*
|--------------------------------------------------------------------------
| Public Wall of Love
|--------------------------------------------------------------------------
|
| The public Wall of Love for a space. A wall is meant to be shared, so the
| route is public: a visitor reaches it without an account and without an
| email verification.
|
| Only a published space is served. An unpublished space is reported as
| missing, so neither its wall nor the fact that the space exists is
| exposed.
|
*/

Route::get('/wall/{slug}', [PublicWallController::class, 'show'])->name('spaces.public.wall');
