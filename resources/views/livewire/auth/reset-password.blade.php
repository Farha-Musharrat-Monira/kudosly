<div class="mt-8">
    <form wire:submit="resetPassword" class="space-y-5">
        <div>
            <x-label for="password">New password</x-label>
            <x-input
                id="password"
                type="password"
                name="password"
                wire:model="password"
                autocomplete="new-password"
                autofocus
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

        <x-button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="resetPassword">
            <span wire:loading.remove wire:target="resetPassword">Reset password</span>
            <span wire:loading wire:target="resetPassword">Resetting…</span>
        </x-button>
    </form>
</div>
