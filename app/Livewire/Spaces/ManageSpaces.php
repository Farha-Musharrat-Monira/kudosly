<?php

namespace App\Livewire\Spaces;

use App\Models\Space;
use App\Models\SpaceField;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ManageSpaces extends Component
{
    /**
     * The space currently being created or edited.
     */
    public ?int $editingSpaceId = null;

    /**
     * Whether the create or edit panel is open.
     */
    public bool $showForm = false;

    /**
     * The space name.
     */
    public string $name = '';

    /**
     * The headline shown on the public page.
     */
    public string $title = '';

    /**
     * The sub-headline shown on the public page.
     */
    public string $subtitle = '';

    /**
     * The prompt asking people to leave a testimonial.
     */
    public string $testimonial_request = '';

    /**
     * The selected theme key.
     */
    public string $theme = Space::DEFAULT_THEME;

    /**
     * Whether the public page asks for a star rating.
     */
    public bool $collect_rating = true;

    /**
     * The optional field keys that are enabled.
     *
     * @var array<int, string>
     */
    public array $enabledFields = [];

    /**
     * The optional field keys that are marked required.
     *
     * A field that is required is always enabled, so this list can only
     * ever contain keys that are also present in the enabled fields.
     *
     * @var array<int, string>
     */
    public array $requiredFields = [];

    /**
     * The ordered keys of the fields shown on the public page.
     *
     * The order is validated against the predefined field keys on save, so a
     * tampered order can never introduce an unknown field.
     *
     * @var array<int, string>
     */
    public array $fieldOrder = [];

    /**
     * The space awaiting deletion.
     */
    public ?int $deletingSpaceId = null;

    /**
     * The id of the space that was just saved.
     *
     * It tells a create apart from an edit so the success panel can say
     * the right thing.
     */
    public ?int $savedSpaceId = null;

    /**
     * Whether the space that was just saved was newly created.
     *
     * The success panel says something different for a new space than it
     * does for an edit.
     */
    public bool $savedAsNew = false;

    /**
     * The slug of the space that was just created, shown in the success panel.
     */
    public ?string $createdSlug = null;

    /**
     * The user's spaces.
     */
    #[Computed]
    public function spaces(): Collection
    {
        return Space::query()
            ->where('user_id', auth()->id())
            ->withCount('testimonials')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Whether the user has reached their space limit.
     */
    #[Computed]
    public function atSpaceLimit(): bool
    {
        return ! auth()->user()->canCreateSpace();
    }

    /**
     * The field keys in the order they are shown on the public page.
     *
     * The order is normalised the same way it is when it is saved, so the
     * form always shows exactly the keys that will be persisted.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function orderedFieldKeys(): array
    {
        return $this->normalisedFieldOrder();
    }

    /**
     * Open a blank form for creating a space.
     */
    public function create(): void
    {
        $this->reset([
            'editingSpaceId',
            'name',
            'title',
            'subtitle',
            'testimonial_request',
            'theme',
            'collect_rating',
            'enabledFields',
            'requiredFields',
            'fieldOrder',
            'createdSlug',
            'savedSpaceId',
            'savedAsNew',
            'deletingSpaceId',
        ]);

        $this->theme = Space::DEFAULT_THEME;
        $this->collect_rating = true;
        $this->enabledFields = [];
        $this->requiredFields = [];
        $this->fieldOrder = array_keys(SpaceField::KEYS);
        $this->savedAsNew = false;
        $this->showForm = true;
    }

    /**
     * Open the form for editing an existing space.
     */
    public function edit(int $spaceId): void
    {
        $space = Space::query()
            // The query is scoped to the authenticated account before the
            // policy runs, so a space owned by another account is reported
            // as missing rather than forbidden. That reveals nothing about
            // the other account's spaces, and the policy still governs
            // every space the account does own.
            ->where('user_id', auth()->id())
            ->findOrFail($spaceId);

        $this->authorize('update', $space);

        $this->editingSpaceId = $space->id;
        $this->name = $space->name;
        $this->title = (string) $space->title;
        $this->subtitle = (string) $space->subtitle;
        $this->testimonial_request = (string) $space->testimonial_request;
        $this->theme = $space->theme;
        $this->collect_rating = (bool) $space->collect_rating;
        $this->enabledFields = $space->fields()
            ->where('enabled', true)
            ->whereNotIn('key', SpaceField::ALWAYS_ON)
            ->pluck('key')
            ->all();
        $this->requiredFields = $space->fields()
            ->where('required', true)
            ->whereNotIn('key', SpaceField::ALWAYS_ON)
            ->pluck('key')
            ->all();
        $this->fieldOrder = $space->fields()
            ->pluck('key')
            ->all();
        $this->createdSlug = null;
        $this->savedSpaceId = null;
        $this->savedAsNew = false;
        $this->deletingSpaceId = null;
        $this->showForm = true;
    }

    /**
     * Save the space.
     */
    public function save(): void
    {
        $isCreating = $this->editingSpaceId === null;

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'testimonial_request' => ['nullable', 'string', 'max:1000'],
            'theme' => ['required', Rule::in(array_keys(Space::THEMES))],
            'collect_rating' => ['boolean'],
            'enabledFields' => ['array'],
            'enabledFields.*' => ['string', Rule::in(array_keys(SpaceField::KEYS))],
            'requiredFields' => ['array'],
            'requiredFields.*' => ['string', Rule::in(array_keys(SpaceField::KEYS))],
            'fieldOrder' => ['array'],
            'fieldOrder.*' => ['string', Rule::in(array_keys(SpaceField::KEYS))],
        ]);

        if ($isCreating) {
            try {
                // The policy is the single authority for the plan limit, so
                // it cannot be bypassed by calling this method directly.
                $this->authorize('create', Space::class);
            } catch (AuthorizationException) {
                // A denial is reported as a validation error on the name
                // field so the form stays open and the reason is visible to
                // the user, instead of a forbidden response.
                throw ValidationException::withMessages([
                    'name' => [__('You have reached the space limit for your plan. Upgrade to Pro to create more spaces.')],
                ]);
            }
        }

        $payload = [
            'name' => $this->name,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'testimonial_request' => $this->testimonial_request,
            'theme' => $this->theme,
            'collect_rating' => $this->collect_rating,
        ];

        $space = DB::transaction(function () use ($payload, $isCreating): Space {
            if ($isCreating) {
                // Lock the user row so concurrent create requests serialise
                // and the quota is re-read from a consistent state.
                $user = auth()->user()->newQuery()
                    ->whereKey(auth()->id())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $user->canCreateSpace()) {
                    throw ValidationException::withMessages([
                        'name' => __('You have reached the space limit for your plan. Upgrade to create more spaces.'),
                    ]);
                }

                $space = Space::createWithUniqueSlug([
                    ...$payload,
                    'user_id' => $user->id,
                ], $this->name);

                SpaceField::defaultsFor($space);

                $this->applyFields($space);

                return $space;
            }

            $space = Space::query()
                ->where('user_id', auth()->id())
                ->findOrFail($this->editingSpaceId);

            $this->authorize('update', $space);

            $space->update($payload);

            $this->applyFields($space);

            return $space;
        });

        $this->createdSlug = $space->slug;
        $this->savedSpaceId = $space->id;
        $this->savedAsNew = $isCreating;
        $this->showForm = false;
        $this->editingSpaceId = null;

        unset($this->spaces);
    }

    /**
     * Move a field one position earlier in the collection order.
     */
    public function moveFieldUp(string $key): void
    {
        $this->moveField($key, -1);
    }

    /**
     * Move a field one position later in the collection order.
     */
    public function moveFieldDown(string $key): void
    {
        $this->moveField($key, 1);
    }

    /**
     * Move a field within the collection order.
     *
     * The order is rebuilt from the known field keys on every move, so an
     * unknown key can never enter the list and a missing key is restored.
     */
    protected function moveField(string $key, int $offset): void
    {
        $order = $this->normalisedFieldOrder();

        $index = array_search($key, $order, true);

        if ($index === false) {
            return;
        }

        $target = $index + $offset;

        if ($target < 0 || $target >= count($order)) {
            return;
        }

        [$order[$index], $order[$target]] = [$order[$target], $order[$index]];

        $this->fieldOrder = $order;
    }

    /**
     * The field order with only known keys, every known key present once.
     *
     * Unknown keys are dropped and duplicated keys are collapsed, so a
     * tampered order cannot introduce a field that does not exist.
     *
     * @return array<int, string>
     */
    protected function normalisedFieldOrder(): array
    {
        $order = [];

        foreach ($this->fieldOrder as $key) {
            if (! is_string($key) || ! array_key_exists($key, SpaceField::KEYS)) {
                continue;
            }

            if (in_array($key, $order, true)) {
                continue;
            }

            $order[] = $key;
        }

        foreach (array_keys(SpaceField::KEYS) as $key) {
            if (! in_array($key, $order, true)) {
                $order[] = $key;
            }
        }

        return $order;
    }

    /**
     * Apply the field configuration to the space.
     *
     * Name and email are always collected and always required. The values
     * are written directly rather than being reset first, so the guarantee
     * does not depend on a second write restoring it.
     */
    protected function applyFields(Space $space): void
    {
        $positions = array_flip($this->normalisedFieldOrder());

        foreach (SpaceField::KEYS as $key => $config) {
            if (in_array($key, SpaceField::ALWAYS_ON, true)) {
                $space->fields()->where('key', $key)->update([
                    'enabled' => true,
                    'required' => true,
                    'position' => $positions[$key],
                ]);

                continue;
            }

            $enabled = in_array($key, $this->enabledFields, true);

            // A field that is not collected can never be required.
            $required = $enabled && in_array($key, $this->requiredFields, true);

            $space->fields()->where('key', $key)->update([
                'enabled' => $enabled,
                'required' => $required,
                'position' => $positions[$key],
            ]);
        }
    }

    /**
     * Publish or unpublish a space.
     *
     * The space is scoped to the authenticated account before the policy
     * runs, so a space owned by another account is reported as missing
     * rather than forbidden. Publishing is a change to the space, so it is
     * governed by the same update ability as editing.
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

        unset($this->spaces);
    }

    /**
     * Show the delete confirmation for a space.
     */
    public function confirmDelete(int $spaceId): void
    {
        $space = Space::query()
            ->where('user_id', auth()->id())
            ->findOrFail($spaceId);

        $this->authorize('delete', $space);

        $this->deletingSpaceId = $space->id;

        // The modal is opened only after the ownership check and the policy
        // authorization have both succeeded. Livewire sends the detail as an
        // object, so the name is passed under the "name" key.
        $this->dispatch('open-modal', name: 'delete-space');
    }

    /**
     * Cancel the delete confirmation and clear the pending space.
     */
    public function cancelDelete(): void
    {
        $this->deletingSpaceId = null;
    }

    /**
     * Delete the confirmed space.
     */
    public function delete(): void
    {
        $space = Space::query()
            ->where('user_id', auth()->id())
            ->findOrFail($this->deletingSpaceId);

        $this->authorize('delete', $space);

        $space->delete();

        $this->deletingSpaceId = null;
        $this->savedSpaceId = null;
        $this->createdSlug = null;
        $this->savedAsNew = false;

        // Close the confirmation modal once the space is gone.
        $this->dispatch('close-modal', name: 'delete-space');

        unset($this->spaces);
    }

    /**
     * Close the form without saving.
     */
    public function cancel(): void
    {
        $this->showForm = false;
        $this->editingSpaceId = null;
        $this->deletingSpaceId = null;
        $this->savedAsNew = false;
    }

    /**
     * Dismiss the success panel.
     */
    public function dismissSuccess(): void
    {
        $this->createdSlug = null;
        $this->savedSpaceId = null;
        $this->savedAsNew = false;
    }

    public function render()
    {
        return view('livewire.spaces.manage-spaces');
    }
}
