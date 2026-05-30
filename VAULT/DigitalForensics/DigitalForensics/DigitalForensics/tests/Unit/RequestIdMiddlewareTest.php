<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\LogRequestMetrics;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RequestIdMiddlewareTest extends TestCase
{
    public function test_request_id_is_generated_and_returned(): void
    {
        $request = Request::create('/unit/request-id', 'GET');

        $response = app(LogRequestMetrics::class)->handle(
            $request,
            fn (Request $handledRequest): Response => new Response(
                (string) $handledRequest->attributes->get('request_id'),
            ),
        );

        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        $this->assertSame($response->headers->get('X-Request-ID'), $response->getContent());
    }

    public function test_existing_request_id_is_preserved(): void
    {
        $request = Request::create('/unit/request-id', 'GET');
        $request->headers->set('X-Request-ID', 'req-known-123');

        $response = app(LogRequestMetrics::class)->handle(
            $request,
            fn (): Response => new Response('ok'),
        );

        $this->assertSame('req-known-123', $response->headers->get('X-Request-ID'));
        $this->assertSame('req-known-123', $request->attributes->get('request_id'));
    }
}
