<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;
use App\Security\PasswordHasher;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
Env::load($root . '/.env');

$config = Config::fromFile($root . '/config/config.php');

// Tests run against a dedicated database with a freshly migrated schema.
$dbConfig = $config->all()['db'];
$dbConfig['database'] = Env::get('DB_TEST_DATABASE', 'retroboards_test');

$database = new Database($dbConfig);
$pdo = $database->pdo();

// Rebuilding the schema (drop + replay every migration) costs ~14s and dominates
// short runs. Only pay it when the migration files actually changed; otherwise
// the existing schema is reused and per-test transaction rollback keeps data
// clean. Force a rebuild with RB_TEST_FRESH=1 (also recovers from an interrupted
// run that left committed rows behind).
$migrationsPath = $config->get('paths.migrations');
$migrator = new Migrator($pdo, $migrationsPath);

$fingerprintSource = '';
foreach (glob($migrationsPath . '/*.php') ?: [] as $migrationFile) {
    $fingerprintSource .= basename($migrationFile) . ':' . filemtime($migrationFile) . "\n";
}
$fingerprint = md5($fingerprintSource);
$stampFile = sys_get_temp_dir() . '/rb-test-schema-' . md5((string) $dbConfig['database']) . '.fingerprint';

$schemaIsCurrent = getenv('RB_TEST_FRESH') !== '1'
    && is_file($stampFile)
    && trim((string) file_get_contents($stampFile)) === $fingerprint
    && $migrator->isSynced();

if (!$schemaIsCurrent) {
    $migrator->fresh();
    file_put_contents($stampFile, $fingerprint);
}

// Argon2id at PHP's secure defaults is ~300ms per hash by design — across the
// suite's many makeUser()/login flows that alone is minutes. Tests don't assert
// hash strength, so drop the cost to near-zero. Production never calls this and
// keeps the secure defaults (DESIGN §11).
PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);

// Point the rate-limit store at a throwaway directory (most tests inject an
// in-memory limiter anyway).
$config = new Config(array_replace_recursive($config->all(), [
    'paths' => ['ratelimit' => sys_get_temp_dir() . '/rb-test-ratelimit'],
    // Uploaded media goes to a throwaway dir, never the real storage root.
    'uploads' => ['storage_path' => sys_get_temp_dir() . '/rb-test-media'],
    // Assert the HSTS header in tests regardless of the local .env value.
    'security' => ['hsts' => true],
]));

$GLOBALS['__RB_TEST_PDO'] = $pdo;
$GLOBALS['__RB_TEST_CONFIG'] = $config;
$GLOBALS['__RB_TEST_DBCONFIG'] = $dbConfig;
