<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;
use Laravel\Cashier\Subscription;

/**
 * Shows the billing dashboard.
 *
 * Every number on the page is read from the database and from the account's
 * own entitlement, so the page can never claim usage or a plan the account
 * does not actually have. The controller only gathers data; every decision
 * about what the account is entitled to stays with the model.
 */
class BillingController extends Controller
{
    /**
     * Show the billing page.
     */
    public function __invoke(): View
    {
        $user = auth()->user();

        $plan = $user->effectivePlan();
        $subscription = $user->proSubscription();

        // The space counts, and the testimonials held by each of them. The
        // per-space counts are what the quota is measured against, because
        // the limit applies to each space separately.
        $spaces = $user->spaces()->orderBy('name')->get();

        $testimonialCounts = Testimonial::query()
            ->where('user_id', $user->id)
            ->selectRaw('space_id, COUNT(*) as total')
            ->groupBy('space_id')
            ->pluck('total', 'space_id');

        $testimonialsUsed = (int) $testimonialCounts->sum();
        $testimonialQuota = (int) $user->testimonialQuota();
        $spacesUsed = $user->spacesUsed();
        $spaceQuota = (int) $plan->max_spaces;

        return view('pages.billing', [
            'plan' => $plan,
            'subscription' => $subscription,
            'freePlan' => Plan::query()->where('slug', 'free')->first(),
            'proPlan' => Plan::query()->where('slug', 'pro')->first(),
            'spaces' => $spaces,
            'testimonialCounts' => $testimonialCounts,
            'spacesUsed' => $spacesUsed,
            'spaceQuota' => $spaceQuota,
            'spacesRemaining' => max(0, $spaceQuota - $spacesUsed),
            'testimonialsUsed' => $testimonialsUsed,
            'testimonialQuota' => $testimonialQuota,
            'testimonialsRemaining' => max(0, ($testimonialQuota * max(1, $spaces->count())) - $testimonialsUsed),
            'isPro' => $user->subscribedToPro(),
            'isCancelling' => $this->isCancelling($subscription),
        ]);
    }

    /**
     * Whether the subscription is scheduled to stop renewing.
     *
     * A subscription that is still running but will not renew is on its way
     * out: the account keeps its Pro access until the paid period ends.
     */
    protected function isCancelling(?Subscription $subscription): bool
    {
        return $subscription !== null
            && $subscription->ends_at !== null
            && $subscription->ends_at->isFuture();
    }
}
