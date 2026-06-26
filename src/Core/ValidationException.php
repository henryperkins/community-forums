<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Thrown by services when user input fails validation. Controllers catch it and
 * re-render the originating form with the errors and the user's old input
 * (HTTP 422), so the no-JS flow keeps the user's text.
 */
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string,string> $errors field => message
     * @param array<string,mixed> $old submitted values to repopulate the form
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $old = [],
        string $message = 'The submitted data was invalid.',
    ) {
        parent::__construct($message);
    }

    public function first(): string
    {
        foreach ($this->errors as $message) {
            return $message;
        }
        return $this->getMessage();
    }
}
