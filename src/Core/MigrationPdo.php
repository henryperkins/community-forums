<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * The connection the migration runner uses (`bin/console migrate*`).
 *
 * Identical to the application's PDO except that every statement entry point a
 * migration can use -- exec(), query(), prepare() and the returned statement's
 * execute() -- runs under a SchemaRaceRetry policy. On Vitess that absorbs the
 * asynchronous schema propagation described in
 * docs/runbooks/deployment-cloudflare.md §3; on MariaDB/MySQL the policy is
 * inert and behaviour is byte-for-byte the plain PDO's.
 */
final class MigrationPdo extends PDO
{
    private SchemaRaceRetry $retry;

    /** @param array<int,mixed>|null $options */
    public function __construct(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        ?array $options = null,
        ?SchemaRaceRetry $retry = null,
    ) {
        parent::__construct($dsn, $username, $password, $options);
        $this->retry = $retry ?? SchemaRaceRetry::forServerVersion($this->serverVersion());
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [MigrationStatement::class, [$this->retry]]);
    }

    public function retry(): SchemaRaceRetry
    {
        return $this->retry;
    }

    public function exec(string $statement): int|false
    {
        return $this->retry->run(fn (): int|false => parent::exec($statement));
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return $this->retry->run(
            fn (): PDOStatement|false => parent::query($query, $fetchMode, ...$fetchModeArgs),
        );
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return $this->retry->run(fn (): PDOStatement|false => parent::prepare($query, $options));
    }

    private function serverVersion(): string
    {
        try {
            $stmt = parent::query('SELECT VERSION()');
            return $stmt === false ? '' : (string) $stmt->fetchColumn();
        } catch (PDOException) {
            return '';
        }
    }
}
