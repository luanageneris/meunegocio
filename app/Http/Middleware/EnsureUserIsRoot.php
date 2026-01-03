<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsRoot
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isRoot()) {
            return response()->json([
                'message' => 'Unauthorized. Only root users can perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
