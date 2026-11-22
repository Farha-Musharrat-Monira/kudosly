@props([
    'label' => null,
    'error' => null,
    'hint' => null,
])

<div {{ $attributes->only('class')->merge(['space-y-1.5']) }}>
    @if ($label)
        <x-label>{{ $label }}</x-label>
    @endif

    <input
        {{ $attributes->except('class')->merge([
            'class' => 'block w-full rounded-lg border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500 sm:text-sm',
        ]) }}
        @if ($error) aria-invalid="true" @endif
    />

    @if ($hint)
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif

    @if ($error)
        <x-error>{{ $error }}</x-error>
    @endif
</div>
