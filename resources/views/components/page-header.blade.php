@props([
    'title' => null,
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($title)
            <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h2>
        @endif

        @if ($description)
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
        @endif
    </div>

    @if (! $slot->isEmpty())
        <div class="flex shrink-0 items-center gap-3">
            {{ $slot }}
        </div>
    @endif
</div>
