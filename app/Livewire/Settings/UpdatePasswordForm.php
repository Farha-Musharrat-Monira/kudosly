<?php

namespace App\Livewire\Settings;

use App\Actions\Fortify\UpdateUserPassword;
use Livewire\Component;

class UpdatePasswordForm extends Component
{
    /**
     * The user's current password.
     */
    public string $current_password = '';

    /**
     * The new password.
     */
    public string $password = '';

    /**
     * Confirmation of the new password.
     */
    public string $password_confirmation = '';

    /**
     * Update the account password.
     */
    public function save(): void
    {
        app(UpdateUserPassword::class)->update(auth()->user(), [
            'current_password' => $this->current_password,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        // The plaintext passwords are cleared immediately so they are never
        // carried into the next Livewire request payload.
        $this->current_password = '';
        $this->password = '';
        $this->password_confirmation = '';

        session()->flash('status', 'Password updated successfully.');
    }

    public function render()
    {
        return view('livewire.settings.update-password-form');
    }
}
