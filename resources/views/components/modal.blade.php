@props([
    'name' => 'modal',
    'title' => null,
    'maxWidth' => 'md',
])

@php
    $widths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-lg',
        'lg' => 'sm:max-w-2xl',
        'xl' => 'sm:max-w-4xl',
    ];
@endphp

<div
    x-data="{
        open: false,
        focusableSelector: `a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex='-1'])`,
        lastFocused: null,

        focusable() {
            if (! this.$el) return [];
            return Array.from(this.$el.querySelectorAll(this.focusableSelector))
                .filter((el) => el.offsetParent !== null || el === document.activeElement);
        },

        /**
         * Match the event detail against this modal's name.
         *
         * Livewire dispatches the detail as an object ({ name: 'delete-space' }),
         * while the vanilla JS helper dispatches a plain string.
         */
        matches(detail) {
            if (detail === null || detail === undefined) return false;
            if (typeof detail === 'string') return detail === '{{ $name }}';
            return detail.name === '{{ $name }}';
        },

        openModal() {
            this.lastFocused = document.activeElement;
            this.open = true;
            this.$nextTick(() => {
                const elements = this.focusable();
                if (elements.length > 0) {
                    elements[0].focus();
                }
            });
        },

        closeModal() {
            this.open = false;
            this.$nextTick(() => {
                if (this.lastFocused && typeof this.lastFocused.focus === 'function') {
                    this.lastFocused.focus();
                }
                this.lastFocused = null;
            });
        },

        trapFocus(event) {
            if (! this.open || event.key !== 'Tab') return;

            const elements = this.focusable();
            if (elements.length === 0) return;

            const first = elements[0];
            const last = elements[elements.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (! event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }"
    x-on:open-modal.window="if (matches($event.detail)) openModal()"
    x-on:close-modal.window="if (matches($event.detail)) closeModal()"
    x-on:keydown.escape.window="if (open) closeModal()"
    x-on:keydown.tab="trapFocus($event)"
>
    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.200ms
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4"
        role="dialog"
        aria-modal="true"
        x-on:click.self="closeModal()"
    >
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @class(['relative w-full rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900', $widths[$maxWidth] ?? $widths['md']])
        >
            @if ($title)
                <div class="mb-5 flex items-start justify-between gap-4">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:hover:bg-slate-800"
                        x-on:click="closeModal()"
                        aria-label="Close"
                    >
                        <x-icon name="x" class="size-5" />
                    </button>
                </div>
            @endif

            {{ $slot }}
        </div>
    </div>
</div>
