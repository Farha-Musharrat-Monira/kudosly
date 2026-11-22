<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses webhook deliveries when no webhook secret is configured.
 *
 * Signature verification is only meaningful when there is a secret to verify
 * against. Without one, the signature check would compare the delivery
 * against an empty key, which a forged payload could satisfy just as easily
 * as a genuine one. The endpoint therefore stays closed until a secret is
 * configured, rather than accepting deliveries it cannot authenticate.
 */
class EnsureWebhookSecretIsConfigured
{
    /**
     * Handle the incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('cashier.webhook.secret');

        if (! is_string($secret) || $secret === '') {
            return response('Webhook endpoint is not configured.', 503);
        }

        return $next($request);
    }
}
