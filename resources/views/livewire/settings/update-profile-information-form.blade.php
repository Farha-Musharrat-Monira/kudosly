<div>
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-5">
        <div>
            <x-label for="profile_name">Name</x-label>
            <x-input
                id="profile_name"
                type="text"
                name="name"
                wire:model="name"
                autocomplete="name"
                required
                class="mt-1.5"
                :error="$errors->first('name')"
            />
        </div>

        <div>
            <x-label for="profile_email">Email address</x-label>
            <x-input
                id="profile_email"
                type="email"
                name="email"
                wire:model="email"
                autocomplete="username"
                required
                class="mt-1.5"
                :error="$errors->first('email')"
            />
            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                Changing your email address requires your current password, and you will need to verify the new address.
            </p>
        </div>

        <div>
            <x-label for="profile_current_password">Current password</x-label>
            <x-input
                id="profile_current_password"
                type="password"
                name="current_password"
                wire:model="current_password"
                autocomplete="current-password"
                class="mt-1.5"
                :error="$errors->first('current_password')"
            />
        </div>

        <x-button type="submit" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Save profile</span>
            <span wire:loading wire:target="save">Saving…</span>
        </x-button>
    </form>
</div>
