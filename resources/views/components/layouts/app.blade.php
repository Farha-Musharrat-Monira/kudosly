<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Kudosly') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="min-h-full lg:flex">
        <!-- Sidebar -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full border-r border-slate-200 bg-white transition-transform duration-200 lg:static lg:translate-x-0 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex h-16 items-center border-b border-slate-200 px-5 dark:border-slate-800">
                <x-logo :href="route('dashboard')" />
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main">
                @php
                    $navigation = [
                        ['name' => 'Overview', 'icon' => 'layout-dashboard', 'route' => 'dashboard'],
                        ['name' => 'Spaces', 'icon' => 'layers', 'route' => 'spaces.index'],
                        ['name' => 'Inbox', 'icon' => 'inbox', 'route' => 'inbox'],
                        ['name' => 'Wall of Love', 'icon' => 'heart', 'route' => 'wall'],
                        ['name' => 'Widgets', 'icon' => 'code', 'route' => 'widgets'],
                        ['name' => 'Billing', 'icon' => 'credit-card', 'route' => 'billing'],
                        ['name' => 'Settings', 'icon' => 'settings', 'route' => 'settings'],
                    ];
                @endphp

                @foreach ($navigation as $item)
                    <a
                        href="{{ route($item['route']) }}"
                        @class([
                            'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                            'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300' => request()->routeIs($item['route']),
                            'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' => ! request()->routeIs($item['route']),
                        ])
                        @if (request()->routeIs($item['route'])) aria-current="page" @endif
                    >
                        <x-icon :name="$item['icon']" class="size-5 shrink-0" />
                        {{ $item['name'] }}
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-slate-200 p-3 dark:border-slate-800">
                @if (auth()->user()?->onFreePlan())
                    <div class="rounded-xl bg-indigo-50 p-4 dark:bg-indigo-950/40">
                        <p class="text-sm font-semibold text-indigo-900 dark:text-indigo-200">Free plan</p>
                        <p class="mt-1 text-xs text-indigo-700 dark:text-indigo-300">
                            {{ auth()->user()->remainingSpaces() }} of {{ auth()->user()->spaceQuota() }} spaces left.
                        </p>
                        <x-button :href="route('billing')" variant="primary" class="mt-3 w-full !px-3 !py-2 text-xs">
                            View plans
                        </x-button>
                    </div>
                @endif
            </div>
        </aside>

        <!-- Backdrop -->
        <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden" aria-hidden="true"></div>

        <!-- Main column -->
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8 dark:border-slate-800 dark:bg-slate-900/90">
                <button
                    type="button"
                    id="sidebar-toggle"
                    class="-ml-1 inline-flex items-center justify-center rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 lg:hidden dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    aria-controls="sidebar"
                    aria-expanded="false"
                    aria-label="Open navigation"
                >
                    <x-icon name="menu" class="size-6" />
                </button>

                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-semibold text-slate-900 dark:text-white">
                        {{ $header ?? config('app.name', 'Kudosly') }}
                    </h1>
                </div>

                <div class="relative" id="user-menu">
                    <button
                        type="button"
                        id="user-menu-toggle"
                        class="flex items-center gap-2 rounded-full p-1 pr-3 text-sm font-medium text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-slate-200 dark:hover:bg-slate-800"
                        aria-haspopup="true"
                        aria-expanded="false"
                    >
                        <span class="flex size-8 items-center justify-center rounded-full bg-indigo-600 text-xs font-semibold text-white">
                            {{ Str::upper(Str::substr(auth()->user()->name ?? '?', 0, 2)) }}
                        </span>
                        <span class="hidden max-w-[10rem] truncate sm:block">{{ auth()->user()->name }}</span>
                        <x-icon name="chevron-down" class="size-4 text-slate-400" />
                    </button>

                    <div id="user-menu-panel" class="absolute right-0 mt-2 hidden w-56 origin-top-right rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                            <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                        </div>

                        <a href="{{ route('settings') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                            <x-icon name="user" class="size-4" />
                            Profile
                        </a>
                        <a href="{{ route('settings') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                            <x-icon name="settings" class="size-4" />
                            Settings
                        </a>

                        <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-200 dark:border-slate-800">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                <x-icon name="logout" class="size-4" />
                                Log out
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
