<?php

namespace App\Livewire\Auth;

use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ResetPassword extends Component
{
    #[Validate('required|string|confirmed')]
    public string $password = '';

    #[Validate('nullable|string')]
    public string $password_confirmation = '';

    /**
     * The reset token and email address supplied by the reset link.
     */
    public string $token = '';

    public string $email = '';

    /**
     * Populate the form from the password reset link.
     *
     * The token and email may already be set when the component is mounted
     * with explicit parameters, so they are only read from the request when
     * they are still empty.
     */
    public function mount(Request $request): void
    {
        if ($this->token === '') {
            $this->token = (string) $request->route('token', '');
        }

        if ($this->email === '') {
            $this->email = (string) $request->string('email', '');
        }
    }

    /**
     * Reset the account password.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user, string $password): void {
                app(ResetUserPassword::class)->reset($user, [
                    'password' => $password,
                    'password_confirmation' => $this->password_confirmation,
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        $this->redirect(route('login'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
