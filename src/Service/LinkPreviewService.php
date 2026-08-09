<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\EgressBlockedException;
use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\LinkPreviewRepository;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
use App\Security\BoardAuthority;
use App\Security\Cap;
use App\Security\EgressGuard;
use App\Security\WriteGate;

/**
 * Server-side link unfurling (DECISIONS §6 #5, ADR 0025).
 *
 * Three independent gates decide whether a URL is ever fetched, and all three
 * must open:
 *
 *   1. the `link_previews` feature flag — makes the subsystem available;
 *   2. `boards.link_previews_enabled` — the locked per-board opt-in, default 0,
 *      re-checked at fetch time so switching a board off stops its backlog;
 *   3. `link_preview_allowed_hosts` + EgressGuard — the SSRF allowlist, which
 *      is empty by default, so a fresh install fetches nothing.
 *
 * Members hold the last word on their own posts: `remove()` parks a card in the
 * sticky `removed` state that survives edits, re-queues, and operator refresh.
 */
final class LinkPreviewService
{
    /** Source gate outcomes — see sourceGate(). */
    private const GATE_OK = 'ok';
    /** Permanently not previewable (deleted, held, non-public board, DM, gone). */
    private const GATE_INELIGIBLE = 'ineligible';
    /** The board simply has not opted in — reversible, so never terminal. */
    private const GATE_OPTED_OUT = 'opted_out';

    public function __construct(
        private Database $db,
        private LinkPreviewRepository $previews,
        private PostRepository $posts,
        private SettingRepository $settings,
        private Config $config,
        private EgressGuard $egress,
        private WriteGate $writeGate,
        private FeatureFlags $flags,
        private ?BoardAuthority $boardAuthority = null,
    ) {
    }

    /**
     * The flag gates the *subsystem*, including the cron worker — which builds
     * this service directly and so is not covered by the route gates. Without
     * this check a rolled-back install would keep draining its queue to the
     * network on every `worker:previews` run, which is exactly what an operator
     * setting `features.link_previews=false` is trying to stop.
     */
    private function enabled(): bool
    {
        return $this->flags->enabled('link_previews');
    }

    public function queueFromBody(string $sourceType, int $sourceId, string $body): int
    {
        if (!$this->enabled() || $this->sourceGate($sourceType, $sourceId) !== self::GATE_OK) {
            return 0;
        }

        $queued = 0;
        foreach ($this->extractUrls($body) as $url) {
            if ($this->isNeverFetchedLocalUrl($url)) {
                continue;
            }
            $queued += $this->previews->queue($sourceType, $sourceId, $url, hash('sha256', $url)) ? 1 : 0;
        }
        return $queued;
    }

    /**
     * Renderable cards keyed by source id. Rows the viewer may act on are
     * flagged `can_manage`; for those sources the author-removed rows are
     * returned too (status `removed`, no metadata) so the thread can offer a
     * restore. Everyone else sees only fetched cards.
     *
     * @param list<int>|array<int,mixed> $sourceIds
     * @param list<int>|array<int,mixed> $manageableIds sources whose removed rows the viewer may see
     * @return array<int,array<int,array<string,mixed>>>
     */
    public function cardsForSources(string $sourceType, array $sourceIds, array $manageableIds = []): array
    {
        $sourceIds = $this->normalizeIds($sourceIds);
        if ($sourceIds === []) {
            return [];
        }
        $manageable = array_flip(array_intersect($this->normalizeIds($manageableIds), $sourceIds));

        $rows = $manageable === []
            ? $this->previews->fetchedForSources($sourceType, $sourceIds)
            : $this->previews->authorVisibleForSources($sourceType, $sourceIds);

        $out = [];
        foreach ($rows as $row) {
            $sourceId = (int) $row['source_id'];
            $canManage = isset($manageable[$sourceId]);
            $removed = (string) $row['status'] === 'removed';
            if ($removed && !$canManage) {
                continue;
            }
            $out[$sourceId][] = [
                'id' => (int) $row['id'],
                'url' => (string) ($row['final_url'] ?: $row['url']),
                'title' => (string) ($row['title'] ?: $row['url']),
                'description' => (string) ($row['description'] ?? ''),
                'site_name' => (string) ($row['site_name'] ?? ''),
                'removed' => $removed,
                'can_manage' => $canManage,
            ];
        }
        return $out;
    }

    /**
     * Member-facing suppression. Authorised for the post's author and for
     * anyone who can moderate its board; account state still beats role, so a
     * suspended author cannot reach it.
     */
    public function remove(User $actor, int $previewId): void
    {
        $row = $this->requireManageableRow($actor, $previewId);
        if ((string) $row['status'] === 'removed') {
            return;
        }
        $this->previews->markRemoved($previewId, $actor->id());
    }

    /** Undo a removal: the card goes back through the normal fetch queue. */
    public function restore(User $actor, int $previewId): void
    {
        $row = $this->requireManageableRow($actor, $previewId);
        if ((string) $row['status'] !== 'removed') {
            return;
        }
        $this->previews->markQueued($previewId);
    }

    /**
     * Operator refresh. Deliberately refuses an author-removed row: the console
     * must not be a way around a member's decision about their own post — an
     * operator who needs the URL gone has purge, and one who disagrees with the
     * removal has the moderation surfaces.
     */
    public function refresh(int $id): void
    {
        $row = $this->requireRow($id);
        if ((string) $row['status'] === 'removed') {
            throw new ValidationException(['preview' => 'That preview was removed by its author; refresh will not override it.']);
        }
        $this->previews->markQueued($id);
    }

    /**
     * Operator purge. Refuses an author-removed row for the same reason refresh
     * does, and for a sharper one: `purged` is deliberately revived by the queue
     * upsert, so purging a `removed` row would quietly re-arm it and the card
     * would come back the next time its author saved the post. A removed row has
     * no stored metadata left to wipe anyway.
     */
    public function purge(int $id): void
    {
        $row = $this->requireRow($id);
        if ((string) $row['status'] === 'removed') {
            throw new ValidationException(['preview' => 'That preview was removed by its author; purging would re-queue it on the next edit.']);
        }
        $this->previews->markPurged($id);
    }

    public function storeFetchedMetadata(int $id, string $finalUrl, int $httpStatus, string $html): void
    {
        $row = $this->requireRow($id);
        $this->validateFetchUrl($finalUrl);
        $meta = $this->extractMetadata($html);
        $this->previews->markFetched(
            $id,
            $finalUrl,
            $httpStatus,
            $meta,
            json_encode(['source_url' => $row['url']], JSON_UNESCAPED_SLASHES) ?: null,
        );
    }

    /** @return array{fetched:int,blocked:int,failed:int,skipped:int} */
    public function fetchQueued(int $limit = 25): array
    {
        $stats = ['fetched' => 0, 'blocked' => 0, 'failed' => 0, 'skipped' => 0];
        // Both global pauses leave every row queued and untouched: the operator
        // is stopping traffic, not retiring work.
        if (!$this->enabled() || $this->killSwitchEngaged()) {
            $stats['skipped'] = count($this->previews->queued($limit));
            return $stats;
        }

        foreach ($this->previews->queued($limit) as $row) {
            $id = (int) $row['id'];

            // Re-checked here, not just at queue time: a board switched off (or a
            // post deleted / moved private) after the row was queued must not
            // still reach the network. The two outcomes are deliberately
            // different — a board opt-out is a reversible operator choice, so the
            // row stays queued and drains when the board comes back; anything
            // else means the source will never be previewable again.
            $gate = $this->sourceGate((string) $row['source_type'], (int) $row['source_id']);
            if ($gate === self::GATE_OPTED_OUT) {
                $stats['skipped']++;
                continue;
            }

            try {
                if ($gate !== self::GATE_OK) {
                    throw new EgressBlockedException('Source is no longer eligible for link previews.');
                }
                $this->validateFetchUrl((string) $row['url']);
                [$finalUrl, $status, $html] = $this->fetchHtml((string) $row['url']);
                $this->storeFetchedMetadata($id, $finalUrl, $status, $html);
                $stats['fetched']++;
            } catch (EgressBlockedException|ValidationException $e) {
                $this->previews->markBlocked($id, $e->getMessage());
                $stats['blocked']++;
            } catch (\Throwable $e) {
                $this->previews->markFailed($id, $e->getMessage());
                $stats['failed']++;
            }
        }
        return $stats;
    }

    public function killSwitchEngaged(): bool
    {
        return (bool) $this->settings->get('link_preview_kill_switch', false);
    }

    /**
     * The effective host allowlist: the stored operator list when present,
     * otherwise the LINK_PREVIEW_ALLOWED_HOSTS config default.
     *
     * @return list<string>
     */
    public function allowedHosts(): array
    {
        $allowed = $this->settings->get('link_preview_allowed_hosts', $this->config->get('link_previews.allowed_hosts', []));
        if (is_string($allowed)) {
            $allowed = explode(',', $allowed);
        }
        if (!is_array($allowed)) {
            return [];
        }
        $out = [];
        foreach ($allowed as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern !== '' && !in_array($pattern, $out, true)) {
                $out[] = $pattern;
            }
        }
        return $out;
    }

    public function validateFetchUrl(string $url): string
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
        return $this->egress->validate($url);
    }

    /**
     * A row the actor may suppress or restore: the source post's author, or
     * anyone with board-scoped delete authority over it.
     *
     * `WriteGate` is a required constructor dependency precisely because this
     * check must not be skippable — "state beats role" has to hold on this write
     * path too, so a banned or suspended author is refused before authorship is
     * even considered. `BoardAuthority` stays optional but fails closed (`?? false`):
     * without it nobody gains moderator reach they would not otherwise have.
     *
     * @return array<string,mixed>
     */
    private function requireManageableRow(User $actor, int $previewId): array
    {
        $row = $this->requireRow($previewId);
        if ((string) $row['source_type'] !== 'post') {
            throw new NotFoundException('Preview not found.');
        }
        $post = $this->posts->findWithContext((int) $row['source_id']);
        if ($post === null || (int) ($post['is_deleted'] ?? 0) === 1) {
            throw new NotFoundException('Preview not found.');
        }

        $this->writeGate->assertCanWrite($actor);

        $isAuthor = (int) ($post['user_id'] ?? 0) === $actor->id();
        $canModerate = $this->boardAuthority?->canModerate($actor, (int) $post['board_id'], Cap::POST_DELETE_ANY) ?? false;
        if (!$isAuthor && !$canModerate) {
            throw new ForbiddenException('You cannot change previews on that post.');
        }

        return $row;
    }

    /** @return array<string,mixed> */
    private function requireRow(int $id): array
    {
        $row = $this->previews->find($id);
        if ($row === null) {
            throw new NotFoundException('Preview not found.');
        }
        return $row;
    }

    /**
     * Eligibility of the *source*, split into a permanent verdict and a
     * reversible one so the worker can treat them differently:
     *
     *  - GATE_INELIGIBLE — deleted, approval-held, not on a public board, a DM,
     *    or simply gone. Nothing an operator does brings this row back, so the
     *    worker retires it as `blocked`. DMs are never unfurled server-side: the
     *    fetch would tell the URL's operator that a private message contains it.
     *  - GATE_OPTED_OUT — the board has not opted in. That is one checkbox away
     *    from changing, so the row stays `queued` and drains by itself when the
     *    operator switches the board back on.
     */
    private function sourceGate(string $sourceType, int $sourceId): string
    {
        if ($sourceType === 'dm_message') {
            return self::GATE_INELIGIBLE;
        }
        if ($sourceType === 'post') {
            $post = $this->posts->findWithContext($sourceId);
            // `thread_deleted` matters as much as the post's own flag:
            // ThreadRepository::softDelete() only sets threads.is_deleted, so
            // every post under a deleted thread still reads is_deleted = 0.
            // Without this a queued URL from a deleted topic would still reach
            // its external host on the next worker pass.
            if ($post === null
                || (int) ($post['is_deleted'] ?? 0) === 1
                || (int) ($post['thread_deleted'] ?? 0) === 1
                || (int) ($post['is_pending'] ?? 0) === 1
                || (string) ($post['board_visibility'] ?? '') !== 'public') {
                return self::GATE_INELIGIBLE;
            }
            return (int) ($post['board_link_previews_enabled'] ?? 0) === 1
                ? self::GATE_OK
                : self::GATE_OPTED_OUT;
        }
        if ($sourceType === 'summary') {
            $row = $this->db->fetch(
                "SELECT b.visibility, b.link_previews_enabled
                 FROM thread_summaries s
                 JOIN threads t ON t.id = s.thread_id
                 JOIN boards b ON b.id = t.board_id
                 WHERE s.id = ?",
                [$sourceId],
            );
            if ($row === null || (string) ($row['visibility'] ?? '') !== 'public') {
                return self::GATE_INELIGIBLE;
            }
            return (int) ($row['link_previews_enabled'] ?? 0) === 1
                ? self::GATE_OK
                : self::GATE_OPTED_OUT;
        }
        return self::GATE_INELIGIBLE;
    }

    /**
     * @param list<int>|array<int,mixed> $ids
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    /** @return list<string> */
    private function extractUrls(string $body): array
    {
        if (preg_match_all('~https?://[^\s<>"\')\]]+~i', $body, $m) === false) {
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
        foreach ($this->allowedHosts() as $pattern) {
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
        $timeout = max(1, (int) $this->config->get('link_previews.timeout_seconds', 4));
        $current = $url;
        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $ip = $this->validateFetchUrl($current);
            $resolve = $this->curlResolve($current, $ip);
            $headers = '';
            $body = '';
            $bytes = 0;
            $tooLarge = false;
            $ch = curl_init($current);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'RetroBoardsLinkPreview/1.0',
                CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
                CURLOPT_REDIR_PROTOCOLS => 0,
                CURLOPT_RESOLVE => [$resolve],
                CURLOPT_HEADERFUNCTION => function ($ch, string $chunk) use (&$headers): int {
                    $headers .= $chunk;
                    return strlen($chunk);
                },
                CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$body, &$bytes, &$tooLarge, $maxBytes): int {
                    $len = strlen($chunk);
                    if ($bytes + $len > $maxBytes) {
                        $remaining = max(0, $maxBytes - $bytes);
                        if ($remaining > 0) {
                            $body .= substr($chunk, 0, $remaining);
                        }
                        $bytes += $len;
                        $tooLarge = true;
                        return -1;
                    }
                    $body .= $chunk;
                    $bytes += $len;
                    return $len;
                },
            ]);
            $ok = curl_exec($ch);
            if ($tooLarge) {
                throw new \RuntimeException('Preview response exceeded maximum size.');
            }
            if ($ok === false) {
                $error = curl_error($ch);
                throw new \RuntimeException($error);
            }
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if (in_array($status, [301, 302, 303, 307, 308], true)
                && preg_match('/^Location:\s*(.+)$/im', $headers, $m) === 1) {
                $current = $this->resolveRedirect($current, trim($m[1]));
                continue;
            }
            return [$current, $status, $body];
        }
        throw new EgressBlockedException('Preview redirect limit exceeded.');
    }

    private function curlResolve(string $url, string $ip): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = trim((string) ($parts['host'] ?? ''), '[]');
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        return $host . ':' . $port . ':' . $ip;
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
