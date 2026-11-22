@props([
    'theme' => 'minimal_light',
])

@php
    /**
     * The preview palettes. Each theme gets its own original drawing: a
     * stylised page frame with a headline, a quote card and a rating row.
     */
    $palettes = [
        'minimal_light' => [
            'page' => '#ffffff',
            'border' => '#e2e8f0',
            'heading' => '#0f172a',
            'body' => '#94a3b8',
            'accent' => '#6366f1',
            'chip' => '#f1f5f9',
        ],
        'modern_dark' => [
            'page' => '#0f172a',
            'border' => '#1e293b',
            'heading' => '#f8fafc',
            'body' => '#64748b',
            'accent' => '#818cf8',
            'chip' => '#1e293b',
        ],
        'soft_gradient' => [
            'page' => '#f5f3ff',
            'border' => '#ddd6fe',
            'heading' => '#4c1d95',
            'body' => '#a78bfa',
            'accent' => '#8b5cf6',
            'chip' => '#ede9fe',
        ],
    ];

    $palette = $palettes[$theme] ?? $palettes['minimal_light'];
@endphp

<svg
    viewBox="0 0 160 100"
    class="h-full w-full"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    role="img"
    aria-label="{{ \App\Models\Space::themeLabel($theme) }} theme preview"
>
    <rect x="0.5" y="0.5" width="159" height="99" rx="8" fill="{{ $palette['page'] }}" stroke="{{ $palette['border'] }}" />

    {{-- A headline and a sub-heading, so the shape of the page is readable. --}}
    <rect x="16" y="20" width="64" height="7" rx="3.5" fill="{{ $palette['heading'] }}" />
    <rect x="16" y="33" width="96" height="4" rx="2" fill="{{ $palette['body'] }}" />

    {{-- The quote card. --}}
    <rect x="16" y="48" width="128" height="30" rx="6" fill="{{ $palette['chip'] }}" />
    <rect x="24" y="57" width="72" height="4" rx="2" fill="{{ $palette['body'] }}" />
    <rect x="24" y="66" width="48" height="4" rx="2" fill="{{ $palette['body'] }}" />

    {{-- The rating row. --}}
    <g fill="{{ $palette['accent'] }}">
        <path d="M104 60l1.6 3.4 3.7.5-2.7 2.6.7 3.7-3.3-1.8-3.3 1.8.7-3.7-2.7-2.6 3.7-.5z" />
        <path d="M116 60l1.6 3.4 3.7.5-2.7 2.6.7 3.7-3.3-1.8-3.3 1.8.7-3.7-2.7-2.6 3.7-.5z" />
        <path d="M128 60l1.6 3.4 3.7.5-2.7 2.6.7 3.7-3.3-1.8-3.3 1.8.7-3.7-2.7-2.6 3.7-.5z" />
    </g>
</svg>
