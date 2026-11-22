<?php

namespace App\Livewire\Dashboard;

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Overview extends Component
{
    /**
     * The chart range in days. One of 7, 30 or 90.
     */
    public int $range = 30;

    /**
     * The available chart ranges.
     */
    public const RANGES = [7, 30, 90];

    /**
     * Change the chart range.
     */
    public function updatedRange(): void
    {
        // Guard the range on the server so a tampered value can never reach
        // the query with an unexpected window size.
        $this->range = in_array((int) $this->range, self::RANGES, true)
            ? (int) $this->range
            : 30;
    }

    /**
     * The authenticated user with their plan relationship.
     */
    #[Computed]
    public function user(): User
    {
        return auth()->user()->loadMissing('plan');
    }

    /**
     * The authenticated user's spaces.
     */
    #[Computed]
    public function spaces(): Collection
    {
        return Space::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * The total number of spaces owned by the user.
     */
    #[Computed]
    public function totalSpaces(): int
    {
        return Space::query()->where('user_id', auth()->id())->count();
    }

    /**
     * The total number of testimonials across all of the user's spaces.
     */
    #[Computed]
    public function totalTestimonials(): int
    {
        return Testimonial::query()->where('user_id', auth()->id())->count();
    }

    /**
     * The number of unique customers who left a testimonial.
     */
    #[Computed]
    public function uniqueCustomers(): int
    {
        return Testimonial::query()
            ->where('user_id', auth()->id())
            ->distinct()
            ->count('email');
    }

    /**
     * The number of testimonials submitted this month.
     */
    #[Computed]
    public function thisMonth(): int
    {
        return Testimonial::query()
            ->where('user_id', auth()->id())
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();
    }

    /**
     * The average rating across all rated testimonials, or null when no
     * ratings exist. A null average is displayed as an em dash rather than a
     * misleading zero.
     */
    #[Computed]
    public function averageRating(): ?float
    {
        $average = Testimonial::query()
            ->where('user_id', auth()->id())
            ->whereNotNull('rating')
            ->avg('rating');

        return $average === null ? null : round((float) $average, 1);
    }

    /**
     * The daily submission counts for the selected range.
     *
     * @return Collection<int, array{date: string, label: string, count: int}>
     */
    #[Computed]
    public function chartSeries(): Collection
    {
        $range = in_array((int) $this->range, self::RANGES, true) ? (int) $this->range : 30;
        $start = Carbon::today()->subDays($range - 1);

        $counts = Testimonial::query()
            ->where('user_id', auth()->id())
            ->where('created_at', '>=', $start->startOfDay())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = collect();

        for ($i = 0; $i < $range; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->format('Y-m-d');

            $series->push([
                'date' => $key,
                'label' => $range === 7
                    ? $date->format('D')
                    : $date->format('M j'),
                'count' => (int) ($counts[$key] ?? 0),
            ]);
        }

        return $series;
    }

    /**
     * The testimonial usage for each space, against that space's own quota.
     *
     * The quota is a per-space limit, so each space is measured against it
     * separately. Comparing the aggregate count against a single per-space
     * quota would misrepresent the usage.
     *
     * @return Collection<int, array{space: Space, count: int, quota: int, percent: int}>
     */
    #[Computed]
    public function spaceUsage(): Collection
    {
        $user = $this->user;
        $quota = (int) $user->testimonialQuota();

        $counts = Testimonial::query()
            ->where('user_id', $user->id)
            ->selectRaw('space_id, COUNT(*) as total')
            ->groupBy('space_id')
            ->pluck('total', 'space_id');

        return $user->spaces()->get()->map(function (Space $space) use ($counts, $quota): array {
            $count = (int) ($counts[$space->id] ?? 0);

            return [
                'space' => $space,
                'count' => $count,
                'quota' => $quota,
                'percent' => $quota > 0 ? (int) min(100, round(($count / $quota) * 100)) : 0,
            ];
        });
    }

    /**
     * The most recent submissions.
     */
    #[Computed]
    public function recentSubmissions(): Collection
    {
        return Testimonial::query()
            ->where('user_id', auth()->id())
            ->with('space:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.dashboard.overview');
    }
}
