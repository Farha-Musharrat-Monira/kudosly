@props([
    'href' => null,
])

@if ($href)
    <a href="{{ $href }}" class="inline-flex items-center gap-2.5">
        <span class="flex size-9 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm">
            <x-icon name="sparkles" class="size-5" />
        </span>
        <span class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Kudosly</span>
    </a>
@else
    <span class="inline-flex items-center gap-2.5">
        <span class="flex size-9 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm">
            <x-icon name="sparkles" class="size-5" />
        </span>
        <span class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Kudosly</span>
    </span>
@endif
