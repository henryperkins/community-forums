<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Thrown by ApiTokenService::mint() when the api_tokens kill switch is dark. */
final class ApiTokensDisabledException extends RuntimeException
{
}
