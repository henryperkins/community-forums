<?php
// Isolated source-level reproduction: no .env, network or application database.
declare(strict_types=1);
$root = $argv[1] ?? dirname(__DIR__, 3);
$revisionContext = $argv[2] ?? null;
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $path = $root . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) { require $path; }
    }
});
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE settings (`key` TEXT PRIMARY KEY, `value` TEXT, updated_at TEXT)');
$db = new App\Core\Database([]);
$db->setPdo($pdo);
$settings = new App\Repository\SettingRepository($db);
$audit = new App\Repository\ModerationLogRepository($db);
$flags = new App\Core\FeatureFlags($settings);
$ui = new App\Service\AdminSettingsService($db, $settings, $audit, new App\Security\WriteGate(), $flags);
$observations = [];
foreach (['observe', 'hold', 'block'] as $configuredMode) {
    $pdo->exec('DELETE FROM settings');
    $runtime = new App\Service\AntiAbuseService($db, new App\Core\Config(['antiabuse' => ['mode' => $configuredMode]]), $settings, $audit);
    $observations[] = [
        'case' => 'no stored override',
        'configured_mode' => $configuredMode,
        'runtime_mode' => $runtime->mode(),
        'admin_form_mode' => $ui->moderationModel()['antiabuse_mode'],
    ];
}
foreach (['hold', '', 'invalid-mode'] as $storedMode) {
    $pdo->exec('DELETE FROM settings');
    $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?)')->execute(['antiabuse_mode', json_encode($storedMode)]);
    $runtime = new App\Service\AntiAbuseService($db, new App\Core\Config(['antiabuse' => ['mode' => 'block']]), $settings, $audit);
    $observations[] = [
        'case' => 'stored override',
        'configured_mode' => 'block',
        'stored_override' => $storedMode,
        'runtime_mode' => $runtime->mode(),
        'admin_form_mode' => $ui->moderationModel()['antiabuse_mode'],
    ];
}
echo json_encode([
    'recorded_at_utc' => gmdate('c'),
    'revision_context' => $revisionContext,
    'boundary' => 'Direct HEAD service methods with real PDO SQLite in-memory SELECTs. This is a reproduction of mode resolution, not a MySQL, HTTP, browser or full-suite result.',
    'observations' => $observations,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
