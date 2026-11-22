<x-layouts.guest title="Create account · {{ config('app.name', 'Kudosly') }}">
    <div>
        <h1 class="text-center text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            Create your account
        </h1>
        <p class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">
            Start collecting testimonials for free.
        </p>
    </div>

    <livewire:auth.register />
</x-layouts.guest>
