<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\Controller;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Controller::localPath is the one check behind every redirect a request can
 * name: a form's `return`, the sign-in `next`, and the path an MFA challenge
 * carries to its second step. A browser strips tab, LF and CR from a Location
 * before resolving it and reads "\" as "/", so any of them after the leading
 * slash turns a local path into a protocol-relative one.
 */
final class LocalPathTest extends TestCase
{
    #[DataProvider('localPaths')]
    public function test_a_local_path_is_kept(string $path): void
    {
        self::assertSame($path, Controller::localPath($path, '/fallback'));
    }

    #[DataProvider('refusedPaths')]
    public function test_anything_else_falls_back(mixed $path): void
    {
        self::assertSame('/fallback', Controller::localPath($path, '/fallback'));
    }

    /** @return iterable<string,array{string}> */
    public static function localPaths(): iterable
    {
        yield 'root' => ['/'];
        yield 'page' => ['/inbox'];
        yield 'query and fragment' => ['/t/12-a-topic?page=3#p45'];
        yield 'encoded query' => ['/u/galadriel?tab=connections&cq=two%20words&page=2'];
        // Percent-encoding is not decoded while a Location resolves, so an
        // encoded tab stays part of this site's path.
        yield 'encoded tab' => ['/%09/still-local'];
    }

    /** @return iterable<string,array{mixed}> */
    public static function refusedPaths(): iterable
    {
        yield 'tab after the slash' => ["/\t/evil.example"];
        yield 'newline after the slash' => ["/\n/evil.example"];
        yield 'carriage return after the slash' => ["/\r/evil.example"];
        yield 'CRLF after the slash' => ["/\r\n/evil.example"];
        yield 'tab then backslash' => ["/\t\\evil.example"];
        yield 'backslash after the slash' => ['/\\evil.example'];
        yield 'backslash then slash' => ['\\/evil.example'];
        yield 'two backslashes' => ['\\\\evil.example'];
        yield 'protocol-relative' => ['//evil.example'];
        yield 'absolute URL' => ['https://evil.example/'];
        yield 'script scheme' => ['javascript:alert(1)'];
        yield 'leading space' => [' //evil.example'];
        yield 'relative path' => ['inbox'];
        // Refused anywhere, not only after the first slash.
        yield 'tab deeper in' => ["/inbox/\t/evil.example"];
        yield 'backslash deeper in' => ['/inbox/\\evil.example'];
        yield 'space' => ['/two words'];
        yield 'NUL' => ["/\0/evil.example"];
        yield 'DEL' => ["/\x7F"];
        yield 'empty' => [''];
        yield 'missing' => [null];
        yield 'array input' => [['/inbox']];
    }
}
