<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectSuperAdmins
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_super_admin) {
            return redirect()->route('reports.dashboard');
        }

        return $next($request);
    }
}
