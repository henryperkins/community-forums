<?php

declare(strict_types=1);

namespace App\Security\Packages;

/**
 * Coded, fail-closed package-policy refusal. `$e->code` is the stable machine
 * token for tests, telemetry, and controller rendering.
 */
final class PackagePolicyException extends \RuntimeException
{
    public function __construct(private readonly string $policyCode, string $message)
    {
        parent::__construct($message);
    }

    public function __get(string $name): string
    {
        if ($name === 'code') {
            return $this->policyCode;
        }

        throw new \LogicException('Undefined property: ' . self::class . '::$' . $name);
    }
}
