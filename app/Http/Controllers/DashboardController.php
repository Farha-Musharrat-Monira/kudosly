<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /**
     * Show the dashboard overview.
     */
    public function index(): View
    {
        return view('pages.dashboard');
    }
}
