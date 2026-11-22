@props([
    'label' => null,
    'used' => 0,
    'limit' => 0,
    'unit' => null,
    'hint' => null,
])

@php
    $used = (int) $used;
    $limit = (int) $limit;
    $remaining = max(0, $limit - $used);
    $percent = $limit > 0 ? min(100, round(($used / $limit) * 100)) : 0;

    // The bar changes colour as the limit is approached, so a nearly full
    // limit is noticeable without having to read the numbers.
    $barClass = match (true) {
        $percent >= 90 => 'bg-red-600',
        $percent >= 75 => 'bg-amber-500',
        default => 'bg-indigo-600',
    };
@endphp

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $label }}</span>

        <span class="text-xs text-slate-500 dark:text-slate-400">
            {{-- The counter is built as one string so the numbers and the
                 separator are not broken up by markup between them. --}}
            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $used.' / '.$limit }}</span>
            @if ($unit)
                <span class="sr-only">{{ $unit }}</span>
            @endif
        </span>
    </div>

    <div
        class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800"
        role="progressbar"
        aria-valuenow="{{ $used }}"
        aria-valuemin="0"
        aria-valuemax="{{ $limit }}"
        aria-label="{{ $label }}"
    >
        <div class="h-full rounded-full transition-all {{ $barClass }}" style="width: {{ $percent }}%"></div>
    </div>

    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
        @if ($hint)
            {{ $hint }}
        @else
            {{ $remaining }} remaining
        @endif
    </p>
</div>
