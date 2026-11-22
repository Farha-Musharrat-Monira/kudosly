<x-layouts.guest title="Verify your email · {{ config('app.name', 'Kudosly') }}">
    <div>
        <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-950/50">
            <x-icon name="mail" class="size-6 text-indigo-600 dark:text-indigo-400" />
        </div>

        <h1 class="mt-4 text-center text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            Verify your email address
        </h1>
        <p class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">
            We sent a verification link to <span class="font-medium text-slate-700 dark:text-slate-300">{{ auth()->user()->email }}</span>.
            Please follow the link to activate your account.
        </p>
    </div>

    <div class="mt-8 space-y-5">
        @if (session('status') === 'verification-link-sent')
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                A fresh verification link has been sent to your email address.
            </div>
        @endif

        @if (config('mail.default') === 'log')
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                <span class="font-medium">Local development:</span> the verification email is written to
                <code class="font-mono text-xs">storage/logs/laravel.log</code> rather than sent. Open that file and follow the link it contains.
            </div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}" class="flex items-center justify-between gap-4">
            @csrf

            <p class="text-sm text-slate-500 dark:text-slate-400">
                Did not receive the email?
            </p>

            <x-button type="submit" variant="secondary">
                Resend verification email
            </x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button type="submit" variant="ghost" class="w-full">
                Log out
            </x-button>
        </form>
    </div>
</x-layouts.guest>
