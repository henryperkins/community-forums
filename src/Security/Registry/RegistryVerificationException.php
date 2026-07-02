<?php

declare(strict_types=1);

namespace App\Security\Registry;

/**
 * A fail-closed refusal from the registry trust chain. `code` is a stable
 * machine token (telemetry + tests match on it); the message is operator-facing.
 */
final class RegistryVerificationException extends \RuntimeException
{
    public function __construct(private readonly string $registryCode, string $message)
    {
        parent::__construct($message);
    }

    public function __get(string $name): mixed
    {
        if ($name === 'code') {
            return $this->registryCode;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? ['file' => __FILE__, 'line' => __LINE__];
        trigger_error(
            sprintf('Undefined property: %s::$%s in %s on line %s', self::class, $name, $trace['file'], $trace['line']),
            E_USER_NOTICE,
        );

        return null;
    }
}
