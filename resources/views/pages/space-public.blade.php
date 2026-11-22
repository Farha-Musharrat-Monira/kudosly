@php
    $theme = \App\Support\PublicTheme::for($space->theme);
    $old = old();
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
    <div class="mx-auto w-full max-w-2xl px-4 py-10 sm:px-6 sm:py-16">
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
        </header>

        @if ($space->testimonial_request)
            <p class="mx-auto mt-6 max-w-xl text-center text-base leading-relaxed {{ $theme['body'] }}">
                {{ $space->testimonial_request }}
            </p>
        @endif

        <div class="mt-10 rounded-2xl border {{ $theme['border'] }} {{ $theme['surface'] }} p-6 shadow-sm sm:p-8">
            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-red-300 bg-red-50 p-4" role="alert">
                    <p class="text-sm font-semibold text-red-800">Please fix the following before sending:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('spaces.public.submit', $space->slug) }}" class="space-y-6" novalidate>
                @csrf

                {{--
                    The honeypot is hidden from customers and must stay empty.
                    A filled field means a bot, so the submission is discarded
                    quietly rather than reported back to the sender.
                --}}
                <div class="absolute left-[-9999px] top-auto h-px w-px overflow-hidden" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="{{ old('website') }}">
                </div>

                @foreach ($fields as $field)
                    @if ($field->enabled)
                        @include('partials.public-field', ['field' => $field, 'theme' => $theme])
                    @endif
                @endforeach

                @if ($space->collect_rating)
                    <div>
                        <fieldset>
                            <legend class="block text-sm font-medium {{ $theme['heading'] }}">
                                Rating
                                <span class="text-red-500" aria-hidden="true">*</span>
                            </legend>

                            <div class="mt-2 flex items-center gap-1">
                                @for ($star = 1; $star <= 5; $star++)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="rating" value="{{ $star }}"
                                            class="peer sr-only"
                                            @checked((int) old('rating') === $star)>
                                        <span class="block text-3xl leading-none {{ $theme['star'] }} peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-indigo-500 peer-checked:scale-110 transition"
                                            aria-hidden="true">&#9733;</span>
                                        <span class="sr-only">{{ $star }} {{ $star === 1 ? 'star' : 'stars' }}</span>
                                    </label>
                                @endfor
                            </div>

                            @error('rating')
                                <x-error class="mt-2">{{ $message }}</x-error>
                            @enderror
                        </fieldset>
                    </div>
                @endif

                <div>
                    <label for="body" class="block text-sm font-medium {{ $theme['heading'] }}">
                        Testimonial
                        <span class="text-red-500" aria-hidden="true">*</span>
                    </label>

                    <textarea id="body" name="body" rows="6" required
                        placeholder="Tell us what you think..."
                        class="mt-1.5 block w-full rounded-lg border {{ $theme['field'] }} shadow-sm placeholder:text-slate-400 focus:ring-2 sm:text-sm {{ $theme['accent_ring'] }}">{{ old('body') }}</textarea>

                    @error('body')
                        <x-error class="mt-2">{{ $message }}</x-error>
                    @enderror
                </div>

                <div class="flex items-start gap-3">
                    <input type="hidden" name="consent_to_share" value="0">
                    <input type="checkbox" id="consent_to_share" name="consent_to_share" value="1"
                        class="mt-0.5 size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        @checked(old('consent_to_share') === '1')>
                    <label for="consent_to_share" class="text-sm {{ $theme['body'] }}">
                        I give permission to publish my testimonial.
                    </label>
                </div>

                @error('consent_to_share')
                    <x-error>{{ $message }}</x-error>
                @enderror

                <button type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 {{ $theme['accent'] }} {{ $theme['accent_hover'] }} {{ $theme['accent_ring'] }}">
                    Send my testimonial
                </button>
            </form>
        </div>

        <p class="mt-8 text-center text-xs {{ $theme['muted'] }}">
            &copy; {{ date('Y') }} {{ config('app.name', 'Kudosly') }}. All rights reserved.
        </p>
    </div>
</body>
</html>
