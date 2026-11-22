@props([
    'label' => null,
    'value' => null,
    'icon' => 'chart',
    'hint' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900']) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="truncate text-sm font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $value }}</p>

            @if ($hint)
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
            @endif
        </div>

        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
            <x-icon :name="$icon" class="size-5" />
        </span>
    </div>
</div>
