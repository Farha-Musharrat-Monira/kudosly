@php
    /**
     * @var string $title
     * @var string $icon
     * @var string $heading
     * @var string $description
     * @var string $comingSoon
     */
@endphp

<x-layouts.app :title="$title.' · '.config('app.name', 'Kudosly')" :header="$heading">
    <x-empty-state :icon="$icon" :title="$heading" :description="$description">
        <x-badge variant="warning">{{ $comingSoon }}</x-badge>
    </x-empty-state>
</x-layouts.app>
