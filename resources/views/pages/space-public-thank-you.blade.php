@php
    $theme = \App\Support\PublicTheme::for($space->theme);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $space->name }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full items-center justify-center {{ $theme['page'] }} px-4 py-12 {{ $theme['body'] }} antialiased">
    <div class="w-full max-w-lg text-center">
        <div class="rounded-2xl border {{ $theme['border'] }} {{ $theme['surface'] }} p-8 shadow-sm">
            <span class="mx-auto flex size-12 items-center justify-center rounded-full {{ $theme['quote'] }}">
                <x-icon name="check" class="size-6 {{ $theme['accent_text'] }}" />
            </span>

            <h1 class="mt-5 text-xl font-bold tracking-tight {{ $theme['heading'] }}">
                You're officially awesome! 🎉
            </h1>

            <p class="mt-3 text-sm leading-relaxed {{ $theme['body'] }}">
                Your kind words just made someone's day.
            </p>

            <p class="mt-5 text-sm {{ $theme['muted'] }}">
                Your testimonial has been received and is awaiting review.
            </p>
        </div>

        <p class="mt-8 text-xs {{ $theme['muted'] }}">
            &copy; {{ date('Y') }} {{ config('app.name', 'Kudosly') }}. All rights reserved.
        </p>
    </div>
</body>
</html>
