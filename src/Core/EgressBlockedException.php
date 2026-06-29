<?php

declare(strict_types=1);

namespace App\Core;

/** Thrown when a webhook URL or target fails the egress policy. */
final class EgressBlockedException extends \RuntimeException
{
}
