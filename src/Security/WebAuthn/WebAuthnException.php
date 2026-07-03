<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

/**
 * Coded, fail-closed WebAuthn ceremony failure.
 *
 * @property-read string $code Stable WebAuthn failure code.
 */
final class WebAuthnException extends \RuntimeException
{
    public function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    public function __get(string $name): mixed
    {
        if ($name === 'code') {
            return $this->failureCode;
        }

        trigger_error(
            'Undefined property: ' . self::class . '::$' . $name,
            E_USER_NOTICE,
        );
        return null;
    }
}
