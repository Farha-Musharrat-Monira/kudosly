<?php

namespace App\Http\Controllers;

use App\Models\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public widget surfaces.
 *
 * A widget is meant to be embedded on somebody else's website, so both
 * routes here are public: a visitor reaches them without an account and
 * without an email verification.
 *
 * Only a widget whose space is published is served. An unpublished space
 * is reported as missing, so neither its content nor the fact that it
 * exists is exposed, which is the same rule the public Wall of Love and
 * the collection pages follow.
 *
 * The two routes serve different things:
 *
 *   /w/{publicId}             The standalone page, for previewing and for
 *                             opening a widget in a new tab.
 *   /widget/{publicId}/embed  The fragment, which is what an iframe or
 *                             the JavaScript loader actually loads. It
 *                             carries the widget markup and its stylesheet
 *                             and nothing else.
 *
 * The fragment is a separate route so that it can carry its own headers.
 * It is allowed to be framed, because framing it is the point, while the
 * standalone page is not a frame target and says so.
 */
class PublicWidgetController extends Controller
{
    /**
     * Show the standalone public page for a widget.
     */
    public function show(string $publicId): View
    {
        $widget = $this->findPublishedWidget($publicId);

        return view('pages.widget-public', [
            'widget' => $widget,
            'space' => $widget->space,
            'testimonials' => $widget->visibleTestimonials()->get(),
        ]);
    }

    /**
     * Show the embeddable fragment for a widget.
     *
     * The fragment is a bare piece of HTML with no document shell, so it
     * can be dropped straight into a host page. The widget's stylesheet
     * travels with it, scoped to the widget's own id, so the host page
     * never has to know anything about how the widget looks.
     *
     * The fragment is public and anonymous, so it is cacheable. It is
     * read-only, so the cross-origin headers that let the JavaScript
     * loader read it never allow a cross-origin request to change
     * anything.
     */
    public function embed(string $publicId): Response
    {
        $widget = $this->findPublishedWidget($publicId);

        $html = view('components.widget-render', [
            'publicId' => $widget->public_id,
            'layout' => $widget->layout,
            'theme' => $widget->theme,
            'backgroundColor' => $widget->background_color,
            'hasAnimation' => $widget->has_animation,
            'testimonials' => $widget->visibleTestimonials()->get(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /**
     * Find a widget that may be shown publicly.
     *
     * The widget is looked up by its public identifier, never by its
     * numeric key, so an internal identifier is never exposed in a URL.
     * A widget whose space is unpublished is treated as missing.
     */
    protected function findPublishedWidget(string $publicId): Widget
    {
        $widget = Widget::query()
            ->where('public_id', $publicId)
            ->with('space')
            ->first();

        if ($widget === null || $widget->space === null) {
            throw new NotFoundHttpException;
        }

        if (! $widget->space->is_published) {
            throw new NotFoundHttpException;
        }

        return $widget;
    }
}
