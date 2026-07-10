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
use App\Security\AuthorityGate;
use App\Security\BoardPolicy;
use App\Security\Cap;
use App\Security\WriteGate;
use App\Service\ThreadIntelligence\ThreadIntelligenceQueue;
use App\Service\ThreadIntelligence\ThreadIntelligenceQueueResult;
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
        private ?AuthorityGate $authority = null,
        private ?ThreadIntelligenceQueue $threadIntelligence = null,
    ) {
    }

    private function gate(): AuthorityGate
    {
        return $this->authority ?? AuthorityGate::legacy();
    }

    public function requestRefresh(User $actor, int $threadId): ThreadIntelligenceQueueResult
    {
        $this->assertCurator($actor, $threadId);
        if ($this->threadIntelligence === null) {
            return new ThreadIntelligenceQueueResult(false, 'automated_context_disabled', 'Automatic context is disabled');
        }
        return $this->threadIntelligence->requestRefresh($threadId);
    }

    /** @param list<int> $sourcePostIds */
    public function publishSummary(User $actor, int $threadId, string $body, array $sourcePostIds): void
    {
        $body = trim($body);
        if ($body === '') {
            throw new ValidationException(['body' => 'Write a summary before publishing.']);
        }
        $sourcePostIds = array_values(array_unique(array_filter(array_map('intval', $sourcePostIds), fn (int $id): bool => $id > 0)));
        $html = $this->markdown->render($body);

        $this->db->transaction(function () use ($actor, $threadId, $body, $html, $sourcePostIds): void {
            $thread = $this->threads->findForUpdate($threadId);
            $this->assertCuratorForLockedThread($actor, $thread);
            $published = $this->db->fetch(
                "SELECT id FROM thread_summaries
                 WHERE thread_id = ? AND status = 'published'
                 ORDER BY version DESC, id DESC LIMIT 1 FOR UPDATE",
                [$threadId],
            );
            $parentSummaryId = $published === null ? null : (int) $published['id'];
            $version = 1 + (int) $this->db->fetchValue('SELECT COALESCE(MAX(version), 0) FROM thread_summaries WHERE thread_id = ?', [$threadId]);
            $this->db->run(
                "UPDATE thread_summaries SET status = 'retired', retired_at = UTC_TIMESTAMP()
                 WHERE thread_id = ? AND status = 'published'",
                [$threadId],
            );
            $id = $this->db->insert(
                "INSERT INTO thread_summaries
                    (thread_id, kind, status, body, body_html, version, author_id, reviewer_id,
                     parent_summary_id, published_at, created_at)
                 VALUES (?, 'manual', 'published', ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                [$threadId, $body, $html, $version, $actor->id(), $actor->id(), $parentSummaryId],
            );
            foreach ($sourcePostIds as $postId) {
                $post = $this->db->fetch(
                    'SELECT id, thread_id, is_deleted, is_pending FROM posts WHERE id = ? FOR UPDATE',
                    [$postId],
                );
                if ($post !== null
                    && (int) $post['thread_id'] === $threadId
                    && (int) $post['is_deleted'] === 0
                    && (int) $post['is_pending'] === 0) {
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
        if ($sourceThreadId === $targetThreadId) {
            throw new ValidationException(['related' => 'Choose a different topic.']);
        }
        $this->db->transaction(function () use ($actor, $sourceThreadId, $targetThreadId, $reason): void {
            $source = $this->threads->findForUpdate($sourceThreadId);
            $this->assertCuratorForLockedThread($actor, $source);
            if ($this->threads->find($targetThreadId) === null) {
                throw new NotFoundException('Related topic not found.');
            }
            $this->db->run(
                "INSERT INTO related_threads
                    (source_thread_id, related_thread_id, relation_type, source, reason, status, curator_id,
                     ai_generation_id, ai_reason, ai_selected, ai_selected_at, created_at)
                 VALUES (?, ?, 'related', 'curated', ?, 'approved', ?, NULL, NULL, 0, NULL, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE
                    source = 'curated',
                    reason = VALUES(reason),
                    status = 'approved',
                    curator_id = VALUES(curator_id),
                    ai_generation_id = NULL,
                    ai_reason = NULL,
                    ai_selected = 0,
                    ai_selected_at = NULL",
                [$sourceThreadId, $targetThreadId, mb_substr(trim($reason), 0, 255), $actor->id()],
            );
        });
    }

    public function retireSummary(User $actor, int $threadId): void
    {
        $this->db->transaction(function () use ($actor, $threadId): void {
            $thread = $this->threads->findForUpdate($threadId);
            $this->assertCuratorForLockedThread($actor, $thread);
            $this->db->run(
                "UPDATE thread_summaries
                 SET status = 'retired', retired_at = UTC_TIMESTAMP(), reviewer_id = ?, updated_at = UTC_TIMESTAMP()
                 WHERE thread_id = ? AND status = 'published'",
                [$actor->id(), $threadId],
            );
            $this->threadIntelligence?->setAutomationPaused($threadId, true, $actor->id());
        });
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
        $this->db->transaction(function () use ($actor, $threadId, $summaryId): void {
            $thread = $this->threads->findForUpdate($threadId);
            $this->assertCuratorForLockedThread($actor, $thread);
            $lockedSummary = $this->db->fetch(
                'SELECT id, thread_id FROM thread_summaries WHERE id = ? FOR UPDATE',
                [$summaryId],
            );
            if ($lockedSummary === null || (int) $lockedSummary['thread_id'] !== $threadId) {
                throw new NotFoundException('Summary not found.');
            }
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

    public function resumeAutomation(User $actor, int $threadId): void
    {
        $this->db->transaction(function () use ($actor, $threadId): void {
            $thread = $this->threads->findForUpdate($threadId);
            $this->assertCuratorForLockedThread($actor, $thread);
            $this->threadIntelligence?->resumeAndRequeue($threadId, $actor->id());
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
        $threadId = (int) $post['thread_id'];
        $this->db->transaction(function () use ($actor, $postId, $body, $html, $reason, $threadId): void {
            $this->recordRevision($postId, $actor->id(), $body, $html, $reason);
            $this->posts->update($postId, $body, $html, $actor->id());
            $this->threadIntelligence?->markStale($threadId, ThreadIntelligenceQueue::TRIGGER_WIKI_EDITED);
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
        $threadId = (int) $post['thread_id'];
        $this->db->transaction(function () use ($actor, $postId, $body, $html, $revisionId, $threadId): void {
            $this->recordRevision($postId, $actor->id(), $body, $html, 'revert:' . $revisionId);
            $this->posts->update($postId, $body, $html, $actor->id());
            $this->threadIntelligence?->markStale($threadId, ThreadIntelligenceQueue::TRIGGER_WIKI_REVERTED);
        });
    }

    /** @return array<string,mixed>|null */
    public function publishedSummary(int $threadId): ?array
    {
        return $this->db->fetch(
            "SELECT s.*, u.username AS author_username
             FROM thread_summaries s LEFT JOIN users u ON u.id = s.author_id
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
             FROM thread_summaries s LEFT JOIN users u ON u.id = s.author_id
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
        $thread = $this->threads->find($threadId);
        $this->assertCuratorForLockedThread($actor, $thread);
    }

    /** @param array<string,mixed>|null $thread */
    private function assertCuratorForLockedThread(User $actor, ?array $thread): void
    {
        $this->writeGate->assertCanWrite($actor);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        $boardId = (int) $thread['board_id'];
        $allowed = $this->gate()->allows(
            fn (): bool => $actor->isAdmin() || $this->moderators->isModerator($boardId, $actor->id()),
            $actor,
            Cap::MEMORY_CURATE,
            ['board_id' => $boardId],
            'CommunityMemoryService::assertCurator',
        );
        if (!$allowed) {
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
