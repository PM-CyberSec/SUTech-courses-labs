<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAgent
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = (string) config('app.dlds_api_key', '');

        if ($configuredKey === '') {
            return $next($request);
        }

        $receivedKey = (string) $request->header('X-API-KEY', '');
        if ($receivedKey === '' || ! hash_equals($configuredKey, $receivedKey)) {
            return response()->json([
                'message' => 'Unauthorized agent',
            ], 401);
        }

        return $next($request);
    }
}
