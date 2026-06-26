<?php

declare(strict_types=1);

use App\Core\App;

// Let the PHP built-in server serve existing static files directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if (is_file($file)) {
        return false;
    }
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

App::boot($root)->run();
