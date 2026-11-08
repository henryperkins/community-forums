<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;

// Destructive only to this explicitly selected, dedicated rehearsal database.
if (getenv('DB_DATABASE') !== 'retroboards_flags_rehearsal') {
    throw new RuntimeException('Select DB_DATABASE=retroboards_flags_rehearsal.');
}
$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));
(new Migrator($db->migrationPdo(), $root . '/database/migrations'))->fresh();
$users = new UserRepository($db);
$adminId = $users->create([
    'username' => 'rehearsal_admin', 'email' => 'rehearsal@retro.test',
    'password_hash' => password_hash('fixture-only', PASSWORD_BCRYPT, ['cost' => 4]),
]);
$db->run("UPDATE users SET role = 'admin' WHERE id = ?", [$adminId]);
$settings = new SettingRepository($db);
$scratch = sys_get_temp_dir() . '/rb-flags-lock-' . bin2hex(random_bytes(6));
mkdir($scratch, 0700);
$failures = [];
$observations = [];

/** @return array{disable:string,restore:string} */
function recipes(string $markdown): array
{
    preg_match_all("~php -r '([^']*)'~s", $markdown, $matches);
    if (count($matches[1]) !== 3) {
        throw new RuntimeException('Expected the inspect, disable and restore PHP recipes.');
    }
    return ['disable' => $matches[1][1], 'restore' => $matches[1][2]];
}

/** @return resource */
function child(string $code, string $name)
{
    global $root, $scratch;
    $process = proc_open([PHP_BINARY, '-r', $code], [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', $scratch . '/' . $name . '.out', 'w'],
        2 => ['file', $scratch . '/' . $name . '.err', 'w'],
    ], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start rehearsal child.');
    }
    return $process;
}

function waitFor(string $file): void
{
    $deadline = microtime(true) + 8;
    while (!is_file($file)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Timed out waiting for ' . basename($file));
        }
        usleep(10_000);
    }
}

/** @param array<string,mixed>|null $seed @return array<string,mixed> */
function collision(string $code, ?array $seed, string $name, bool $locked): array
{
    global $db, $settings, $adminId, $scratch;
    $db->run('DELETE FROM settings WHERE `key` = ?', ['features']);
    if ($seed !== null) {
        $settings->set('features', (object) $seed);
    }
    $ready = $scratch . '/' . $name . '.ready';
    $release = $scratch . '/' . $name . '.release';
    $started = $scratch . '/' . $name . '.started';
    $done = $scratch . '/' . $name . '.done';
    // Only add synchronization after the published map read; keep its SQL and
    // mutation statements unchanged, inside or outside its original transaction.
    $gate = 'touch(' . var_export($ready, true) . ');'
        . '$deadline=microtime(true)+8; while(!is_file(' . var_export($release, true) . ')) {'
        . 'if(microtime(true)>$deadline){throw new RuntimeException("release timeout");} usleep(10000);}';
    $instrumented = preg_replace_callback(
        '~\$f\s*=\s*(?:\(array\)\s*)?\$s->(?:get|lockForUpdate)\([^;]+;~',
        static fn (array $match): string => $match[0] . $gate,
        $code,
        1,
        $count,
    );
    if ($count !== 1) {
        throw new RuntimeException('Could not locate the published map read.');
    }
    $a = child($instrumented, $name . '-a');
    $b = null;
    try {
        waitFor($ready);
        $console = 'require "vendor/autoload.php"; App\\Core\\Env::load(".env");'
            . '$c=App\\Core\\Config::fromFile("config/config.php");'
            . '$db=new App\\Core\\Database($c->get("db"));'
            . '$s=new App\\Repository\\SettingRepository($db);'
            . '$actor=(new App\\Repository\\UserRepository($db))->findEntity(' . $adminId . ');'
            . '$service=new App\\Service\\FeatureFlagAdminService($db,$s,new App\\Repository\\ModerationLogRepository($db),new App\\Security\\WriteGate(),new App\\Core\\FeatureFlags($s));'
            . 'touch(' . var_export($started, true) . ');'
            . '$service->setEnabled($actor,"search",false); touch(' . var_export($done, true) . ');';
        $b = child($console, $name . '-b');
        waitFor($started);
        if ($locked) {
            usleep(150_000);
        } else {
            waitFor($done);
        }
        $pending = !is_file($done);
        touch($release);
        $aExit = proc_close($a);
        $a = null;
        $bExit = proc_close($b);
        $b = null;
        if ($aExit !== 0 || $bExit !== 0) {
            throw new RuntimeException('Writer failed: ' . file_get_contents($scratch . '/' . $name . '-a.err')
                . file_get_contents($scratch . '/' . $name . '-b.err'));
        }
        return ['final_map' => $settings->get('features'), 'console_pending_before_release' => $pending];
    } finally {
        touch($release);
        if (is_resource($a)) { proc_close($a); }
        if (is_resource($b)) { proc_close($b); }
    }
}

function check(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) { $failures[] = $message; }
}

try {
    $currentMarkdown = file_get_contents($root . '/docs/runbooks/operations.md');
    $current = recipes($currentMarkdown);
    $git = proc_open(['git', 'show', 'b1d2a591b3846fda586cd66ffd2346438de8286c:docs/runbooks/operations.md'], [
        0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes, $root);
    $baselineMarkdown = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($git) !== 0) { throw new RuntimeException('Could not load the pinned baseline.'); }
    $baseline = collision(recipes($baselineMarkdown)['disable'], ['email' => false], 'baseline', false);
    check(!array_key_exists('search', $baseline['final_map']), 'Baseline did not reproduce the lost console change.');
    $observations['baseline_unlocked'] = $baseline;

    foreach (['absent' => null, 'existing' => ['email' => false, 'legacy' => ['keep' => 'value']]] as $label => $seed) {
        $locked = str_contains($current['disable'], 'lockForUpdate');
        $result = collision($current['disable'], $seed, 'current-' . $label, $locked);
        $map = $result['final_map'];
        check(($map['community'] ?? null) === false && ($map['search'] ?? null) === false, $label . ': independent changes were not both retained.');
        foreach ($seed ?? [] as $key => $value) { check(($map[$key] ?? null) === $value, $label . ': prior key changed: ' . $key); }
        $observations['current_' . $label] = $result;
    }
    $result = collision($current['restore'], ['community' => false, 'legacy' => ['keep']], 'restore', str_contains($current['restore'], 'lockForUpdate'));
    check(!array_key_exists('community', $result['final_map']) && ($result['final_map']['search'] ?? null) === false && $result['final_map']['legacy'] === ['keep'], 'Restore did not preserve the concurrent and unknown keys.');
    $observations['restore_with_console'] = $result;

    $settings->set('features', ['community' => false]);
    $exit = proc_close(child($current['restore'], 'empty'));
    $raw = $db->fetchValue('SELECT `value` FROM settings WHERE `key` = ?', ['features']);
    check($exit === 0 && $raw === '{}', 'The last override did not become an empty JSON object.');
    $observations['last_override'] = ['exit' => $exit, 'stored_json' => $raw];

    foreach (['null', '"not-an-object"', '[false,true]', '{"community":false,"legacy_counter":1e999}', '{"community":1e999,"search":false}'] as $index => $raw) {
      foreach (['disable', 'restore'] as $action) {
        $db->run('UPDATE settings SET `value` = ? WHERE `key` = ?', [$raw, 'features']);
        $before = $db->fetchValue('SELECT `value` FROM settings WHERE `key` = ?', ['features']);
        $name = 'refuse-' . $action . '-' . $index;
        $exit = proc_close(child($current[$action], $name));
        $after = $db->fetchValue('SELECT `value` FROM settings WHERE `key` = ?', ['features']);
        $controlled = str_contains(file_get_contents($scratch . '/' . $name . '.err'), 'RuntimeException: settings.features');
        check($exit !== 0 && $after === $before && $controlled, 'Malformed/unwritable map was not refused unchanged by its guard: ' . $action . '-' . $index);
        $observations[$name] = ['exit' => $exit, 'unchanged' => $after === $before, 'controlled_refusal' => $controlled];
      }
    }
    $settings->set('features', new stdClass());
    $deliberateEnable = str_replace('$f["community"]=false;', '$f["custom_css"]=true;', $current['disable'], $count);
    check($count === 1, 'Could not adapt the single flag assignment.');
    $exit = proc_close(child($deliberateEnable, 'deliberate-enable'));
    check($exit === 0 && $settings->get('features')['custom_css'] === true, 'Manual deliberate enablement was lost.');
    $observations['deliberate_enable'] = ['exit' => $exit, 'custom_css_override' => $settings->get('features')['custom_css'] ?? null];

    echo json_encode([
        'recorded_at_utc' => gmdate('c'), 'database_server' => $db->pdo()->getAttribute(PDO::ATTR_SERVER_VERSION),
        'runbook_sha256' => hash('sha256', $currentMarkdown),
        'disable_recipe_sha256' => hash('sha256', $current['disable']),
        'restore_recipe_sha256' => hash('sha256', $current['restore']),
        'boundary' => 'Dedicated local rehearsal database; published recipes extracted verbatim, with synchronization only after their map read; competing writer uses the real console service.',
        'observations' => $observations, 'failures' => $failures,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
} finally {
    foreach (glob($scratch . '/*') ?: [] as $file) { unlink($file); }
    rmdir($scratch);
}
exit($failures === [] ? 0 : 1);
