<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking
        $response->header('X-Frame-Options', 'DENY');

        // Prevent MIME sniffing
        $response->header('X-Content-Type-Options', 'nosniff');

        // Enable XSS protection
        $response->header('X-XSS-Protection', '1; mode=block');

        // Content Security Policy
        $cspScriptSrc = "'self' 'unsafe-inline'";
        $cspImgSrc = "'self' data: https:";
        $cspConnectSrc = "'self' https://www.googleapis.com https://fonts.bunny.net https://fonts.gstatic.com";
        
        if (app()->environment('local')) {
            // Allow Vite dev server in local development
            $cspScriptSrc .= " http://localhost:5173 ws://localhost:5173";
            $cspImgSrc .= " http://localhost:5173";
            $cspConnectSrc .= " http://localhost:5173 ws://localhost:5173";
        }

        $response->header(
            'Content-Security-Policy',
            "default-src 'self'; script-src {$cspScriptSrc}; style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com; img-src {$cspImgSrc}; font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com; connect-src {$cspConnectSrc};"
        );

        // HSTS (HTTPS Strict Transport Security) - only in production
        if (app()->environment('production')) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Referrer Policy
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy
        $response->header(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=()'
        );

        return $response;
    }
}
