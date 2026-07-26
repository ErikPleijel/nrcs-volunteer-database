<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerifiedOrAbsent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $exemptRoutes = ['verification.required', 'verification.resend', 'logout'];
        if ($request->routeIs(...$exemptRoutes)) {
            return $next($request);
        }

        // Same condition as the banner in profile/show.blade.php:24 — only
        // gate users who have an email and haven't verified it. Users with
        // no email on file (e.g. phone-only admin-registered accounts) pass
        // through untouched.
        if (! $user->hasVerifiedEmail() && $user->email) {
            return redirect()->route('verification.required');
        }

        return $next($request);
    }
}
