<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Thrown when a secret reference is unknown. Messages never contain a secret value. */
final class SecretNotFoundException extends RuntimeException
{
}
