<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\Cap;
use App\Security\CapabilityCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Inc 6 follow-up: per-key capability constants. A typo'd free-string key
 * fail-darks under enforce (denies everyone, invisibly to CI); a typo'd
 * Cap::* constant is a fatal at first touch. This pins Cap ⟷ catalogue
 * parity so the constants can never drift from the taxonomy.
 */
final class CapTest extends TestCase
{
    public function test_constants_mirror_the_catalogue_exactly(): void
    {
        $consts = (new \ReflectionClass(Cap::class))->getConstants();
        $values = array_values($consts);
        sort($values);
        $keys = CapabilityCatalog::keys();
        sort($keys);

        self::assertSame($keys, $values, 'Cap constants must cover every catalogue key exactly once');
        self::assertSame(count($consts), count(array_unique($values)), 'no duplicate constant values');
    }

    public function test_constant_names_derive_deterministically_from_keys(): void
    {
        foreach ((new \ReflectionClass(Cap::class))->getConstants() as $name => $value) {
            self::assertIsString($value);
            self::assertStringStartsWith('core.', $value);
            $expected = strtoupper(str_replace('.', '_', substr($value, strlen('core.'))));
            self::assertSame($expected, $name, "constant for {$value} must be named {$expected}");
        }
    }
}
