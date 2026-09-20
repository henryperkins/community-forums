<?php
// Local lab only: match production's immutable hashed-asset caching policy.
$root = '/home/ubuntu/community-forums/public';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($root . $path);
if (str_starts_with($path, '/assets/dist/') && $file && str_starts_with($file, $root . '/assets/dist/') && is_file($file)) {
    $types = ['css' => 'text/css', 'js' => 'text/javascript', 'woff2' => 'font/woff2'];
    header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    return true;
}
if ($file && str_starts_with($file, $root . '/') && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') { return false; }
require $root . '/index.php';
