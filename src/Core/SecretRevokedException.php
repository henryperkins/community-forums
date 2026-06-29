<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Thrown when reading or rotating a revoked secret reference. */
final class SecretRevokedException extends RuntimeException
{
}
