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

        $exemptRoutes = ['policy.accept', 'policy.accept.store', 'logout'];
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
