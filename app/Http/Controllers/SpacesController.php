<?php

namespace App\Http\Controllers;

use App\Models\Space;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class SpacesController extends Controller
{
    /**
     * Show the spaces management screen.
     */
    public function index(): View
    {
        return view('pages.spaces');
    }

    /**
     * Show the detail screen for a single space.
     *
     * The space is scoped to the authenticated account before the policy
     * runs, so a space owned by another account is reported as missing
     * rather than forbidden. That reveals nothing about the other
     * account's spaces, and the policy still governs every space the
     * account does own.
     */
    public function show(int $spaceId): View
    {
        $space = Space::query()
            ->where('user_id', auth()->id())
            ->with('fields')
            ->withCount('testimonials')
            ->findOrFail($spaceId);

        Gate::authorize('view', $space);

        return view('pages.space-show', [
            'space' => $space,
        ]);
    }
}
