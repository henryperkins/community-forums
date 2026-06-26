<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use Throwable;

/**
 * PDO factory + thin transaction/query helpers. All application SQL goes through
 * prepared statements; there is no string-built SQL anywhere in the app.
 */
final class Database
{
    private ?PDO $pdo = null;

    /** @param array<string,mixed> $config db config block */
    public function __construct(private array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            (int) $this->config['port'],
            $this->config['database'],
            $this->config['charset'] ?? 'utf8mb4',
        );

        $this->pdo = new PDO(
            $dsn,
            (string) $this->config['username'],
            (string) $this->config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_TIMEOUT => 5,
            ],
        );

        return $this->pdo;
    }

    /** Allow tests to inject a pre-built PDO (e.g. a shared transaction). */
    public function setPdo(PDO $pdo): void
    {
        $this->pdo = $pdo;
    }

    /**
     * Run a query with bound params and return the statement.
     *
     * @param array<string,mixed>|list<mixed> $params
     */
    public function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** @param array<string,mixed>|list<mixed> $params */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string,mixed>|list<mixed> $params @return array<int,array<string,mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @param array<string,mixed>|list<mixed> $params */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        return $this->run($sql, $params)->fetchColumn();
    }

    /** @param array<string,mixed>|list<mixed> $params */
    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Execute the callback inside a transaction, committing on success and
     * rolling back on any throwable. Returns whatever the callback returns.
     *
     * Nested calls reuse the active transaction (no savepoints needed for P1).
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        if ($pdo->inTransaction()) {
            return $callback();
        }

        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function ping(): bool
    {
        try {
            return (int) $this->pdo()->query('SELECT 1')->fetchColumn() === 1;
        } catch (PDOException) {
            return false;
        }
    }
}
