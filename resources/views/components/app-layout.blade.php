@props([
    'title' => null,
    'header' => null,
])

<x-layouts.app :title="$title" :header="$header">
    {{ $slot }}
</x-layouts.app>
