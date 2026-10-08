<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class RequestJsonTest extends TestCase
{
    public function test_explicit_json_format_is_supported_in_query_and_post(): void
    {
        self::assertTrue((new Request('GET', '/inbox', ['format' => 'json']))->wantsJson());
        self::assertTrue((new Request('POST', '/t/1/star', [], ['format' => 'json']))->wantsJson());
    }

    public function test_malformed_format_values_are_ignored_without_coercion_warnings(): void
    {
        foreach ([null, ['json'], ['nested' => ['json']], false, true, 1, 0, 'json '] as $format) {
            self::assertFalse((new Request('GET', '/inbox', ['format' => $format]))->wantsJson());
            self::assertFalse((new Request('POST', '/t/1/star', [], ['format' => $format]))->wantsJson());
        }
    }

    public function test_malformed_format_does_not_disable_json_header_negotiation(): void
    {
        self::assertTrue((new Request('POST', '/t/1/star', [], ['format' => ['json']], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]))->wantsJson());
        self::assertTrue((new Request('POST', '/t/1/star', [], ['format' => ['json']], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]))->wantsJson());
    }
}
