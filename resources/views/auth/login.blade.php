<x-layouts.guest title="Sign in · {{ config('app.name', 'Kudosly') }}">
    <div>
        <h1 class="text-center text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            Welcome back
        </h1>
        <p class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">
            Sign in to manage your testimonials.
        </p>
    </div>

    <livewire:auth.login />
</x-layouts.guest>
