<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsApproved
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            Log::warning('Access denied: unauthenticated request to protected route', [
                'event_type' => 'auth.access_denied.unauthenticated',
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated'], 401)
                : redirect()->route('login');
        }

        if (! $user->is_active || $user->approved_at === null) {
            Log::warning('Access denied: user is not approved or inactive', [
                'event_type' => 'auth.access_denied.unapproved',
                'user_id' => $user->id,
                'email' => $user->email,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Account approval is required',
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Your account is pending approval or has been deactivated.',
            ]);
        }

        return $next($request);
    }
}
