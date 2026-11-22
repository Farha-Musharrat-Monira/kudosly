<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Page not available</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full items-center justify-center bg-slate-50 px-4 py-12 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="w-full max-w-lg">
        <div class="mb-8 flex justify-center">
            <x-logo />
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                <x-icon name="clock" class="size-6 text-slate-500 dark:text-slate-400" />
            </span>

            <h1 class="mt-5 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
                This page is not available
            </h1>

            <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                This page has not been published yet. Please check back later.
            </p>
        </div>

        <p class="mt-8 text-center text-xs text-slate-500 dark:text-slate-400">
            &copy; {{ date('Y') }} {{ config('app.name', 'Kudosly') }}. All rights reserved.
        </p>
    </div>
</body>
</html>
