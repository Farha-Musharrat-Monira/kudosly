<div class="mt-8">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="sendResetLink" class="space-y-5">
        <div>
            <x-label for="email">Email address</x-label>
            <x-input
                id="email"
                type="email"
                name="email"
                wire:model="email"
                autocomplete="username"
                autofocus
                required
                class="mt-1.5"
                :error="$errors->first('email')"
            />
        </div>

        <x-button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="sendResetLink">
            <span wire:loading.remove wire:target="sendResetLink">Email password reset link</span>
            <span wire:loading wire:target="sendResetLink">Sending…</span>
        </x-button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
            Back to sign in
        </a>
    </p>
</div>
