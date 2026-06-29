<?php

declare(strict_types=1);

namespace App\Core;

/** Thrown by webhook write paths when the webhooks flag is dark. */
final class WebhooksDisabledException extends \RuntimeException
{
}
