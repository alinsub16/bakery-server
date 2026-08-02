<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevents MIME-sniffing attacks (browser trusting a declared
        // Content-Type rather than guessing from content).
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Prevents this API's JSON responses from being framed — mostly
        // irrelevant for a pure API, but harmless and cheap.
        $response->headers->set('X-Frame-Options', 'DENY');

        // Disables the browser's legacy XSS filter override — modern
        // browsers ignore this, kept for older client compatibility.
        $response->headers->set('X-XSS-Protection', '0');

        // Restricts what info leaks via the Referer header when links
        // from this API are followed elsewhere.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}