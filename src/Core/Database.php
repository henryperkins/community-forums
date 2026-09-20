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
    private int $connectionCount = 0;
    private float $connectionDurationMs = 0.0;
    private int $queryCount = 0;
    private float $queryDurationMs = 0.0;
    private bool $requestCacheActive = false;
    /** @var array<string,mixed> Only explicitly selected repository reads. */
    private array $requestCache = [];
    /** @var array<string,list<string>> Missing dependencies invalidate on every write. */
    private array $requestCacheTables = [];
    /** @var list<list<string>|null> Union of invalidations within each transaction. */
    private array $transactionCacheTables = [];

    /** @param array<string,mixed> $config db config block */
    public function __construct(private array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $startedAt = hrtime(true);
        $this->connectionCount++;
        try {
            $this->pdo = new PDO(
                $this->dsn(),
                (string) $this->config['username'],
                (string) $this->config['password'],
                $this->driverOptions(),
            );
        } finally {
            $this->connectionDurationMs += (hrtime(true) - $startedAt) / 1_000_000;
        }

        return $this->pdo;
    }

    /**
     * A separate connection for the migration runner (`bin/console migrate*`).
     * Same DSN and driver options as pdo(), but statements retry across
     * Vitess's asynchronous schema propagation (see MigrationPdo). Not memoised:
     * the caller holds it for the duration of one migrate run. A policy may be
     * injected (tests); by default it is chosen from the server version.
     */
    public function migrationPdo(?SchemaRaceRetry $retry = null): MigrationPdo
    {
        $startedAt = hrtime(true);
        $this->connectionCount++;
        try {
            return new MigrationPdo(
                $this->dsn(),
                (string) $this->config['username'],
                (string) $this->config['password'],
                $this->driverOptions(),
                $retry,
            );
        } finally {
            $this->connectionDurationMs += (hrtime(true) - $startedAt) / 1_000_000;
        }
    }

    private function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            (int) $this->config['port'],
            $this->config['database'],
            $this->config['charset'] ?? 'utf8mb4',
        );
    }

    /** @return array<int,mixed> */
    private function driverOptions(): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => (bool) ($this->config['emulate_prepares'] ?? true),
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];
        // Preserve the native-prepare single-statement boundary under emulation.
        // Prefer the PHP 8.4+ name without introducing an 8.2 runtime requirement.
        foreach (['Pdo\\Mysql::ATTR_MULTI_STATEMENTS', 'PDO::MYSQL_ATTR_MULTI_STATEMENTS'] as $name) {
            if (defined($name)) {
                $options[constant($name)] = false;
                break;
            }
        }
        return $options + $this->tlsOptions();
    }

    /**
     * TLS driver options for the MySQL connection. Empty (plaintext) unless
     * db.ssl.enabled is on — a same-host/private-network connection needs no
     * TLS, but a managed database reached over the public internet does, or the
     * credentials and every row cross the wire in the clear.
     *
     * The MYSQL_ATTR_SSL_* constants only exist when pdo_mysql is loaded, so
     * they are resolved defensively: a machine without the driver can still
     * construct this class (unit tests never open a MySQL socket).
     *
     * @return array<int,mixed>
     */
    private function tlsOptions(): array
    {
        $ssl = $this->config['ssl'] ?? [];
        if (empty($ssl['enabled'])) {
            return [];
        }

        $options = [];
        $ca = (string) ($ssl['ca'] ?? '');
        if ($ca !== '' && defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
        }
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            // Verifying the server certificate requires a CA to verify it against.
            // Without one, asking for verification fails the connection outright,
            // so only assert it when we actually have a trust anchor.
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $ca !== ''
                && (bool) ($ssl['verify'] ?? true);
        }

        return $options;
    }

    /** Allow tests to inject a pre-built PDO (e.g. a shared transaction). */
    public function setPdo(PDO $pdo): void
    {
        $this->clearRequestCache();
        $this->pdo = $pdo;
    }

    /** App::handle owns this scope; CLI workers deliberately do not cache. */
    public function beginRequestCache(): void
    {
        $this->clearRequestCache();
        $this->requestCacheActive = true;
    }

    public function endRequestCache(): void
    {
        $this->requestCacheActive = false;
        $this->clearRequestCache();
    }

    public function isRequestCacheActive(): bool
    {
        return $this->requestCacheActive;
    }

    /**
     * Invalidate all reads by default. A bounded write may name every table it
     * affects (including indirect effects); unclassified reads still expire.
     *
     * @param list<string>|null $writtenTables
     */
    public function clearRequestCache(?array $writtenTables = null): void
    {
        // A nested operation may affect more than its enclosing declaration.
        // Carry that union through commit/rollback, including reads repopulated
        // after the inner write. An unknown mutation makes the whole scope unknown.
        foreach ($this->transactionCacheTables as &$tables) {
            $tables = $tables === null || $writtenTables === null || $writtenTables === []
                ? null
                : array_values(array_unique([...$tables, ...$writtenTables]));
        }
        unset($tables);
        if ($writtenTables !== null && $writtenTables !== []) {
            foreach ($this->requestCache as $key => $_) {
                $dependencies = $this->requestCacheTables[$key] ?? [];
                if ($dependencies === [] || array_intersect($dependencies, $writtenTables) !== []) {
                    unset($this->requestCache[$key], $this->requestCacheTables[$key]);
                }
            }
            return;
        }
        $this->requestCache = [];
        $this->requestCacheTables = [];
    }

    /**
     * Memoize a pure, non-locking read across repositories sharing this DB.
     * Null/false are valid results. Exceptions are never cached. Callers using
     * raw PDO for writes must clear the cache or use transaction() boundaries.
     *
     * @template T
     * @param callable():T $loader
     * @param list<string> $tables Every table contributing to the cached value.
     * @return T
     */
    public function remember(string $key, callable $loader, array $tables = []): mixed
    {
        if (!$this->requestCacheActive) {
            return $loader();
        }
        if (!array_key_exists($key, $this->requestCache)) {
            $this->requestCache[$key] = $loader();
            $this->requestCacheTables[$key] = $tables;
        }
        return $this->requestCache[$key];
    }

    /**
     * Run a query with bound params and return the statement.
     *
     * @param array<string,mixed>|list<mixed> $params
     * @param list<string>|null $writtenTables Null/empty keeps full invalidation.
     */
    public function run(string $sql, array $params = [], ?array $writtenTables = null): \PDOStatement
    {
        // Unknown statements and failed writes invalidate conservatively too.
        if (!$this->isReadOnlyStatement($sql)) {
            $this->clearRequestCache($writtenTables);
        }
        $pdo = $this->pdo();
        $startedAt = hrtime(true);
        $this->queryCount++;
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } finally {
            $this->queryDurationMs += (hrtime(true) - $startedAt) / 1_000_000;
        }
    }

    /** Recognize SELECT and WITH ... SELECT without exempting WITH ... UPDATE/DELETE. */
    private function isReadOnlyStatement(string $sql): bool
    {
        if (preg_match('/^\s*SELECT\b/i', $sql)) {
            return true;
        }
        if (!preg_match('/^\s*WITH\b/i', $sql)
            || str_contains($sql, '\\')
            || preg_match('~/\*(?:!|M!)~i', $sql)) {
            // Backslash quoting depends on SQL mode; executable comments can
            // contain statements. Neither is needed by our directory CTE.
            return false;
        }

        // Ignore literals, quoted identifiers and ordinary comments before
        // balancing groups. The token after the final CTE must be SELECT;
        // a SELECT inside a CTE or its comments says nothing about that verb.
        $structure = preg_replace(
            <<<'REGEX'
            ~'(?:''|[^'])*'|"(?:""|[^"])*"|`(?:``|[^`])*`|--(?=\s|$)[^\r\n]*|\#[^\r\n]*|/\*.*?\*/~s
            REGEX,
            ' ',
            $sql,
        );
        if ($structure === null) {
            return false;
        }
        preg_match_all('/[(),;]|[a-z_][a-z0-9_$]*/i', $structure, $matches);
        $depth = 0;
        $afterGroup = false;
        foreach ($matches[0] as $token) {
            if ($token === '(') {
                $depth++;
            } elseif ($token === ')') {
                $depth--;
                $afterGroup = $depth === 0;
            } elseif ($depth === 0 && $afterGroup) {
                if ($token === ',' || strcasecmp($token, 'AS') === 0) {
                    // Another CTE, or the end of an optional CTE column list.
                    $afterGroup = false;
                } else {
                    return strcasecmp($token, 'SELECT') === 0;
                }
            }
        }
        return false;
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
     * @param list<string>|null $writtenTables All tables the callback may mutate.
     *   Scoped writes inside must also declare their tables; unknown writes and
     *   nested transactions still invalidate everything, never restore old reads.
     * @return T
     */
    public function transaction(callable $callback, ?array $writtenTables = null): mixed
    {
        $this->clearRequestCache($writtenTables);
        $this->transactionCacheTables[] = $writtenTables ?: null;
        try {
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
        } finally {
            $this->clearRequestCache(array_pop($this->transactionCacheTables));
        }
    }

    public function ping(): bool
    {
        $pdo = null;
        try {
            $pdo = $this->pdo();
        } catch (PDOException) {
            return false;
        }

        $startedAt = hrtime(true);
        $this->queryCount++;
        try {
            return (int) $pdo->query('SELECT 1')->fetchColumn() === 1;
        } catch (PDOException) {
            return false;
        } finally {
            $this->queryDurationMs += (hrtime(true) - $startedAt) / 1_000_000;
        }
    }

    /** @return array{connections:int,connection_ms:float,queries:int,query_ms:float} */
    public function metrics(): array
    {
        return [
            'connections' => $this->connectionCount,
            'connection_ms' => round($this->connectionDurationMs, 3),
            'queries' => $this->queryCount,
            'query_ms' => round($this->queryDurationMs, 3),
        ];
    }

    public function resetMetrics(): void
    {
        $this->connectionCount = 0;
        $this->connectionDurationMs = 0.0;
        $this->queryCount = 0;
        $this->queryDurationMs = 0.0;
    }
}
