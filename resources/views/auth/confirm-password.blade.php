<x-layouts.guest title="Confirm password · {{ config('app.name', 'Kudosly') }}">
    <div>
        <h1 class="text-center text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            Confirm your password
        </h1>
        <p class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">
            This is a secure area. Please confirm your password before continuing.
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <x-label for="password">Password</x-label>
            <x-input
                id="password"
                type="password"
                name="password"
                autocomplete="current-password"
                autofocus
                required
                class="mt-1.5"
                :error="$errors->first('password')"
            />
        </div>

        <x-button type="submit" class="w-full">
            Confirm
        </x-button>
    </form>
</x-layouts.guest>
