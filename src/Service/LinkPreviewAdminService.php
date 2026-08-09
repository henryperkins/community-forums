<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardRepository;
use App\Repository\LinkPreviewRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;

/**
 * Read model and operator writes behind `GET /admin/link-previews` (ADR 0025).
 *
 * The console is the one place an operator can see why the subsystem is or is
 * not fetching: the three gates (flag, per-board opt-in, host allowlist) are
 * each reported with their live value, and every write here is audited.
 */
final class LinkPreviewAdminService
{
    /** Bound on the stored allowlist so one paste cannot make the settings row unbounded. */
    private const MAX_HOSTS = 100;

    /** How many rows the console table shows. */
    public const RECENT_LIMIT = 50;

    public const STATUSES = ['queued', 'fetched', 'blocked', 'failed', 'purged', 'removed'];

    public function __construct(
        private Database $db,
        private LinkPreviewRepository $previews,
        private BoardRepository $boards,
        private SettingRepository $settings,
        private ModerationLogRepository $log,
        private LinkPreviewService $service,
        private Config $config,
    ) {
    }

    /** @return array<string,mixed> */
    public function dashboard(?string $status = null): array
    {
        $status = in_array((string) $status, self::STATUSES, true) ? (string) $status : null;
        $hosts = $this->service->allowedHosts();
        $boards = [];
        $optedIn = 0;
        foreach ($this->boards->allOrdered() as $board) {
            $enabled = (int) ($board['link_previews_enabled'] ?? 0) === 1;
            // Counted the same way BoardRepository::countWithLinkPreviews does —
            // opted in AND public — so the tile, the blocker callout and the
            // /admin/features dormancy badge can never disagree about whether
            // this install is live.
            $optedIn += $enabled && (string) $board['visibility'] === 'public' ? 1 : 0;
            $boards[] = [
                'id' => (int) $board['id'],
                'name' => (string) $board['name'],
                'slug' => (string) $board['slug'],
                'visibility' => (string) $board['visibility'],
                'is_archived' => (int) ($board['is_archived'] ?? 0) === 1,
                'enabled' => $enabled,
                // Only public boards are ever unfurled, so an opted-in private
                // board is inert — say so rather than implying it is live.
                'effective' => $enabled && (string) $board['visibility'] === 'public',
            ];
        }

        return [
            'counts' => $this->previews->statusCounts(),
            'kill_switch' => $this->service->killSwitchEngaged(),
            'allowed_hosts' => $hosts,
            'allowed_hosts_text' => implode("\n", $hosts),
            'hosts_source' => $this->settings->has('link_preview_allowed_hosts') ? 'setting' : 'config',
            'boards' => $boards,
            'boards_opted_in' => $optedIn,
            'status_filter' => $status,
            'rows' => $this->rows($status),
            'transport' => [
                'allow_http' => (bool) $this->config->get('link_previews.allow_http', false),
                'timeout_seconds' => (int) $this->config->get('link_previews.timeout_seconds', 4),
                'max_bytes' => (int) $this->config->get('link_previews.max_bytes', 262144),
            ],
            // Everything that has to be true before a queued row can be fetched.
            'blockers' => $this->blockers($hosts, $optedIn),
        ];
    }

    /**
     * Why the queue is not draining, in the order an operator should fix them.
     *
     * @param list<string> $hosts
     * @return list<string>
     */
    private function blockers(array $hosts, int $optedIn): array
    {
        $blockers = [];
        if ($this->service->killSwitchEngaged()) {
            $blockers[] = 'The kill switch is engaged: the worker skips every queued row until it is released.';
        }
        if ($hosts === []) {
            $blockers[] = 'No hosts are allowlisted, so every fetch is refused. Add the hosts you trust below.';
        }
        if ($optedIn === 0) {
            $blockers[] = 'No public board has opted in, so nothing is being queued. Enable previews on the boards that want them.';
        }
        return $blockers;
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(?string $status): array
    {
        $rows = [];
        foreach ($this->previews->listRecent($status, self::RECENT_LIMIT) as $row) {
            $threadId = (int) ($row['thread_id'] ?? 0);
            $rows[] = [
                'id' => (int) $row['id'],
                'status' => (string) $row['status'],
                'url' => (string) $row['url'],
                'final_url' => (string) ($row['final_url'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'site_name' => (string) ($row['site_name'] ?? ''),
                'http_status' => $row['http_status'] === null ? null : (int) $row['http_status'],
                'error' => (string) ($row['error'] ?? ''),
                'source_type' => (string) $row['source_type'],
                'source_id' => (int) $row['source_id'],
                'board_name' => (string) ($row['board_name'] ?? ''),
                'board_visibility' => (string) ($row['board_visibility'] ?? ''),
                'board_opted_in' => (int) ($row['board_previews_enabled'] ?? 0) === 1,
                'thread_title' => (string) ($row['thread_title'] ?? ''),
                // Only ever a public-board permalink; the join is left so a row
                // whose post was hard-deleted still lists with no destination.
                'thread_href' => $threadId > 0 && (string) ($row['board_visibility'] ?? '') === 'public'
                    ? '/t/' . $threadId . '-' . (string) ($row['thread_slug'] ?? '')
                    : null,
                'created_at' => (string) $row['created_at'],
                'fetched_at' => (string) ($row['fetched_at'] ?? ''),
                'removed_at' => (string) ($row['removed_at'] ?? ''),
                // An author-removed row is the member's decision; the console
                // shows it but offers neither action (the service refuses both —
                // the buttons would be lies). Purge matters most: `purged` is
                // revived by the queue upsert, so purging a removed row would
                // re-arm the card on the author's next edit.
                'can_refresh' => (string) $row['status'] !== 'removed',
                'can_purge' => (string) $row['status'] !== 'removed',
                // Only a post source has a #p anchor; a summary id is not a post id.
                'source_anchor' => (string) $row['source_type'] === 'post'
                    ? '#p' . (int) $row['source_id']
                    : '',
            ];
        }
        return $rows;
    }

    /**
     * Persist the allowlist and kill switch together — they are the two levers
     * an operator reaches for in the same incident.
     *
     * @param array<string,mixed> $input
     */
    public function saveSettings(User $admin, array $input): void
    {
        $hosts = $this->parseHosts((string) ($input['allowed_hosts'] ?? ''));
        $killSwitch = !empty($input['kill_switch']);

        $before = ['allowed_hosts' => $this->service->allowedHosts(), 'kill_switch' => $this->service->killSwitchEngaged()];

        $this->db->transaction(function () use ($admin, $hosts, $killSwitch, $before): void {
            $this->settings->set('link_preview_allowed_hosts', $hosts);
            $this->settings->set('link_preview_kill_switch', $killSwitch);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'link_preview_settings',
                'target_type' => 'setting',
                'target_id' => 0,
                'before' => $before,
                'after' => ['allowed_hosts' => $hosts, 'kill_switch' => $killSwitch],
            ]);
        });
    }

    public function setBoardOptIn(User $admin, int $boardId, bool $enabled): string
    {
        $board = $this->boards->find($boardId);
        if ($board === null) {
            throw new NotFoundException('Board not found.');
        }
        $was = (int) ($board['link_previews_enabled'] ?? 0) === 1;

        $this->db->transaction(function () use ($admin, $boardId, $enabled, $was, $board): void {
            $this->db->run(
                'UPDATE boards SET link_previews_enabled = ? WHERE id = ?',
                [$enabled ? 1 : 0, $boardId],
            );
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => $enabled ? 'link_preview_board_enable' : 'link_preview_board_disable',
                'target_type' => 'board',
                'target_id' => $boardId,
                'before' => ['link_previews_enabled' => $was ? 1 : 0],
                'after' => ['link_previews_enabled' => $enabled ? 1 : 0],
            ]);
        });

        return sprintf(
            'Link previews %s on #%s.',
            $enabled ? 'enabled' : 'disabled',
            (string) $board['name'],
        );
    }

    /**
     * Re-queue one preview and record it, atomically.
     *
     * The status change and its audit row are a two-table mutation, so they
     * share one transaction: a failed audit must not leave an unaudited queue
     * change behind. The row is resolved first, so a missing preview 404s
     * before anything is written — the audit can never name `target_id = 0`.
     */
    public function refreshPreview(User $admin, int $previewId): string
    {
        return $this->act($admin, $previewId, 'link_preview_refresh', 'Preview queued for refresh.');
    }

    /** Wipe one preview's stored metadata and record it, atomically. */
    public function purgePreview(User $admin, int $previewId): string
    {
        return $this->act($admin, $previewId, 'link_preview_purge', 'Preview metadata purged.');
    }

    /**
     * One row action: resolve, mutate and audit inside a single transaction, and
     * return the flash the console should show.
     *
     * A refusal (the author-removed guard) is a *policy* answer to a button
     * press, not a rejected form — there is no typed input to preserve — so it
     * comes back as a message rather than propagating a ValidationException the
     * controller would have to translate into a 422 render of a page with no
     * form state to restore.
     */
    private function act(User $admin, int $previewId, string $action, string $success): string
    {
        return $this->db->transaction(function () use ($admin, $previewId, $action, $success): string {
            // Read before the mutation so the audit row still carries the URL.
            $row = $this->requirePreview($previewId);
            try {
                $action === 'link_preview_refresh'
                    ? $this->service->refresh($previewId)
                    : $this->service->purge($previewId);
            } catch (ValidationException $e) {
                return $e->first();
            }
            $this->auditPreviewAction($admin, $action, $row);

            return $success;
        });
    }

    /**
     * The operator steps still standing between "available" and "unfurling".
     * Lives here rather than in the controller so the readiness console reads
     * link-preview policy from the service that owns it.
     *
     * @return list<string> empty when the subsystem is fully configured
     */
    public function missingActivationSteps(): array
    {
        $missing = [];
        if ($this->service->allowedHosts() === []) {
            $missing[] = 'no host is allowlisted';
        }
        if ($this->boards->countWithLinkPreviews() === 0) {
            $missing[] = 'no board has opted in';
        }
        return $missing;
    }

    /** @return array<string,mixed> */
    private function requirePreview(int $previewId): array
    {
        $row = $this->previews->find($previewId);
        if ($row === null) {
            throw new NotFoundException('Preview not found.');
        }
        return $row;
    }

    /**
     * A post-sourced row is anchored to the post, not the preview id, so
     * `idx_modlog_target` surfaces it alongside every other action taken on that
     * post. Any other source type must NOT borrow that anchor — a summary id is
     * not a post id, and logging it as one would attach the entry to an
     * unrelated post. `moderation_log.target_type` has no `summary` member, so
     * those land on `setting` with the real identity in the payload.
     *
     * @param array<string,mixed> $row
     */
    private function auditPreviewAction(User $admin, string $action, array $row): void
    {
        $sourceType = (string) ($row['source_type'] ?? '');
        $isPost = $sourceType === 'post';

        $this->log->log([
            'actor_id' => $admin->id(),
            'action' => $action,
            'target_type' => $isPost ? 'post' : 'setting',
            'target_id' => $isPost ? (int) ($row['source_id'] ?? 0) : 0,
            'after' => [
                'preview_id' => (int) $row['id'],
                'url' => (string) ($row['url'] ?? ''),
                'source_type' => $sourceType,
                'source_id' => (int) ($row['source_id'] ?? 0),
            ],
        ]);
    }

    /**
     * Newline/comma separated hostnames, optionally `*.` wildcarded. Anything
     * with a scheme, path, port, or space is rejected loudly rather than
     * silently normalised — a mistyped entry that quietly matches nothing looks
     * identical to a working one from the console.
     *
     * @return list<string>
     */
    private function parseHosts(string $raw): array
    {
        $hosts = [];
        $errors = [];
        foreach (preg_split('/[\s,]+/', $raw) ?: [] as $candidate) {
            $host = strtolower(trim((string) $candidate));
            if ($host === '') {
                continue;
            }
            if (!$this->validHostPattern($host)) {
                $errors[] = $host;
                continue;
            }
            if (!in_array($host, $hosts, true)) {
                $hosts[] = $host;
            }
        }
        if ($errors !== []) {
            throw new ValidationException([
                'allowed_hosts' => 'Not a hostname or *.wildcard pattern: ' . implode(', ', array_slice($errors, 0, 5)) . '.',
            ]);
        }
        if (count($hosts) > self::MAX_HOSTS) {
            throw new ValidationException([
                'allowed_hosts' => 'At most ' . self::MAX_HOSTS . ' hosts can be allowlisted.',
            ]);
        }
        return $hosts;
    }

    private function validHostPattern(string $host): bool
    {
        $bare = str_starts_with($host, '*.') ? substr($host, 2) : $host;
        if ($bare === '' || strlen($host) > 253) {
            return false;
        }
        // A bare wildcard, or one that would match a single-label host, is an
        // allowlist that is not one.
        if (str_starts_with($host, '*.') && !str_contains($bare, '.')) {
            return false;
        }
        return preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))*$/', $bare) === 1;
    }
}
