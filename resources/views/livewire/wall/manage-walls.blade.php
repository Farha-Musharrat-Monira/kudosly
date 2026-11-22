@php
    /**
     * The owner's Wall of Love management page.
     *
     * A wall belongs to a space, so the page lists the owner's spaces and
     * lets them choose what goes on each space's public wall. A customer's
     * email address is never rendered: it is private data and has no place
     * on a wall.
     *
     * @var \App\Livewire\Wall\ManageWalls $this
     */
    $spaces = $this->spaces;
    $managingSpace = $this->managingSpace;
    $pickerTestimonials = $this->testimonials;
@endphp

<div>
    @if ($notice)
        <div
            class="mb-6 flex items-start gap-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-300"
            role="status"
            aria-live="polite"
        >
            <x-icon name="check" class="size-5 shrink-0" />
            <p>{{ $notice }}</p>
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Wall of Love</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Share your best testimonials on a page you can send to anyone.
            </p>
        </div>

        <x-badge variant="neutral">
            {{ $spaces->count() }} {{ Str::plural('space', $spaces->count()) }}
        </x-badge>
    </div>

    @if ($spaces->isEmpty())
        <x-empty-state
            icon="heart"
            title="No spaces yet"
            description="Create a space first, then heart the testimonials you want to show off and share its wall."
        >
            <x-button :href="route('spaces.index')" variant="secondary">
                <x-icon name="layers" class="size-4" />
                Go to Spaces
            </x-button>
        </x-empty-state>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($spaces as $space)
                @php
                    $urlId = 'wall-url-'.$space->id;
                @endphp

                <x-card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1 basis-40">
                            <h3 class="truncate text-base font-semibold text-slate-900 dark:text-white">
                                {{ $space->name }}
                            </h3>
                            <code id="{{ $urlId }}" class="mt-0.5 block truncate font-mono text-xs text-slate-500 dark:text-slate-400">
                                {{ $space->wallUrl() }}
                            </code>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <x-badge variant="{{ $space->is_published ? 'success' : 'neutral' }}">
                                {{ $space->is_published ? 'Published' : 'Unpublished' }}
                            </x-badge>

                            <x-toggle-switch
                                :checked="$space->is_published"
                                :value="$space->id"
                                wire:change="togglePublish({{ $space->id }})"
                                :aria-label="'Publish '.$space->name.' wall'"
                            />
                        </div>
                    </div>

                    <dl class="mt-4 flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                        <div class="flex items-center gap-1.5">
                            <x-icon name="heart" class="size-4" />
                            {{ $space->wall_testimonials_count }} on the wall
                        </div>

                        <div class="flex items-center gap-1.5">
                            <x-icon name="check" class="size-4" />
                            {{ $space->eligible_testimonials_count }} eligible
                        </div>
                    </dl>

                    <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <x-button type="button" variant="secondary" wire:click="manage({{ $space->id }})" class="!px-3 !py-2 text-xs">
                            <x-icon name="pencil" class="size-4" />
                            Choose testimonials
                        </x-button>

                        <button
                            type="button"
                            data-copy-target="{{ $urlId }}"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        >
                            <x-icon name="copy" class="size-4" />
                            Copy link
                        </button>

                        <a
                            href="{{ $space->wallUrl() }}"
                            target="_blank"
                            rel="noopener"
                            class="ml-auto inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200"
                        >
                            View wall
                            <x-icon name="external" class="size-4" />
                        </a>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    @if ($managingSpace)
        <x-card class="mt-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                        Choose what goes on this wall
                    </h3>

                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ $managingSpace->name }} · shared at
                        <span class="font-mono text-xs">{{ $managingSpace->wallUrl() }}</span>
                    </p>
                </div>

                <x-button type="button" variant="ghost" wire:click="closePicker" class="!px-3 !py-2 text-xs">
                    Close
                </x-button>
            </div>

            <div class="mt-5 max-w-xs">
                <x-label for="wall-status">Filter by status</x-label>
                <select
                    id="wall-status"
                    wire:model.live="status"
                    class="mt-1.5 block w-full rounded-lg border-slate-300 bg-white text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white sm:text-sm"
                >
                    <option value="">All statuses</option>
                    @foreach (\App\Livewire\Wall\ManageWalls::statusOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($pickerTestimonials->isEmpty())
                <x-empty-state
                    icon="inbox"
                    title="{{ $status !== '' ? 'No '.\App\Livewire\Wall\ManageWalls::statusOptions()[$status].' testimonials' : 'Nothing is on this wall yet' }}"
                    description="{{ $status !== '' ? 'This space has no testimonials with that status. Try another filter.' : 'This space has no testimonials yet. Once a customer leaves one you can put it on the wall here.' }}"
                    class="mt-5"
                >
                    @if ($status !== '')
                        <x-button type="button" variant="secondary" wire:click="$set('status', '')">
                            <x-icon name="x" class="size-4" />
                            Clear filter
                        </x-button>
                    @else
                        <x-button :href="route('inbox')" variant="secondary">
                            <x-icon name="inbox" class="size-4" />
                            Go to Inbox
                        </x-button>
                    @endif
                </x-empty-state>
            @else
                <ul class="mt-5 space-y-3">
                    @foreach ($pickerTestimonials as $testimonial)
                        @php
                            /**
                             * A testimonial is eligible for the wall when it
                             * may appear publicly. The rule lives on the
                             * model so the picker and the public wall can
                             * never disagree.
                             */
                            $isEligible = $testimonial->isPubliclyVisible();
                            $isOnWall = (bool) $testimonial->is_heart;

                            /**
                             * A testimonial that was hearted and then hidden
                             * is off the public wall, but the heart is still
                             * set. The owner must be able to see it and take
                             * the heart off, so it gets its own state.
                             */
                            $isHiddenOnWall = $isOnWall && ! $isEligible;
                        @endphp

                        <li
                            @class([
                                'flex flex-col gap-3 rounded-xl border px-4 py-4 sm:flex-row sm:items-start sm:justify-between',
                                'border-emerald-200 bg-emerald-50/40 dark:border-emerald-900 dark:bg-emerald-950/20' => $isOnWall && $isEligible,
                                'border-amber-200 bg-amber-50/40 dark:border-amber-900 dark:bg-amber-950/20' => $isHiddenOnWall,
                                'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' => ! $isOnWall,
                            ])
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ $testimonial->name }}
                                    </p>

                                    @if ($isOnWall && $isEligible)
                                        <x-badge variant="success">On your wall</x-badge>
                                    @elseif ($isHiddenOnWall)
                                        <x-badge variant="warning">Hidden from the wall</x-badge>
                                    @elseif ($isEligible)
                                        <x-badge variant="indigo">Eligible</x-badge>
                                    @else
                                        <x-badge variant="neutral">Not eligible</x-badge>
                                    @endif
                                </div>

                                <p class="mt-1 line-clamp-2 text-sm text-slate-600 dark:text-slate-400">
                                    {{ $testimonial->body }}
                                </p>

                                <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-400 dark:text-slate-500">
                                    @if ($testimonial->rating !== null)
                                        <span aria-label="Rated {{ $testimonial->rating }} out of 5">
                                            {{ $testimonial->rating }} of 5
                                        </span>
                                    @endif

                                    <span>{{ ucfirst($testimonial->status) }}</span>

                                    @if (! $testimonial->consent_to_share)
                                        <span>No sharing consent</span>
                                    @endif
                                </p>

                                @if (! $isEligible && ! $isOnWall)
                                    <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                                        Only an approved testimonial with consent can go on a wall.
                                    </p>
                                @endif

                                @if ($isHiddenOnWall)
                                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">
                                        This testimonial is hearted but hidden, so it does not appear on the public wall.
                                    </p>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                @if ($isOnWall)
                                    <x-button
                                        type="button"
                                        variant="ghost"
                                        wire:click="unheart({{ $testimonial->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="unheart({{ $testimonial->id }})"
                                        class="!px-3 !py-2 text-xs"
                                    >
                                        <x-icon name="trash" class="size-4" />
                                        Remove
                                    </x-button>
                                @else
                                    <x-button
                                        type="button"
                                        variant="secondary"
                                        wire:click="heart({{ $testimonial->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="heart({{ $testimonial->id }})"
                                        :disabled="! $isEligible"
                                        class="!px-3 !py-2 text-xs"
                                    >
                                        <x-icon name="heart" class="size-4" />
                                        Add to wall
                                    </x-button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @endif
</div>
