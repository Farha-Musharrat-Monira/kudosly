@php
    /**
     * The moderation table for the testimonials a signed-in owner has received.
     *
     * Every testimonial shown here belongs to the signed-in account, so the
     * customer's email address and profile photo are visible to the owner
     * only. Neither is ever published.
     *
     * @var \App\Livewire\Inbox\ManageTestimonials $this
     */
    $testimonials = $this->testimonials;
    $spaces = $this->spaces;

    $total = $testimonials->total();
    $hasFilters = $this->search !== '' || $this->status !== '' || $this->spaceId !== null;
@endphp

<div>
    {{--
        The live region announces the result count and the latest action so
        a screen reader user learns what happened without moving focus.
    --}}
    <div role="status" aria-live="polite" class="sr-only">
        {{ $total }} {{ Str::plural('testimonial', $total) }} found.
        {{ $this->notice }}
    </div>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Inbox</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Review, approve and organise the testimonials your customers have sent.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <x-badge variant="neutral">
                {{ $total }} {{ Str::plural('testimonial', $total) }}
            </x-badge>
        </div>
    </div>

    {{-- Filters --}}
    <x-card class="mb-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2 lg:col-span-1">
                <x-label for="inbox-search">Search</x-label>
                <input
                    id="inbox-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Name, email or testimonial"
                    aria-describedby="inbox-search-hint"
                    class="mt-1.5 block w-full rounded-lg border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500 sm:text-sm"
                >
                <p id="inbox-search-hint" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Searches the name, the email address and the testimonial.
                </p>
            </div>

            <div>
                <x-label for="inbox-status">Status</x-label>
                <select
                    id="inbox-status"
                    wire:model.live="status"
                    class="mt-1.5 block w-full rounded-lg border-slate-300 bg-white text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white sm:text-sm"
                >
                    <option value="">All statuses</option>
                    <option value="{{ \App\Models\Testimonial::STATUS_PENDING }}">Pending</option>
                    <option value="{{ \App\Models\Testimonial::STATUS_APPROVED }}">Approved</option>
                    <option value="{{ \App\Models\Testimonial::STATUS_HIDDEN }}">Hidden</option>
                </select>
            </div>

            <div>
                <x-label for="inbox-space">Space</x-label>
                <select
                    id="inbox-space"
                    wire:model.live="spaceId"
                    class="mt-1.5 block w-full rounded-lg border-slate-300 bg-white text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white sm:text-sm"
                >
                    <option value="">All spaces</option>
                    @foreach ($spaces as $space)
                        <option value="{{ $space->id }}">{{ $space->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                @if ($hasFilters)
                    <x-button type="button" variant="ghost" wire:click="clearFilters" class="w-full">
                        <x-icon name="x" class="size-4" />
                        Clear filters
                    </x-button>
                @endif
            </div>
        </div>
    </x-card>

    @if ($testimonials->isEmpty())
        @if ($hasFilters)
            {{-- Distinct state: the owner has testimonials, but none match. --}}
            <x-empty-state
                icon="alert"
                title="No testimonials match your filters"
                description="Try a different search term, or clear the filters to see every testimonial."
            >
                <x-button type="button" variant="secondary" wire:click="clearFilters">
                    <x-icon name="x" class="size-4" />
                    Clear filters
                </x-button>
            </x-empty-state>
        @else
            {{-- Distinct state: the owner has received no testimonials at all. --}}
            <x-empty-state
                icon="inbox"
                title="No testimonials yet"
                description="Once a customer submits a testimonial to one of your spaces, it will appear here for review."
            >
                <x-button :href="route('spaces.index')" variant="secondary">
                    <x-icon name="layers" class="size-4" />
                    Go to Spaces
                </x-button>
            </x-empty-state>
        @endif
    @else
        <div class="space-y-4">
            @foreach ($testimonials as $testimonial)
                @php
                    $spaceName = $spaces->firstWhere('id', $testimonial->space_id)?->name ?? 'Unknown space';

                    $statusVariant = match ($testimonial->status) {
                        \App\Models\Testimonial::STATUS_APPROVED => 'success',
                        \App\Models\Testimonial::STATUS_HIDDEN => 'neutral',
                        default => 'warning',
                    };

                    $statusLabel = match ($testimonial->status) {
                        \App\Models\Testimonial::STATUS_APPROVED => 'Approved',
                        \App\Models\Testimonial::STATUS_HIDDEN => 'Hidden',
                        default => 'Pending',
                    };

                    $canHeart = $testimonial->isPubliclyVisible();

                    $initial = Str::upper(Str::substr($testimonial->name, 0, 1));
                @endphp

                <x-card>
                    <div class="flex flex-col gap-5 sm:flex-row">
                        {{-- Avatar --}}
                        <div class="shrink-0">
                            @if ($testimonial->hasPhoto())
                                <img
                                    src="{{ route('inbox.testimonials.photo', $testimonial->id) }}"
                                    alt=""
                                    width="48"
                                    height="48"
                                    loading="lazy"
                                    class="size-12 rounded-full object-cover ring-1 ring-slate-200 dark:ring-slate-700"
                                >
                            @else
                                <span
                                    aria-hidden="true"
                                    class="flex size-12 items-center justify-center rounded-full bg-indigo-100 text-base font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                                >{{ $initial }}</span>
                            @endif
                        </div>

                        {{-- Content --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                                    {{ $testimonial->name }}
                                </h3>

                                <x-badge variant="{{ $statusVariant }}">{{ $statusLabel }}</x-badge>

                                @if ($testimonial->is_favorite)
                                    <x-badge variant="indigo">Favourite</x-badge>
                                @endif

                                @if ($testimonial->is_heart)
                                    <x-badge variant="danger">On your wall</x-badge>
                                @endif
                            </div>

                            {{--
                                The email address is owner-only information. It is
                                shown here in the Inbox and is never published.
                            --}}
                            <p class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400">
                                {{ $testimonial->email }}
                                <span class="text-slate-400 dark:text-slate-600">·</span>
                                {{ $spaceName }}
                            </p>

                            <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                                {{ $testimonial->body }}
                            </p>

                            {{-- Rating --}}
                            <div class="mt-3 flex items-center gap-2">
                                @if ($testimonial->rating !== null)
                                    <span
                                        class="text-sm text-amber-500"
                                        role="img"
                                        aria-label="Rated {{ $testimonial->rating }} out of 5"
                                    >
                                        @for ($star = 1; $star <= 5; $star++)
                                            <span aria-hidden="true">{{ $star <= $testimonial->rating ? '★' : '☆' }}</span>
                                        @endfor
                                    </span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $testimonial->rating }} of 5
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400 dark:text-slate-500">No rating</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
                        @if ($testimonial->status === \App\Models\Testimonial::STATUS_PENDING)
                            <x-button type="button" variant="primary" wire:click="approve({{ $testimonial->id }})" class="!px-3 !py-2 text-xs">
                                <x-icon name="check" class="size-4" />
                                Approve
                            </x-button>
                        @elseif ($testimonial->status === \App\Models\Testimonial::STATUS_APPROVED)
                            <x-button type="button" variant="secondary" wire:click="hide({{ $testimonial->id }})" class="!px-3 !py-2 text-xs">
                                <x-icon name="eye-off" class="size-4" />
                                Hide
                            </x-button>
                        @else
                            <x-button type="button" variant="secondary" wire:click="unhide({{ $testimonial->id }})" class="!px-3 !py-2 text-xs">
                                <x-icon name="check" class="size-4" />
                                Restore
                            </x-button>
                        @endif

                        {{-- Favourite --}}
                        <button
                            type="button"
                            wire:click="{{ $testimonial->is_favorite ? 'unfavorite' : 'favorite' }}({{ $testimonial->id }})"
                            aria-pressed="{{ $testimonial->is_favorite ? 'true' : 'false' }}"
                            aria-label="{{ $testimonial->is_favorite ? 'Remove ' : 'Favourite ' }}{{ $testimonial->name }}{{ $testimonial->is_favorite ? ' from favourites' : '' }}"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-indigo-500 {{ $testimonial->is_favorite ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}"
                        >
                            <x-icon name="star" class="size-4" />
                            {{ $testimonial->is_favorite ? 'Favourited' : 'Favourite' }}
                        </button>

                        {{--
                            Wall of Love heart. Only a testimonial that may
                            appear publicly can carry a heart, so the button is
                            disabled otherwise and the reason is exposed to
                            assistive technology.
                        --}}
                        <button
                            type="button"
                            wire:click="{{ $testimonial->is_heart ? 'unheart' : 'heart' }}({{ $testimonial->id }})"
                            @disabled(! $canHeart)
                            aria-disabled="{{ $canHeart ? 'false' : 'true' }}"
                            aria-pressed="{{ $testimonial->is_heart ? 'true' : 'false' }}"
                            @if (! $canHeart) aria-describedby="heart-reason-{{ $testimonial->id }}" @endif
                            aria-label="{{ $testimonial->is_heart ? 'Remove heart from ' : 'Add to your wall: ' }}{{ $testimonial->name }}"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-60 {{ $testimonial->is_heart ? 'text-red-600 dark:text-red-400' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}"
                        >
                            <x-icon name="heart" class="size-4" />
                            {{ $testimonial->is_heart ? 'On your wall' : 'Add to wall' }}
                        </button>

                        @if (! $canHeart)
                            <p id="heart-reason-{{ $testimonial->id }}" class="w-full text-xs text-slate-400 dark:text-slate-500 sm:w-auto">
                                Only an approved testimonial with consent can be hearted.
                            </p>
                        @endif

                        <x-button type="button" variant="ghost" wire:click="confirmDelete({{ $testimonial->id }})" class="ml-auto !px-3 !py-2 text-xs">
                            <x-icon name="trash" class="size-4" />
                            Delete
                        </x-button>
                    </div>
                </x-card>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">
                Showing {{ $testimonials->firstItem() ?? 0 }} to {{ $testimonials->lastItem() ?? 0 }} of {{ $total }}
            </p>

            {{ $testimonials->links() }}
        </div>
    @endif

    {{-- Delete confirmation --}}
    <x-modal name="delete-testimonial" title="Delete this testimonial?">
        @php
            $deleting = $testimonials->firstWhere('id', $this->deletingTestimonialId);
        @endphp

        <p class="text-sm text-slate-600 dark:text-slate-400">
            <span class="font-semibold text-slate-900 dark:text-white">{{ $deleting?->name ?? 'This testimonial' }}</span>
            will be permanently deleted, along with its private photo. This cannot be undone.
        </p>

        <div class="mt-6 flex justify-end gap-3">
            <x-button type="button" variant="ghost" wire:click="cancelDelete" data-modal-close="delete-testimonial">
                Cancel
            </x-button>

            {{--
                The confirm button always carries an id, so the attribute is
                always well formed. When no testimonial is pending, the id is
                0, which matches no row, and the button is disabled anyway.

                The disabled state is applied through the attribute bag rather
                than a @disabled directive inside the tag, because Blade emits
                any directive placed in a component's attribute list as raw
                text and the template would not compile.
            --}}
            <x-button
                type="button"
                variant="danger"
                wire:click="delete({{ $this->deletingTestimonialId ?? 0 }})"
                :disabled="! $this->deletingTestimonialId"
                wire:loading.attr="disabled"
            >
                Delete testimonial
            </x-button>
        </div>
    </x-modal>
</div>
