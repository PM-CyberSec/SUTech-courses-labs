<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if ($user === null) {
            Log::warning('Access denied: unauthenticated request to role-protected route', [
                'event_type' => 'auth.access_denied.unauthenticated_role_route',
                'required_roles' => $roles,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated'], 401)
                : redirect()->route('login');
        }

        $normalizedRoles = array_map(
            static fn (string $role): string => strtolower(trim($role)),
            $roles,
        );

        if (! in_array(strtolower((string) $user->role), $normalizedRoles, true)) {
            Log::warning('Access denied: user lacks required role', [
                'event_type' => 'auth.access_denied.role_mismatch',
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'required_roles' => $normalizedRoles,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return $request->expectsJson()
                ? response()->json(['message' => 'Forbidden'], 403)
                : abort(403);
        }

        return $next($request);
    }
}
