<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMessage;
use App\Services\EndpointResolver;
use App\Services\MessageRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IngestController extends Controller
{
    public function __construct(
        private readonly EndpointResolver $resolver,
        private readonly MessageRecorder $recorder,
    ) {}

    public function __invoke(Request $request, string $path = ''): Response
    {
        $resolved = $this->resolver->resolve($path);

        // Ismeretlen vagy kikapcsolt endpoint: egységes 404, hogy ne lehessen
        // létező URL-eket kitalálgatni a válaszokból.
        if (! $resolved || ! $resolved->endpoint->enabled) {
            return new Response('Not found', 404, ['Content-Type' => 'text/plain']);
        }

        $endpoint = $resolved->endpoint;

        if ($request->getRealMethod() === 'OPTIONS' && $endpoint->cors) {
            return (new Response('', 204))->withHeaders($this->corsHeaders());
        }

        $message = $this->recorder->record($endpoint, $request, $resolved->suffix);

        if ($endpoint->response_delay_ms > 0) {
            usleep(min($endpoint->response_delay_ms, 10000) * 1000);
        }

        $status = $resolved->statusOverride() ?? $endpoint->response_status;

        $message->response_status = $status;
        $message->save();

        ProcessMessage::dispatch($message->id);

        $response = new Response(
            $endpoint->response_body ?? '',
            $status,
            [
                'Content-Type' => $endpoint->response_content_type ?: 'text/plain',
                'X-Message-Id' => $message->uuid,
            ]
        );

        if ($endpoint->cors) {
            $response->withHeaders($this->corsHeaders());
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
            'Access-Control-Max-Age' => '86400',
        ];
    }
}
