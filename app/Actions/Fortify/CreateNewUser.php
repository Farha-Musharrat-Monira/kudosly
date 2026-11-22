<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\EmailVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Fortify;
use RuntimeException;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $username = Fortify::username();

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            $username => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ])->validate();

        // The account and its Free plan are created together. If the plan
        // cannot be assigned the whole registration is rolled back, so no
        // orphaned account is left behind.
        //
        // The plan column is deliberately not fillable, so it is never set
        // here. The User model's created hook assigns the persisted Free
        // plan, which keeps the rule true for every creation path without
        // assigning it twice.
        return DB::transaction(function () use ($input, $username): User {
            $user = User::create([
                'name' => $input['name'],
                $username => $input[$username],
                'password' => Hash::make($input['password']),
            ]);

            if (blank($user->plan_id)) {
                throw new RuntimeException(
                    'The Free plan could not be assigned. Please run the database migrations.'
                );
            }

            // Local development does not verify email addresses, so the account
            // is treated as verified from the start. Every other environment
            // leaves the account unverified until the emailed link is followed.
            if (! EmailVerification::isRequired()) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return $user;
        });
    }
}
