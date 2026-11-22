@props([
    'icon' => 'inbox',
    'title' => null,
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-700']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
        <x-icon :name="$icon" class="size-6" />
    </span>

    @if ($title)
        <h3 class="mt-4 text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
    @endif

    @if ($description)
        <p class="mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
    @endif

    @if (! $slot->isEmpty())
        <div class="mt-5">
            {{ $slot }}
        </div>
    @endif
</div>
