<?php

declare(strict_types=1);

namespace App\Service\Webhook;

/** Result of one delivery attempt. status=0 means no HTTP response. */
final class WebhookResponse
{
    public function __construct(public int $status, public ?string $error = null)
    {
    }
}
