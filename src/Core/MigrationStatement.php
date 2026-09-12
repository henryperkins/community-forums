<?php

declare(strict_types=1);

namespace App\Core;

use PDOStatement;

/**
 * Statement class installed on MigrationPdo via PDO::ATTR_STATEMENT_CLASS so
 * that execute() shares the connection's SchemaRaceRetry policy. PDO
 * instantiates it internally and passes the constructor arguments registered
 * with the attribute.
 */
final class MigrationStatement extends PDOStatement
{
    protected function __construct(private SchemaRaceRetry $retry)
    {
    }

    public function execute(?array $params = null): bool
    {
        return $this->retry->run(fn (): bool => parent::execute($params));
    }
}
