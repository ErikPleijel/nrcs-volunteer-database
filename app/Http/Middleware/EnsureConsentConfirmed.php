<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends every logged-in user with no recorded Code of Conduct acceptance
 * (legacy-imported and staff-registered accounts) to the one-time
 * confirmation page. Appended to the web group before
 * RequiresPolicyAcceptance, so consent comes before the staff policy.
 */
class EnsureConsentConfirmed
{
    private const EXEMPT_ROUTES = [
        'logout',
        'consent.confirm',
        'consent.confirm.store',
        'verification.required',
        'verification.resend',
        'verification.verify',
        'password.confirm',
        'password.confirm.store',
        'photos.show',
        'archived-account.show',
        'privacy-policy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->code_of_conduct_accepted_at !== null) {
            return $next($request);
        }

        if ($request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Please confirm the Code of Conduct and consent before continuing.',
            ], 403);
        }

        // Only a GET can be replayed after confirming; anything else falls
        // back to the controller's default destination.
        return $request->isMethod('GET')
            ? redirect()->guest(route('consent.confirm'))
            : redirect()->route('consent.confirm');
    }
}
