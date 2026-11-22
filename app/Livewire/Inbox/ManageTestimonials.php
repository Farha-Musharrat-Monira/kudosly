<?php

namespace App\Livewire\Inbox;

use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Server-side moderation for the testimonials a signed-in owner has received.
 *
 * Every query is scoped to the signed-in owner, so an owner can never see or
 * moderate another account's submissions. The status column is the single
 * source of truth for a testimonial's state.
 */
class ManageTestimonials extends Component
{
    use WithPagination;

    /**
     * How many testimonials a single page holds.
     */
    public const PER_PAGE = 25;

    /**
     * Free text search across the name, the email and the body.
     */
    public string $search = '';

    /**
     * The status filter. An empty string means "every status".
     */
    public string $status = '';

    /**
     * The space filter. Null means "every space".
     */
    public ?int $spaceId = null;

    /**
     * The testimonial awaiting delete confirmation.
     */
    public ?int $deletingTestimonialId = null;

    /**
     * The message shown after a moderation action.
     */
    public ?string $notice = null;

    /**
     * Render the moderation table.
     */
    public function render()
    {
        return view('livewire.inbox.manage-testimonials', [
            'testimonials' => $this->testimonials,
            'spaces' => $this->spaces,
        ]);
    }

    /**
     * The owner's testimonials, filtered and paginated.
     *
     * Favourites sort first, then the newest. The pagination is server side,
     * so the browser only ever holds one page of results.
     *
     * @return LengthAwarePaginator<int, Testimonial>
     */
    #[Computed]
    public function testimonials()
    {
        return Testimonial::query()
            ->where('user_id', auth()->id())
            ->when($this->search !== '', function ($query): void {
                $term = '%'.str_replace('%', '\%', $this->search).'%';

                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('body', 'like', $term);
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->spaceId !== null, fn ($query) => $query->where('space_id', $this->spaceId))
            ->orderByDesc('is_favorite')
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE);
    }

    /**
     * The owner's spaces, for the space filter.
     *
     * @return Collection<int, Space>
     */
    #[Computed]
    public function spaces()
    {
        return Space::query()
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->get();
    }

    /**
     * Approve a testimonial so it may appear on a public wall.
     *
     * A testimonial without consent stays pending. Approval alone never
     * publishes a testimonial the customer did not agree to share.
     */
    public function approve(int $testimonialId): void
    {
        $testimonial = $this->findTestimonial($testimonialId);

        $testimonial->update([
            'status' => $testimonial->consent_to_share
                ? Testimonial::STATUS_APPROVED
                : Testimonial::STATUS_PENDING,
        ]);

        $this->notice = $testimonial->consent_to_share
            ? 'Testimonial approved.'
            : 'Testimonial approved but held as pending because it has no consent.';

        $this->resetPage();
    }

    /**
     * Hide a testimonial. A hidden testimonial is never shown publicly.
     */
    public function hide(int $testimonialId): void
    {
        $testimonial = $this->findTestimonial($testimonialId);

        $testimonial->update(['status' => Testimonial::STATUS_HIDDEN]);

        $this->notice = 'Testimonial hidden.';

        $this->resetPage();
    }

    /**
     * Restore a hidden testimonial back to the review queue.
     *
     * The status column holds one value at a time, so the state a testimonial
     * held before it was hidden is gone from the database once it is hidden.
     * There is no second status column to remember it in, so the previous
     * state cannot be restored and is never guessed. A hidden testimonial
     * always returns to pending, which puts it back in front of the owner
     * for a deliberate decision. It is never promoted to approved here,
     * because a testimonial the owner never approved must not reach the
     * public wall.
     */
    public function unhide(int $testimonialId): void
    {
        $testimonial = $this->findTestimonial($testimonialId);

        $testimonial->update(['status' => Testimonial::STATUS_PENDING]);

        $this->notice = 'Testimonial restored to pending. '
            .'Its previous status was not stored, so approve it again to publish it.';

        $this->resetPage();
    }

    /**
     * Mark a testimonial as a favourite.
     */
    public function favorite(int $testimonialId): void
    {
        $testimonial = $this->findTestimonial($testimonialId);

        $testimonial->update(['is_favorite' => true]);

        $this->resetPage();
    }

    /**
     * Remove the favourite mark from a testimonial.
     */
    public function unfavorite(int $testimonialId): void
    {
        $testimonial = $this->findTestimonial($testimonialId);

        $testimonial->update(['is_favorite' => false]);

        $this->resetPage();
    }

    /**
     * Heart a testimonial.
     *
     * Only a testimonial that may appear publicly can be hearted, so a
     * pending or hidden testimonial is never given a heart.
     */
    public function heart(int $testimonialId): void
    {
        $testimonial = $this->findTestimonial($testimonialId);

        if (! $testimonial->isPubliclyVisible()) {
            $this->notice = 'Only an approved testimonial with consent can be hearted.';

            return;
        }

        $testimonial->update(['is_heart' => true]);

        $this->resetPage();
    }

    /**
     * Remove the heart from a testimonial.
     */
    public function unheart(int $testimonialId): void
    {
        $testimonial = $this->findTestimonial($testimonialId);

        $testimonial->update(['is_heart' => false]);

        $this->resetPage();
    }

    /**
     * Ask for delete confirmation.
     */
    public function confirmDelete(int $testimonialId): void
    {
        $this->findTestimonial($testimonialId);

        $this->deletingTestimonialId = $testimonialId;

        // The modal is opened only after the ownership check has succeeded.
        // Livewire sends the detail as an object, so the name is passed under
        // the "name" key.
        $this->dispatch('open-modal', name: 'delete-testimonial');
    }

    /**
     * Abandon the delete.
     *
     * The selected testimonial is cleared and the modal is closed, so the
     * two never drift apart. The close event is dispatched from here as
     * well as from the button, so the modal closes even if the button is
     * reached without the vanilla helper.
     */
    public function cancelDelete(): void
    {
        $this->deletingTestimonialId = null;

        $this->dispatch('close-modal', name: 'delete-testimonial');
    }

    /**
     * Delete a testimonial and its private photo for good.
     */
    public function delete(int $testimonialId): void
    {
        $testimonial = $this->findTestimonial($testimonialId);

        $photo = $testimonial->profile_photo;

        $testimonial->delete();

        if (is_string($photo) && $photo !== '') {
            Storage::disk(Testimonial::PHOTO_DISK)->delete($photo);
        }

        $this->deletingTestimonialId = null;

        $this->notice = 'Testimonial deleted.';

        // Close the confirmation modal once the testimonial is gone.
        $this->dispatch('close-modal', name: 'delete-testimonial');

        $this->resetPage();
    }

    /**
     * Clear every filter and return to the first page.
     */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->spaceId = null;

        $this->resetPage();
    }

    /**
     * Reload the computed query state after a filter or a moderation change.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'spaceId'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Find one of the owner's testimonials.
     *
     * The owner scope is applied before the lookup, so a testimonial that
     * belongs to another account is never found.
     */
    protected function findTestimonial(int $testimonialId): Testimonial
    {
        return Testimonial::query()
            ->where('user_id', auth()->id())
            ->findOrFail($testimonialId);
    }
}
