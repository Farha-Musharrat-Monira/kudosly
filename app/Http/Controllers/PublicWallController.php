<?php

namespace App\Http\Controllers;

use App\Models\Space;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public Wall of Love for a space.
 *
 * A wall is meant to be shared, so this route is public: a visitor reaches
 * it without an account and without an email verification. Only a published
 * space is served. An unpublished space is reported as missing, so neither
 * its content nor the fact that it exists is exposed.
 *
 * The wall shows the hearted testimonials of the space. A testimonial is
 * shown only when it is publicly visible, which means it is approved, not
 * hidden and carries explicit sharing consent. The heart flag alone is
 * never enough, because a testimonial can be hidden or lose its consent
 * after it was hearted.
 *
 * A customer's email address is private data and is never selected here.
 * Customer photos are private data too, so no photo is shown on the wall:
 * the consent a customer gave covers the testimonial text, not the
 * publication of their photograph.
 */
class PublicWallController extends Controller
{
    /**
     * Show the public Wall of Love for a space.
     */
    public function show(string $slug): View
    {
        $space = Space::query()->where('slug', $slug)->first();

        if ($space === null) {
            throw new NotFoundHttpException;
        }

        if (! $space->is_published) {
            // An unpublished space gets the same generic notice the other
            // public pages use. It carries no information about the space.
            return view('pages.space-public-unpublished');
        }

        return view('pages.space-wall-public', [
            'space' => $space,
            'testimonials' => $space->wallTestimonials()->get(),
        ]);
    }
}
