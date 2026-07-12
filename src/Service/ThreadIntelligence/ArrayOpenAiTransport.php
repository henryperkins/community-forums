<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use LogicException;

/**
 * In-memory transport for provider-shape tests: records path/payload/timeout
 * and replays scripted responses. It deliberately accepts NO api key — the
 * credential exists only inside the production cURL transport.
 */
final class ArrayOpenAiTransport implements OpenAiTransport
{
    /** @var list<OpenAiTransportResponse> */
    private array $queue = [];

    /** @var list<array{path:string, payload:array<string,mixed>, timeout:int}> */
    private array $requests = [];

    public function queue(OpenAiTransportResponse $response): void
    {
        $this->queue[] = $response;
    }

    public function post(string $path, array $payload, int $timeoutSeconds): OpenAiTransportResponse
    {
        $this->requests[] = ['path' => $path, 'payload' => $payload, 'timeout' => $timeoutSeconds];

        if ($this->queue === []) {
            throw new LogicException('ArrayOpenAiTransport: no scripted response queued');
        }
        return array_shift($this->queue);
    }

    /** @return list<array{path:string, payload:array<string,mixed>, timeout:int}> */
    public function requests(): array
    {
        return $this->requests;
    }
}
