<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\UploadLimits;
use PHPUnit\Framework\TestCase;

final class UploadLimitsTest extends TestCase
{
    public function test_php_quantities_are_binary_and_never_overflow(): void
    {
        foreach ([['2M', 2097152], ['10m', 10485760], ['1K', 1024], ['1g', 1073741824],
            [' 8M ', 8388608], ['123', 123], ['0', null], ['-1', null], ['', null], [false, null],
            ['2.5M', null], ['8MB', null], [str_repeat('9', 30), null], [(string) PHP_INT_MAX . 'G', null]] as [$value, $expected]) {
            self::assertSame($expected, UploadLimits::phpBytes($value));
        }
    }

    public function test_member_limit_labels_use_clear_binary_units(): void
    {
        self::assertSame('5 MiB', UploadLimits::label(5242880));
        self::assertSame('512 KiB', UploadLimits::label(524288));
    }
}
