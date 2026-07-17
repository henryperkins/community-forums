<?php

declare(strict_types=1);

use App\Support\ImladrisAssetBuilder;

require dirname(__DIR__) . '/vendor/autoload.php';

$builder = new ImladrisAssetBuilder(dirname(__DIR__));
$check = in_array('--check', $argv, true);
$printApplicationDigest = in_array('--print-application-digest', $argv, true);

try {
    if ($printApplicationDigest) {
        fwrite(STDOUT, $builder->applicationSurfaceDigest() . PHP_EOL);
        exit(0);
    }
    if ($check) {
        $errors = $builder->check();
        if ($errors !== []) {
            fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
            exit(1);
        }
        fwrite(STDOUT, "Imladris runtime assets are current.\n");
        exit(0);
    }

    $files = $builder->build();
    fwrite(STDOUT, 'Built Imladris runtime assets (' . count($files) . " files).\n");
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
