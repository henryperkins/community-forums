<?php

declare(strict_types=1);

namespace App\Service\Webhook;

/** Replaceable outbound-HTTP seam for webhook delivery. */
interface WebhookTransport
{
    /** @param array<string,string> $headers */
    public function deliver(string $url, array $headers, string $body, int $timeoutSeconds): WebhookResponse;
}
