<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\UserAgentLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserAgentLabelTest extends TestCase
{
    #[DataProvider('agents')]
    public function test_labels_are_bounded_and_specific(?string $agent, string $expected): void
    {
        self::assertSame($expected, UserAgentLabel::for($agent));
    }

    public static function agents(): iterable
    {
        yield [null, 'Unknown device'];
        yield ['', 'Unknown device'];
        yield ['   ', 'Unknown device'];
        yield ['<script>alert(1)</script>', 'Unknown device'];
        yield [str_repeat('x', 100000) . ' Chrome/149 Windows NT', 'Unknown device'];
        yield ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/149.0.0.0 Safari/537.36', 'Chrome on Windows'];
        yield ['Mozilla/5.0 (Windows NT 10.0) Chrome/149 Safari/537.36 Edg/149.0', 'Edge on Windows'];
        yield ['Mozilla/5.0 (X11; Linux x86_64) Chrome/149 Safari/537.36 OPR/123.0', 'Opera on Linux'];
        yield ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 FxiOS/140.0 Mobile/15E148 Safari/605.1.15', 'Firefox on iOS'];
        yield ['Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) CriOS/149.0 Mobile/15E148 Safari/604.1', 'Chrome on iOS'];
        yield ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) EdgiOS/140.0 Mobile/15E148 Safari/605.1.15', 'Edge on iOS'];
        yield ['Mozilla/5.0 (Linux; Android 15) Chrome/149.0 Safari/537.36 EdgA/140.0', 'Edge on Android'];
        yield ['Mozilla/5.0 (Linux; Android 15) Chrome/149.0 Safari/537.36 SamsungBrowser/28.0', 'Samsung Internet on Android'];
        yield ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) Version/17.5 Safari/605.1.15', 'Safari on macOS'];
        yield ['Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0', 'Firefox on Linux'];
        yield ['Mozilla/5.0 (Linux; Android 15) Chrome/149.0 Safari/537.36', 'Chrome on Android'];
        yield ['Mozilla/5.0 (Windows NT 10.0)', 'Unknown browser on Windows'];
        yield ['Firefox/140.0', 'Firefox on unknown OS'];
        yield ['Chrome/ Safari/', 'Unknown device'];
    }
}
