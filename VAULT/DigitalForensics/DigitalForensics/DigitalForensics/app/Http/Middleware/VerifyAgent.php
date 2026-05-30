<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $hmacSecret = (string) config('app.dlds_hmac_secret', '');
        $maxSkewSeconds = (int) config('app.dlds_ingest_max_skew_seconds', 300);

        if ($configuredKey === '' || $hmacSecret === '') {
            Log::critical('DLDS ingestion auth is not configured', [
                'event_type' => 'dlds.ingest.auth_not_configured',
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Ingestion authentication is not configured',
            ], 503);
        }

        $receivedKey = (string) $request->header('X-API-KEY', '');
        $timestampHeader = (string) $request->header('X-TIMESTAMP', '');
        $signatureHeader = (string) $request->header('X-SIGNATURE', '');

        if ($receivedKey === '' || $timestampHeader === '' || $signatureHeader === '') {
            Log::warning('DLDS ingestion rejected: missing required auth headers', [
                'event_type' => 'dlds.ingest.auth_missing_headers',
                'path' => $request->path(),
                'ip' => $request->ip(),
                'has_key' => $receivedKey !== '',
                'has_timestamp' => $timestampHeader !== '',
                'has_signature' => $signatureHeader !== '',
            ]);

            return response()->json([
                'message' => 'Missing required ingestion authentication headers',
            ], 400);
        }

        if (! hash_equals($configuredKey, $receivedKey)) {
            Log::warning('DLDS ingestion rejected: invalid API key', [
                'event_type' => 'dlds.ingest.auth_invalid_key',
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Unauthorized agent',
            ], 401);
        }

        if (! ctype_digit($timestampHeader)) {
            Log::warning('DLDS ingestion rejected: invalid timestamp format', [
                'event_type' => 'dlds.ingest.auth_invalid_timestamp',
                'path' => $request->path(),
                'ip' => $request->ip(),
                'timestamp' => $timestampHeader,
            ]);

            return response()->json([
                'message' => 'Unauthorized agent',
            ], 401);
        }

        $timestamp = (int) $timestampHeader;
        $now = now()->timestamp;
        if (abs($now - $timestamp) > $maxSkewSeconds) {
            Log::warning('DLDS ingestion rejected: stale or replayed timestamp', [
                'event_type' => 'dlds.ingest.auth_stale_timestamp',
                'path' => $request->path(),
                'ip' => $request->ip(),
                'timestamp' => $timestamp,
                'now' => $now,
            ]);

            return response()->json([
                'message' => 'Unauthorized agent',
            ], 401);
        }

        $providedSignature = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;
        $signedPayload = $timestampHeader.'.'.$request->getContent();
        $expectedSignature = hash_hmac('sha256', $signedPayload, $hmacSecret);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            Log::warning('DLDS ingestion rejected: invalid HMAC signature', [
                'event_type' => 'dlds.ingest.auth_invalid_signature',
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Unauthorized agent',
            ], 401);
        }

        return $next($request);
    }
}
