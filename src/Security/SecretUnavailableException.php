<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * A stored secret cannot be opened with the configured application key: its
 * AES-GCM authentication tag does not verify, so the record was sealed under a
 * different key or has been altered. The plaintext is unavailable to this
 * deployment; nothing here can recover it.
 *
 * It extends RuntimeException, so every existing catch of RuntimeException or
 * Throwable still handles it unchanged. A missing APP_KEY is a configuration
 * failure and is deliberately NOT reported through this exception.
 */
final class SecretUnavailableException extends RuntimeException
{
}
