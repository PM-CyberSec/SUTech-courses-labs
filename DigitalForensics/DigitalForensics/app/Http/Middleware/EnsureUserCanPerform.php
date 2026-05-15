<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Security\PermissionRegistry;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanPerform
{
    public function __construct(
        private readonly PermissionRegistry $permissions,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            $this->auditLogger->record('auth.permission_denied', 'blocked', [
                'reason' => 'unauthenticated',
                'permission' => $permission,
                'path' => $request->path(),
                'ip' => $request->ip(),
                'request_id' => $request->attributes->get('request_id') ?: $request->header('X-Request-ID'),
            ]);

            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated'], 401)
                : redirect()->route('login');
        }

        if (! $this->permissions->allows($user, $permission)) {
            $this->auditLogger->record('auth.permission_denied', 'blocked', [
                'reason' => 'insufficient_permission',
                'permission' => $permission,
                'user_id' => $user->id,
                'role' => $user->role,
                'path' => $request->path(),
                'ip' => $request->ip(),
                'request_id' => $request->attributes->get('request_id') ?: $request->header('X-Request-ID'),
            ]);

            return $request->expectsJson()
                ? response()->json(['message' => 'Forbidden'], 403)
                : abort(403);
        }

        return $next($request);
    }
}
