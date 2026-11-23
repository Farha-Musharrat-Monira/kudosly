@php
    $urlId = 'space-detail-url-'.$space->id;
@endphp

<x-layouts.app :title="$space->name.' · '.config('app.name', 'Kudosly')" :header="$space->name">
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-button :href="route('spaces.index')" variant="ghost" class="!px-3 !py-2 text-xs">
            <x-icon name="arrow-left" class="size-4" />
            Back to spaces
        </x-button>

        <x-badge variant="{{ $space->is_published ? 'success' : 'neutral' }}">
            {{ $space->is_published ? 'Published' : 'Unpublished' }}
        </x-badge>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Space information</h3>

                <dl class="mt-5 space-y-4">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Name</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $space->name }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Headline</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $space->title ?: '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Sub-headline</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $space->subtitle ?: '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Testimonial request</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $space->testimonial_request ?: '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Ask for a star rating</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $space->collect_rating ? 'Yes' : 'No' }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Fields to collect</h3>

                <ul class="mt-5 space-y-2">
                    @forelse ($space->fields as $field)
                        <li
                            @class([
                                'flex items-center justify-between gap-3 rounded-xl border px-4 py-3 text-sm',
                                'border-indigo-200 bg-indigo-50/40 dark:border-indigo-900 dark:bg-indigo-950/20' => $field->enabled,
                                'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' => ! $field->enabled,
                            ])
                        >
                            <span class="min-w-0 truncate font-medium text-slate-900 dark:text-white">{{ $field->label }}</span>

                            @if ($field->isAlwaysOn())
                                <x-badge variant="indigo">Required</x-badge>
                            @elseif ($field->required)
                                <x-badge variant="success">Required</x-badge>
                            @elseif ($field->enabled)
                                <x-badge variant="neutral">Optional</x-badge>
                            @else
                                <x-badge variant="neutral">Not collected</x-badge>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-slate-500 dark:text-slate-400">
                            No fields are configured for this space yet.
                        </li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Collection page</h3>

                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <input
                        id="{{ $urlId }}"
                        type="text"
                        readonly
                        value="{{ $space->collectionUrl() }}"
                        class="w-full rounded-lg border-slate-300 bg-white px-3 py-2 font-mono text-xs text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                    >
                    <x-button type="button" data-copy-target="{{ $urlId }}" class="shrink-0">
                        Copy URL
                    </x-button>
                </div>

                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    @if ($space->is_published)
                        This space is published, so customers can leave a testimonial at this URL.
                    @else
                        This space is unpublished. Its collection page is not available until you publish it from the spaces list.
                    @endif
                </p>

                {{-- The collection page only answers for a published space, so the
                     link is offered only then. An unpublished space keeps its
                     copyable url above, but is not given a link that would 404. --}}
                @if ($space->is_published)
                    <a
                        href="{{ $space->collectionUrl() }}"
                        target="_blank"
                        rel="noopener"
                        class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200"
                    >
                        View page
                        <x-icon name="external" class="size-4" />
                    </a>
                @endif
            </x-card>

            <x-card>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Theme</h3>

                <span class="mt-4 block aspect-[16/10] w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950">
                    <x-theme-preview :theme="$space->theme" />
                </span>

                <p class="mt-3 text-sm text-slate-900 dark:text-white">
                    {{ \App\Models\Space::themeLabel($space->theme) }}
                </p>
            </x-card>

            <x-card>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Testimonials</h3>

                <p class="mt-3 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ $space->testimonials_count }}
                </p>

                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Collected on this space so far.
                </p>
            </x-card>

            <x-card>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Manage</h3>

                <x-button :href="route('spaces.index')" variant="secondary" class="mt-4 w-full">
                    <x-icon name="pencil" class="size-4" />
                    Edit this space
                </x-button>
            </x-card>
        </div>
    </div>
</x-layouts.app>
