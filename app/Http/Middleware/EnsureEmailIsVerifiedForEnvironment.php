<?php

namespace App\Http\Middleware;

use App\Support\EmailVerification;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require a verified email address only in environments that need it.
 *
 * This mirrors the framework's "verified" middleware, but skips the check when
 * email verification is not required for the running environment. It never
 * bypasses authentication: it only runs after the "auth" middleware has
 * already resolved the user.
 */
class EnsureEmailIsVerifiedForEnvironment
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! EmailVerification::isRequired()) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof MustVerifyEmail) {
            return $next($request);
        }

        if ($user->hasVerifiedEmail()) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Your email address is not verified.'], 403)
            : redirect()->route('verification.notice');
    }
}
