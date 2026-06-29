<?php

declare(strict_types=1);

namespace App\Security;

/** The read-only API scope catalogue (designed to extend with write/PII scopes later). */
final class ApiScopes
{
    /** @var array<string,string> scope => human description */
    public const SCOPES = [
        'read:boards' => 'List public boards',
        'read:threads' => 'Read threads in a public board',
    ];

    public static function isValid(string $scope): bool
    {
        return isset(self::SCOPES[$scope]);
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::SCOPES;
    }
}
