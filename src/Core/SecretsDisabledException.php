<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Thrown by store()/rotate() when the service_secrets kill switch is dark. */
final class SecretsDisabledException extends RuntimeException
{
}
