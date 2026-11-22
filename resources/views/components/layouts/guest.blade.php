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
<body class="flex min-h-full flex-col bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-md">
            <div class="mb-8 flex justify-center">
                <x-logo />
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                {{ $slot }}
            </div>

            <p class="mt-8 text-center text-xs text-slate-500 dark:text-slate-400">
                &copy; {{ date('Y') }} {{ config('app.name', 'Kudosly') }}. All rights reserved.
            </p>
        </div>
    </div>

    @livewireScripts
</body>
</html>
