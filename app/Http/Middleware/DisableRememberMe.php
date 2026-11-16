<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class DisableRememberMe
{
    public function handle(Request $request, Closure $next): Response
    {
        $recallerName = Auth::guard('web')->getRecallerName();

        if (! $request->cookies->has($recallerName)) {
            return $next($request);
        }

        $request->cookies->remove($recallerName);

        return $next($request)->withCookie(Cookie::forget($recallerName));
    }
}
