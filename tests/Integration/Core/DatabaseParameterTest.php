<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Database;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatabaseParameterTest extends TestCase
{
    #[DataProvider('prepareModes')]
    public function test_bound_values_round_trip_with_default_and_native_prepares(?bool $emulate): void
    {
        $config = $GLOBALS['__RB_TEST_DBCONFIG'];
        unset($config['emulate_prepares']);
        if ($emulate !== null) {
            $config['emulate_prepares'] = $emulate;
        }
        $db = new Database($config);
        self::assertSame($emulate ?? true, (bool) $db->pdo()->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        $db->run('CREATE TEMPORARY TABLE parameter_values (
            id INT PRIMARY KEY, body TEXT, binary_value VARBINARY(256), nullable_value VARCHAR(8) NULL,
            number_value BIGINT, decimal_value DECIMAL(8,2), bool_value TINYINT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $body = "quote ' and \\\" double; ? :body -- \\ backslash\nUnicode 雪 🦉";
        $binary = implode('', array_map(chr(...), range(0, 255)));

        $db->run('INSERT INTO parameter_values VALUES (:id, :body, :binary, :nullable, :number, :decimal, :bool)', [
            'id' => 1,
            'body' => $body,
            'binary' => $binary,
            'nullable' => null,
            'number' => -12345,
            'decimal' => 12.5,
            'bool' => true,
        ]);
        $db->run('INSERT INTO parameter_values VALUES (?, ?, ?, ?, ?, ?, ?)', [
            2, $body, $binary, null, -12345, 12.5, true,
        ]);

        foreach ([1, 2] as $id) {
            $row = $db->fetch('SELECT * FROM parameter_values WHERE id = ?', [$id]);
            self::assertSame($body, $row['body']);
            self::assertSame($binary, $row['binary_value']);
            self::assertNull($row['nullable_value']);
            self::assertSame(-12345, $row['number_value']);
            self::assertSame('12.50', $row['decimal_value']);
            self::assertSame(1, $row['bool_value']);
        }
        self::assertSame(2, (int) $db->fetchValue('SELECT COUNT(*) FROM parameter_values'));
    }

    public static function prepareModes(): array
    {
        return ['default' => [null], 'emulated' => [true], 'native rollback' => [false]];
    }

    #[DataProvider('prepareModes')]
    public function test_a_second_statement_cannot_mutate_data_in_either_prepare_mode(?bool $emulate): void
    {
        $config = $GLOBALS['__RB_TEST_DBCONFIG'];
        unset($config['emulate_prepares']);
        if ($emulate !== null) {
            $config['emulate_prepares'] = $emulate;
        }
        $db = new Database($config);
        $db->run('CREATE TEMPORARY TABLE parameter_guard (value VARCHAR(16))');
        $db->run('INSERT INTO parameter_guard VALUES (?)', ['original']);
        $rejected = false;
        try {
            $statement = $db->run("SELECT 1; UPDATE parameter_guard SET value = 'mutated'");
            $statement->closeCursor();
        } catch (PDOException $e) {
            $rejected = true;
            self::assertSame(1064, (int) ($e->errorInfo[1] ?? 0));
        }
        self::assertTrue($rejected, 'Each Database::run call must execute at most one statement.');
        self::assertSame('original', $db->fetchValue('SELECT value FROM parameter_guard'));
    }
}
