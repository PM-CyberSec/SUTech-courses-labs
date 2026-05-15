<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(40);
        $request->attributes->set('csp_nonce', $nonce);
        View::share('cspNonce', $nonce);

        if ($this->mustEnforceHttps($request)) {
            if (in_array($request->method(), ['GET', 'HEAD'], true)) {
                return redirect()->secure($request->getRequestUri(), 301);
            }

            return response()->json([
                'message' => 'HTTPS is required',
            ], 400);
        }

        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));

        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }

    private function mustEnforceHttps(Request $request): bool
    {
        if (! app()->environment('production')) {
            return false;
        }

        if ($request->isSecure()) {
            return false;
        }

        $forwardedProto = strtolower((string) $request->header('X-Forwarded-Proto', ''));

        return $forwardedProto !== 'https';
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self' 'nonce-{$nonce}'",
            "connect-src 'self' ws: wss:",
            "font-src 'self' data:",
            "upgrade-insecure-requests",
        ]);
    }
}
