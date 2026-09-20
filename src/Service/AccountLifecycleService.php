<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\AccountDeletionRepository;
use App\Repository\BanRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\ServerDraftRepository;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use App\Repository\WebAuthnCredentialRepository;
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
        private WebAuthnCredentialRepository $webauthnCredentials,
        private ?LastOwnerGuard $ownerGuard = null,
        private ?BanRepository $bans = null,
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
            'passkeys' => $this->passkeysForExport($user->id()),
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

    /** @return list<array{nickname:mixed,created_at:mixed,last_used_at:mixed,transports:mixed,backed_up:bool}> */
    private function passkeysForExport(int $userId): array
    {
        $passkeys = [];
        foreach ($this->webauthnCredentials->activeForUser($userId) as $row) {
            $passkeys[] = [
                'nickname' => $row['nickname'],
                'created_at' => $row['created_at'],
                'last_used_at' => $row['last_used_at'],
                'transports' => $row['transports'],
                'backed_up' => (int) $row['is_backed_up'] === 1,
            ];
        }

        return $passkeys;
    }

    public function deactivate(User $user, string $currentPassword, ?string $currentSessionId = null): void
    {
        $this->db->transaction(function () use ($user, $currentPassword, $currentSessionId): void {
            [$before, $pending, $restrictions] = $this->lockState($user, true);
            $this->assertPassword(User::fromRow($before), $currentPassword);
            $this->assertAction('deactivate', $before, $pending, $restrictions);
            $this->users->setStatus($user->id(), 'deactivated', $before['suspended_until']);
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
        $this->db->transaction(function () use ($user): void {
            [$row, $pending, $restrictions] = $this->lockState($user);
            $this->assertAction('reactivate', $row, $pending, $restrictions);
            $this->users->setStatus($user->id(), 'active', $row['suspended_until']);
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
        $this->db->transaction(function () use ($user, $currentPassword, $currentSessionId): void {
            [$row, $pending, $restrictions] = $this->lockState($user, true);
            $this->assertPassword(User::fromRow($row), $currentPassword);
            if ($pending !== null) {
                return; // A repeat cannot overwrite an independent restriction.
            }
            $this->assertAction('request_deletion', $row, $pending, $restrictions);

            $purgeAfter = gmdate('Y-m-d H:i:s', time() + 30 * 86400);
            $requestId = $this->deletions->create($user->id(), $user->id(), $purgeAfter, 'self_service');
            $this->users->setStatus($user->id(), 'pending_deletion', $row['suspended_until']);
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
        $this->db->transaction(function () use ($user): void {
            [$row, $pending, $restrictions] = $this->lockState($user);
            $this->assertAction('cancel_deletion', $row, $pending, $restrictions);
            if (!$this->deletions->cancel((int) $pending['id'], $user->id())) {
                return;
            }
            // Cancellation removes only the deletion hold. Reconcile a live
            // restriction even if an older bypass left the cached status wrong.
            [$status, $until] = $this->statusAfterCancellation($row, $restrictions);
            $this->users->setStatus($user->id(), $status, $until);
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
                $row = $this->users->findForUpdate($userId);
                $pending = $this->deletions->pendingForUserForUpdate($userId);
                // The durable request survives moderation during grace. Legacy
                // active/deactivated anomalies require targeted reconciliation.
                if ($row === null || !in_array($row['status'], ['pending_deletion', 'banned', 'suspended'], true)
                    || $pending === null || (int) $pending['id'] !== (int) $request['id']
                    || (string) $pending['purge_after'] > gmdate('Y-m-d H:i:s')) {
                    return;
                }
                if (!$this->deletions->markPurged((int) $pending['id'])) {
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
        $this->reauth->requirePassword($user, $currentPassword, missingPasswordError: 'Set a password in Security before deactivating or deleting your account.');
    }

    /** @return array{deactivate:bool,reactivate:bool,request_deletion:bool,cancel_deletion:bool} */
    public function availableActions(User $user): array
    {
        return $this->db->transaction(function () use ($user): array {
            return $this->actions(...$this->lockState($user));
        });
    }

    /**
     * Owner-loss operations lock shared owner/admin rowsets first, then target,
     * pending request, and restrictions. Recovery never acquires owner rowsets.
     * @return array{array<string,mixed>,?array,list<array<string,mixed>>}
     */
    private function lockState(User $user, bool $ownerLoss = false): array
    {
        $otherAdmins = null;
        if ($ownerLoss && $user->isAdmin()) {
            $this->ownerGuard?->assertNotLastOwnerForUpdate($user, 'current_password');
            $otherAdmins = $this->users->activeAdminCountExcludingForUpdate($user->id());
        }
        $row = $this->users->findForUpdate($user->id());
        if ($row === null) {
            throw new ValidationException(['account' => 'This account is no longer available.']);
        }
        if ($ownerLoss && $row['role'] === 'admin' && $otherAdmins === null) {
            // A concurrent promotion changed which rowsets this operation needs.
            // Do not acquire them after the target lock; retry from a fresh page.
            throw new ValidationException(['account' => 'Your account role changed. Reload this page and try again.']);
        }
        if ($ownerLoss && $row['role'] === 'admin' && $otherAdmins === 0) {
            throw new ValidationException(['current_password' => 'Add another active admin before changing this account lifecycle state.']);
        }
        return [
            $row,
            $this->deletions->pendingForUserForUpdate($user->id()),
            ($this->bans ?? new BanRepository($this->db))->activeSiteRestrictionsForUpdate($user->id()),
        ];
    }

    /** @param array<string,mixed> $row @param list<array<string,mixed>> $restrictions */
    private function actions(array $row, ?array $pending, array $restrictions): array
    {
        $user = User::fromRow($row);
        $restricted = $restrictions !== [] || $user->isBanned() || $user->isSuspended()
            || ($row['suspended_until'] !== null && strtotime($row['suspended_until'] . ' UTC') > time());
        $active = in_array($row['status'], ['active', 'suspended'], true) && $user->isActive();
        return [
            'deactivate' => $active && $pending === null && !$restricted,
            'reactivate' => $user->isDeactivated() && $pending === null && !$restricted,
            'request_deletion' => ($active || $user->isDeactivated()) && $pending === null && !$restricted,
            'cancel_deletion' => $pending !== null && $row['status'] !== 'deleted',
        ];
    }

    private function assertAction(string $action, array $row, ?array $pending, array $restrictions): void
    {
        if (!$this->actions($row, $pending, $restrictions)[$action]) {
            throw new ValidationException(['account' => match ($action) {
                'deactivate' => 'Only an active account without a pending deletion or site restriction can be deactivated.',
                'reactivate' => 'Only a self-deactivated account without a pending deletion or site restriction can be reactivated.',
                'request_deletion' => 'Account deletion requires an active or self-deactivated account without a site restriction.',
                default => 'No pending deletion request is available to cancel.',
            }]);
        }
    }

    /** @return array{string,?string} */
    private function statusAfterCancellation(array $row, array $restrictions): array
    {
        $until = $row['suspended_until'];
        if ($row['status'] === 'banned') {
            return ['banned', $until];
        }
        foreach ($restrictions as $restriction) {
            if ($restriction['type'] === 'full') {
                return ['banned', $until];
            }
        }
        if (User::fromRow($row)->isSuspended()) {
            return ['suspended', $until];
        }
        if ($restrictions !== []) {
            $expiry = null;
            foreach ($restrictions as $restriction) {
                if ($restriction['expires_at'] === null) {
                    return ['suspended', null];
                }
                $expiry = max($expiry ?? '', $restriction['expires_at']);
            }
            return ['suspended', $expiry];
        }
        if ($until !== null && strtotime($until . ' UTC') > time()) {
            return ['suspended', $until];
        }
        return ['active', $until];
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
