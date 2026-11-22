@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus:ring-offset-slate-900';

    $variants = [
        'primary' => 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-500 focus:ring-indigo-500',
        'secondary' => 'bg-white text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:ring-slate-500 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700 dark:hover:bg-slate-700',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-500 focus:ring-red-500',
        'ghost' => 'text-slate-700 hover:bg-slate-100 focus:ring-slate-500 dark:text-slate-200 dark:hover:bg-slate-800',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
