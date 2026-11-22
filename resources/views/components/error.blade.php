@props([
    'messages' => null,
])

@php
    $messages = $messages !== null
        ? (array) $messages
        : (trim((string) $slot) !== '' ? [trim((string) $slot)] : []);
@endphp

@if (count($messages) > 0)
    <ul {{ $attributes->merge(['class' => 'space-y-1 text-sm text-red-600 dark:text-red-400']) }}>
        @foreach ($messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
