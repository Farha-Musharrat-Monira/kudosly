<?php

namespace App\Livewire\Auth;

use App\Support\EmailVerification;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Login extends Component
{
    #[Validate('required|string')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('nullable|boolean')]
    public bool $remember = false;

    /**
     * Attempt to authenticate the request.
     */
    public function authenticate(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->credentials(), $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // Prevent session fixation by rotating the session id on login.
        session()->regenerate();

        // Local development does not verify email addresses, so an unverified
        // account is allowed straight through. Every other environment keeps
        // verification mandatory.
        if (Features::enabled(Features::emailVerification()) && EmailVerification::isRequired()) {
            if (! Auth::user()->hasVerifiedEmail()) {
                $this->redirectIntended(route('verification.notice'), navigate: false);

                return;
            }
        }

        $this->redirectIntended(Fortify::redirects('login'), navigate: false);
    }

    /**
     * Ensure the login request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication request credentials.
     *
     * @return array<string, string>
     */
    protected function credentials(): array
    {
        return [
            Fortify::username() => $this->email,
            'password' => $this->password,
        ];
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
