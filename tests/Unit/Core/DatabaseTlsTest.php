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

    public function test_dsn_uses_host_and_port_without_a_socket(): void
    {
        $database = new Database(['host' => '10.0.0.5', 'port' => 3307, 'database' => 'retroboards', 'socket' => '']);

        self::assertSame('mysql:host=10.0.0.5;port=3307;dbname=retroboards;charset=utf8mb4', $this->dsn($database));
    }

    /** Cloud Run's Cloud SQL connector is a Unix socket named after the instance. */
    public function test_dsn_prefers_a_configured_unix_socket_over_host_and_port(): void
    {
        $database = new Database([
            'host' => '127.0.0.1',
            'port' => 3306,
            'socket' => '/cloudsql/project:us-east4:instance',
            'database' => 'retroboards',
        ]);

        self::assertSame(
            'mysql:unix_socket=/cloudsql/project:us-east4:instance;dbname=retroboards;charset=utf8mb4',
            $this->dsn($database),
        );
    }

    private function dsn(Database $database): string
    {
        $method = new ReflectionMethod($database, 'dsn');
        $method->setAccessible(true);

        return (string) $method->invoke($database);
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
