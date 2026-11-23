@php
    /**
     * The widget builder.
     *
     * The preview on the right uses the same template as the public embed,
     * so what the owner sees here is exactly what a visitor sees.
     *
     * @var \App\Livewire\Widgets\ManageWidgets $this
     * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Space> $spaces
     * @var \App\Models\Widget|null $widget
     */
@endphp

<div class="space-y-6">
    @if ($notice)
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ $notice }}
        </div>
    @endif

    @if ($error)
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            {{ $error }}
        </div>
    @endif

    @if ($spaces->isEmpty())
        <x-empty-state icon="code" title="No spaces to embed yet" description="Create a space first, then come back here to embed its testimonials on your own website.">
            <x-button :href="route('spaces.index')">Go to Spaces</x-button>
        </x-empty-state>
    @else
        <div class="grid gap-6 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <x-card>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                        {{ $widget ? 'Widget settings' : 'Create a widget' }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose the space to embed, then pick how it looks.</p>

                    <div class="mt-5 space-y-5">
                        <div>
                            <x-label for="widget-space">Space</x-label>
                            {{-- The space is chosen with an explicit change action rather than a
                                 bound model, because choosing a space must also load that
                                 space's existing widget settings into the form.
                                 The placeholder option is disabled so an empty value is never
                                 sent to the component, which expects a real space. --}}
                            <select
                                id="widget-space"
                                wire:change="edit($event.target.value)"
                                class="mt-1.5 block w-full rounded-lg border-slate-300 bg-white text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white sm:text-sm"
                            >
                                <option value="" disabled>Choose a space</option>
                                @foreach ($spaces as $space)
                                    <option value="{{ $space->id }}" @selected($spaceId === $space->id)>
                                        {{ $space->name }}{{ $space->widget ? ' (embedded)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('space')<x-error>{{ $message }}</x-error>@enderror
                        </div>

                        <fieldset>
                            <legend class="text-sm font-medium text-slate-700 dark:text-slate-300">Layout</legend>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                @foreach (\App\Models\Widget::LAYOUTS as $key => $label)
                                    <label @class([
                                        'flex cursor-pointer items-center justify-center rounded-lg border px-3 py-2.5 text-sm font-medium transition',
                                        'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-500 dark:bg-indigo-950/40 dark:text-indigo-300' => $layout === $key,
                                        'border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800' => $layout !== $key,
                                    ])>
                                        <input type="radio" name="layout" value="{{ $key }}" wire:model.live="layout" class="sr-only">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            @error('layout')<x-error>{{ $message }}</x-error>@enderror
                        </fieldset>

                        <fieldset>
                            <legend class="text-sm font-medium text-slate-700 dark:text-slate-300">Theme</legend>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                @foreach (\App\Models\Widget::THEMES as $key => $label)
                                    <label @class([
                                        'flex cursor-pointer items-center justify-center rounded-lg border px-3 py-2.5 text-sm font-medium transition',
                                        'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-500 dark:bg-indigo-950/40 dark:text-indigo-300' => $theme === $key,
                                        'border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800' => $theme !== $key,
                                    ])>
                                        <input type="radio" name="theme" value="{{ $key }}" wire:model.live="theme" class="sr-only">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            @error('theme')<x-error>{{ $message }}</x-error>@enderror
                        </fieldset>

                        <div>
                            <x-label for="widget-background">Background colour</x-label>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                @foreach (\App\Models\Widget::BACKGROUND_COLORS as $colour)
                                    <label @class([
                                        'relative size-8 cursor-pointer rounded-full border-2 transition',
                                        'border-indigo-500 ring-2 ring-indigo-200 dark:ring-indigo-900' => $backgroundColor === $colour,
                                        'border-slate-300 dark:border-slate-600' => $backgroundColor !== $colour,
                                    ]) style="background-color: {{ $colour }};">
                                        <span class="sr-only">{{ $colour }}</span>
                                        <input type="radio" name="backgroundColor" value="{{ $colour }}" wire:model.live="backgroundColor" class="absolute inset-0 size-full cursor-pointer opacity-0">
                                    </label>
                                @endforeach
                            </div>
                            <div class="mt-3">
                                <x-input id="widget-background" wire:model.live.debounce.500ms="backgroundColor" placeholder="#ffffff" maxlength="7" />
                            </div>
                            @error('backgroundColor')<x-error>{{ $message }}</x-error>@enderror
                        </div>

                        <div>
                            <x-label for="widget-limit">Testimonials to show</x-label>
                            <input
                                id="widget-limit" type="number" wire:model.live.debounce.500ms="displayLimit"
                                min="{{ \App\Models\Widget::MIN_DISPLAY_LIMIT }}" max="{{ \App\Models\Widget::MAX_DISPLAY_LIMIT }}"
                                class="mt-1.5 block w-full rounded-lg border-slate-300 bg-white text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white sm:text-sm"
                            >
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Between {{ \App\Models\Widget::MIN_DISPLAY_LIMIT }} and {{ \App\Models\Widget::MAX_DISPLAY_LIMIT }} testimonials.
                            </p>
                            @error('displayLimit')<x-error>{{ $message }}</x-error>@enderror
                        </div>

                        <div>
                            <x-toggle-switch label="Animate the cards" wire:model.live="hasAnimation" />
                            @error('hasAnimation')<x-error>{{ $message }}</x-error>@enderror
                        </div>

                        <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5 dark:border-slate-800">
                            <x-button wire:click="save" wire:loading.attr="disabled">
                                {{ $widget ? 'Save changes' : 'Create widget' }}
                            </x-button>
                            @if ($widget)
                                <x-button variant="danger" wire:click="delete" wire:loading.attr="disabled">Delete widget</x-button>
                            @endif
                        </div>
                    </div>
                </x-card>

                @if ($widget)
                    <x-card class="mt-6">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Embed this widget</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Paste either snippet into your website. Both show only approved
                            testimonials that consented to being shared.
                        </p>
                        <div class="mt-5 space-y-5">
                            <div>
                                <x-label>JavaScript</x-label>
                                <textarea
                                    id="widget-script-snippet" rows="3" readonly
                                    class="mt-1.5 block w-full rounded-lg border-slate-300 bg-slate-50 font-mono text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300"
                                >{{ $widget->scriptSnippet() }}</textarea>
                                <button type="button" data-copy-target="widget-script-snippet" class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                    <x-icon name="copy" class="size-4" /> Copy snippet
                                </button>
                            </div>
                            <div>
                                <x-label>iframe</x-label>
                                <textarea
                                    id="widget-iframe-snippet" rows="3" readonly
                                    class="mt-1.5 block w-full rounded-lg border-slate-300 bg-slate-50 font-mono text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300"
                                >{{ $widget->iframeSnippet() }}</textarea>
                                <button type="button" data-copy-target="widget-iframe-snippet" class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                    <x-icon name="copy" class="size-4" /> Copy snippet
                                </button>
                            </div>
                            <div>
                                <x-label>Preview link</x-label>
                                <a href="{{ $widget->publicUrl() }}" target="_blank" rel="noopener" class="mt-1.5 inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                    {{ $widget->publicUrl() }}
                                    <x-icon name="external" class="size-4" />
                                </a>
                            </div>
                        </div>
                    </x-card>
                @endif
            </div>

            <div class="lg:col-span-3">
                <x-card>
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Live preview</h3>
                        <x-badge variant="indigo">{{ ucfirst($layout) }} · {{ ucfirst($theme) }}</x-badge>
                    </div>
                    <div class="mt-5 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                        @if ($spaceId !== null && $this->selectedSpace !== null)
                            <x-widget-render
                                :public-id="$widget?->public_id ?? 'preview'"
                                :layout="$layout" :theme="$theme"
                                :background-color="$backgroundColor"
                                :has-animation="$hasAnimation"
                                :testimonials="$this->previewTestimonials"
                            />
                        @else
                            <div class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                                Choose a space to see a preview of your widget.
                            </div>
                        @endif
                    </div>
                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                        This is the exact widget your visitors will see. Only approved
                        testimonials that consented to being shared are shown.
                    </p>
                </x-card>
            </div>
        </div>
    @endif
</div>
