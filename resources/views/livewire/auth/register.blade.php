<div class="mt-8">
    <form wire:submit="register" class="space-y-5">
        <div>
            <x-label for="name">Full name</x-label>
            <x-input
                id="name"
                type="text"
                name="name"
                wire:model="name"
                autocomplete="name"
                autofocus
                required
                class="mt-1.5"
                :error="$errors->first('name')"
            />
        </div>

        <div>
            <x-label for="email">Email address</x-label>
            <x-input
                id="email"
                type="email"
                name="email"
                wire:model="email"
                autocomplete="username"
                required
                class="mt-1.5"
                :error="$errors->first('email')"
            />
        </div>

        <div>
            <x-label for="password">Password</x-label>
            <x-input
                id="password"
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
            <x-label for="password_confirmation">Confirm password</x-label>
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

        <x-button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="register">
            <span wire:loading.remove wire:target="register">Create account</span>
            <span wire:loading wire:target="register">Creating account…</span>
        </x-button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        Already have an account?
        <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
            Sign in
        </a>
    </p>
</div>
