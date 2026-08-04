<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class DatabaseTlsTest extends TestCase
{
    public function test_tls_options_are_empty_when_managed_database_tls_is_disabled(): void
    {
        $database = new Database(['ssl' => ['enabled' => false]]);

        self::assertTrue(method_exists($database, 'tlsOptions'));
        self::assertSame([], $this->tlsOptions($database));
    }

    public function test_tls_options_use_the_configured_ca_and_verify_the_server_certificate(): void
    {
        self::assertTrue(defined('PDO::MYSQL_ATTR_SSL_CA'));
        self::assertTrue(defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT'));

        $database = new Database([
            'ssl' => [
                'enabled' => true,
                'ca' => '/etc/ssl/certs/ca-certificates.crt',
                'verify' => true,
            ],
        ]);

        self::assertTrue(method_exists($database, 'tlsOptions'));
        self::assertSame(
            [
                PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
            ],
            $this->tlsOptions($database),
        );
    }

    /** @return array<int,mixed> */
    private function tlsOptions(Database $database): array
    {
        $method = new ReflectionMethod($database, 'tlsOptions');
        $method->setAccessible(true);

        /** @var array<int,mixed> $options */
        $options = $method->invoke($database);

        return $options;
    }
}
