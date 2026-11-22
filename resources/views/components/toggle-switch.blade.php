@props([
    'label' => null,
    'checked' => false,
    'disabled' => false,
    'name' => null,
    'value' => null,
])

<label
    @class([
        'inline-flex items-center gap-3',
        'cursor-pointer' => ! $disabled,
        'cursor-not-allowed opacity-60' => $disabled,
    ])
>
    <span class="relative inline-flex shrink-0 items-center">
        <input
            type="checkbox"
            @if ($name) name="{{ $name }}" @endif
            @if ($value !== null) value="{{ $value }}" @endif
            @checked($checked)
            @disabled($disabled)
            {{ $attributes->merge(['class' => 'peer sr-only']) }}
        >

        <span
            aria-hidden="true"
            class="pointer-events-none relative h-6 w-11 rounded-full bg-slate-300 transition-colors duration-200 peer-checked:bg-indigo-600 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 peer-focus-visible:ring-offset-2 dark:bg-slate-700 dark:peer-checked:bg-indigo-500 dark:peer-focus-visible:ring-offset-slate-900"
        >
            <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition-transform duration-200 peer-checked:translate-x-5"></span>
        </span>
    </span>

    @if ($label)
        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $label }}</span>
    @endif
</label>
