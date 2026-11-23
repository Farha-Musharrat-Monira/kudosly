<div>
    @if ($createdSlug)
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950/40">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white">
                    <x-icon name="check" class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-200">
                        {{ $savedAsNew ? 'Your space is ready' : 'Space updated' }}
                    </p>
                    <p class="mt-0.5 text-sm text-emerald-700 dark:text-emerald-300">
                        @if ($savedAsNew)
                            Share this URL to start collecting testimonials.
                        @else
                            Your changes are saved. The public URL stays the same.
                        @endif
                    </p>

                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <input
                            id="created-space-url"
                            type="text"
                            readonly
                            value="{{ url('/s/'.$createdSlug.'/submit') }}"
                            class="w-full rounded-lg border-slate-300 bg-white px-3 py-2 font-mono text-xs text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                        >
                        <x-button type="button" data-copy-target="created-space-url" class="shrink-0">
                            Copy URL
                        </x-button>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3">
                        @if ($savedAsNew)
                            <x-button type="button" wire:click="create" variant="secondary">
                                Create another space
                            </x-button>
                        @else
                            <x-button type="button" wire:click="edit({{ $savedSpaceId }})" variant="secondary">
                                Edit again
                            </x-button>
                        @endif

                        <x-button :href="route('dashboard')" variant="ghost">
                            Go to dashboard
                        </x-button>

                        <x-button type="button" wire:click="dismissSuccess" variant="ghost">
                            Dismiss
                        </x-button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Spaces</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Each space has its own public page for collecting testimonials.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <span class="text-sm text-slate-500 dark:text-slate-400">
                {{ auth()->user()->spacesUsed() }} / {{ auth()->user()->spaceQuota() }} used
            </span>

            <x-button type="button" wire:click="create" :disabled="$this->atSpaceLimit">
                <x-icon name="plus" class="size-4" />
                New space
            </x-button>
        </div>
    </div>

    @if ($this->atSpaceLimit)
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
            <x-icon name="alert" class="size-5 shrink-0" />
            <p>
                You have reached the space limit for your plan. Upgrade to Pro to create more spaces.
            </p>
        </div>
    @endif

    @if ($showForm)
        <x-card class="mb-6">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                {{ $editingSpaceId ? 'Edit space' : 'Create a space' }}
            </h3>

            <form wire:submit="save" class="mt-5 space-y-6">
                <div>
                    <x-label for="space_name">Name</x-label>
                    <x-input
                        id="space_name"
                        type="text"
                        name="name"
                        wire:model="name"
                        required
                        autofocus
                        class="mt-1.5"
                        :error="$errors->first('name')"
                        hint="Used to identify the space in your dashboard."
                    />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-label for="space_title">Headline</x-label>
                        <x-input
                            id="space_title"
                            type="text"
                            name="title"
                            wire:model="title"
                            class="mt-1.5"
                            :error="$errors->first('title')"
                            hint="Shown at the top of the public page."
                        />
                    </div>

                    <div>
                        <x-label for="space_subtitle">Sub-headline</x-label>
                        <x-input
                            id="space_subtitle"
                            type="text"
                            name="subtitle"
                            wire:model="subtitle"
                            class="mt-1.5"
                            :error="$errors->first('subtitle')"
                        />
                    </div>
                </div>

                <div>
                    <x-label for="space_request">Testimonial request</x-label>
                    <textarea
                        id="space_request"
                        name="testimonial_request"
                        wire:model="testimonial_request"
                        rows="3"
                        class="mt-1.5 block w-full rounded-lg border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white sm:text-sm"
                    ></textarea>
                    @error('testimonial_request')
                        <x-error>{{ $message }}</x-error>
                    @enderror
                </div>

                <fieldset>
                    <legend class="text-sm font-medium text-slate-700 dark:text-slate-300">Theme</legend>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Choose how the public page looks. You can change this at any time.
                    </p>

                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        @foreach (\App\Models\Space::THEMES as $key => $label)
                            <label
                                @class([
                                    'group relative cursor-pointer overflow-hidden rounded-xl border-2 transition',
                                    'border-indigo-500 ring-1 ring-indigo-500' => $theme === $key,
                                    'border-slate-200 hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600' => $theme !== $key,
                                ])
                            >
                                <input
                                    type="radio"
                                    name="theme"
                                    value="{{ $key }}"
                                    wire:model.live="theme"
                                    class="peer sr-only"
                                >

                                <span class="block aspect-[16/10] w-full border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950">
                                    <x-theme-preview :theme="$key" />
                                </span>

                                <span class="flex items-center justify-between gap-2 px-3 py-2.5">
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $label }}</span>

                                    <span
                                        @class([
                                            'flex size-5 items-center justify-center rounded-full border transition',
                                            'border-indigo-600 bg-indigo-600 text-white' => $theme === $key,
                                            'border-slate-300 text-transparent dark:border-slate-600' => $theme !== $key,
                                        ])
                                        aria-hidden="true"
                                    >
                                        <x-icon name="check" class="size-3" />
                                    </span>
                                </span>

                                <span class="pointer-events-none absolute inset-0 rounded-xl ring-2 ring-transparent peer-focus-visible:ring-indigo-500"></span>
                            </label>
                        @endforeach
                    </div>

                    @error('theme')
                        <x-error class="mt-2">{{ $message }}</x-error>
                    @enderror
                </fieldset>

                <div>
                    <label class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            name="collect_rating"
                            wire:model="collect_rating"
                            class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700"
                        >
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Ask for a star rating</span>
                    </label>
                </div>

                <fieldset>
                    <legend class="text-sm font-medium text-slate-700 dark:text-slate-300">Fields to collect</legend>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Name and email are always collected and always required. Use the arrows to change the order the fields appear in.
                    </p>

                    @php
                        $orderedKeys = $this->orderedFieldKeys;
                    @endphp

                    <div class="mt-3 space-y-2">
                        @foreach ($orderedKeys as $index => $key)
                            @php
                                $config = \App\Models\SpaceField::KEYS[$key];
                                $alwaysOn = in_array($key, \App\Models\SpaceField::ALWAYS_ON, true);
                                $isEnabled = $alwaysOn || in_array($key, $enabledFields, true);
                                $isRequired = $alwaysOn || in_array($key, $requiredFields, true);
                            @endphp

                            <x-field-row
                                :key="$key"
                                :label="$config['label']"
                                :enabled="$isEnabled"
                                :required="$isRequired"
                                :always-on="$alwaysOn"
                                :first="$index === 0"
                                :last="$index === count($orderedKeys) - 1"
                            />
                        @endforeach
                    </div>

                    @error('enabledFields')
                        <x-error class="mt-2">{{ $message }}</x-error>
                    @enderror
                    @error('enabledFields.*')
                        <x-error class="mt-2">{{ $message }}</x-error>
                    @enderror
                    @error('requiredFields')
                        <x-error class="mt-2">{{ $message }}</x-error>
                    @enderror
                    @error('requiredFields.*')
                        <x-error class="mt-2">{{ $message }}</x-error>
                    @enderror
                    @error('fieldOrder')
                        <x-error class="mt-2">{{ $message }}</x-error>
                    @enderror
                    @error('fieldOrder.*')
                        <x-error class="mt-2">{{ $message }}</x-error>
                    @enderror
                </fieldset>

                <div class="flex items-center gap-3 border-t border-slate-200 pt-5 dark:border-slate-800">
                    <x-button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $editingSpaceId ? 'Save changes' : 'Create space' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </x-button>

                    <x-button type="button" variant="ghost" wire:click="cancel">
                        Cancel
                    </x-button>
                </div>
            </form>
        </x-card>
    @endif

    @php
        $spaceList = $this->spaces;
    @endphp

    @if ($spaceList->isEmpty())
        <x-empty-state
            icon="layers"
            title="No spaces yet"
            description="Create your first space to start collecting testimonials."
        >
            <x-button type="button" wire:click="create" :disabled="$this->atSpaceLimit">
                <x-icon name="plus" class="size-4" />
                Create your first space
            </x-button>
        </x-empty-state>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($spaceList as $space)
                @php
                    $urlId = 'space-url-'.$space->id;
                @endphp

                <x-card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-semibold text-slate-900 dark:text-white">
                                <a
                                    href="{{ route('spaces.show', $space->id) }}"
                                    class="hover:text-indigo-600 hover:underline dark:hover:text-indigo-400"
                                >
                                    {{ $space->name }}
                                </a>
                            </h3>
                            <code id="{{ $urlId }}" class="mt-0.5 block truncate font-mono text-xs text-slate-500 dark:text-slate-400">
                                {{ $space->collectionUrl() }}
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
                                :aria-label="'Publish '.$space->name"
                            />
                        </div>
                    </div>

                    <dl class="mt-4 flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                        <div class="flex items-center gap-1.5">
                            <x-icon name="layers" class="size-4" />
                            {{ \App\Models\Space::themeLabel($space->theme) }}
                        </div>
                        <div class="flex items-center gap-1.5">
                            <x-icon name="inbox" class="size-4" />
                            {{ $space->testimonials_count }} testimonials
                        </div>
                    </dl>

                    <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <x-button type="button" variant="secondary" wire:click="edit({{ $space->id }})" class="!px-3 !py-2 text-xs">
                            <x-icon name="pencil" class="size-4" />
                            Edit
                        </x-button>

                        <x-button type="button" variant="ghost" wire:click="confirmDelete({{ $space->id }})" class="!px-3 !py-2 text-xs">
                            <x-icon name="trash" class="size-4" />
                            Delete
                        </x-button>

                        <button
                            type="button"
                            data-copy-target="{{ $urlId }}"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        >
                            Copy URL
                        </button>

                        {{-- The collection page only answers for a published space, so the
                             link is offered only then. An unpublished space keeps its
                             copyable url above, but is not given a link that would 404. --}}
                        @if ($space->is_published)
                            <a
                                href="{{ $space->collectionUrl() }}"
                                target="_blank"
                                rel="noopener"
                                class="ml-auto inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200"
                            >
                                View page
                                <x-icon name="external" class="size-4" />
                            </a>
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    <x-modal name="delete-space" title="Delete this space?">
        @php
            $deleting = $this->spaces->firstWhere('id', $deletingSpaceId);
        @endphp

        <p class="text-sm text-slate-600 dark:text-slate-400">
            <span class="font-semibold text-slate-900 dark:text-white">{{ $deleting?->name }}</span>
            will be permanently deleted. This cannot be undone.
        </p>

        <div class="mt-6 flex justify-end gap-3">
            <x-button type="button" variant="ghost" wire:click="cancelDelete" data-modal-close="delete-space">
                Cancel
            </x-button>

            <x-button type="button" variant="danger" wire:click="delete" wire:loading.attr="disabled">
                Delete space
            </x-button>
        </div>
    </x-modal>
</div>
