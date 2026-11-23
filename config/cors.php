<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Only the public, read-only widget embed endpoint is cross-origin. The
    | widget is meant to be embedded on somebody else's website, so the
    | JavaScript loader on the host page has to be allowed to read the
    | fragment over a cross-origin request.
    |
    | Every other route keeps the browser's same-origin policy. The dashboard,
    | the billing page, the collection pages and the Stripe webhook are all
    | untouched by this configuration.
    |
    */

    'paths' => ['widget/*/embed'],

    /*
     * The embed endpoint is read-only. A cross-origin request may read it,
     | but it may never change anything.
     */
    'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],

    /*
     * A widget is embedded on arbitrary customer websites, so the origin
     * cannot be an allowlist of known hosts.
     *
     * The wildcard is safe here because the endpoint is public, anonymous
     * and read-only, and because credentialed requests are switched off
     * below. A hostile page gains no privilege: it can already read the
     * same fragment from a browser address bar.
     */
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type'],

    'exposed_headers' => [],

    /*
     * A host page that embeds several widgets should not make a preflight
     * request for each one. The value also bounds how long a change to
     * this policy takes to reach a browser.
     */
    'max_age' => 600,

    /*
     * Credentialed cross-origin requests are deliberately NOT enabled.
     *
     * The embed endpoint carries no session cookie, no CSRF token and no
     * personal data. Keeping credentials off means a hostile page cannot
     * make the browser attach a visitor's session to the request, so the
     * endpoint can never be used to read anything the visitor could not
     * already read anonymously.
     */
    'supports_credentials' => false,

];
