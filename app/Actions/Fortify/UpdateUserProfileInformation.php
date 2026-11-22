<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Fortify\Fortify;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input): void
    {
        $username = Fortify::username();

        $emailChanged = ($input[$username] ?? $user->{$username}) !== $user->{$username};

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            $username => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ];

        // The current password is only required when the email address
        // changes, so a name-only update never asks for it.
        if ($emailChanged) {
            $rules['current_password'] = ['required', 'string', 'current_password'];
        }

        Validator::make($input, $rules, [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updateProfileInformation');

        if ($emailChanged && $user instanceof MustVerifyEmail) {
            $user->forceFill([
                'name' => $input['name'],
                $username => $input[$username],
                'email_verified_at' => null,
            ])->save();

            $user->sendEmailVerificationNotification();

            return;
        }

        $user->forceFill([
            'name' => $input['name'],
            $username => $input[$username],
        ])->save();
    }
}
