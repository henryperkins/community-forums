<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\ApiPrincipal;
use App\Security\ApiScopes;
use PHPUnit\Framework\TestCase;

final class ApiScopesTest extends TestCase
{
    public function test_catalogue_validates_known_scopes(): void
    {
        self::assertTrue(ApiScopes::isValid('read:boards'));
        self::assertTrue(ApiScopes::isValid('read:threads'));
        self::assertFalse(ApiScopes::isValid('write:everything'));
        self::assertArrayHasKey('read:boards', ApiScopes::all());
    }

    public function test_principal_scope_check(): void
    {
        $p = new ApiPrincipal(7, 'ci', ['read:boards'], 3, '2026-06-28 00:00:00', str_repeat('a', 64));
        self::assertTrue($p->hasScope('read:boards'));
        self::assertFalse($p->hasScope('read:threads'));
        self::assertSame('ci', $p->name());
        self::assertSame(str_repeat('a', 64), $p->tokenHash());
        self::assertSame('2026-06-28 00:00:00', $p->createdAt());
    }
}
