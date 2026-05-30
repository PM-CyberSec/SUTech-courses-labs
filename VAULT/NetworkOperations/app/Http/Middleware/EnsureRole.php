<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role;

        $roleFromQuery = strtolower((string) $request->query('as_role', ''));
        if (in_array($roleFromQuery, ['admin', 'engineer', 'viewer'], true)) {
            $request->session()->put('role', $roleFromQuery);
            $role = $roleFromQuery;
        }

        if (! $role && $request->hasSession()) {
            $sessionRole = strtolower((string) $request->session()->get('role', ''));
            if (in_array($sessionRole, ['admin', 'engineer', 'viewer'], true)) {
                $role = $sessionRole;
            }
        }

        if (
            ! $role
            && config('autoconfiglab.allow_role_header', false)
            && app()->environment(['local', 'testing'])
        ) {
            $role = strtolower((string) $request->header('X-Role', $request->query('as_role', '')));
        }

        if (! $role && app()->environment(['local', 'testing']) && ! $request->expectsJson()) {
            $role = 'viewer';
            $request->session()->put('role', $role);
        }

        if (! $role) {
            if ($request->expectsJson()) {
                return new JsonResponse([
                    'message' => 'Unauthenticated role context. Authenticate or pass X-Role in local/testing.',
                ], 401);
            }

            abort(401, 'Unauthenticated role context.');
        }

        if (! in_array($role, $roles, true)) {
            if ($request->expectsJson()) {
                return new JsonResponse([
                    'message' => 'Forbidden for role: '.$role,
                    'allowed_roles' => $roles,
                ], 403);
            }

            abort(403, 'Forbidden for role: '.$role);
        }

        $request->attributes->set('resolved_role', $role);

        return $next($request);
    }
}
