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
     * Show the widgets placeholder.
     */
    public function widgets(): View
    {
        return view('pages.widgets', [
            'title' => 'Widgets',
            'icon' => 'code',
            'heading' => 'Widgets',
            'description' => 'Embed your testimonials on your own website with a lightweight widget and live filtering.',
            'comingSoon' => 'Embeddable widgets arrive in a later release of Kudosly.',
        ]);
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
