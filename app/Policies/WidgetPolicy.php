<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Widget;

class WidgetPolicy
{
    /**
     * Determine whether the user can view the widget.
     */
    public function view(User $user, Widget $widget): bool
    {
        return $this->owns($user, $widget);
    }

    /**
     * Determine whether the user can update the widget.
     */
    public function update(User $user, Widget $widget): bool
    {
        return $this->owns($user, $widget);
    }

    /**
     * Determine whether the user can delete the widget.
     */
    public function delete(User $user, Widget $widget): bool
    {
        return $this->owns($user, $widget);
    }

    /**
     * Determine whether the user can create a widget.
     *
     * Widget creation is deliberately not limited by the space quota. The
     * two are separate concerns: an account that has reached its space
     * limit can still embed the spaces it already owns. Which spaces may
     * be embedded is decided when the widget is saved, by checking that
     * the space belongs to the signed-in account.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user owns the widget.
     *
     * A widget has no owner column of its own. Ownership is reached
     * through the space, which is what the widget belongs to, so a
     * widget can never point at a different account than its space.
     */
    protected function owns(User $user, Widget $widget): bool
    {
        $space = $widget->space;

        return $space !== null && $space->user_id === $user->id;
    }
}
