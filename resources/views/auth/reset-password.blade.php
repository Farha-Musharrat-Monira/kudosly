<x-layouts.guest title="Choose a new password · {{ config('app.name', 'Kudosly') }}">
    <div>
        <h1 class="text-center text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            Choose a new password
        </h1>
        <p class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">
            Your new password must be different from your previous one.
        </p>
    </div>

    <livewire:auth.reset-password />
</x-layouts.guest>
