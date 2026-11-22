<div>
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-5">
        <div>
            <x-label for="password_current_password">Current password</x-label>
            <x-input
                id="password_current_password"
                type="password"
                name="current_password"
                wire:model="current_password"
                autocomplete="current-password"
                required
                class="mt-1.5"
                :error="$errors->first('current_password')"
            />
        </div>

        <div>
            <x-label for="password_new">New password</x-label>
            <x-input
                id="password_new"
                type="password"
                name="password"
                wire:model="password"
                autocomplete="new-password"
                required
                class="mt-1.5"
                :error="$errors->first('password')"
            />
        </div>

        <div>
            <x-label for="password_confirmation">Confirm new password</x-label>
            <x-input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                wire:model="password_confirmation"
                autocomplete="new-password"
                required
                class="mt-1.5"
                :error="$errors->first('password_confirmation')"
            />
        </div>

        <x-button type="submit" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Update password</span>
            <span wire:loading wire:target="save">Updating…</span>
        </x-button>
    </form>
</div>
