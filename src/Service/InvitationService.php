<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\InvitationRepository;
use App\Repository\ModerationLogRepository;

/**
 * Invitation lifecycle (P5-13, Inc 9). Tokens are 256-bit random, hash-only
 * at rest, and shown exactly once at creation. An invitation is onboarding
 * evidence, NOT authority (decision #36): redemption grants ordinary
 * membership plus at most the stored board membership — never a role
 * (`onboarding_role_id` is neither issued nor applied; no approved
 * onboarding-role policy exists — docs/phase5/invitation-defaults.md).
 *
 * Enumeration responses are uniform: missing, malformed, expired, revoked and
 * exhausted tokens are indistinguishable (INVALID_MESSAGE / null preview),
 * per TM-IN-01.
 */
final class InvitationService
{
    public const INVALID_MESSAGE = 'This invitation link is invalid or no longer active.';

    private const TOKEN_BYTES = 32;               // 64 lowercase hex chars
    private const DEFAULT_EXPIRY_DAYS = 14;       // invitations always expire
    private const MAX_EXPIRY_DAYS = 365;
    private const MAX_USES_CEILING = 100;

    public function __construct(
        private Database $db,
        private InvitationRepository $invitations,
        private AuthService $auth,
        private BoardRepository $boards,
        private BoardMemberRepository $boardMembers,
        private ModerationLogRepository $log,
    ) {
    }

    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /**
     * Issue an invitation. The raw token is returned ONCE and never persisted.
     *
     * @param array<string,mixed> $input
     * @return array{id:int, token:string}
     * @throws ValidationException
     */
    public function create(User $admin, array $input): array
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $domain = strtolower(ltrim(trim((string) ($input['domain'] ?? '')), '@'));
        $maxUsesRaw = trim((string) ($input['max_uses'] ?? ''));
        $expiresRaw = trim((string) ($input['expires_in_days'] ?? ''));
        $boardRaw = trim((string) ($input['onboarding_board_id'] ?? ''));

        $errors = [];
        $old = [
            'email' => $email,
            'domain' => $domain,
            'max_uses' => $maxUsesRaw,
            'expires_in_days' => $expiresRaw,
            'onboarding_board_id' => $boardRaw,
        ];

        if ($email !== '' && $domain !== '') {
            $errors['domain'] = 'Bind to an email address or a domain, not both.';
        }
        if ($email !== '' && (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 255)) {
            $errors['email'] = 'Enter a valid email address to bind, or leave blank.';
        }
        if ($domain !== '' && (strlen($domain) > 190 || preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) !== 1)) {
            $errors['domain'] = 'Enter a bare domain like example.com, or leave blank.';
        }
        $maxUses = $maxUsesRaw === '' ? 1 : (int) $maxUsesRaw;
        if ($maxUses < 1 || $maxUses > self::MAX_USES_CEILING || ($maxUsesRaw !== '' && (string) $maxUses !== $maxUsesRaw)) {
            $errors['max_uses'] = 'Max uses must be between 1 and ' . self::MAX_USES_CEILING . '.';
        }
        $days = $expiresRaw === '' ? self::DEFAULT_EXPIRY_DAYS : (int) $expiresRaw;
        if ($days < 1 || $days > self::MAX_EXPIRY_DAYS || ($expiresRaw !== '' && (string) $days !== $expiresRaw)) {
            $errors['expires_in_days'] = 'Expiry must be between 1 and ' . self::MAX_EXPIRY_DAYS . ' days.';
        }
        $boardId = $boardRaw === '' ? null : (int) $boardRaw;
        if ($boardId !== null && $this->boards->find($boardId) === null) {
            $errors['onboarding_board_id'] = 'That board does not exist.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $old);
        }

        // `onboarding_role_id` is deliberately never read from input and never
        // stored by this console (decision #36 — role grants require a separate
        // authenticated assignment path under an approved policy; none exists).
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $days * 86400);

        $id = $this->db->transaction(function () use ($admin, $token, $email, $domain, $boardId, $maxUses, $expiresAt): int {
            $id = $this->invitations->create([
                'token_hash' => self::hash($token),
                'created_by' => $admin->id(),
                'email' => $email !== '' ? $email : null,
                'domain' => $domain !== '' ? $domain : null,
                'onboarding_board_id' => $boardId,
                'max_uses' => $maxUses,
                'expires_at' => $expiresAt,
            ]);
            // The audit row carries the CONSTRAINTS, never the token (TM-IN-06).
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'invitation_created',
                'target_type' => 'invitation',
                'target_id' => $id,
                'after' => [
                    'email' => $email !== '' ? $email : null,
                    'domain' => $domain !== '' ? $domain : null,
                    'onboarding_board_id' => $boardId,
                    'max_uses' => $maxUses,
                    'expires_at' => $expiresAt,
                ],
            ]);
            return $id;
        });

        return ['id' => $id, 'token' => $token];
    }

    /** @return array<int,array<string,mixed>> console rows with a derived `status` */
    public function list(): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $rows = [];
        foreach ($this->invitations->all() as $row) {
            $row['status'] = $this->status($row, $now);
            $rows[] = $row;
        }
        return $rows;
    }

    public function revoke(User $admin, int $id): void
    {
        if ($this->invitations->find($id) === null) {
            throw new NotFoundException('Invitation not found.');
        }
        $this->db->transaction(function () use ($admin, $id): void {
            if ($this->invitations->revoke($id, $admin->id()) === 1) {
                $this->log->log([
                    'actor_id' => $admin->id(),
                    'action' => 'invitation_revoked',
                    'target_type' => 'invitation',
                    'target_id' => $id,
                ]);
            }
        });
    }

    /**
     * Atomic redemption + registration (PHASE_5_PLAN §8.5). One transaction,
     * ordered: uniform validity check → binding check → guarded consumeUse →
     * AuthService::register → redemption row → board grant → audit.
     *
     * Consume-before-register means a concurrent loser exits before creating
     * anything, and a later registration failure rolls the consumed use back
     * with the transaction. `onboarding_role_id` is NEVER applied — an
     * invitation is onboarding evidence, not authority (decision #36;
     * TM-IN-05) — while `onboarding_board_id` grants plain board membership.
     *
     * @param array<string,mixed> $input the /register POST fields
     * @throws ValidationException uniform `invite` error, binding `email`
     *         error, or the registration field errors — always carrying `old`
     *         (typed draft + the invite token) for the 422 re-render
     */
    public function redeem(string $rawToken, array $input, ?string $ip): User
    {
        $old = [
            'username' => trim((string) ($input['username'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'display_name' => trim((string) ($input['display_name'] ?? '')),
            'invite' => $rawToken,
        ];

        return $this->db->transaction(function () use ($rawToken, $input, $ip, $old): User {
            $row = $this->preview($rawToken);
            if ($row === null) {
                throw new ValidationException(['invite' => self::INVALID_MESSAGE], $old);
            }

            $email = strtolower(trim((string) ($input['email'] ?? '')));
            if ($row['email'] !== null && strcasecmp((string) $row['email'], $email) !== 0) {
                throw new ValidationException(['email' => 'This invitation is for a different email address.'], $old);
            }
            if ($row['domain'] !== null) {
                $at = strrpos($email, '@');
                $submittedDomain = $at === false ? '' : substr($email, $at + 1);
                if (strcasecmp($submittedDomain, (string) $row['domain']) !== 0) {
                    throw new ValidationException(['email' => 'This invitation requires an email address at ' . $row['domain'] . '.'], $old);
                }
            }

            if ($this->invitations->consumeUse((int) $row['id']) !== 1) {
                // Lost a concurrent race, or the row's state changed since preview.
                throw new ValidationException(['invite' => self::INVALID_MESSAGE], $old);
            }

            try {
                $user = $this->auth->register($input);
            } catch (ValidationException $e) {
                // Re-carry the invite token so the 422 re-render keeps the link
                // alive; the transaction rollback restores the consumed use.
                throw new ValidationException($e->errors, $e->old + ['invite' => $rawToken]);
            }

            $this->invitations->recordRedemption((int) $row['id'], $user->id(), $ip);
            if ($row['onboarding_board_id'] !== null && $this->boards->find((int) $row['onboarding_board_id']) !== null) {
                $this->boardMembers->add(
                    (int) $row['onboarding_board_id'],
                    $user->id(),
                    $row['created_by'] !== null ? (int) $row['created_by'] : null,
                );
            }
            // onboarding_role_id deliberately ignored (see docblock).
            $this->log->log([
                'actor_id' => $user->id(),
                'action' => 'invitation_redeemed',
                'target_type' => 'invitation',
                'target_id' => (int) $row['id'],
                'after' => ['user_id' => $user->id()],
            ]);
            return $user;
        });
    }

    /**
     * Look up a token for display purposes. Returns the row only when the
     * invitation is redeemable RIGHT NOW; every invalid reason — unknown,
     * malformed, expired, revoked, exhausted — collapses to null (TM-IN-01).
     *
     * @return array<string,mixed>|null
     */
    public function preview(string $rawToken): ?array
    {
        if (preg_match('/^[0-9a-f]{64}$/', $rawToken) !== 1) {
            return null;
        }
        $row = $this->invitations->findByTokenHash(self::hash($rawToken));
        if ($row === null || $this->status($row, gmdate('Y-m-d H:i:s')) !== 'active') {
            return null;
        }
        return $row;
    }

    /** @param array<string,mixed> $row */
    private function status(array $row, string $nowUtc): string
    {
        if ($row['revoked_at'] !== null) {
            return 'revoked';
        }
        if ($row['expires_at'] !== null && (string) $row['expires_at'] <= $nowUtc) {
            return 'expired';
        }
        if ((int) $row['used_count'] >= (int) $row['max_uses']) {
            return 'exhausted';
        }
        return 'active';
    }
}
