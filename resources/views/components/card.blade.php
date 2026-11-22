@props([
    'padding' => true,
])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900']) }}>
    <div @class(['p-6', 'p-0' => ! $padding])>
        {{ $slot }}
    </div>
</div>
