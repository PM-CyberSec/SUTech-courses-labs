<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogRequestMetrics
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $requestId = (string) ($request->headers->get('X-Request-ID') ?: Str::uuid());
        $request->headers->set('X-Request-ID', $requestId);
        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            $this->logRequest($request, $requestId, $startedAt, 500, $exception);
            $this->auditRequest($request, $requestId, $startedAt, 500, 'failure');

            throw $exception;
        }

        $response->headers->set('X-Request-ID', $requestId);

        if ($request->is('api/*') || $response->getStatusCode() >= 400) {
            $this->logRequest($request, $requestId, $startedAt, $response->getStatusCode());
        }

        if ($this->shouldAudit($request, $response)) {
            $this->auditRequest(
                $request,
                $requestId,
                $startedAt,
                $response->getStatusCode(),
                $response->getStatusCode() >= 400 ? 'failure' : 'success',
            );
        }

        return $response;
    }

    private function logRequest(
        Request $request,
        string $requestId,
        float $startedAt,
        int $status,
        ?\Throwable $exception = null,
    ): void {
        $context = [
            'event_type' => $status >= 400 ? 'http.request_failed' : 'http.request_completed',
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $status,
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'user_agent' => (string) $request->userAgent(),
        ];

        if ($exception !== null) {
            $context['exception'] = $exception::class;
            $context['message'] = $exception->getMessage();
        }

        $status >= 400
            ? Log::warning('DLDS HTTP request failed', $context)
            : Log::info('DLDS HTTP request completed', $context);
    }

    private function shouldAudit(Request $request, Response $response): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || $response->getStatusCode() >= 400;
    }

    private function auditRequest(
        Request $request,
        string $requestId,
        float $startedAt,
        int $status,
        string $outcome,
    ): void {
        $this->auditLogger->record('http.request', $outcome, [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $status,
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
        ]);
    }
}
