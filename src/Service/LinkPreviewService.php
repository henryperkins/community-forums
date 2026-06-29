<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\EgressBlockedException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
use App\Security\EgressGuard;

final class LinkPreviewService
{
    public function __construct(
        private Database $db,
        private PostRepository $posts,
        private SettingRepository $settings,
        private Config $config,
        private EgressGuard $egress,
    ) {
    }

    public function queueFromBody(string $sourceType, int $sourceId, string $body): int
    {
        if (!$this->publicFetchableSource($sourceType, $sourceId)) {
            return 0;
        }

        $queued = 0;
        foreach ($this->extractUrls($body) as $url) {
            if ($this->isNeverFetchedLocalUrl($url)) {
                continue;
            }
            $queued += $this->db->run(
                "INSERT INTO link_previews (source_type, source_id, url, url_hash, status, created_at)
                 VALUES (?, ?, ?, ?, 'queued', UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE status = IF(status = 'purged', 'queued', status), updated_at = UTC_TIMESTAMP()",
                [$sourceType, $sourceId, $url, hash('sha256', $url)],
            )->rowCount() > 0 ? 1 : 0;
        }
        return $queued;
    }

    /** @return array<int,array<string,mixed>> */
    public function cardsForSources(string $sourceType, array $sourceIds): array
    {
        $sourceIds = array_values(array_unique(array_filter(array_map('intval', $sourceIds), fn (int $id): bool => $id > 0)));
        if ($sourceIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($sourceIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT * FROM link_previews
             WHERE source_type = ? AND source_id IN ($placeholders) AND status = 'fetched'
             ORDER BY id ASC",
            array_merge([$sourceType], $sourceIds),
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['source_id']][] = [
                'url' => (string) ($row['final_url'] ?: $row['url']),
                'title' => (string) ($row['title'] ?: $row['url']),
                'description' => (string) ($row['description'] ?? ''),
                'site_name' => (string) ($row['site_name'] ?? ''),
            ];
        }
        return $out;
    }

    public function refresh(int $id): void
    {
        $this->requireRow($id);
        $this->db->run(
            "UPDATE link_previews
             SET status = 'queued', final_url = NULL, http_status = NULL, error = NULL,
                 title = NULL, description = NULL, image_url = NULL, site_name = NULL,
                 fetched_at = NULL, purged_at = NULL, updated_at = UTC_TIMESTAMP()
             WHERE id = ?",
            [$id],
        );
    }

    public function purge(int $id): void
    {
        $this->requireRow($id);
        $this->db->run(
            "UPDATE link_previews
             SET status = 'purged', final_url = NULL, http_status = NULL, error = NULL,
                 title = NULL, description = NULL, image_url = NULL, site_name = NULL,
                 metadata = NULL, purged_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = ?",
            [$id],
        );
    }

    public function storeFetchedMetadata(int $id, string $finalUrl, int $httpStatus, string $html): void
    {
        $row = $this->requireRow($id);
        $this->validateFetchUrl($finalUrl);
        $meta = $this->extractMetadata($html);
        $this->db->run(
            "UPDATE link_previews
             SET status = 'fetched', final_url = ?, http_status = ?, title = ?, description = ?,
                 image_url = ?, site_name = ?, metadata = ?, error = NULL,
                 fetched_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = ?",
            [
                $finalUrl,
                $httpStatus,
                $meta['title'],
                $meta['description'],
                $meta['image_url'],
                $meta['site_name'],
                json_encode(['source_url' => $row['url']], JSON_UNESCAPED_SLASHES),
                $id,
            ],
        );
    }

    /** @return array{fetched:int,blocked:int,failed:int,skipped:int} */
    public function fetchQueued(int $limit = 25): array
    {
        $stats = ['fetched' => 0, 'blocked' => 0, 'failed' => 0, 'skipped' => 0];
        if ((bool) $this->settings->get('link_preview_kill_switch', false)) {
            $stats['skipped'] = count($this->queued($limit));
            return $stats;
        }

        foreach ($this->queued($limit) as $row) {
            $id = (int) $row['id'];
            try {
                $this->validateFetchUrl((string) $row['url']);
                [$finalUrl, $status, $html] = $this->fetchHtml((string) $row['url']);
                $this->storeFetchedMetadata($id, $finalUrl, $status, $html);
                $stats['fetched']++;
            } catch (EgressBlockedException|ValidationException $e) {
                $this->markBlocked($id, $e->getMessage());
                $stats['blocked']++;
            } catch (\Throwable $e) {
                $this->markFailed($id, $e->getMessage());
                $stats['failed']++;
            }
        }
        return $stats;
    }

    public function validateFetchUrl(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new ValidationException(['url' => 'Preview URL is malformed.']);
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new EgressBlockedException('Credentials in preview URL are not allowed.');
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new EgressBlockedException('Only http(s) preview URLs are allowed.');
        }
        $host = strtolower(trim((string) $parts['host'], '[]'));
        if (!$this->hostAllowed($host)) {
            throw new EgressBlockedException('Preview host is not allowlisted.');
        }
        if ($this->isNeverFetchedLocalUrl($url)) {
            throw new EgressBlockedException('Private RetroBoards URLs are not fetched for previews.');
        }
        $this->egress->validate($url);
    }

    /** @return array<int,array<string,mixed>> */
    private function queued(int $limit): array
    {
        $limit = max(1, min(100, $limit));
        return $this->db->fetchAll(
            "SELECT * FROM link_previews WHERE status = 'queued' ORDER BY created_at ASC, id ASC LIMIT " . $limit,
        );
    }

    private function markBlocked(int $id, string $error): void
    {
        $this->db->run(
            "UPDATE link_previews SET status = 'blocked', error = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?",
            [mb_substr($error, 0, 255), $id],
        );
    }

    private function markFailed(int $id, string $error): void
    {
        $this->db->run(
            "UPDATE link_previews SET status = 'failed', error = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?",
            [mb_substr($error, 0, 255), $id],
        );
    }

    /** @return array<string,mixed> */
    private function requireRow(int $id): array
    {
        $row = $this->db->fetch('SELECT * FROM link_previews WHERE id = ?', [$id]);
        if ($row === null) {
            throw new NotFoundException('Preview not found.');
        }
        return $row;
    }

    private function publicFetchableSource(string $sourceType, int $sourceId): bool
    {
        if ($sourceType === 'dm_message') {
            return false;
        }
        if ($sourceType === 'post') {
            $post = $this->posts->findWithContext($sourceId);
            return $post !== null
                && (int) ($post['is_deleted'] ?? 0) === 0
                && (int) ($post['is_pending'] ?? 0) === 0
                && (string) ($post['board_visibility'] ?? '') === 'public';
        }
        if ($sourceType === 'summary') {
            $row = $this->db->fetch(
                "SELECT b.visibility
                 FROM thread_summaries s
                 JOIN threads t ON t.id = s.thread_id
                 JOIN boards b ON b.id = t.board_id
                 WHERE s.id = ?",
                [$sourceId],
            );
            return $row !== null && (string) ($row['visibility'] ?? '') === 'public';
        }
        return false;
    }

    /** @return list<string> */
    private function extractUrls(string $body): array
    {
        if (preg_match_all('~https?://[^\s<>"\')\]]+~i', $body, $m) !== 1) {
            return [];
        }
        $urls = [];
        foreach ($m[0] as $url) {
            $urls[] = rtrim((string) $url, '.,;:!');
        }
        return array_values(array_unique($urls));
    }

    private function hostAllowed(string $host): bool
    {
        $allowed = $this->settings->get('link_preview_allowed_hosts', $this->config->get('link_previews.allowed_hosts', []));
        if (is_string($allowed)) {
            $allowed = array_filter(array_map('trim', explode(',', $allowed)));
        }
        if (!is_array($allowed) || $allowed === []) {
            return false;
        }
        foreach ($allowed as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern === $host) {
                return true;
            }
            if (str_starts_with($pattern, '*.') && str_ends_with($host, substr($pattern, 1))) {
                return true;
            }
        }
        return false;
    }

    private function isNeverFetchedLocalUrl(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return true;
        }
        $path = (string) ($parts['path'] ?? '');
        $appHost = strtolower((string) (parse_url((string) $this->config->get('app.url', ''), PHP_URL_HOST) ?: ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($appHost !== '' && $host !== $appHost) {
            return false;
        }
        if ($path === '' || $path === '/') {
            return false;
        }
        if (preg_match('~^/(messages|dm|settings|admin|notifications|media)(/|$)~', $path) === 1) {
            return true;
        }
        if (preg_match('~^/t/(\d+)~', $path, $m) === 1) {
            $thread = $this->db->fetch(
                'SELECT b.visibility FROM threads t JOIN boards b ON b.id = t.board_id WHERE t.id = ?',
                [(int) $m[1]],
            );
            return $thread !== null && (string) $thread['visibility'] !== 'public';
        }
        return false;
    }

    /** @return array{title:?string,description:?string,image_url:?string,site_name:?string} */
    private function extractMetadata(string $html): array
    {
        $html = substr($html, 0, (int) $this->config->get('link_previews.max_parse_bytes', 131072));
        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        if (!$ok) {
            return ['title' => null, 'description' => null, 'image_url' => null, 'site_name' => null];
        }

        $title = $this->firstMeta($doc, ['og:title', 'twitter:title']);
        if ($title === null) {
            $nodes = $doc->getElementsByTagName('title');
            $title = $nodes->length > 0 ? $nodes->item(0)?->textContent : null;
        }

        return [
            'title' => $this->cleanText($title, 255),
            'description' => $this->cleanText($this->firstMeta($doc, ['og:description', 'description', 'twitter:description']), 500),
            'image_url' => $this->cleanUrl($this->firstMeta($doc, ['og:image', 'twitter:image'])),
            'site_name' => $this->cleanText($this->firstMeta($doc, ['og:site_name']), 120),
        ];
    }

    /** @param list<string> $names */
    private function firstMeta(\DOMDocument $doc, array $names): ?string
    {
        foreach ($doc->getElementsByTagName('meta') as $meta) {
            $name = strtolower((string) ($meta->getAttribute('property') ?: $meta->getAttribute('name')));
            if (in_array($name, array_map('strtolower', $names), true)) {
                $content = trim((string) $meta->getAttribute('content'));
                if ($content !== '') {
                    return $content;
                }
            }
        }
        return null;
    }

    private function cleanText(?string $value, int $max): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
        if ($value === '') {
            return null;
        }
        return mb_substr(strip_tags($value), 0, $max);
    }

    private function cleanUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $parts = parse_url($value);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        return in_array($scheme, ['http', 'https'], true) ? mb_substr($value, 0, 1024) : null;
    }

    /** @return array{0:string,1:int,2:string} */
    private function fetchHtml(string $url): array
    {
        $maxBytes = (int) $this->config->get('link_previews.max_bytes', 262144);
        $timeout = (int) $this->config->get('link_previews.timeout_seconds', 4);
        $current = $url;
        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $this->validateFetchUrl($current);
            $ch = curl_init($current);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'RetroBoardsLinkPreview/1.0',
                CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
            ]);
            $raw = curl_exec($ch);
            if ($raw === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new \RuntimeException($error);
            }
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            $headers = substr((string) $raw, 0, $headerSize);
            $body = substr((string) $raw, $headerSize, $maxBytes);
            if (in_array($status, [301, 302, 303, 307, 308], true)
                && preg_match('/^Location:\s*(.+)$/im', $headers, $m) === 1) {
                $current = $this->resolveRedirect($current, trim($m[1]));
                continue;
            }
            return [$current, $status, $body];
        }
        throw new EgressBlockedException('Preview redirect limit exceeded.');
    }

    private function resolveRedirect(string $base, string $location): string
    {
        if (preg_match('~^https?://~i', $location) === 1) {
            return $location;
        }
        $parts = parse_url($base);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new EgressBlockedException('Malformed redirect base.');
        }
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $path = (string) ($parts['path'] ?? '/');
        return $origin . rtrim(dirname($path), '/') . '/' . $location;
    }
}
