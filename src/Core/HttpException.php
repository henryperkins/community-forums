<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Base for exceptions that map to an HTTP status code. The kernel renders an
 * appropriate error page.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private int $statusCode,
        string $message = '',
        public readonly ?string $redirectTo = null,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
