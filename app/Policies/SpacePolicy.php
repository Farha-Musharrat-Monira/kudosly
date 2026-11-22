<?php

namespace App\Policies;

use App\Models\Space;
use App\Models\User;

class SpacePolicy
{
    /**
     * Determine whether the user can view the space.
     */
    public function view(User $user, Space $space): bool
    {
        return $this->owns($user, $space);
    }

    /**
     * Determine whether the user can update the space.
     */
    public function update(User $user, Space $space): bool
    {
        return $this->owns($user, $space);
    }

    /**
     * Determine whether the user can delete the space.
     */
    public function delete(User $user, Space $space): bool
    {
        return $this->owns($user, $space);
    }

    /**
     * Determine whether the user can create a space.
     *
     * Plan limits are enforced here so the rule cannot be bypassed by
     * calling the endpoint directly.
     */
    public function create(User $user): bool
    {
        return $user->canCreateSpace();
    }

    /**
     * Determine whether the user owns the space.
     */
    protected function owns(User $user, Space $space): bool
    {
        return $space->user_id === $user->id;
    }
}
