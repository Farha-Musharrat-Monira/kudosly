@props([
    'label' => null,
    'key' => null,
    'enabled' => false,
    'required' => false,
    'alwaysOn' => false,
    'first' => false,
    'last' => false,
])

<div
    @class([
        'flex flex-col gap-3 rounded-xl border px-4 py-3 transition sm:flex-row sm:items-center sm:gap-4',
        'border-indigo-200 bg-indigo-50/40 dark:border-indigo-900 dark:bg-indigo-950/20' => $enabled,
        'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' => ! $enabled,
    ])
>
    {{-- The ordering controls. --}}
    <div class="flex shrink-0 items-center gap-1 sm:flex-col">
        <button
            type="button"
            wire:click="moveFieldUp('{{ $key }}')"
            @disabled($first)
            aria-label="Move {{ $label }} up"
            class="inline-flex size-7 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
        >
            <x-icon name="chevron-up" class="size-4" />
        </button>

        <button
            type="button"
            wire:click="moveFieldDown('{{ $key }}')"
            @disabled($last)
            aria-label="Move {{ $label }} down"
            class="inline-flex size-7 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
        >
            <x-icon name="chevron-down" class="size-4" />
        </button>
    </div>

    {{-- The field name and its current state. --}}
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $label }}</p>

        @if ($alwaysOn)
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                Always collected and always required.
            </p>
        @else
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                {{ $enabled ? ($required ? 'Required' : 'Optional') : 'Not collected' }}
            </p>
        @endif
    </div>

    {{-- The enable switch. --}}
    <div class="flex shrink-0 items-center gap-2">
        <x-toggle-switch
            :checked="$enabled"
            :disabled="$alwaysOn"
            wire:model.live="enabledFields"
            :value="$key"
            :aria-label="'Collect '.$label"
        />
        <span class="w-16 text-xs text-slate-500 dark:text-slate-400">
            {{ $alwaysOn ? 'Always on' : 'Collect' }}
        </span>
    </div>

    {{-- The required switch, only for the optional fields. --}}
    <div class="flex shrink-0 items-center gap-2">
        @if (! $alwaysOn)
            <x-toggle-switch
                :checked="$required"
                :disabled="! $enabled"
                wire:model.live="requiredFields"
                :value="$key"
                :aria-label="'Require '.$label"
            />
            <span class="w-16 text-xs text-slate-500 dark:text-slate-400">Required</span>
        @else
            <x-badge variant="indigo">Required</x-badge>
        @endif
    </div>
</div>
