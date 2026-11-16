<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and enforce enterprise security headers.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Prevent Clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Legacy XSS protection for older user agents
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Prevent cross-domain Flash/PDF policy abuse
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Restrict sensitive browser features/APIs
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Content Security Policy (Strict with allowed trusted assets)
        $cspPolicy = "default-src 'self'; "
            ."script-src 'self' 'unsafe-inline'; "
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            ."font-src 'self' https://fonts.gstatic.com data:; "
            ."img-src 'self' data: blob:; "
            ."object-src 'none'; "
            ."connect-src 'self'; "
            ."frame-ancestors 'self'; "
            ."base-uri 'self'; "
            ."form-action 'self';";
        $response->headers->set('Content-Security-Policy', $cspPolicy);

        // Remove server software/version disclosure headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
        if (function_exists('header_remove')) {
            @header_remove('X-Powered-By');
        }

        // If HTTPS, enforce Strict-Transport-Security
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
