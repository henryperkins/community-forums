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
use App\Repository\ThreadRepository;
use App\Security\BoardPolicy;
use App\Security\WriteGate;

final class PollService
{
    public function __construct(
        private Database $db,
        private ThreadRepository $threads,
        private BoardModeratorRepository $moderators,
        private BoardMemberRepository $members,
        private BoardPolicy $policy,
        private WriteGate $writeGate,
    ) {
    }

    /** @param array<string,mixed> $input */
    public function create(User $actor, int $threadId, array $input): int
    {
        $this->writeGate->assertCanWrite($actor);
        $thread = $this->threadOrFail($threadId);
        $this->assertCanRead($actor, $thread);
        if (!$this->canManageThread($actor, $thread)) {
            throw new ForbiddenException('You cannot add a poll to this thread.');
        }
        if ($this->db->fetchValue('SELECT 1 FROM polls WHERE thread_id = ? LIMIT 1', [$threadId]) !== false) {
            throw new ValidationException(['poll' => 'This thread already has a poll.'], $input);
        }

        $question = trim((string) ($input['question'] ?? ''));
        $mode = (string) ($input['mode'] ?? 'single');
        $options = $this->parseOptions((string) ($input['options'] ?? ''));
        $errors = [];
        if ($question === '' || mb_strlen($question) > 255) {
            $errors['question'] = 'Poll question must be 1 to 255 characters.';
        }
        if (!in_array($mode, ['single', 'multiple'], true)) {
            $errors['mode'] = 'Choose single or multiple choice.';
        }
        if (count($options) < 2) {
            $errors['options'] = 'Add at least two options.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $input);
        }

        return $this->db->transaction(function () use ($threadId, $actor, $question, $mode, $options): int {
            $pollId = $this->db->insert(
                "INSERT INTO polls (thread_id, question, mode, status, results_policy, created_by, created_at)
                 VALUES (?, ?, ?, 'open', 'after_vote_or_close', ?, UTC_TIMESTAMP())",
                [$threadId, $question, $mode, $actor->id()],
            );
            $pos = 0;
            foreach ($options as $option) {
                $this->db->run(
                    'INSERT INTO poll_options (poll_id, body, position, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
                    [$pollId, $option, $pos++],
                );
            }
            return $pollId;
        });
    }

    /** @param list<int> $optionIds */
    public function vote(User $actor, int $pollId, array $optionIds): int
    {
        $this->writeGate->assertCanWrite($actor);
        $poll = $this->pollOrFail($pollId);
        $thread = $this->threadOrFail((int) $poll['thread_id']);
        $this->assertCanRead($actor, $thread);
        if (!$this->isOpen($poll)) {
            throw new ForbiddenException('This poll is closed.');
        }
        $optionIds = array_values(array_unique(array_filter(array_map('intval', $optionIds), fn (int $id): bool => $id > 0)));
        if ($optionIds === []) {
            throw new ValidationException(['option_ids' => 'Choose an option.']);
        }
        if ((string) $poll['mode'] === 'single' && count($optionIds) !== 1) {
            throw new ValidationException(['option_ids' => 'Choose one option.']);
        }
        $valid = $this->validOptionIds($pollId);
        foreach ($optionIds as $id) {
            if (!isset($valid[$id])) {
                throw new ValidationException(['option_ids' => 'Choose a valid option.']);
            }
        }

        return $this->db->transaction(function () use ($actor, $pollId, $optionIds): int {
            $this->db->run('DELETE FROM poll_votes WHERE poll_id = ? AND user_id = ?', [$pollId, $actor->id()]);
            foreach ($optionIds as $optionId) {
                $this->db->run(
                    'INSERT INTO poll_votes (poll_id, option_id, user_id, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
                    [$pollId, $optionId, $actor->id()],
                );
            }
            return count($optionIds);
        });
    }

    public function close(User $actor, int $pollId): void
    {
        $this->writeGate->assertCanWrite($actor);
        $poll = $this->pollOrFail($pollId);
        $thread = $this->threadOrFail((int) $poll['thread_id']);
        $this->assertCanRead($actor, $thread);
        if (!$this->canManageThread($actor, $thread)) {
            throw new ForbiddenException('You cannot close this poll.');
        }
        $this->db->run("UPDATE polls SET status = 'closed', closed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?", [$pollId]);
    }

    /** @return array<string,mixed>|null */
    public function forThread(int $threadId, ?User $viewer): ?array
    {
        $poll = $this->db->fetch('SELECT * FROM polls WHERE thread_id = ?', [$threadId]);
        if ($poll === null) {
            return null;
        }
        $options = $this->optionsWithCounts((int) $poll['id']);
        $voted = $viewer !== null && $this->db->fetchValue(
            'SELECT 1 FROM poll_votes WHERE poll_id = ? AND user_id = ? LIMIT 1',
            [(int) $poll['id'], $viewer->id()],
        ) !== false;
        $closed = !$this->isOpen($poll);
        $poll['options'] = $options;
        $poll['viewer_voted'] = $voted;
        $poll['results_visible'] = $voted || $closed;
        $poll['can_vote'] = $viewer !== null && !$closed;
        $poll['can_close'] = false;
        if ($viewer !== null) {
            $thread = $this->threads->findWithBoard($threadId);
            $poll['can_close'] = $thread !== null && $this->canManageThread($viewer, $thread) && !$closed;
        }
        return $poll;
    }

    /** @param array<string,mixed> $thread */
    public function canManageThread(User $actor, array $thread): bool
    {
        return (int) $thread['user_id'] === $actor->id()
            || $actor->isAdmin()
            || $this->moderators->isModerator((int) $thread['board_id'], $actor->id());
    }

    /** @return array<string,mixed> */
    public function pollOrFail(int $pollId): array
    {
        $poll = $this->db->fetch('SELECT * FROM polls WHERE id = ?', [$pollId]);
        if ($poll === null) {
            throw new NotFoundException('Poll not found.');
        }
        return $poll;
    }

    /** @return array<string,mixed> */
    private function threadOrFail(int $threadId): array
    {
        $thread = $this->threads->findWithBoard($threadId);
        if ($thread === null || (int) $thread['is_deleted'] === 1 || (int) $thread['is_pending'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        return $thread;
    }

    /** @param array<string,mixed> $thread */
    private function assertCanRead(User $actor, array $thread): void
    {
        $isMember = $this->members->isMember((int) $thread['board_id'], $actor->id());
        if (!$this->policy->canRead(['visibility' => $thread['board_visibility']], $actor, $isMember)) {
            throw new NotFoundException('Thread not found.');
        }
    }

    /** @return list<string> */
    private function parseOptions(string $raw): array
    {
        $lines = preg_split('/\R+/', $raw) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $out[] = mb_substr($line, 0, 255);
            }
        }
        return array_slice(array_values(array_unique($out)), 0, 10);
    }

    /** @return array<int,true> */
    private function validOptionIds(int $pollId): array
    {
        $rows = $this->db->fetchAll('SELECT id FROM poll_options WHERE poll_id = ?', [$pollId]);
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['id']] = true;
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private function optionsWithCounts(int $pollId): array
    {
        return $this->db->fetchAll(
            'SELECT po.*, COUNT(pv.id) AS vote_count
             FROM poll_options po
             LEFT JOIN poll_votes pv ON pv.option_id = po.id
             WHERE po.poll_id = ?
             GROUP BY po.id
             ORDER BY po.position ASC, po.id ASC',
            [$pollId],
        );
    }

    /** @param array<string,mixed> $poll */
    private function isOpen(array $poll): bool
    {
        if ((string) $poll['status'] !== 'open') {
            return false;
        }
        if (!empty($poll['closes_at'])) {
            $ts = strtotime((string) $poll['closes_at'] . ' UTC');
            return $ts === false || $ts > time();
        }
        return true;
    }
}
