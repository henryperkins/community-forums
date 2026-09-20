<?php

declare(strict_types=1);

// Standalone committed-fixture test: never include tests/bootstrap.php (it owns
// the ordinary PHPUnit schema), and never use the development database.
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\ForbiddenException;
use App\Core\Migrator;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\AccountDeletionRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\ServerDraftRepository;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Security\LastOwnerGuard;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\AccountLifecycleService;
use App\Service\UserModerationService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
Env::load(dirname(__DIR__, 2) . '/.env');
$config = Config::fromFile(dirname(__DIR__, 2) . '/config/config.php');
$dbConfig = $config->all()['db'];
$schema = getenv('DB_LIFECYCLE_RACE_DATABASE');
if ($schema !== 'retroboards_unified_lifecycle_race') {
    throw new RuntimeException('Set DB_LIFECYCLE_RACE_DATABASE=retroboards_unified_lifecycle_race; no other schema is permitted.');
}
$dbConfig['database'] = $schema;
$db = new Database($dbConfig);
$db->run('SET SESSION innodb_lock_wait_timeout = 10');
$users = new UserRepository($db);
$owners = new ProtectedOwnerRepository($db);
$log = new ModerationLogRepository($db);
PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);
$lifecycle = new AccountLifecycleService($db, $users, new AccountDeletionRepository($db), new SessionRepository($db), $log, new ServerDraftRepository($db), new ReauthGate(new PasswordHasher()), new WebAuthnCredentialRepository($db), new LastOwnerGuard($owners, $users));
$moderation = new UserModerationService($db, $users, $log, new WriteGate(), new BoardModeratorRepository($db));

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function perform(string $action, User $user, User $admin): string
{
    global $lifecycle, $moderation;
    try {
        match ($action) {
            'deactivate' => $lifecycle->deactivate($user, 'password123'),
            'cancel' => $lifecycle->cancelDeletion($user),
            'ban' => $moderation->ban($admin, $user->id(), 'Concurrent moderation fixture'),
        };
        return 'applied';
    } catch (ValidationException) {
        return 'refused';
    }
}

if (($argv[1] ?? '') === 'child') {
    $subject = $users->findEntity((int) $argv[3]);
    $admin = $users->findEntity((int) $argv[4]);
    check($subject !== null && $admin !== null, 'Missing child fixture');
    echo json_encode(['ready' => true, 'connection' => (int) $db->fetchValue('SELECT CONNECTION_ID()')]) . "\n";
    flush();
    // Parent observes a genuine server-side lock wait before committing.
    $outcome = perform($argv[2], $subject, $admin);
    echo json_encode(['outcome' => $outcome]) . "\n";
    exit(0);
}

(new Migrator($db->migrationPdo(), $config->get('paths.migrations')))->migrate();
$ids = [];
$prefix = 'race_' . bin2hex(random_bytes(5));
$children = [];
$results = [];
try {
    foreach (['admin', 'member', 'coowner'] as $name) {
        $ids[$name] = $users->create(['username' => $prefix . '_' . $name, 'email' => $prefix . '_' . $name . '@example.test', 'password_hash' => (new PasswordHasher())->hash('password123'), 'role' => $name === 'member' ? 'user' : 'admin']);
    }
    $admin = $users->findEntity($ids['admin']);
    foreach (['deactivate', 'cancel'] as $action) {
        foreach (['moderator_first', 'lifecycle_first'] as $order) {
            $id = $ids['member'];
            $db->run('DELETE FROM bans WHERE user_id = ?', [$id]);
            $db->run('DELETE FROM account_deletion_requests WHERE user_id = ?', [$id]);
            $users->setStatus($id, 'active', null);
            $user = $users->findEntity($id);
            if ($action === 'cancel') {
                $lifecycle->requestDeletion($user, 'password123');
                $user = $users->findEntity($id);
            }
            $db->pdo()->beginTransaction();
            $users->findForUpdate($id);
            // The child loads the committed, pre-transition subject into a User.
            // Both sides then invoke the real services on separate connections.
            $childAction = $order === 'moderator_first' ? $action : 'ban';
            $process = proc_open([PHP_BINARY, __FILE__, 'child', $childAction, (string) $id, (string) $admin->id()], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            check(is_resource($process), 'Could not start race child');
            $children[] = $process;
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            $ready = json_decode((string) fgets($pipes[1]), true);
            check(($ready['ready'] ?? false) === true, 'Child did not become ready');
            $waiting = false;
            $deadline = microtime(true) + 5;
            do {
                foreach ($db->fetchAll('SHOW FULL PROCESSLIST') as $connection) {
                    if ((int) $connection['Id'] === $ready['connection'] && str_contains(strtoupper((string) $connection['Info']), 'FOR UPDATE')) {
                        $waiting = true;
                    }
                }
                if (!$waiting) {
                    usleep(10000);
                }
            } while (!$waiting && microtime(true) < $deadline);
            check($waiting, 'Child did not reach a competing locking read');
            $parentOutcome = perform($order === 'moderator_first' ? 'ban' : $action, $user, $admin);
            $db->pdo()->commit();
            $childResult = json_decode((string) fgets($pipes[1]), true);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            array_pop($children);
            check($exit === 0, 'Child exit ' . $exit . ': ' . $stderr);
            $current = $users->findEntity($id);
            check($current->status() === 'banned', 'Lost moderation restriction');
            try {
                (new WriteGate())->assertCanWrite($current);
                throw new RuntimeException('Normal write was not forbidden');
            } catch (ForbiddenException $e) {
                check($e->statusCode() === 403, 'Normal write did not return 403');
            }
            $expectedChild = $order === 'moderator_first' && $action === 'deactivate' ? 'refused' : 'applied';
            check(($childResult['outcome'] ?? '') === $expectedChild, 'Unexpected child transition result');
            if ($action === 'cancel') {
                check((new AccountDeletionRepository($db))->pendingForUser($id) === null, 'Cancellation did not remove durable request');
            }
            $results[] = ['action' => $action, 'order' => $order, 'competing_lock_observed' => true, 'parent_outcome' => $parentOutcome, 'child_outcome' => $childResult['outcome'], 'child_exit' => $exit, 'status' => $current->status(), 'normal_write' => 403];
        }
    }
    // Two administrators cannot each lock their own row before the shared
    // owner rowset. The losing owner-loss request must refuse, not deadlock.
    $owners->designate($ids['admin']);
    $owners->designate($ids['coowner']);
    $db->pdo()->beginTransaction();
    $lifecycle->deactivate($admin, 'password123');
    $process = proc_open([PHP_BINARY, __FILE__, 'child', 'deactivate', (string) $ids['coowner'], (string) $admin->id()], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    check(is_resource($process), 'Could not start owner race child');
    $children[] = $process;
    fclose($pipes[0]);
    stream_set_timeout($pipes[1], 15);
    $ready = json_decode((string) fgets($pipes[1]), true);
    check(($ready['ready'] ?? false) === true, 'Owner child did not become ready');
    $waiting = false;
    $deadline = microtime(true) + 5;
    do {
        foreach ($db->fetchAll('SHOW FULL PROCESSLIST') as $connection) {
            if ((int) $connection['Id'] === $ready['connection'] && str_contains(strtoupper((string) $connection['Info']), 'FOR UPDATE')) {
                $waiting = true;
            }
        }
        if (!$waiting) {
            usleep(10000);
        }
    } while (!$waiting && microtime(true) < $deadline);
    check($waiting, 'Owner child did not reach a competing locking read');
    $db->pdo()->commit();
    $childResult = json_decode((string) fgets($pipes[1]), true);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    array_pop($children);
    check($exit === 0, 'Owner child exit ' . $exit . ': ' . $stderr);
    check(($childResult['outcome'] ?? '') === 'refused', 'Both owners removed themselves');
    check($users->findEntity($ids['coowner'])->isActive(), 'Remaining owner lost write access');
    $results[] = ['action' => 'two_owner_deactivations', 'competing_lock_observed' => true, 'child_outcome' => $childResult['outcome'], 'child_exit' => $exit, 'active_owners' => 1];
    echo json_encode(['schema' => $schema, 'cases' => $results], JSON_PRETTY_PRINT) . "\n";
} finally {
    if ($db->pdo()->inTransaction()) {
        $db->pdo()->rollBack();
    }
    foreach ($children as $process) {
        proc_terminate($process);
        proc_close($process);
    }
    // Exact IDs from this invocation only. Foreign keys handle dependent rows;
    // audit rows have generic target IDs, so remove those explicitly first.
    foreach ($ids as $id) {
        $db->run("DELETE FROM moderation_log WHERE target_type = 'user' AND target_id = ?", [$id]);
    }
    foreach (array_reverse($ids) as $id) {
        $db->run('DELETE FROM users WHERE id = ? AND username LIKE ?', [$id, $prefix . '%']);
    }
    echo json_encode(['fixture_rows_remaining' => (int) $db->fetchValue('SELECT COUNT(*) FROM users WHERE username LIKE ?', [$prefix . '%'])]) . "\n";
}
