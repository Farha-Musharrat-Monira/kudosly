<x-layouts.app title="Billing · {{ config('app.name', 'Kudosly') }}" header="Billing">
    @php
        $user = auth()->user();
        $plan = $user->plan;
        $free = \App\Models\Plan::query()->where('slug', 'free')->first();
        $pro = \App\Models\Plan::query()->where('slug', 'pro')->first();

        // Each space is measured against the per-space quota separately, so
        // the aggregate count is never compared against a single limit.
        $quota = (int) $user->testimonialQuota();

        $testimonialCounts = \App\Models\Testimonial::query()
            ->where('user_id', $user->id)
            ->selectRaw('space_id, COUNT(*) as total')
            ->groupBy('space_id')
            ->pluck('total', 'space_id');

        $spaces = $user->spaces()->orderBy('name')->get();
    @endphp

    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Billing</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Your current plan and how much of it you are using.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ $plan->name }}</h3>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $plan->description }}</p>
                </div>
                <x-badge variant="indigo">Current plan</x-badge>
            </div>

            <p class="mt-4 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                {{ $plan->priceLabel() }}
            </p>

            <div class="mt-6 space-y-4 border-t border-slate-200 pt-5 dark:border-slate-800">
                <div>
                    <div class="mb-1.5 flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-300">Spaces</span>
                        <span class="text-slate-500 dark:text-slate-400">{{ $user->spacesUsed() }} / {{ $plan->max_spaces }}</span>
                    </div>
                    <x-progress :value="$user->spacesUsed()" :max="$plan->max_spaces" />
                </div>
            </div>
        </x-card>

        <x-card>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Available plans</h3>

            <div class="mt-4 space-y-4">
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold text-slate-900 dark:text-white">{{ $free?->name ?? 'Free' }}</p>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $free?->priceLabel() ?? 'Price unavailable' }}</p>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ $free?->max_spaces ?? 3 }} spaces · {{ $free?->max_testimonials_per_space ?? 100 }} testimonials per space
                    </p>
                </div>

                <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4 dark:border-indigo-900 dark:bg-indigo-950/30">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold text-slate-900 dark:text-white">{{ $pro?->name ?? 'Pro' }}</p>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $pro?->priceLabel() ?? 'Price unavailable' }}</p>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ $pro?->max_spaces ?? 25 }} spaces · {{ $pro?->max_testimonials_per_space ?? 1000 }} testimonials per space
                    </p>
                </div>
            </div>

            <div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-800">
                <x-button type="button" disabled class="w-full cursor-not-allowed opacity-60">
                    Checkout coming soon
                </x-button>

                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    Subscriptions are not available yet. Your plan stays as it is until then.
                </p>
            </div>
        </x-card>
    </div>

    <x-card class="mt-6">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Testimonial usage</h3>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Each space may hold up to {{ $quota }} testimonials.
        </p>

        @if ($spaces->isEmpty())
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">No spaces yet.</p>
        @else
            <ul class="mt-4 space-y-4">
                @foreach ($spaces as $space)
                    @php
                        $count = (int) ($testimonialCounts[$space->id] ?? 0);
                    @endphp

                    <li>
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate text-sm font-medium text-slate-700 dark:text-slate-300">{{ $space->name }}</span>
                            <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ $count }} / {{ $quota }}</span>
                        </div>
                        <div class="mt-1.5">
                            <x-progress :value="$count" :max="$quota" />
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-layouts.app>
