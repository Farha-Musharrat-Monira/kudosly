<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PlaceholderController extends Controller
{
    /**
     * Show the inbox, where the owner reviews their testimonials.
     *
     * The Inbox mounts the moderation component, so the page itself carries
     * no data of its own.
     */
    public function inbox(): View
    {
        return view('pages.inbox');
    }

    /**
     * Show the widgets page.
     *
     * The Widgets page mounts the widget builder, so the page itself carries
     * no data of its own.
     */
    public function widgets(): View
    {
        return view('pages.widgets');
    }

    /**
     * Show the settings page.
     *
     * Settings is functional in Phase 1: the profile form and the password
     * form both save through the Fortify action classes.
     */
    public function settings(): View
    {
        return view('pages.settings', [
            'title' => 'Settings',
        ]);
    }
}
