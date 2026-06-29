<?php

declare(strict_types=1);

namespace App\Service\Webhook;

/** Test double that records every delivery call. */
final class FakeWebhookTransport implements WebhookTransport
{
    /** @var array<int,array{url:string,headers:array<string,string>,body:string}> */
    public array $calls = [];

    /** @var null|callable(string,array<string,string>,string):WebhookResponse */
    private $responder;

    /** @param null|callable(string,array<string,string>,string):WebhookResponse $responder */
    public function __construct(?callable $responder = null)
    {
        $this->responder = $responder;
    }

    public function deliver(string $url, array $headers, string $body, int $timeoutSeconds): WebhookResponse
    {
        $this->calls[] = ['url' => $url, 'headers' => $headers, 'body' => $body];
        if ($this->responder !== null) {
            return ($this->responder)($url, $headers, $body);
        }
        return new WebhookResponse(200, null);
    }
}
