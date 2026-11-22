<?php

namespace App\Support;

class EmailVerification
{
    /**
     * Whether the running environment requires a verified email address.
     *
     * Local development skips verification so a new account can reach the
     * dashboard straight away. Every other environment, including production,
     * keeps verification mandatory.
     */
    public static function isRequired(): bool
    {
        return config('app.env') !== 'local';
    }
}
