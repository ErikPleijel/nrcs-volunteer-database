<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresPolicyAcceptance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        // The verification routes are exempt because policy.accept sits behind
        // verified.or.absent: a role holder with an unverified email would
        // otherwise bounce between policy.accept and verification.required.
        // The consent routes are exempt because EnsureConsentConfirmed runs
        // first and sends unconsented users there; redirecting them on to
        // policy.accept would bounce them straight back.
        $exemptRoutes = [
            'policy.accept', 'policy.accept.store', 'logout',
            'verification.required', 'verification.resend', 'verification.verify',
            'consent.confirm', 'consent.confirm.store',
        ];
        if ($request->routeIs(...$exemptRoutes)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user->getRoleNames()->isEmpty()) {
            return $next($request);
        }

        if ($user->policy_accepted_at === null) {
            return redirect()->route('policy.accept');
        }

        return $next($request);
    }
}
