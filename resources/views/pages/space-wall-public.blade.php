@php
    /**
     * The public Wall of Love for a space.
     *
     * The page shows the hearted testimonials of the space. A testimonial
     * reaches this page only when it is approved, not hidden and carries
     * explicit sharing consent.
     *
     * A customer's email address is private data and is never shown here.
     * A customer's photograph is private data too, so no photo is shown on
     * the wall: the consent a customer gave covers the testimonial text,
     * not the publication of their picture.
     *
     * @var \App\Models\Space $space
     * @var \Illuminate\Support\Collection<int, \App\Models\Testimonial> $testimonials
     */
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
<body class="min-h-full {{ $theme['page'] }} {{ $theme['body'] }} antialiased">
    <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 sm:py-16">
        <header class="text-center">
            <h1 class="text-3xl font-bold tracking-tight {{ $theme['heading'] }} sm:text-4xl">
                {{ $space->name }}
            </h1>

            @if ($space->title)
                <p class="mt-3 text-lg font-medium {{ $theme['heading'] }}">{{ $space->title }}</p>
            @endif

            @if ($space->subtitle)
                <p class="mt-2 {{ $theme['body'] }}">{{ $space->subtitle }}</p>
            @endif

            <p class="mt-4 text-sm {{ $theme['muted'] }}">
                {{ $testimonials->count() }} {{ Str::plural('testimonial', $testimonials->count()) }} on the wall.
            </p>
        </header>

        @if ($testimonials->isEmpty())
            {{-- Nothing has been put on the wall yet. --}}
            <div class="mt-10 rounded-2xl border border-dashed {{ $theme['border'] }} {{ $theme['surface'] }} px-6 py-12 text-center">
                <span class="mx-auto flex size-12 items-center justify-center rounded-full {{ $theme['quote'] }}">
                    <x-icon name="heart" class="size-6 {{ $theme['accent_text'] }}" />
                </span>

                <h2 class="mt-4 text-base font-semibold {{ $theme['heading'] }}">
                    No testimonials on the wall yet
                </h2>

                <p class="mx-auto mt-1 max-w-md text-sm {{ $theme['body'] }}">
                    The best testimonials will appear here soon.
                </p>
            </div>
        @else
            <div class="mt-10 grid gap-5 sm:grid-cols-2">
                @foreach ($testimonials as $testimonial)
                    @php
                        /**
                         * The social link is only rendered when its scheme is
                         * safe. A customer supplies this value, so an unsafe
                         * scheme is dropped rather than linked.
                         */
                        $socialUrl = $testimonial->safeSocialUrl();
                    @endphp

                    <figure class="flex h-full flex-col rounded-2xl border {{ $theme['border'] }} {{ $theme['surface'] }} p-6 shadow-sm">
                        {{-- The heart marks a testimonial the owner put on the wall. --}}
                        <span class="flex size-9 items-center justify-center rounded-full {{ $theme['quote'] }}">
                            <x-icon name="heart" class="size-5 {{ $theme['accent_text'] }}" />
                        </span>

                        <blockquote class="mt-4 flex-1">
                            <p class="text-sm leading-relaxed {{ $theme['body'] }}">
                                {{ $testimonial->body }}
                            </p>
                        </blockquote>

                        @if ($testimonial->rating !== null)
                            <div class="mt-4">
                                <span
                                    class="text-sm {{ $theme['star'] }}"
                                    role="img"
                                    aria-label="Rated {{ $testimonial->rating }} out of 5"
                                >
                                    @for ($star = 1; $star <= 5; $star++)
                                        <span aria-hidden="true">{{ $star <= $testimonial->rating ? '★' : '☆' }}</span>
                                    @endfor
                                </span>
                            </div>
                        @endif

                        <figcaption class="mt-5 border-t {{ $theme['border'] }} pt-4">
                            <p class="text-sm font-semibold {{ $theme['heading'] }}">
                                {{ $testimonial->name }}
                            </p>

                            @if ($testimonial->company_name)
                                <p class="mt-0.5 text-xs {{ $theme['muted'] }}">
                                    {{ $testimonial->company_name }}
                                </p>
                            @endif

                            @if ($socialUrl)
                                <a
                                    href="{{ $socialUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer nofollow"
                                    class="mt-2 inline-flex items-center gap-1 text-xs font-medium {{ $theme['accent_text'] }} hover:underline"
                                >
                                    View profile
                                    <x-icon name="external" class="size-3.5" />
                                </a>
                            @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif

        <p class="mt-10 text-center text-xs {{ $theme['muted'] }}">
            &copy; {{ date('Y') }} {{ config('app.name', 'Kudosly') }}. All rights reserved.
        </p>
    </div>
</body>
</html>
