@props([
    'value' => 0,
    'max' => 100,
    'label' => null,
    'showCount' => false,
])

@php
    $max = (float) max(1, (float) $max);
    $percent = min(100, max(0, ((float) $value / $max) * 100));
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }}>
    @if ($label || $showCount)
        <div class="mb-1.5 flex items-center justify-between text-xs">
            @if ($label)
                <span class="font-medium text-slate-600 dark:text-slate-300">{{ $label }}</span>
            @else
                <span></span>
            @endif

            @if ($showCount)
                <span class="text-slate-500 dark:text-slate-400">{{ (int) $value }} / {{ (int) $max }}</span>
            @endif
        </div>
    @endif

    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
        <div class="h-full rounded-full bg-indigo-600 transition-all" style="width: {{ $percent }}%"></div>
    </div>
</div>
