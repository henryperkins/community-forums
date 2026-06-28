<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/** Internal control-flow signal: ApiController::respond() catches it and returns JSON 403. */
final class ApiForbiddenException extends RuntimeException
{
    public function __construct(private string $scope)
    {
        parent::__construct('Missing scope: ' . $scope);
    }

    public function scope(): string
    {
        return $this->scope;
    }
}
