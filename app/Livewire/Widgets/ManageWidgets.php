<?php

namespace App\Livewire\Widgets;

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\Widget;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The owner's widget builder.
 *
 * A widget belongs to a space and decides how that space's testimonials
 * are presented on somebody else's website. The builder lets the owner
 * choose the layout, the theme, the background colour, whether the cards
 * animate and how many testimonials are shown, and it shows a live
 * preview of exactly that.
 *
 * The preview and the public embed share one template, so the preview
 * cannot drift away from what a visitor actually sees.
 *
 * Every query is scoped to the signed-in account, so an owner never sees
 * another account's spaces or widgets. The space a widget is created for
 * is checked for ownership on the server, not merely hidden in the
 * browser.
 */
class ManageWidgets extends Component
{
    /**
     * The space the widget being edited belongs to.
     */
    public ?int $spaceId = null;

    /**
     * The layout of the widget.
     */
    public string $layout = Widget::DEFAULT_LAYOUT;

    /**
     * The theme of the widget.
     */
    public string $theme = Widget::DEFAULT_THEME;

    /**
     * The background colour of the widget.
     */
    public string $backgroundColor = Widget::DEFAULT_BACKGROUND_COLOR;

    /**
     * Whether the cards animate when they appear.
     */
    public bool $hasAnimation = true;

    /**
     * How many testimonials the widget shows.
     *
     * The value is held as a string rather than an integer because it comes
     * straight from a number input. A visitor may clear the field or type
     * something that is not a number, and a typed integer property would
     * raise a TypeError on that assignment and break the page. The value is
     * validated before it is used, and only cast to an integer once it is
     * known to be one.
     */
    public string $displayLimit = (string) Widget::DEFAULT_DISPLAY_LIMIT;

    /**
     * The message shown after an action.
     */
    public ?string $notice = null;

    /**
     * The error shown when the widget could not be saved.
     */
    public ?string $error = null;

    /**
     * Render the builder.
     */
    public function render()
    {
        return view('livewire.widgets.manage-widgets', [
            'spaces' => $this->spaces,
            'widget' => $this->widget,
        ]);
    }

    /**
     * The owner's spaces that may be embedded.
     *
     * A space may be embedded once. The spaces that already have a widget
     * are listed as well, so the owner can see which space is already
     * being embedded and edit that widget instead.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Space>
     */
    #[Computed]
    public function spaces()
    {
        return Space::query()
            ->where('user_id', auth()->id())
            ->with('widget')
            ->orderBy('name')
            ->get();
    }

    /**
     * The widget of the selected space.
     *
     * The space is scoped to the authenticated account, so a space owned by
     * another account is reported as missing rather than forbidden. That
     * reveals nothing about the other account's spaces.
     */
    #[Computed]
    public function widget(): ?Widget
    {
        if ($this->spaceId === null) {
            return null;
        }

        $space = $this->selectedSpace;

        if ($space === null) {
            return null;
        }

        return $space->widget;
    }

    /**
     * The testimonials the preview shows.
     *
     * The preview uses the same visibility rule as the public embed, so an
     * owner never sees a pending, hidden or unconsented testimonial in the
     * preview either.
     *
     * @return Collection<int, Testimonial>
     */
    #[Computed]
    public function previewTestimonials()
    {
        $space = $this->selectedSpace;

        if ($space === null) {
            return collect();
        }

        return $space->testimonials()
            ->publiclyVisible()
            ->orderByDesc('created_at')
            ->limit($this->previewLimit())
            ->get();
    }

    /**
     * The space whose widget is being edited or created.
     *
     * The space is scoped to the authenticated account before it is used,
     * so a space belonging to another account is never reached.
     */
    #[Computed]
    public function selectedSpace(): ?Space
    {
        if ($this->spaceId === null) {
            return null;
        }

        return Space::query()
            ->where('user_id', auth()->id())
            ->find($this->spaceId);
    }

    /**
     * Load a space's widget into the builder.
     *
     * The space is scoped to the authenticated account before the policy
     * runs, so a space owned by another account is reported as missing
     * rather than forbidden.
     */
    public function edit(int $spaceId): void
    {
        $space = Space::query()
            ->where('user_id', auth()->id())
            ->findOrFail($spaceId);

        $widget = $space->widget;

        // A space that already has a widget is governed by the widget's
        // own policy. A space that has none yet is governed by the space's
        // policy, because the decision being made is whether this space
        // may be embedded at all.
        $this->authorize('update', $widget ?? $space);

        $this->spaceId = $space->id;
        $this->notice = null;
        $this->error = null;

        if ($widget === null) {
            $this->resetSettings();

            return;
        }

        $this->layout = $widget->layout;
        $this->theme = $widget->theme;
        $this->backgroundColor = $widget->background_color;
        $this->hasAnimation = $widget->has_animation;
        $this->displayLimit = (string) $widget->effectiveDisplayLimit();
    }

    /**
     * Save the widget for the selected space.
     *
     * The space is checked for ownership on the server. The settings are
     * validated against the values the model itself supports, so an
     * unexpected layout or theme is refused rather than stored.
     */
    public function save(): void
    {
        $this->error = null;
        $this->notice = null;

        $space = $this->selectedSpace;

        if ($space === null) {
            $this->error = 'Choose one of your spaces to embed.';

            return;
        }

        $widget = $space->widget;

        $this->authorize('update', $widget ?? $space);

        $this->validate();

        $attributes = [
            'space_id' => $space->id,
            'layout' => $this->layout,
            'theme' => $this->theme,
            'background_color' => $this->backgroundColor,
            'has_animation' => $this->hasAnimation,
            'display_limit' => $this->validatedDisplayLimit(),
        ];

        if ($widget === null) {
            $widget = Widget::createWithPublicId($attributes);

            $this->notice = 'Widget created. Copy an embed snippet to put it on your website.';
        } else {
            $widget->update($attributes);

            $this->notice = 'Widget updated.';
        }

        unset($this->widget, $this->spaces, $this->previewTestimonials);
    }

    /**
     * Delete the widget of the selected space.
     */
    public function delete(): void
    {
        $this->error = null;
        $this->notice = null;

        $space = $this->selectedSpace;

        if ($space === null) {
            return;
        }

        $widget = $space->widget;

        if ($widget === null) {
            return;
        }

        $this->authorize('delete', $widget);

        $widget->delete();

        $this->resetSettings();

        $this->notice = 'Widget deleted.';

        unset($this->widget, $this->spaces, $this->previewTestimonials);
    }

    /**
     * The number of testimonials the preview shows.
     *
     * The builder's own value is whatever the owner has typed so far, so it
     * may be empty or not a number while the field is being edited. The
     * preview must not break on that, and it must never quietly show
     * nothing because the value could not be read.
     *
     * A readable value is used as it stands. An unreadable one falls back
     * to the limit the widget already has, or to the default, so the
     * preview keeps showing the widget rather than an empty box.
     */
    protected function previewLimit(): int
    {
        if (is_numeric($this->displayLimit)) {
            return (int) $this->displayLimit;
        }

        $widget = $this->widget;

        return $widget?->effectiveDisplayLimit() ?? Widget::DEFAULT_DISPLAY_LIMIT;
    }

    /**
     * The display limit as an integer.
     *
     * This is only called once the builder's rules have passed, so the
     * value is known to be a whole number inside the allowed range by the
     * time it is cast. Casting an unreadable value would silently turn it
     * into zero, which is why the cast lives here and not earlier.
     */
    protected function validatedDisplayLimit(): int
    {
        return (int) $this->displayLimit;
    }

    /**
     * Validate the builder's settings.
     */
    protected function rules(): array
    {
        return [
            'layout' => ['required', 'string', Rule::in(array_keys(Widget::LAYOUTS))],
            'theme' => ['required', 'string', Rule::in(array_keys(Widget::THEMES))],
            'backgroundColor' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'hasAnimation' => ['boolean'],
            'displayLimit' => [
                'required',
                'integer',
                'min:'.Widget::MIN_DISPLAY_LIMIT,
                'max:'.Widget::MAX_DISPLAY_LIMIT,
            ],
        ];
    }

    /**
     * Validation messages for the builder.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'layout' => 'layout',
            'theme' => 'theme',
            'backgroundColor' => 'background colour',
            'hasAnimation' => 'animation',
            'displayLimit' => 'display limit',
        ];
    }

    /**
     * Reset the builder to its defaults.
     */
    protected function resetSettings(): void
    {
        $this->layout = Widget::DEFAULT_LAYOUT;
        $this->theme = Widget::DEFAULT_THEME;
        $this->backgroundColor = Widget::DEFAULT_BACKGROUND_COLOR;
        $this->hasAnimation = true;
        $this->displayLimit = (string) Widget::DEFAULT_DISPLAY_LIMIT;
    }
}
