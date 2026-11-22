<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $space->name }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full items-center justify-center bg-slate-50 px-4 py-12 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="w-full max-w-lg">
        <div class="mb-8 flex justify-center">
            <x-logo />
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-950/50">
                <x-icon name="clock" class="size-6 text-amber-600 dark:text-amber-400" />
            </span>

            <h1 class="mt-5 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
                This space's public page is reserved
            </h1>

            <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                Testimonial submission is not available yet — it arrives with the collection phase.
                Your URL is reserved and will work when submission goes live.
            </p>

            @if ($space->title)
                <p class="mt-4 text-base font-medium text-slate-900 dark:text-white">{{ $space->title }}</p>
            @endif

            @if ($space->subtitle)
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $space->subtitle }}</p>
            @endif

            <p class="mt-6 rounded-lg bg-slate-50 px-4 py-3 font-mono text-xs text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                {{ $space->publicUrl() }}
            </p>
        </div>

        <p class="mt-8 text-center text-xs text-slate-500 dark:text-slate-400">
            &copy; {{ date('Y') }} {{ config('app.name', 'Kudosly') }}. All rights reserved.
        </p>
    </div>
</body>
</html>
