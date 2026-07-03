<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\ApiTokensDisabledException;
use App\Core\Config;
use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ApiTokenRepository;
use App\Repository\ModerationLogRepository;
use App\Security\ApiPrincipal;
use App\Security\ApiScopes;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\Packages\PackageCredentialAuthGuard;

/**
 * Mint/authenticate/revoke admin/service API tokens. Tokens are shown once and
 * stored only as sha256 hashes. A token is a standalone scoped principal —
 * never a User. The api_tokens flag is a service-level kill switch.
 */
final class ApiTokenService
{
    public function __construct(
        private Database $db,
        private ApiTokenRepository $tokens,
        private ModerationLogRepository $log,
        private FeatureFlags $flags,
        private Config $config,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ?PackageCredentialAuthGuard $packageAuthGuard = null,
    ) {
    }

    /**
     * @param array<int,mixed> $scopes
     * @return array{token:string,id:int}
     */
    public function mint(User $admin, string $currentPassword, string $name, array $scopes, ?int $expiresInDays): array
    {
        $this->writeGate->assertCanWrite($admin);
        if (!$this->flags->enabled('api_tokens')) {
            throw new ApiTokensDisabledException('API tokens are disabled.');
        }
        $this->reauth->requirePassword($admin, $currentPassword);

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new ValidationException(['name' => 'Name must be 1–80 characters.']);
        }
        $clean = [];
        foreach ($scopes as $scope) {
            if (!is_string($scope) || !ApiScopes::isValid($scope)) {
                throw new ValidationException(['scopes' => 'Unknown scope.']);
            }
            if (in_array($scope, $clean, true)) {
                // Spec: distinct scopes — a duplicate is a client error, not silently deduped.
                throw new ValidationException(['scopes' => 'Duplicate scope.']);
            }
            $clean[] = $scope;
        }
        if ($clean === []) {
            throw new ValidationException(['scopes' => 'Select at least one scope.']);
        }
        $expiresAt = null;
        if ($expiresInDays !== null) {
            if ($expiresInDays < 1 || $expiresInDays > 365) {
                throw new ValidationException(['expires_in_days' => 'Expiry must be 1–365 days.']);
            }
            $expiresAt = gmdate('Y-m-d H:i:s', time() + $expiresInDays * 86400);
        }

        $plaintext = 'rbt_' . bin2hex(random_bytes(24));
        $hash = hash('sha256', $plaintext);

        $id = $this->db->transaction(function () use ($name, $hash, $clean, $admin, $expiresAt): int {
            $id = $this->tokens->insert($name, $hash, json_encode($clean) ?: '[]', $admin->id(), $expiresAt);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'api_token_minted',
                'target_type' => 'api_token',
                'target_id' => $id,
                'after' => ['name' => $name, 'scopes' => $clean, 'expires_at' => $expiresAt],
            ]);
            return $id;
        });

        return ['token' => $plaintext, 'id' => $id];
    }

    public function authenticate(string $bearer): ?ApiPrincipal
    {
        if (!$this->flags->enabled('api_tokens')) {
            return null;
        }
        // Require the "Bearer " scheme — a raw token without it must NOT authenticate.
        if (!preg_match('/^Bearer\s+(\S.*)$/i', trim($bearer), $m)) {
            return null;
        }
        $token = trim($m[1]);
        if ($token === '') {
            return null;
        }
        $hash = hash('sha256', $token);
        $row = $this->tokens->findActiveByHash($hash);
        if ($row === null) {
            return null;
        }
        // Package-owned tokens fail closed when the owning install is unsafe (emergency
        // disable, revoked link, non-enabled/unreviewed install, local/advisory block).
        // Human/legacy tokens have no credential link and pass through unchanged. Denied
        // package tokens must not touch last_used_at.
        if ($this->packageAuthGuard !== null && !$this->packageAuthGuard->allowsApiToken((int) $row['id'])) {
            return null;
        }
        $this->tokens->touchLastUsed((int) $row['id']);
        $scopes = json_decode((string) $row['scopes'], true);
        return new ApiPrincipal(
            (int) $row['id'],
            (string) $row['name'],
            is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [],
            (int) $row['created_by'],
            (string) $row['created_at'],
            $hash,
        );
    }

    public function revoke(User $admin, int $tokenId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->db->transaction(function () use ($admin, $tokenId): void {
            // Audit only a real state change: a no-op revoke (unknown id, or one already
            // revoked) must NOT forge an `api_token_revoked` row. Idempotent either way.
            if ($this->tokens->revoke($tokenId) !== 1) {
                return;
            }
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'api_token_revoked',
                'target_type' => 'api_token',
                'target_id' => $tokenId,
            ]);
        });
    }

    public function auditScopeDenied(ApiPrincipal $p, string $scope): void
    {
        $this->log->log([
            'actor_id' => null,
            'action' => 'api_token_scope_denied',
            'target_type' => 'api_token',
            'target_id' => $p->tokenId(),
            'after' => ['scope' => $scope],
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function list(): array
    {
        return $this->tokens->list();
    }
}
