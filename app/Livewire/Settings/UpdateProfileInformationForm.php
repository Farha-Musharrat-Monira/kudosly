<?php

namespace App\Livewire\Settings;

use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Fortify\Fortify;
use Livewire\Component;

class UpdateProfileInformationForm extends Component
{
    /**
     * The user's current name.
     */
    public string $name = '';

    /**
     * The user's current email address.
     */
    public string $email = '';

    /**
     * The user's current password. Only required when the email changes.
     */
    public string $current_password = '';

    /**
     * Mount the form with the authenticated user's details.
     */
    public function mount(): void
    {
        $user = auth()->user();

        $this->name = (string) $user->name;
        $this->email = (string) $user->{Fortify::username()};
    }

    /**
     * Save the updated profile information.
     */
    public function save(): void
    {
        $user = auth()->user();

        app(UpdateUserProfileInformation::class)->update($user, [
            'name' => $this->name,
            Fortify::username() => $this->email,
            'current_password' => $this->current_password,
        ]);

        // The plaintext password is cleared immediately so it is never
        // carried into the next Livewire request payload.
        $this->current_password = '';

        if ($user instanceof MustVerifyEmail && ! $user->fresh()->hasVerifiedEmail()) {
            session()->flash('status', 'Profile updated. Please verify your new email address.');

            return;
        }

        session()->flash('status', 'Profile updated successfully.');
    }

    public function render()
    {
        return view('livewire.settings.update-profile-information-form');
    }
}
