<x-layouts.guest title="Reset password · {{ config('app.name', 'Kudosly') }}">
    <div>
        <h1 class="text-center text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            Forgot your password?
        </h1>
        <p class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">
            Enter your email address and we will send you a reset link.
        </p>
    </div>

    <livewire:auth.forgot-password />
</x-layouts.guest>
