<x-layouts.app title="Settings · {{ config('app.name', 'Kudosly') }}" header="Settings">
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Settings</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Manage your profile and account security.
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Profile information</h3>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                Update your name and email address.
            </p>

            <div class="mt-5">
                <livewire:settings.update-profile-information-form />
            </div>
        </x-card>

        <x-card>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Password</h3>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                Choose a new password for your account.
            </p>

            <div class="mt-5">
                <livewire:settings.update-password-form />
            </div>
        </x-card>
    </div>

    <x-card class="mt-6">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Account</h3>

        <dl class="mt-4 space-y-3 text-sm">
            <div class="flex items-center justify-between gap-4">
                <dt class="text-slate-500 dark:text-slate-400">Current plan</dt>
                <dd>
                    <x-badge variant="indigo">{{ auth()->user()->effectivePlan()->name }}</x-badge>
                </dd>
            </div>

            <div class="flex items-center justify-between gap-4">
                <dt class="text-slate-500 dark:text-slate-400">Email verified</dt>
                <dd>
                    @if (auth()->user()->hasVerifiedEmail())
                        <x-badge variant="success">Verified</x-badge>
                    @else
                        <x-badge variant="warning">Not verified</x-badge>
                    @endif
                </dd>
            </div>

            <div class="flex items-center justify-between gap-4">
                <dt class="text-slate-500 dark:text-slate-400">Spaces used</dt>
                <dd class="font-medium text-slate-900 dark:text-white">
                    {{ auth()->user()->spacesUsed() }} / {{ auth()->user()->spaceQuota() }}
                </dd>
            </div>
        </dl>
    </x-card>
</x-layouts.app>
