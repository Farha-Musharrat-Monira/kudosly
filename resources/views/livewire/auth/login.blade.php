<div class="mt-8">
    <form wire:submit="authenticate" class="space-y-5">
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

        <div>
            <div class="flex items-center justify-between">
                <x-label for="password">Password</x-label>
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                    Forgot password?
                </a>
            </div>
            <x-input
                id="password"
                type="password"
                name="password"
                wire:model="password"
                autocomplete="current-password"
                required
                class="mt-1.5"
                :error="$errors->first('password')"
            />
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
            <input
                type="checkbox"
                name="remember"
                wire:model="remember"
                class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950"
            />
            Remember me
        </label>

        <x-button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="authenticate">
            <span wire:loading.remove wire:target="authenticate">Sign in</span>
            <span wire:loading wire:target="authenticate">Signing in…</span>
        </x-button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        New to {{ config('app.name', 'Kudosly') }}?
        <a href="{{ route('register') }}" class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
            Create an account
        </a>
    </p>
</div>
