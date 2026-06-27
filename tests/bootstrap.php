<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
Env::load($root . '/.env');

$config = Config::fromFile($root . '/config/config.php');

// Tests run against a dedicated database with a freshly migrated schema.
$dbConfig = $config->all()['db'];
$dbConfig['database'] = Env::get('DB_TEST_DATABASE', 'retroboards_test');

$database = new Database($dbConfig);
$pdo = $database->pdo();

(new Migrator($pdo, $config->get('paths.migrations')))->fresh();

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
