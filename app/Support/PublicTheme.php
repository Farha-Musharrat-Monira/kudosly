<?php

namespace App\Support;

use App\Models\Space;

/**
 * The public page themes.
 *
 * Each theme is a set of Tailwind classes rather than a separate view, so
 * the live collection page and the theme preview in the dashboard stay in
 * step. The colours match the palettes used by the theme preview
 * component, so a space looks the same in the picker as it does on its
 * public page.
 */
class PublicTheme
{
    /**
     * The theme definitions.
     *
     * @var array<string, array<string, string>>
     */
    public const THEMES = [
        'minimal_light' => [
            'page' => 'bg-slate-50',
            'surface' => 'bg-white',
            'border' => 'border-slate-200',
            'heading' => 'text-slate-900',
            'body' => 'text-slate-600',
            'muted' => 'text-slate-500',
            'accent' => 'bg-indigo-600',
            'accent_hover' => 'hover:bg-indigo-500',
            'accent_text' => 'text-indigo-600',
            'accent_ring' => 'focus:ring-indigo-500',
            'field' => 'border-slate-300 bg-white text-slate-900',
            'star' => 'text-amber-400',
            'quote' => 'bg-slate-50',
        ],
        'modern_dark' => [
            'page' => 'bg-slate-950',
            'surface' => 'bg-slate-900',
            'border' => 'border-slate-800',
            'heading' => 'text-white',
            'body' => 'text-slate-300',
            'muted' => 'text-slate-400',
            'accent' => 'bg-indigo-500',
            'accent_hover' => 'hover:bg-indigo-400',
            'accent_text' => 'text-indigo-400',
            'accent_ring' => 'focus:ring-indigo-500',
            'field' => 'border-slate-700 bg-slate-950 text-white',
            'star' => 'text-amber-400',
            'quote' => 'bg-slate-950',
        ],
        'soft_gradient' => [
            'page' => 'bg-[#f5f3ff]',
            'surface' => 'bg-white',
            'border' => 'border-[#ddd6fe]',
            'heading' => 'text-[#4c1d95]',
            'body' => 'text-[#6d28d9]',
            'muted' => 'text-[#7c3aed]',
            'accent' => 'bg-[#8b5cf6]',
            'accent_hover' => 'hover:bg-[#7c3aed]',
            'accent_text' => 'text-[#8b5cf6]',
            'accent_ring' => 'focus:ring-[#8b5cf6]',
            'field' => 'border-[#ddd6fe] bg-white text-[#4c1d95]',
            'star' => 'text-amber-400',
            'quote' => 'bg-[#ede9fe]',
        ],
    ];

    /**
     * The theme definition for a theme key.
     *
     * An unknown key falls back to the default theme, so a space with an
     * unexpected theme value still renders a complete page.
     *
     * @return array<string, string>
     */
    public static function for(string $theme): array
    {
        return self::THEMES[$theme] ?? self::THEMES[Space::DEFAULT_THEME];
    }
}
