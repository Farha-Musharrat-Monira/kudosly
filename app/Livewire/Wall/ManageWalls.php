<?php

namespace App\Livewire\Wall;

use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The owner's Wall of Love management page.
 *
 * A wall belongs to a space, exactly as the product requirements describe:
 * each space has a public wall at /wall/{slug}. The wall itself is not a
 * record of its own. What is on the wall is derived from the space's
 * testimonials, so the page never stores a second copy of that decision.
 *
 * The page lists the spaces the signed-in account owns, the public URL of
 * each space's wall, how many testimonials are on it, and how many are
 * eligible to go on it. Every query is scoped to the signed-in account, so
 * an owner never sees another account's spaces or their testimonials.
 *
 * The heart and unheart actions live in the Inbox too, where the moderation
 * decisions are made. This page is where an owner finds the wall, chooses
 * what goes on it, and shares it.
 */
class ManageWalls extends Component
{
    /**
     * The space whose wall is being edited.
     *
     * Null means no picker is open.
     */
    public ?int $managingSpaceId = null;

    /**
     * The message shown after an action.
     */
    public ?string $notice = null;

    /**
     * The status filter for the testimonial picker.
     *
     * An empty string means "every status", which is the same convention the
     * Inbox uses. The value is one of the statuses the Testimonial model
     * defines, so a value that is no longer valid is rejected rather than
     * silently matching nothing.
     */
    public string $status = '';

    /**
     * The statuses the picker may filter by.
     *
     * These are the statuses the product actually uses. They are read from
     * the model rather than repeated here, so the filter can never offer a
     * status the database does not have.
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            Testimonial::STATUS_PENDING => 'Pending',
            Testimonial::STATUS_APPROVED => 'Approved',
            Testimonial::STATUS_HIDDEN => 'Hidden',
        ];
    }

    /**
     * Render the management page.
     */
    public function render()
    {
        return view('livewire.wall.manage-walls', [
            'spaces' => $this->spaces,
        ]);
    }

    /**
     * The owner's spaces, with the number of testimonials on each wall.
     *
     * The wall count is the number of testimonials that are both hearted
     * and publicly visible, which is exactly what the public wall shows.
     *
     * The eligible count is the number of testimonials that may be put on
     * the wall, whether or not they are on it yet.
     *
     * @return Collection<int, Space>
     */
    #[Computed]
    public function spaces()
    {
        return Space::query()
            ->where('user_id', auth()->id())
            ->withCount([
                'testimonials as wall_testimonials_count' => function ($query): void {
                    $query->hearted()->publiclyVisible();
                },
                'testimonials as eligible_testimonials_count' => function ($query): void {
                    $query->publiclyVisible();
                },
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * The space whose wall is being edited.
     *
     * The space is scoped to the authenticated account, so an id that
     * points at another account's space resolves to null rather than to
     * that space.
     */
    #[Computed]
    public function managingSpace(): ?Space
    {
        if ($this->managingSpaceId === null) {
            return null;
        }

        return Space::query()
            ->where('user_id', auth()->id())
            ->find($this->managingSpaceId);
    }

    /**
     * The testimonials of the managed space that may go on its wall.
     *
     * Every testimonial of the space is listed, not only the eligible
     * ones, so an owner can see why a testimonial is not on the wall. The
     * customer's email address is never selected. It is private data and
     * has no place on a wall.
     *
     * The status filter narrows the list to one moderation status. It only
     * changes which testimonials are shown: it never changes whether a
     * testimonial may be put on the wall, because that decision is made by
     * isPubliclyVisible() when the owner clicks the button.
     *
     * @return Collection<int, Testimonial>
     */
    #[Computed]
    public function testimonials()
    {
        $space = $this->managingSpace;

        if ($space === null) {
            return new Collection;
        }

        return $space->testimonials()
            ->select([
                'id',
                'space_id',
                'user_id',
                'name',
                'body',
                'rating',
                'consent_to_share',
                'status',
                'is_heart',
                'is_favorite',
                'created_at',
                'updated_at',
            ])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderByDesc('is_heart')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Open the picker for a space.
     *
     * The space is scoped to the authenticated account before the policy
     * runs, so a space owned by another account is reported as missing
     * rather than forbidden. That reveals nothing about the other
     * account's spaces.
     */
    public function manage(int $spaceId): void
    {
        $space = Space::query()
            ->where('user_id', auth()->id())
            ->findOrFail($spaceId);

        $this->authorize('update', $space);

        $this->managingSpaceId = $space->id;
        $this->notice = null;

        // The filter belongs to the picker, so it starts fresh on every
        // space. Otherwise a filter left behind on one space would silently
        // hide testimonials on the next one.
        $this->status = '';
    }

    /**
     * Validate the status filter as it changes.
     *
     * Only the statuses the product actually uses are accepted. A value that
     * is not a real status falls back to "every status" rather than matching
     * nothing, so the picker never appears empty for no reason.
     */
    public function updated(string $property): void
    {
        if ($property !== 'status') {
            return;
        }

        if ($this->status !== '' && ! array_key_exists($this->status, self::statusOptions())) {
            $this->status = '';
        }
    }

    /**
     * Close the picker.
     */
    public function closePicker(): void
    {
        $this->managingSpaceId = null;
        $this->notice = null;
    }

    /**
     * Put an eligible testimonial on the wall.
     *
     * Only a testimonial that may appear publicly can be hearted, so a
     * pending, hidden or unconsented testimonial is refused on the server
     * and not merely greyed out in the browser.
     */
    public function heart(int $testimonialId): void
    {
        $testimonial = $this->findTestimonial($testimonialId);

        if (! $testimonial->isPubliclyVisible()) {
            $this->notice = 'Only an approved testimonial with consent can go on a wall.';

            return;
        }

        $testimonial->update(['is_heart' => true]);

        $this->notice = 'Testimonial added to your wall.';

        unset($this->spaces, $this->testimonials);
    }

    /**
     * Take a testimonial off the wall.
     *
     * Removing a heart is always allowed, even for a testimonial that is
     * no longer publicly visible, so an owner is never stuck with a
     * testimonial they cannot see on the wall and cannot take off it.
     */
    public function unheart(int $testimonialId): void
    {
        $testimonial = $this->findTestimonial($testimonialId);

        $testimonial->update(['is_heart' => false]);

        $this->notice = 'Testimonial removed from your wall.';

        unset($this->spaces, $this->testimonials);
    }

    /**
     * Publish or unpublish the wall of a space.
     *
     * Publishing is a change to the space, so it is governed by the same
     * update ability as editing. The space is scoped to the authenticated
     * account before the policy runs, so a space owned by another account
     * is reported as missing rather than forbidden.
     */
    public function togglePublish(int $spaceId): void
    {
        $space = Space::query()
            ->where('user_id', auth()->id())
            ->findOrFail($spaceId);

        $this->authorize('update', $space);

        $space->update([
            'is_published' => ! $space->is_published,
        ]);

        $this->notice = $space->is_published
            ? 'Wall published. Anyone with the link can see it.'
            : 'Wall unpublished. The public page is no longer available.';

        unset($this->spaces);
    }

    /**
     * Find one of the owner's testimonials on the open wall.
     *
     * The owner scope is applied before the lookup, so a testimonial that
     * belongs to another account is never found.
     *
     * The lookup is also scoped to the space whose wall is open. A wall
     * belongs to exactly one space, so a testimonial from another space
     * of the same account must never be added to or removed from this
     * wall. Without this scope an owner could manipulate any of their
     * own testimonials while a picker is open, and the change would
     * silently land on the wrong wall.
     */
    protected function findTestimonial(int $testimonialId): Testimonial
    {
        $space = $this->managingSpace;

        if ($space === null) {
            // The picker is not open, so there is no wall to change.
            throw ValidationException::withMessages([
                'testimonial' => [__('No wall is open.')],
            ]);
        }

        return $space->testimonials()
            ->where('user_id', auth()->id())
            ->findOrFail($testimonialId);
    }
}
