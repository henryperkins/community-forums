<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\AccountDeletionRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\ServerDraftRepository;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use App\Security\LastOwnerGuard;
use App\Security\ReauthGate;

/**
 * Self-service account lifecycle policy (ADR 0006): export, reversible
 * deactivation, deletion grace/cancel, and anonymizing purge.
 */
final class AccountLifecycleService
{
    public function __construct(
        private Database $db,
        private UserRepository $users,
        private AccountDeletionRepository $deletions,
        private SessionRepository $sessions,
        private ModerationLogRepository $logs,
        private ServerDraftRepository $serverDrafts,
        private ReauthGate $reauth,
        private ?LastOwnerGuard $ownerGuard = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function export(User $user): array
    {
        $profile = $this->users->find($user->id()) ?? [];
        unset($profile['password_hash']);

        $payload = [
            'app' => 'RetroBoards',
            'schema' => 'account-export-v1',
            'exported_at' => gmdate('c'),
            'profile' => $profile,
            'preferences' => $this->jsonValue($this->db->fetchValue('SELECT prefs FROM user_preferences WHERE user_id = ?', [$user->id()])),
            'sessions' => $this->db->fetchAll(
                'SELECT user_agent, INET6_NTOA(ip) AS ip, created_at, last_seen_at, expires_at, revoked_at
                 FROM sessions WHERE user_id = ? ORDER BY created_at DESC',
                [$user->id()],
            ),
            'subscriptions' => $this->db->fetchAll('SELECT target_type, target_id, email_enabled, in_app_enabled, frequency, created_at FROM subscriptions WHERE user_id = ? ORDER BY id ASC', [$user->id()]),
            'notifications' => $this->db->fetchAll('SELECT type, actor_id, thread_id, post_id, conversation_id, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id ASC', [$user->id()]),
            'reports' => $this->db->fetchAll('SELECT post_id, dm_message_id, reason_code, reason, status, created_at, resolved_at FROM reports WHERE reporter_id = ? ORDER BY id ASC', [$user->id()]),
            'posts' => $this->db->fetchAll(
                'SELECT p.id, p.thread_id, t.title AS thread_title, p.body, p.is_deleted, p.is_pending, p.created_at
                 FROM posts p JOIN threads t ON t.id = p.thread_id
                 WHERE p.user_id = ?
                 ORDER BY p.id ASC',
                [$user->id()],
            ),
            'direct_messages' => $this->db->fetchAll(
                'SELECT m.id, m.conversation_id, m.user_id, m.body, m.created_at
                 FROM dm_messages m
                 JOIN conversation_participants cp ON cp.conversation_id = m.conversation_id
                 WHERE cp.user_id = ?
                 ORDER BY m.conversation_id ASC, m.id ASC',
                [$user->id()],
            ),
            'server_drafts' => $this->serverDrafts->exportForUser($user->id()),
            'audit_log' => $this->db->fetchAll(
                "SELECT actor_id, action, target_type, target_id, reason, before_json, after_json, created_at
                 FROM moderation_log
                 WHERE actor_id = ? OR (target_type = 'user' AND target_id = ?)
                 ORDER BY id ASC",
                [$user->id(), $user->id()],
            ),
        ];

        $this->logs->log([
            'actor_id' => $user->id(),
            'action' => 'account_exported',
            'target_type' => 'user',
            'target_id' => $user->id(),
            'reason' => 'self_service',
        ]);

        return $payload;
    }

    public function deactivate(User $user, string $currentPassword, ?string $currentSessionId = null): void
    {
        $this->assertPassword($user, $currentPassword);

        $this->db->transaction(function () use ($user, $currentSessionId): void {
            $this->assertNotFinalActiveAdmin($user);
            $this->ownerGuard?->assertNotLastOwnerForUpdate($user, 'current_password');
            $before = $this->users->find($user->id());
            $this->users->setStatus($user->id(), 'deactivated');
            $this->sessions->revokeOthersForUser($user->id(), $currentSessionId ?? '');
            $this->logs->log([
                'actor_id' => $user->id(),
                'action' => 'account_deactivated',
                'target_type' => 'user',
                'target_id' => $user->id(),
                'reason' => 'self_service',
                'before' => ['status' => $before['status'] ?? null],
                'after' => ['status' => 'deactivated'],
            ]);
        });
    }

    public function reactivate(User $user): void
    {
        $row = $this->users->find($user->id()) ?? [];
        if (($row['status'] ?? '') !== 'deactivated') {
            throw new ValidationException(['account' => 'Only deactivated accounts can be reactivated here.']);
        }

        $this->db->transaction(function () use ($user): void {
            $this->users->setStatus($user->id(), 'active');
            $this->logs->log([
                'actor_id' => $user->id(),
                'action' => 'account_reactivated',
                'target_type' => 'user',
                'target_id' => $user->id(),
                'reason' => 'self_service',
                'before' => ['status' => 'deactivated'],
                'after' => ['status' => 'active'],
            ]);
        });
    }

    public function requestDeletion(User $user, string $currentPassword, ?string $currentSessionId = null): void
    {
        $this->assertPassword($user, $currentPassword);

        $this->db->transaction(function () use ($user, $currentSessionId): void {
            $this->assertNotFinalActiveAdmin($user);
            $this->ownerGuard?->assertNotLastOwnerForUpdate($user, 'current_password');
            if ($this->deletions->pendingForUser($user->id()) !== null) {
                return;
            }

            $purgeAfter = gmdate('Y-m-d H:i:s', time() + 30 * 86400);
            $requestId = $this->deletions->create($user->id(), $user->id(), $purgeAfter, 'self_service');
            $this->users->setStatus($user->id(), 'pending_deletion');
            $this->sessions->revokeOthersForUser($user->id(), $currentSessionId ?? '');
            $this->logs->log([
                'actor_id' => $user->id(),
                'action' => 'account_deletion_requested',
                'target_type' => 'user',
                'target_id' => $user->id(),
                'reason' => 'self_service',
                'after' => ['request_id' => $requestId, 'purge_after' => $purgeAfter],
            ]);
        });
    }

    public function cancelDeletion(User $user): void
    {
        $pending = $this->deletions->pendingForUser($user->id());
        if ($pending === null) {
            throw new ValidationException(['account' => 'No pending deletion request was found.']);
        }
        $this->db->transaction(function () use ($user, $pending): void {
            if (!$this->deletions->cancel((int) $pending['id'], $user->id())) {
                return;
            }
            $this->users->setStatus($user->id(), 'active');
            $this->logs->log([
                'actor_id' => $user->id(),
                'action' => 'account_deletion_canceled',
                'target_type' => 'user',
                'target_id' => $user->id(),
                'reason' => 'self_service',
                'before' => ['request_id' => (int) $pending['id'], 'status' => 'pending'],
                'after' => ['request_id' => (int) $pending['id'], 'status' => 'canceled'],
            ]);
        });
    }

    /** @return array{purged:int} */
    public function purgeDue(int $limit = 100): array
    {
        $purged = 0;
        foreach ($this->deletions->due($limit) as $request) {
            $this->db->transaction(function () use ($request, &$purged): void {
                $userId = (int) $request['user_id'];
                $row = $this->users->find($userId);
                // Defence in depth: never anonymize an account that is no longer
                // pending_deletion (e.g. reactivated, or a legacy status desync).
                if ($row === null || (string) ($row['status'] ?? '') !== 'pending_deletion') {
                    return;
                }
                if (!$this->deletions->markPurged((int) $request['id'])) {
                    return;
                }

                $this->purgePii($userId, (string) $row['email']);
                $this->users->anonymizeDeletedAccount($userId);
                $this->logs->log([
                    'actor_id' => null,
                    'action' => 'account_purged',
                    'target_type' => 'user',
                    'target_id' => $userId,
                    'reason' => 'deletion_grace_elapsed',
                    'before' => ['status' => $row['status'] ?? null, 'email' => $row['email'] ?? null],
                    'after' => ['status' => 'deleted'],
                ]);
                $purged++;
            });
        }

        return ['purged' => $purged];
    }

    /** @return array<string,mixed>|null */
    public function pendingDeletion(User $user): ?array
    {
        return $this->deletions->pendingForUser($user->id());
    }

    private function assertPassword(User $user, string $currentPassword): void
    {
        $this->reauth->requirePassword($user, $currentPassword);
    }

    private function assertNotFinalActiveAdmin(User $user): void
    {
        if ($user->isAdmin() && $this->users->activeAdminCountExcludingForUpdate($user->id()) === 0) {
            throw new ValidationException(['current_password' => 'Add another active admin before changing this account lifecycle state.']);
        }
    }

    private function purgePii(int $userId, string $email): void
    {
        $tables = [
            'sessions',
            'verifications',
            'oauth_identities',
            'user_preferences',
            'user_board_prefs',
            'board_folders',
            'thread_bookmark_folders',
            'user_profile_fields',
            'saved_feed_filters',
            'subscriptions',
            'notifications',
            'user_totp_credentials',
            'user_recovery_codes',
            'mfa_login_challenges',
            'server_drafts',
        ];
        foreach ($tables as $table) {
            $this->db->run("DELETE FROM {$table} WHERE user_id = ?", [$userId]);
        }

        $this->db->run("DELETE FROM follows WHERE user_id = ? OR (target_type = 'user' AND target_id = ?)", [$userId, $userId]);
        $this->db->run('DELETE FROM blocks WHERE user_id = ? OR blocked_user_id = ?', [$userId, $userId]);
        $this->db->run('DELETE FROM conversation_participants WHERE user_id = ?', [$userId]);
        $this->db->run('DELETE FROM email_suppressions WHERE email = ?', [strtolower($email)]);
        $this->db->run(
            'UPDATE email_deliveries SET user_id = NULL, email = ? WHERE user_id = ? OR email = ?',
            ['deleted-user-' . $userId . '@deleted.invalid', $userId, $email],
        );
    }

    private function jsonValue(mixed $raw): mixed
    {
        if (!is_string($raw) || $raw === '') {
            return new \stdClass();
        }
        $decoded = json_decode($raw, true);
        return $decoded === null ? new \stdClass() : $decoded;
    }
}
