<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TouchAdminActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only fire on state-changing requests for authenticated users
        if (Auth::check() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            Auth::user()->touchLastAdminActivity();
        }

        return $response;
    }
}
