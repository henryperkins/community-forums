<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Security\BoardPolicy;
use App\Security\WriteGate;
use App\Support\Markdown;

final class CommunityMemoryService
{
    public function __construct(
        private Database $db,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private BoardModeratorRepository $moderators,
        private BoardMemberRepository $members,
        private BoardPolicy $policy,
        private WriteGate $writeGate,
        private Markdown $markdown,
        private ?ContentReferenceService $contentReferences = null,
    ) {
    }

    /** @param list<int> $sourcePostIds */
    public function publishSummary(User $actor, int $threadId, string $body, array $sourcePostIds): void
    {
        $this->assertCurator($actor, $threadId);
        $body = trim($body);
        if ($body === '') {
            throw new ValidationException(['body' => 'Write a summary before publishing.']);
        }
        $sourcePostIds = array_values(array_unique(array_filter(array_map('intval', $sourcePostIds), fn (int $id): bool => $id > 0)));
        $html = $this->markdown->render($body);

        $this->db->transaction(function () use ($actor, $threadId, $body, $html, $sourcePostIds): void {
            $version = 1 + (int) $this->db->fetchValue('SELECT COALESCE(MAX(version), 0) FROM thread_summaries WHERE thread_id = ?', [$threadId]);
            $this->db->run(
                "UPDATE thread_summaries SET status = 'retired', retired_at = UTC_TIMESTAMP()
                 WHERE thread_id = ? AND status = 'published'",
                [$threadId],
            );
            $id = $this->db->insert(
                "INSERT INTO thread_summaries
                    (thread_id, kind, status, body, body_html, version, author_id, reviewer_id, published_at, created_at)
                 VALUES (?, 'manual', 'published', ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                [$threadId, $body, $html, $version, $actor->id(), $actor->id()],
            );
            foreach ($sourcePostIds as $postId) {
                $post = $this->posts->find($postId);
                if ($post !== null && (int) $post['thread_id'] === $threadId) {
                    $this->db->run(
                        'INSERT IGNORE INTO thread_summary_sources (summary_id, post_id) VALUES (?, ?)',
                        [$id, $postId],
                    );
                }
            }
            $this->contentReferences?->capture('summary', $id, $body);
        });
    }

    public function addRelated(User $actor, int $sourceThreadId, int $targetThreadId, string $reason): void
    {
        $this->assertCurator($actor, $sourceThreadId);
        if ($sourceThreadId === $targetThreadId) {
            throw new ValidationException(['related' => 'Choose a different topic.']);
        }
        if ($this->threads->find($targetThreadId) === null) {
            throw new NotFoundException('Related topic not found.');
        }
        $this->db->run(
            "INSERT INTO related_threads
                (source_thread_id, related_thread_id, relation_type, source, reason, status, curator_id, created_at)
             VALUES (?, ?, 'related', 'curated', ?, 'approved', ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = 'approved', curator_id = VALUES(curator_id)",
            [$sourceThreadId, $targetThreadId, mb_substr(trim($reason), 0, 255), $actor->id()],
        );
    }

    public function retireSummary(User $actor, int $threadId): void
    {
        $this->assertCurator($actor, $threadId);
        $this->db->run(
            "UPDATE thread_summaries
             SET status = 'retired', retired_at = UTC_TIMESTAMP(), reviewer_id = ?, updated_at = UTC_TIMESTAMP()
             WHERE thread_id = ? AND status = 'published'",
            [$actor->id(), $threadId],
        );
    }

    public function republishSummary(User $actor, int $summaryId, ?int $expectedThreadId = null): void
    {
        $summary = $this->db->fetch('SELECT * FROM thread_summaries WHERE id = ?', [$summaryId]);
        if ($summary === null) {
            throw new NotFoundException('Summary not found.');
        }
        $threadId = (int) $summary['thread_id'];
        if ($expectedThreadId !== null && $threadId !== $expectedThreadId) {
            throw new NotFoundException('Summary not found.');
        }
        $this->assertCurator($actor, $threadId);
        $this->db->transaction(function () use ($actor, $threadId, $summaryId): void {
            $this->db->run(
                "UPDATE thread_summaries SET status = 'retired', retired_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
                 WHERE thread_id = ? AND status = 'published' AND id <> ?",
                [$threadId, $summaryId],
            );
            $this->db->run(
                "UPDATE thread_summaries
                 SET status = 'published', reviewer_id = ?, published_at = UTC_TIMESTAMP(), retired_at = NULL, updated_at = UTC_TIMESTAMP()
                 WHERE id = ?",
                [$actor->id(), $summaryId],
            );
        });
    }

    public function makeWiki(User $actor, int $postId): void
    {
        $post = $this->postOrFail($postId);
        $this->assertWikiEnabled((int) $post['thread_id']);
        $this->assertCurator($actor, (int) $post['thread_id']);
        $this->db->transaction(function () use ($actor, $post): void {
            if ((int) ($post['is_wiki'] ?? 0) === 0) {
                $this->db->run('UPDATE posts SET is_wiki = 1 WHERE id = ?', [(int) $post['id']]);
                $this->recordRevision((int) $post['id'], $actor->id(), (string) $post['body'], (string) ($post['body_html'] ?? ''), 'wiki_enabled');
            }
        });
    }

    public function editWiki(User $actor, int $postId, string $body, ?string $reason = null): void
    {
        $this->writeGate->assertCanWrite($actor);
        $post = $this->postOrFail($postId);
        if ((int) ($post['is_wiki'] ?? 0) !== 1) {
            throw new ForbiddenException('This post is not wiki-editable.');
        }
        $this->assertWikiEnabled((int) $post['thread_id']);
        $this->assertCurator($actor, (int) $post['thread_id']);
        $body = trim($body);
        if ($body === '') {
            throw new ValidationException(['body' => 'Wiki body cannot be empty.']);
        }
        $html = $this->markdown->render($body);
        $this->db->transaction(function () use ($actor, $postId, $body, $html, $reason): void {
            $this->recordRevision($postId, $actor->id(), $body, $html, $reason);
            $this->posts->update($postId, $body, $html, $actor->id());
        });
    }

    public function revertWiki(User $actor, int $postId, int $revisionId): void
    {
        $this->writeGate->assertCanWrite($actor);
        $post = $this->postOrFail($postId);
        if ((int) ($post['is_wiki'] ?? 0) !== 1) {
            throw new ForbiddenException('This post is not wiki-editable.');
        }
        $this->assertWikiEnabled((int) $post['thread_id']);
        $this->assertCurator($actor, (int) $post['thread_id']);
        $revision = $this->db->fetch('SELECT * FROM post_revisions WHERE id = ? AND post_id = ?', [$revisionId, $postId]);
        if ($revision === null) {
            throw new NotFoundException('Revision not found.');
        }
        $body = (string) $revision['body'];
        $html = (string) ($revision['body_html'] ?? $this->markdown->render($body));
        $this->db->transaction(function () use ($actor, $postId, $body, $html, $revisionId): void {
            $this->recordRevision($postId, $actor->id(), $body, $html, 'revert:' . $revisionId);
            $this->posts->update($postId, $body, $html, $actor->id());
        });
    }

    /** @return array<string,mixed>|null */
    public function publishedSummary(int $threadId): ?array
    {
        return $this->db->fetch(
            "SELECT s.*, u.username AS author_username
             FROM thread_summaries s JOIN users u ON u.id = s.author_id
             WHERE s.thread_id = ? AND s.status = 'published'
             ORDER BY s.version DESC LIMIT 1",
            [$threadId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function summaries(int $threadId): array
    {
        return $this->db->fetchAll(
            'SELECT s.*, u.username AS author_username
             FROM thread_summaries s JOIN users u ON u.id = s.author_id
             WHERE s.thread_id = ?
             ORDER BY s.version DESC, s.id DESC',
            [$threadId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function summarySources(int $summaryId, ?User $viewer): array
    {
        $rows = $this->db->fetchAll(
            'SELECT p.id, p.thread_id, p.body, p.created_at, p.is_deleted, p.is_pending, p.is_anonymous,
                    t.slug AS thread_slug, b.id AS board_id, b.visibility AS board_visibility,
                    u.username AS author_username, u.display_name AS author_display_name
             FROM thread_summary_sources ss
             JOIN posts p ON p.id = ss.post_id
             JOIN threads t ON t.id = p.thread_id
             JOIN boards b ON b.id = t.board_id
             JOIN users u ON u.id = p.user_id
             WHERE ss.summary_id = ?
             ORDER BY p.id ASC',
            [$summaryId],
        );
        $visible = array_values(array_filter($rows, function (array $row) use ($viewer): bool {
            if ((int) ($row['is_deleted'] ?? 0) === 1 || (int) ($row['is_pending'] ?? 0) === 1) {
                return false;
            }
            $isMember = $viewer !== null && $this->members->isMember((int) $row['board_id'], $viewer->id());
            return $this->policy->canRead(['visibility' => $row['board_visibility']], $viewer, $isMember);
        }));

        // Preserve the masked-anonymous invariant (mask_author): this source list
        // renders to every thread viewer, not just curators, so a source post that
        // was published anonymously must never expose its real author here.
        return array_map(static function (array $row): array {
            if ((int) ($row['is_anonymous'] ?? 0) === 1) {
                $row['author_username'] = null;
                $row['author_display_name'] = null;
            }
            return $row;
        }, $visible);
    }

    /** @return array<int,array<string,mixed>> */
    public function relatedForViewer(int $threadId, ?User $viewer): array
    {
        $rows = $this->db->fetchAll(
            "SELECT rt.*, t.title, t.slug, t.board_id, b.slug AS board_slug, b.visibility AS board_visibility
             FROM related_threads rt
             JOIN threads t ON t.id = rt.related_thread_id
             JOIN boards b ON b.id = t.board_id
             WHERE rt.source_thread_id = ? AND rt.status = 'approved'
               AND t.is_deleted = 0 AND t.is_pending = 0
             ORDER BY rt.score DESC, rt.id DESC",
            [$threadId],
        );
        return array_values(array_filter($rows, function (array $row) use ($viewer): bool {
            $isMember = $viewer !== null && $this->members->isMember((int) $row['board_id'], $viewer->id());
            return $this->policy->canRead(['visibility' => $row['board_visibility']], $viewer, $isMember);
        }));
    }

    /** @return array<int,array<string,mixed>> */
    public function revisions(int $postId): array
    {
        return $this->db->fetchAll(
            'SELECT r.*, u.username AS editor_username
             FROM post_revisions r JOIN users u ON u.id = r.editor_id
             WHERE r.post_id = ?
             ORDER BY r.id DESC',
            [$postId],
        );
    }

    /** @return array<string,mixed> */
    private function postOrFail(int $postId): array
    {
        $post = $this->posts->find($postId);
        if ($post === null || (int) $post['is_deleted'] === 1) {
            throw new NotFoundException('Post not found.');
        }
        return $post;
    }

    private function assertCurator(User $actor, int $threadId): void
    {
        $this->writeGate->assertCanWrite($actor);
        $thread = $this->threads->find($threadId);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        if (!$actor->isAdmin() && !$this->moderators->isModerator((int) $thread['board_id'], $actor->id())) {
            throw new ForbiddenException('Moderator access required.');
        }
    }

    private function assertWikiEnabled(int $threadId): void
    {
        $thread = $this->threads->findWithBoard($threadId);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        if ((int) ($thread['board_is_archived'] ?? 0) === 1) {
            throw new ForbiddenException('This board is archived and is read-only.');
        }
        if ((int) ($thread['board_wiki_enabled'] ?? 0) !== 1) {
            throw new ForbiddenException('Wiki editing is disabled for this board.');
        }
    }

    private function recordRevision(int $postId, int $editorId, string $body, string $html, ?string $reason): void
    {
        $this->db->run(
            'INSERT INTO post_revisions (post_id, editor_id, body, body_html, reason, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$postId, $editorId, $body, $html, $reason !== null ? mb_substr(trim($reason), 0, 255) : null],
        );
    }
}
