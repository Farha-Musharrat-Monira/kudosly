<x-layouts.app title="Billing · {{ config('app.name', 'Kudosly') }}" header="Billing">
    @php
        $checkoutState = request()->query('checkout');
        $cancelledAt = session('billing_ends_at');
    @endphp

    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Billing</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Your current plan and how much of it you are using.
        </p>
    </div>

    {{-- Notifications ---------------------------------------------------- --}}

    @if ($checkoutState === 'success')
        <div
            class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
            role="status"
        >
            <x-icon name="check" class="mt-0.5 size-5 shrink-0" />

            <div class="min-w-0">
                {{-- The return from Stripe says only that the checkout session
                     completed. It is not proof that the payment was taken or
                     that the subscription is active: only a signed webhook
                     from Stripe can confirm that. So this notice stays
                     neutral and reports what the account can actually see,
                     which is the plan the webhook has applied so far. --}}
                <p class="font-semibold">Checkout completed</p>
                <p class="mt-0.5">
                    You returned from Stripe. Your plan changes once Stripe confirms the subscription, which usually takes a few seconds.
                    @if (! $isPro)
                        <span class="mt-1 block font-medium">You are still on the {{ $plan->name }} plan.</span>
                    @endif
                </p>
            </div>
        </div>
    @elseif ($checkoutState === 'cancelled')
        <div
            class="mb-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
            role="status"
        >
            <x-icon name="info" class="mt-0.5 size-5 shrink-0" />

            <div class="min-w-0">
                <p class="font-semibold">Checkout cancelled</p>
                <p class="mt-0.5">Nothing was charged and your plan is unchanged. You can upgrade to Pro whenever you are ready.</p>
            </div>
        </div>
    @endif

    @if (session('billing_status') === 'cancelled')
        <div
            class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
            role="status"
        >
            <x-icon name="check" class="mt-0.5 size-5 shrink-0" />

            <div class="min-w-0">
                <p class="font-semibold">Cancellation scheduled</p>
                <p class="mt-0.5">
                    Your subscription will not renew. You keep Pro access until the end of the period you have already paid for.
                    @if ($cancelledAt)
                        <span class="mt-1 block font-medium">
                            Your plan returns to Free on {{ \Illuminate\Support\Carbon::parse($cancelledAt)->format('j M Y') }}.
                        </span>
                    @endif
                </p>
            </div>
        </div>
    @endif

    @if ($subscription !== null && $subscription->ended() && ! $isCancelling)
        <div
            class="mb-6 flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
            role="status"
        >
            <x-icon name="info" class="mt-0.5 size-5 shrink-0 text-slate-400" />

            <div class="min-w-0">
                <p class="font-semibold">Subscription ended</p>
                <p class="mt-0.5">Your Pro subscription has ended and the account is back on the Free plan. Your spaces and testimonials are all still here.</p>
            </div>
        </div>
    @endif

    @if ($errors->has('billing'))
        <div
            class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
            role="alert"
        >
            <x-icon name="alert" class="mt-0.5 size-5 shrink-0" />

            <div class="min-w-0">
                <p class="font-semibold">Billing problem</p>
                <p class="mt-0.5">{{ $errors->first('billing') }}</p>
            </div>
        </div>
    @endif

    {{-- Current plan and usage ------------------------------------------- --}}

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">{{ $plan->name }}</h3>

                        @if ($isCancelling)
                            <x-badge variant="warning">Ends {{ $subscription->ends_at->format('j M Y') }}</x-badge>
                        @elseif ($isPro)
                            <x-badge variant="indigo">Current plan</x-badge>
                        @else
                            <x-badge variant="neutral">Current plan</x-badge>
                        @endif
                    </div>

                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $plan->description }}</p>
                </div>

                <div class="shrink-0 sm:text-right">
                    <p class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ $plan->priceLabel() }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        @if ($plan->price_cents > 0)
                            Billed monthly in {{ $plan->currency }}
                        @else
                            No card required
                        @endif
                    </p>
                </div>
            </div>

            @if ($subscription !== null)
                <dl class="mt-6 grid gap-x-6 gap-y-3 border-t border-slate-200 pt-5 text-sm sm:grid-cols-2 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <dt class="text-slate-500 dark:text-slate-400">Subscription status</dt>
                        <dd class="font-medium text-slate-900 dark:text-white sm:mt-0.5">
                            {{ ucfirst(str_replace('_', ' ', $subscription->stripe_status)) }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-3 sm:block">
                        <dt class="text-slate-500 dark:text-slate-400">Billing interval</dt>
                        <dd class="font-medium text-slate-900 dark:text-white sm:mt-0.5">Monthly</dd>
                    </div>

                    @if ($subscription->trial_ends_at !== null && $subscription->trial_ends_at->isFuture())
                        <div class="flex items-center justify-between gap-3 sm:block">
                            <dt class="text-slate-500 dark:text-slate-400">Trial ends</dt>
                            <dd class="font-medium text-slate-900 dark:text-white sm:mt-0.5">
                                {{ $subscription->trial_ends_at->format('j M Y') }}
                            </dd>
                        </div>
                    @endif

                    @if ($isCancelling)
                        <div class="flex items-center justify-between gap-3 sm:block">
                            <dt class="text-slate-500 dark:text-slate-400">Access until</dt>
                            <dd class="font-medium text-slate-900 dark:text-white sm:mt-0.5">
                                {{ $subscription->ends_at->format('j M Y') }}
                            </dd>
                        </div>
                    @elseif ($isPro)
                        {{-- The renewal date is not stored on the account, and
                             the subscription's ends_at is only ever set when a
                             subscription is on its way out. Rather than show a
                             cancellation date under a renewal label, the renewal
                             date is left to the billing portal, where Stripe
                             shows the real one. --}}
                        <div class="flex items-center justify-between gap-3 sm:block">
                            <dt class="text-slate-500 dark:text-slate-400">Renewal</dt>
                            <dd class="font-medium text-slate-900 dark:text-white sm:mt-0.5">
                                Manage billing to see your renewal date
                            </dd>
                        </div>
                    @endif
                </dl>
            @else
                <div class="mt-6 flex items-start gap-3 border-t border-slate-200 pt-5 text-sm dark:border-slate-800">
                    <x-icon name="info" class="mt-0.5 size-5 shrink-0 text-slate-400" />

                    <p class="text-slate-500 dark:text-slate-400">
                        You are on the Free plan. There is no subscription and no card on file.
                    </p>
                </div>
            @endif

            <div class="mt-6 space-y-5 border-t border-slate-200 pt-5 dark:border-slate-800">
                <x-usage-meter
                    label="Spaces"
                    :used="$spacesUsed"
                    :limit="$spaceQuota"
                    unit="spaces"
                    :hint="$spacesRemaining.' of '.$spaceQuota.' spaces remaining'"
                />

                <x-usage-meter
                    label="Testimonials"
                    :used="$testimonialsUsed"
                    :limit="$testimonialQuota * max(1, $spaces->count())"
                    unit="testimonials"
                    :hint="$testimonialsRemaining.' remaining across your spaces'"
                />
            </div>
        </x-card>

        <x-card class="flex flex-col">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Billing actions</h3>

            <div class="mt-4 flex-1">
                @if ($isPro && ! $isCancelling)
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        You are on the Pro plan. Manage your payment method, download invoices, or cancel from the Stripe billing portal.
                    </p>

                    <form method="POST" action="{{ route('billing.portal') }}" class="mt-4">
                        @csrf

                        <x-button type="submit" variant="secondary" class="w-full">
                            Manage billing
                        </x-button>
                    </form>

                    <form method="POST" action="{{ route('billing.cancel') }}" class="mt-3">
                        @csrf

                        <x-button type="submit" variant="ghost" class="w-full">
                            Cancel subscription
                        </x-button>
                    </form>

                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                        Cancelling stops the next renewal. You keep Pro access until the period you have paid for ends, and your account then returns to the Free plan. Nothing is deleted.
                    </p>
                @elseif ($isCancelling)
                    <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-900 dark:bg-amber-950/30">
                        <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                            Cancellation scheduled
                        </p>
                        <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                            This subscription will not renew. Pro access continues until the period you have paid for ends on
                            <span class="font-semibold">{{ $subscription->ends_at->format('j M Y') }}</span>, and your account then returns to the Free plan.
                        </p>
                    </div>

                    <x-button type="button" disabled class="mt-4 w-full cursor-not-allowed opacity-60">
                        Subscription cancelled
                    </x-button>
                @elseif (\App\Support\CheckoutAvailability::available())
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        Upgrade to Pro for more spaces and a higher testimonial limit.
                    </p>

                    <form method="POST" action="{{ route('billing.checkout') }}" class="mt-4">
                        @csrf

                        <x-button type="submit" class="w-full">
                            Upgrade to Pro
                        </x-button>
                    </form>

                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                        You will be taken to Stripe to complete the payment. Your plan changes once Stripe confirms the subscription.
                    </p>
                @else
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        Checkout is not available on this server right now.
                    </p>

                    <x-button type="button" disabled class="mt-4 w-full cursor-not-allowed opacity-60">
                        Checkout unavailable
                    </x-button>

                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                        {{ \App\Support\CheckoutAvailability::unavailableReason() }} Your plan stays as it is until then.
                    </p>
                @endif
            </div>
        </x-card>
    </div>

    {{-- Plan comparison --------------------------------------------------- --}}

    <x-card class="mt-4">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Plans</h3>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Everything included with each plan.
        </p>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            @foreach ([[$freePlan, false], [$proPlan, true]] as [$option, $isProOption])
                @php
                    $isCurrent = $option !== null && $option->getKey() === $plan->getKey();
                @endphp

                <div
                    @class([
                        'rounded-xl border p-5',
                        'border-indigo-200 bg-indigo-50/60 dark:border-indigo-900 dark:bg-indigo-950/30' => $isProOption,
                        'border-slate-200 dark:border-slate-800' => ! $isProOption,
                        'ring-2 ring-indigo-500 ring-offset-2 dark:ring-offset-slate-900' => $isCurrent,
                    ])
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-base font-semibold text-slate-900 dark:text-white">
                            {{ $option?->name ?? 'Plan' }}
                        </p>

                        @if ($isCurrent)
                            <x-badge variant="indigo">Your plan</x-badge>
                        @elseif ($isProOption)
                            <x-badge variant="neutral">Upgrade</x-badge>
                        @endif
                    </div>

                    <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ $option?->priceLabel() ?? 'Price unavailable' }}
                    </p>

                    @if ($option !== null && $option->price_cents > 0)
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Billed monthly in {{ $option->currency }}
                        </p>
                    @endif

                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li class="flex items-start gap-2.5">
                            <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <span class="text-slate-700 dark:text-slate-300">
                                {{ $option?->max_spaces ?? 3 }} spaces
                            </span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <span class="text-slate-700 dark:text-slate-300">
                                {{ $option?->max_testimonials_per_space ?? 100 }} testimonials per space
                            </span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <span class="text-slate-700 dark:text-slate-300">Dashboard analytics</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <span class="text-slate-700 dark:text-slate-300">Wall of Love</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <span class="text-slate-700 dark:text-slate-300">Embeddable widgets</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <span class="text-slate-700 dark:text-slate-300">All themes</span>
                        </li>
                    </ul>
                </div>
            @endforeach
        </div>
    </x-card>

    {{-- Per-space usage --------------------------------------------------- --}}

    <x-card class="mt-4">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Testimonial usage</h3>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Each space may hold up to {{ $testimonialQuota }} testimonials.
        </p>

        @if ($spaces->isEmpty())
            <x-empty-state
                icon="layers"
                title="No spaces yet"
                description="Create a space to start collecting testimonials. Your usage will appear here."
                class="mt-5 border-0"
            >
                <x-button :href="route('spaces.index')" variant="secondary" class="w-full sm:w-auto">
                    Go to Spaces
                </x-button>
            </x-empty-state>
        @else
            <ul class="mt-5 space-y-4">
                @foreach ($spaces as $space)
                    @php
                        $count = (int) ($testimonialCounts[$space->id] ?? 0);
                    @endphp

                    <li>
                        <x-usage-meter
                            :label="$space->name"
                            :used="$count"
                            :limit="$testimonialQuota"
                            unit="testimonials"
                            :hint="$count.' / '.$testimonialQuota.' testimonials used'"
                        />
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-layouts.app>
