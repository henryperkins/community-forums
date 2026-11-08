<?php

declare(strict_types=1);

// Standalone committed-fixture race: never include tests/bootstrap.php (it owns
// the ordinary PHPUnit schema), and never use the development database.
//
// Proves that when an account's TOTP secret cannot be opened (sealed under a key
// this process does not have), the recovery-code fallback still consumes each
// code at most once while two sign-ins race for it. The parent completes one
// sign-in inside an open transaction; a separate PHP process runs the competing
// sign-in through the same service on its own connection. The parent observes
// the child blocked on the recovery-code row lock before committing.
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;
use App\Core\ValidationException;
use App\Repository\MfaRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\SecretBox;
use App\Security\Totp;
use App\Security\WriteGate;
use App\Service\MfaService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
Env::load(dirname(__DIR__, 2) . '/.env');
$config = Config::fromFile(dirname(__DIR__, 2) . '/config/config.php');
$dbConfig = $config->all()['db'];
$schema = getenv('DB_MFA_RACE_DATABASE');
if ($schema !== 'retroboards_mfa_recovery_race') {
    throw new RuntimeException('Set DB_MFA_RACE_DATABASE=retroboards_mfa_recovery_race; no other schema is permitted.');
}
$dbConfig['database'] = $schema;
$db = new Database($dbConfig);
$db->run('SET SESSION innodb_lock_wait_timeout = 10');

// Dummy keys only. The process runs with ACTIVE; the TOTP secret is sealed under LOST.
$activeKey = str_repeat('1a', 32);
$lostKey = str_repeat('2b', 32);
$items = $config->all();
$items['app']['key'] = $activeKey;
$appConfig = new Config($items);
$repo = new MfaRepository($db);
$users = new UserRepository($db);
$mfa = new MfaService($repo, $users, new ReauthGate(new PasswordHasher()), new SecretBox($activeKey), new Totp(), new WriteGate(), new ModerationLogRepository($db), $appConfig);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string,mixed> */
function attempt(MfaService $mfa, string $token, string $code): array
{
    try {
        $result = $mfa->completeLoginChallenge($token, $code);
        return ['outcome' => 'signed_in', 'user_id' => $result['user']->id(), 'method' => $result['method']];
    } catch (ValidationException $e) {
        return ['outcome' => 'denied', 'error' => (string) ($e->errors['code'] ?? '')];
    }
}

if (($argv[1] ?? '') === 'child') {
    echo json_encode(['ready' => true, 'connection' => (int) $db->fetchValue('SELECT CONNECTION_ID()')]) . "\n";
    flush();
    // Wait for the parent's go signal so it can record the connection first.
    fgets(STDIN);
    echo json_encode(attempt($mfa, $argv[2], $argv[3])) . "\n";
    exit(0);
}

/** @return array{0:resource,1:array<int,resource>,2:array<string,mixed>} */
function startChild(string $token, string $code): array
{
    $process = proc_open([PHP_BINARY, __FILE__, 'child', $token, $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    check(is_resource($process), 'Could not start race child');
    stream_set_timeout($pipes[1], 15);
    $ready = json_decode((string) fgets($pipes[1]), true);
    check(($ready['ready'] ?? false) === true, 'Child did not become ready');
    fwrite($pipes[0], "go\n");
    fflush($pipes[0]);
    return [$process, $pipes, $ready];
}

/** @param array<int,resource> $pipes @return array{0:array<string,mixed>,1:int,2:string} */
function finishChild($process, array $pipes): array
{
    $result = json_decode((string) fgets($pipes[1]), true);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [is_array($result) ? $result : [], proc_close($process), (string) $stderr];
}

function waitForRecoveryCodeLock(Database $db, int $connection): bool
{
    $deadline = microtime(true) + 5;
    do {
        foreach ($db->fetchAll('SHOW FULL PROCESSLIST') as $row) {
            if ((int) $row['Id'] === $connection && str_contains(strtoupper((string) $row['Info']), 'UPDATE USER_RECOVERY_CODES')) {
                return true;
            }
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    return false;
}

(new Migrator($db->migrationPdo(), $config->get('paths.migrations')))->migrate();
$prefix = 'mfarace_' . bin2hex(random_bytes(5));
$userId = null;
$children = [];
$cases = [];
$childLogs = [];
try {
    PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);
    $userId = $users->create([
        'username' => $prefix, 'email' => $prefix . '@example.test',
        'password_hash' => (new PasswordHasher())->hash('password123'), 'role' => 'user',
    ]);
    $secret = (new Totp())->generateSecret();
    $repo->savePendingTotp($userId, (new SecretBox($lostKey))->encrypt($secret));
    $repo->enableTotp($userId, 0);
    $codes = ['RACE1-AAAAA', 'RACE2-BBBBB', 'RACE3-CCCCC'];
    $repo->replaceRecoveryCodes($userId, array_map(
        static fn (string $c): string => hash_hmac('sha256', str_replace('-', '', $c), $activeKey),
        $codes,
    ));
    $usedAt = static fn (string $c): ?string => ($v = $db->fetchValue(
        'SELECT used_at FROM user_recovery_codes WHERE user_id = ? AND code_hash = ?',
        [$userId, hash_hmac('sha256', str_replace('-', '', $c), $activeKey)],
    )) === false || $v === null ? null : (string) $v;
    $consumed = static fn (string $t): bool => $db->fetchValue(
        'SELECT consumed_at FROM mfa_login_challenges WHERE token_hash = ?',
        [hash('sha256', $t)],
    ) !== null;

    // A: two sign-ins (two challenges) race with the same recovery code.
    // B: one challenge submitted twice with the same recovery code.
    foreach ([
        'two_challenges_same_code' => [$codes[0], true],
        'same_challenge_double_submit' => [$codes[1], false],
    ] as $case => [$code, $separateChallenge]) {
        $parentToken = $repo->createLoginChallenge($userId, '/inbox', '127.0.0.1', 'race-parent');
        $childToken = $separateChallenge ? $repo->createLoginChallenge($userId, '/inbox', '127.0.0.1', 'race-child') : $parentToken;
        $db->pdo()->beginTransaction();
        $parent = attempt($mfa, $parentToken, $code);
        check(($parent['outcome'] ?? '') === 'signed_in', $case . ': parent did not sign in');
        [$process, $pipes, $ready] = startChild($childToken, $code);
        $children[] = $process;
        $blocked = waitForRecoveryCodeLock($db, (int) $ready['connection']);
        check($blocked, $case . ': child did not reach the competing recovery-code update');
        $db->pdo()->commit();
        [$child, $exit, $stderr] = finishChild($process, $pipes);
        array_pop($children);
        $childLogs[] = trim($stderr);
        check($exit === 0, $case . ': child exit ' . $exit . ': ' . $stderr);
        check(($child['outcome'] ?? '') === 'denied', $case . ': both sign-ins succeeded with one recovery code');
        check($usedAt($code) !== null, $case . ': code not marked used');
        check($consumed($parentToken), $case . ': winning challenge not consumed');
        if ($separateChallenge) {
            check(!$consumed($childToken), $case . ': losing challenge was consumed');
        }
        $cases[] = [
            'case' => $case,
            'competing_lock_observed' => $blocked,
            'parent' => $parent,
            'child' => $child,
            'child_exit' => $exit,
            'code_used_once' => $usedAt($code) !== null,
            'losing_challenge_consumed' => $separateChallenge ? $consumed($childToken) : null,
        ];
    }

    // C: control, no contention. The child path signs in on its own.
    $token = $repo->createLoginChallenge($userId, '/inbox', '127.0.0.1', 'race-control');
    [$process, $pipes] = startChild($token, $codes[2]);
    $children[] = $process;
    [$control, $exit, $stderr] = finishChild($process, $pipes);
    array_pop($children);
    $childLogs[] = trim($stderr);
    check(($control['outcome'] ?? '') === 'signed_in' && ($control['user_id'] ?? null) === $userId, 'Uncontended child did not sign in');
    $cases[] = ['case' => 'uncontended_control', 'child' => $control, 'child_exit' => $exit];

    $audit = $db->fetchAll(
        "SELECT action, after_json FROM moderation_log WHERE target_type = 'user' AND target_id = ? ORDER BY id",
        [$userId],
    );
    echo json_encode([
        'schema' => $schema,
        'cases' => $cases,
        'unused_recovery_codes' => (int) $db->fetchValue('SELECT COUNT(*) FROM user_recovery_codes WHERE user_id = ? AND used_at IS NULL', [$userId]),
        'audit' => array_map(static fn (array $r): array => ['action' => $r['action'], 'after' => json_decode((string) $r['after_json'], true)], $audit),
        'child_stderr' => array_values(array_unique(array_map(static fn (string $l): string => preg_replace(['/^\[[^]]*\] /', '/user #\d+/'], ['', 'user #N'], $l) ?? $l, array_filter($childLogs)))),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    if ($db->pdo()->inTransaction()) {
        $db->pdo()->rollBack();
    }
    foreach ($children as $process) {
        proc_terminate($process);
        proc_close($process);
    }
    if ($userId !== null) {
        $db->run("DELETE FROM moderation_log WHERE target_type = 'user' AND target_id = ?", [$userId]);
        $db->run('DELETE FROM users WHERE id = ? AND username = ?', [$userId, $prefix]);
    }
    echo json_encode(['fixture_rows_remaining' => (int) $db->fetchValue('SELECT COUNT(*) FROM users WHERE username LIKE ?', [$prefix . '%'])]) . "\n";
}
