<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\Totp;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    public function test_totp_matches_rfc_vector_and_rejects_replay(): void
    {
        $totp = new Totp();
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        self::assertSame('94287082', $totp->code($secret, 59, 30, 8));
        self::assertSame(1, $totp->verify($secret, '94287082', null, 59, 1, 30, 8));
        self::assertNull($totp->verify($secret, '94287082', 1, 59, 1, 30, 8));
    }
}
