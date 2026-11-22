<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ForgotPassword extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    /**
     * Send a password reset link to the given email address.
     */
    public function sendResetLink(): void
    {
        $this->validate();

        Password::sendResetLink(['email' => $this->email]);

        session()->flash(
            'status',
            'If an account exists for this email, a password reset link has been sent.'
        );
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
