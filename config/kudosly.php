<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email verification
    |--------------------------------------------------------------------------
    |
    | Whether a new account must verify its email address before it can
    | reach the dashboard.
    |
    | This is an explicit setting rather than a check on the application
    | environment. An environment check couples a security decision to a
    | string that is easy to get wrong: an environment named "local" that
    | reaches production silently turns verification off, and an
    | environment named "productionn" turns it on where it was not
    | expected.
    |
    | The setting is read from the environment with a default of true, so a
    | deployment that does not configure it keeps verification on. Local
    | development turns it off deliberately, in .env, where it is visible.
    |
    */

    'require_email_verification' => (bool) env('KUDOSLY_REQUIRE_EMAIL_VERIFICATION', true),

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | The proxies whose forwarded headers may be trusted, and how many
    | proxies sit in front of the application.
    |
    | Behind a load balancer or a CDN the application would otherwise see
    | the proxy's address rather than the visitor's, which breaks rate
    | limiting and makes the application build links with the wrong scheme.
    |
    | The setting is empty by default: nothing is trusted until a
    | deployment says so. An untrusted proxy header must never be
    | honoured, so the list is never populated implicitly.
    |
    */

    'trusted_proxies' => (string) env('TRUSTED_PROXIES', ''),

    /*
    |--------------------------------------------------------------------------
    | The public Wall of Love
    |--------------------------------------------------------------------------
    |
    | How many testimonials the public wall shows at once.
    |
    | The wall is public and anonymous, so it is fetched by whoever embeds
    | the link. Without a ceiling a single space with a large number of
    | collected testimonials would render every one of them on every
    | request, which is both slow and easy to abuse.
    |
    */

    'wall_display_limit' => (int) env('KUDOSLY_WALL_DISPLAY_LIMIT', 100),

];
