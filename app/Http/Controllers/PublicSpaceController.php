<?php

namespace App\Http\Controllers;

use App\Models\Space;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicSpaceController extends Controller
{
    /**
     * Show the public page for a space.
     *
     * A published space serves its live collection page. An unpublished
     * space still answers with a page so the reserved URL keeps working,
     * but that page carries no information about the space. The space's
     * name, headline and sub-headline are withheld until the owner
     * publishes it.
     *
     * Testimonial submission is not functional on this page yet — it
     * arrives with the collection phase (Phase 3). This route resolves the
     * space so the URL is genuinely claimed, and renders an honest notice
     * instead of a collection form. No customer data is exposed.
     */
    public function show(string $slug): View
    {
        $space = Space::query()->where('slug', $slug)->first();

        if ($space === null) {
            throw new NotFoundHttpException;
        }

        if (! $space->is_published) {
            return view('pages.space-public-unpublished');
        }

        return view('pages.space-public-reserved', [
            'space' => $space,
        ]);
    }

    /**
     * Show the live testimonial collection page for a published space.
     *
     * This is the page a customer reaches to leave a testimonial. Only a
     * published space is served; an unpublished one is reported as missing
     * so that neither its content nor the fact that it exists is exposed.
     */
    public function collection(string $slug): View
    {
        $space = Space::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with('fields')
            ->first();

        if ($space === null) {
            throw new NotFoundHttpException;
        }

        return view('pages.space-public', [
            'space' => $space,
            'fields' => $space->fields,
        ]);
    }

    /**
     * Show the thank you page after a testimonial has been submitted.
     *
     * The page never shows the submitted testimonial or the customer's
     * details. It only confirms that the submission was received.
     */
    public function thankYou(string $slug): View
    {
        $space = Space::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if ($space === null) {
            throw new NotFoundHttpException;
        }

        return view('pages.space-public-thank-you', [
            'space' => $space,
        ]);
    }
}
