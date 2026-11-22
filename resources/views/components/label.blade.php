@props([
    'for' => null,
])

<label @if ($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'block text-sm font-medium text-slate-700 dark:text-slate-300']) }}>
    {{ $slot }}
</label>
