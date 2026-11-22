<?php

namespace App\Livewire\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Support\EmailVerification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Register extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|email|max:255')]
    public string $email = '';

    #[Validate('required|string|confirmed')]
    public string $password = '';

    #[Validate('nullable|string')]
    public string $password_confirmation = '';

    /**
     * Register a new account.
     *
     * The account and its Free plan are created together inside the
     * CreateNewUser action's database transaction, so no orphaned account is
     * left behind when the plan cannot be assigned.
     */
    public function register(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            Fortify::username() => ['required', 'string', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = app(CreateNewUser::class)->create([
            'name' => $this->name,
            Fortify::username() => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        Auth::login($user);

        session()->regenerate();

        // Local development does not verify email addresses, so no
        // verification email is sent and the account goes straight to the
        // dashboard. Every other environment keeps verification mandatory.
        if (Features::enabled(Features::emailVerification()) && EmailVerification::isRequired()) {
            $user->sendEmailVerificationNotification();

            $this->redirect(route('verification.notice'), navigate: false);

            return;
        }

        $this->redirect(route('dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
