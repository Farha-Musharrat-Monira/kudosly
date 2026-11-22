<div>
    @if ($this->user->onFreePlan())
        <div class="mb-6 flex flex-col gap-4 rounded-2xl border border-indigo-200 bg-indigo-50 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-indigo-900 dark:bg-indigo-950/40">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white">
                    <x-icon name="rocket" class="size-5" />
                </span>
                <div>
                    <p class="text-sm font-semibold text-indigo-900 dark:text-indigo-200">You're on the Free plan</p>
                    <p class="mt-0.5 text-sm text-indigo-700 dark:text-indigo-300">
                        You have {{ $this->user->remainingSpaces() }} of {{ $this->user->spaceQuota() }} spaces remaining.
                        Upgrade to Pro for 25 spaces and 1,000 testimonials per space.
                    </p>
                </div>
            </div>

            <x-button :href="route('billing')" class="shrink-0">
                View plans
            </x-button>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-stat-card label="Total Spaces" :value="$this->totalSpaces" icon="layers" />
        <x-stat-card label="Total Testimonials" :value="$this->totalTestimonials" icon="inbox" />
        <x-stat-card label="Unique Customers" :value="$this->uniqueCustomers" icon="users" />
        <x-stat-card label="This Month" :value="$this->thisMonth" icon="calendar" />
        <x-stat-card
            label="Average Rating"
            :value="$this->averageRating === null ? '—' : $this->averageRating"
            icon="star"
            :hint="$this->averageRating === null ? 'No ratings yet' : null"
        />
        <x-stat-card
            label="Plan"
            :value="$this->user->plan->name"
            icon="credit-card"
            :hint="$this->user->plan->priceLabel()"
        />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Submissions</h3>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Testimonials received per day</p>
                </div>

                <div class="inline-flex rounded-lg bg-slate-100 p-1 dark:bg-slate-800" role="group" aria-label="Chart range">
                    @foreach ([7, 30, 90] as $option)
                        <button
                            type="button"
                            wire:click="$set('range', {{ $option }})"
                            @class([
                                'rounded-md px-3 py-1.5 text-xs font-semibold transition',
                                'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white' => $range === $option,
                                'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200' => $range !== $option,
                            ])
                        >
                            {{ $option }}d
                        </button>
                    @endforeach
                </div>
            </div>

            @php
                $series = $this->chartSeries;
                $maxCount = $series->max('count') ?? 0;
                $total = $series->sum('count');
            @endphp

            @if ($total === 0)
                <x-empty-state
                    icon="trend-up"
                    title="No submissions yet"
                    description="Once your space is collecting testimonials, submissions will appear here."
                />
            @else
                <div class="relative h-48 w-full">
                    <svg viewBox="0 0 {{ max(count($series), 1) * 24 }} 120" class="h-48 w-full" preserveAspectRatio="none" role="img" aria-label="Daily submissions chart">
                        @foreach ($series as $index => $point)
                            @php
                                $height = $maxCount > 0 ? ($point['count'] / $maxCount) * 100 : 0;
                                $x = $index * 24;
                            @endphp
                            <rect
                                x="{{ $x + 3 }}"
                                y="{{ 120 - $height }}"
                                width="18"
                                height="{{ max($height, 1) }}"
                                rx="3"
                                class="fill-indigo-500/80"
                            >
                                <title>{{ $point['label'] }}: {{ $point['count'] }}</title>
                            </rect>
                        @endforeach
                    </svg>

                    <div class="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                        <span>{{ $series->first()['label'] }}</span>
                        <span>{{ $series->last()['label'] }}</span>
                    </div>
                </div>
            @endif
        </x-card>

        <div class="space-y-6">
            <x-card>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Plan &amp; Usage</h3>

                <dl class="mt-4">
                    <dt class="text-sm font-medium text-slate-700 dark:text-slate-300">Spaces</dt>
                    <dd class="mt-2">
                        <x-progress
                            :value="$this->totalSpaces"
                            :max="$this->user->spaceQuota()"
                            :show-count="true"
                        />
                    </dd>
                </dl>

                <div class="mt-5 flex items-center justify-between border-t border-slate-200 pt-4 dark:border-slate-800">
                    <span class="text-sm text-slate-500 dark:text-slate-400">Current plan</span>
                    <x-badge variant="indigo">{{ $this->user->plan->name }}</x-badge>
                </div>
            </x-card>

            <x-card>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Testimonial usage</h3>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    Each space may hold up to {{ $this->user->testimonialQuota() }} testimonials.
                </p>

                @php
                    $usage = $this->spaceUsage;
                @endphp

                @if ($usage->isEmpty())
                    <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">No spaces yet.</p>
                @else
                    <ul class="mt-4 space-y-4">
                        @foreach ($usage as $row)
                            <li>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="truncate text-sm font-medium text-slate-700 dark:text-slate-300">{{ $row['space']->name }}</span>
                                    <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ $row['count'] }} / {{ $row['quota'] }}</span>
                                </div>
                                <div class="mt-1.5">
                                    <x-progress :value="$row['count']" :max="$row['quota']" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Recent submissions</h3>

                @php
                    $recent = $this->recentSubmissions;
                @endphp

                @if ($recent->isEmpty())
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">No submissions yet.</p>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach ($recent as $submission)
                            <li class="flex items-start gap-3">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ Str::upper(Str::substr($submission->name, 0, 2)) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $submission->name }}</p>
                                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ $submission->space?->name ?? 'Unknown space' }} · {{ $submission->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</div>
