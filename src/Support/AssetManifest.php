<?php

declare(strict_types=1);

namespace App\Support;

/** The same build manifest supplies PHP URLs and the Worker's public allowlist. */
final class AssetManifest
{
    /** @var array<string,mixed> */
    private array $manifest = [];
    /** @var array<string,string>|null */
    private ?array $urls = null;
    private ?string $version = null;

    public function __construct(private string $root)
    {
        $this->root = rtrim($root, '/\\');
        $file = $this->root . '/config/assets.json';
        if (is_file($file)) {
            $json = file_get_contents($file);
            $decoded = $json === false ? null : json_decode($json, true);
            if (is_array($decoded)) {
                $this->manifest = $decoded;
            }
        }
    }

    public function version(): string
    {
        if ($this->version !== null) {
            return $this->version;
        }
        $version = $this->manifest['version'] ?? null;
        if (is_string($version) && preg_match('/^[a-f0-9]{16}$/D', $version)) {
            return $this->version = $version;
        }

        // Source-only checkouts still render the working textarea UI. The normal
        // build avoids hashing these large source files during every PHP request.
        $files = array_merge(glob($this->root . '/public/assets/*.css') ?: [], glob($this->root . '/public/assets/*.js') ?: []);
        sort($files);
        $hash = hash_init('sha256');
        foreach ($files as $file) {
            hash_update($hash, basename($file) . "\0");
            hash_update_file($hash, $file);
        }
        return $this->version = substr(hash_final($hash), 0, 16);
    }

    /** @return array<string,string> */
    public function urls(): array
    {
        if ($this->urls !== null) {
            return $this->urls;
        }
        $urls = [];
        foreach (['app.css', 'imladris.css', 'app.js', 'composer.js', 'passkeys.js', 'tour.js', 'fonts/imladris/eb-garamond-latin-400-normal.woff2'] as $name) {
            $urls[$name] = '/assets/' . $name . '?v=' . $this->version();
        }
        foreach (is_array($this->manifest['urls'] ?? null) ? $this->manifest['urls'] : [] as $name => $url) {
            if (is_string($name) && is_string($url)
                && preg_match('#^/assets/dist/[A-Za-z0-9_-]+\.(?:css|js|woff2)$#D', $url)
                && is_file($this->root . '/public' . $url)) {
                $urls[$name] = $url;
            }
        }
        // Never request a missing module or stylesheet in a source-only deploy.
        // composer.js continues to provide the textarea and server form submit.
        if (!isset($urls['wysiwyg-composer.js'], $urls['wysiwyg-composer.css'])) {
            unset($urls['wysiwyg-composer.js'], $urls['wysiwyg-composer.css']);
        }
        return $this->urls = $urls;
    }
}
