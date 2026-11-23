@php
    /**
     * The standalone public page for a widget.
     *
     * This page is for previewing a widget and for opening it in a new
     * tab. The embeddable fragment lives at /widget/{publicId}/embed and
     * is what an iframe or the JavaScript loader actually loads.
     *
     * The page is public and carries no session, so it never shows
     * anything that the embed itself would not show.
     *
     * @var \App\Models\Widget $widget
     * @var \App\Models\Space $space
     * @var iterable<int, \App\Models\Testimonial> $testimonials
     */
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

    <title>{{ $space->name }} · {{ config('app.name', 'Kudosly') }}</title>

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-slate-50 antialiased">
    <div class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6 sm:py-14">
        <header class="mb-8 text-center">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                {{ $space->name }}
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                {{ $widget->layoutLabel() }} widget · {{ $widget->themeLabel() }}
            </p>
        </header>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <x-widget-render
                :public-id="$widget->public_id"
                :layout="$widget->layout"
                :theme="$widget->theme"
                :background-color="$widget->background_color"
                :has-animation="$widget->has_animation"
                :testimonials="$testimonials"
            />
        </div>

        <p class="mt-8 text-center text-xs text-slate-500">
            &copy; {{ date('Y') }} {{ config('app.name', 'Kudosly') }}. All rights reserved.
        </p>
    </div>
</body>
</html>
