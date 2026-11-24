<?php

namespace App\Support;

class EmailVerification
{
    /**
     * Whether the running environment requires a verified email address.
     *
     * The rule is read from an explicit configuration setting rather than
     * from the application environment. Deciding a security question by
     * comparing an environment string is fragile: a deployment whose
     * environment happens to be named "local" would silently turn
     * verification off, and one named "productionn" would turn it on
     * where it was not expected.
     *
     * The setting defaults to on, so a deployment that does not configure
     * it keeps verification mandatory. Local development turns it off
     * deliberately and visibly, in .env.
     */
    public static function isRequired(): bool
    {
        return (bool) config('kudosly.require_email_verification', true);
    }
}
