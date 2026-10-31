<?php

declare(strict_types=1);

/** Two real connections pin conflict responses under an older repeatable-read view. */
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\ServerDraftRepository;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
if (!str_starts_with($config->get('db')['database'], 'retroboards_draftfix_')) {
    throw new RuntimeException('Use a private draftfix browser database.');
}
$reader = new Database($config->get('db'));
$writer = new Database($config->get('db'));
// MariaDB 11.8's stricter snapshot-isolation extension rejects any current write
// against this deliberately old outer snapshot before CAS is reached. Exercise
// MySQL's ordinary repeatable-read semantics on this connection only.
$mysqlCompatibility = str_contains(strtolower((string) $reader->fetchValue('SELECT VERSION()')), 'mariadb');
if ($mysqlCompatibility) { $reader->run('SET SESSION innodb_snapshot_isolation = OFF'); }
$stale = new ServerDraftRepository($reader);
$current = new ServerDraftRepository($writer);
$userId = (int) $writer->fetchValue('SELECT id FROM users WHERE username = ?', ['admin']);
if ($userId < 1) { throw new RuntimeException('Run the browser seed first.'); }
$prefix = 'concurrency-' . bin2hex(random_bytes(6));
$updateKey = $prefix . '-update';
$insertKey = $prefix . '-insert';
$retryKey = $prefix . '-retry';
$probe = 'draft_retry_probe_' . bin2hex(random_bytes(6));
$trigger = $probe . '_insert';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
};
try {
    $first = $current->save($userId, $updateKey, 0, 'First', 'First body', [], 0)['draft'];
    $update = $reader->transaction(function () use ($reader, $stale, $current, $userId, $updateKey, $first, $assert): array {
        // A normal SELECT establishes the old consistent-read view.
        $reader->fetch('SELECT * FROM server_drafts WHERE id = ?', [$first['id']]);
        $second = $current->save($userId, $updateKey, 1, 'Second', 'Latest body', [], (int) $first['id'])['draft'];
        $result = $stale->save($userId, $updateKey, 1, 'Stale', 'Do not overwrite', [], (int) $first['id']);
        $assert($result['status'] === 'conflict', 'A stale CAS must conflict.');
        $assert((int) $result['server']['revision'] === (int) $second['revision'] && $result['server']['body'] === 'Latest body', 'The conflict must return the current committed copy, not its earlier read view.');
        return ['status' => $result['status'], 'current_revision' => (int) $result['server']['revision']];
    });
    $insert = $reader->transaction(function () use ($reader, $stale, $current, $userId, $insertKey, $assert): array {
        $reader->fetch('SELECT * FROM server_drafts WHERE user_id = ? AND context_key = ?', [$userId, $insertKey]);
        $created = $current->save($userId, $insertKey, 0, 'Created elsewhere', 'New committed body', [], 0)['draft'];
        $result = $stale->save($userId, $insertKey, 0, 'Stale create', 'Do not replace', [], 0);
        $assert($result['status'] === 'conflict', 'Competing first saves must conflict.');
        $assert((int) ($result['server']['id'] ?? 0) === (int) $created['id'] && $result['server']['body'] === 'New committed body', 'The duplicate-insert conflict must return the committed row instead of null.');
        return ['status' => $result['status'], 'current_revision' => (int) $result['server']['revision']];
    });
    // Controlled driver aborts verify retry ownership/rollback separately from
    // the real two-connection races above. The nontransactional counter is a
    // test probe only, so its attempts survive rollback. All DDL is private.
    $writer->run("CREATE TABLE `$probe` (attempts INT NOT NULL, fail_until INT NOT NULL, error_code INT NOT NULL) ENGINE=MyISAM");
    $writer->run("INSERT INTO `$probe` VALUES (0, 1, 1213)");
    $quotedKey = $writer->pdo()->quote($retryKey);
    $writer->run("CREATE TRIGGER `$trigger` BEFORE INSERT ON server_drafts FOR EACH ROW
        BEGIN
            DECLARE fault_code INT;
            IF NEW.context_key = $quotedKey THEN
                UPDATE `$probe` SET attempts = attempts + 1;
                IF (SELECT attempts <= fail_until FROM `$probe`) THEN
                    SELECT error_code INTO fault_code FROM `$probe`;
                    SIGNAL SQLSTATE '40001' SET MYSQL_ERRNO = fault_code, MESSAGE_TEXT = 'Synthetic draft retry probe';
                END IF;
            END IF;
        END");
    $retries = [];
    foreach ([1020, 1213] as $code) {
        $writer->run("UPDATE `$probe` SET attempts = 0, fail_until = 1, error_code = ?", [$code]);
        $result = $current->save($userId, $retryKey, 0, 'Retried', 'Exactly once', [], 0);
        $assert($result['status'] === 'saved' && (int) $result['draft']['revision'] === 1, 'An owned aborted transaction must retry safely.');
        $assert((int) $writer->fetchValue("SELECT attempts FROM `$probe`") === 2, 'Exactly one transient abort must require two attempts.');
        $current->discardByContext($userId, $retryKey);
        $writer->run("UPDATE `$probe` SET attempts = 0");
        try {
            $writer->transaction(fn () => $current->save($userId, $retryKey, 0, 'Nested', 'Do not retry an outer transaction', [], 0));
            throw new RuntimeException('A nested transaction must surface its abort.');
        } catch (PDOException $e) {
            $assert((int) ($e->errorInfo[1] ?? 0) === $code, 'The original nested driver error must propagate.');
        }
        $assert((int) $writer->fetchValue("SELECT attempts FROM `$probe`") === 1, 'Nested work must not retry.');
        $assert($current->findByContext($userId, $retryKey) === null, 'A failed nested write must remain rolled back.');
        $retries[$code] = ['owned_attempts' => 2, 'nested_attempts' => 1];
    }
    $writer->run("UPDATE `$probe` SET attempts = 0, fail_until = 3, error_code = 1213");
    try {
        $current->save($userId, $retryKey, 0, 'Exhausted', 'Do not persist', [], 0);
        throw new RuntimeException('Persistent contention must exhaust the bounded retry.');
    } catch (PDOException $e) {
        $assert((int) ($e->errorInfo[1] ?? 0) === 1213, 'The exhausted driver error must propagate.');
    }
    $assert((int) $writer->fetchValue("SELECT attempts FROM `$probe`") === 3, 'Retry must stop after three total attempts.');
    $assert($current->findByContext($userId, $retryKey) === null, 'Exhausted writes must leave no saved row.');
    echo json_encode(['mariadb_snapshot_extension_disabled_for_reader' => $mysqlCompatibility, 'cas_conflict' => $update, 'insert_conflict' => $insert, 'transient_aborts' => $retries, 'exhaustion_attempts' => 3], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
} finally {
    $writer->run("DROP TRIGGER IF EXISTS `$trigger`");
    $writer->run("DROP TABLE IF EXISTS `$probe`");
    $current->discardByContext($userId, $updateKey);
    $current->discardByContext($userId, $insertKey);
    $current->discardByContext($userId, $retryKey);
}
