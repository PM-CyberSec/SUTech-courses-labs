<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $eventType, string $outcome, array $context = []): void
    {
        Log::channel('audit')->info('DLDS audit event', [
            'event_type' => $eventType,
            'outcome' => $outcome,
            'occurred_at' => now('UTC')->toISOString(),
            ...$this->redact($context),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        $redacted = [];

        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (
                str_contains($normalizedKey, 'password')
                || str_contains($normalizedKey, 'secret')
                || str_contains($normalizedKey, 'token')
                || str_contains($normalizedKey, 'signature')
                || $normalizedKey === 'key'
                || str_ends_with($normalizedKey, '_key')
            ) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }
}
