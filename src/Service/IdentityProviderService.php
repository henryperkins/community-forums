<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\OidcVerificationException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\IdentityProviderRepository;
use App\Repository\ModerationLogRepository;
use App\Security\ReauthGate;
use App\Service\OAuth\Oidc\JwksCache;
use App\Service\OAuth\Oidc\OidcDiscovery;

/**
 * Operator lifecycle for generic-OIDC providers (P5-12): validated creation
 * with the client secret stored straight into the encrypted vault (§E
 * sequencing: service_secrets before provider_registry), an explicit health
 * probe (discovery + JWKS through the pinned path, priming both caches), and
 * reauth'd enable/disable that only ever touches configuration — identities
 * are never deleted here. Builtin rows are env-configured and immutable from
 * the console. Every mutation writes a moderation_log audit row.
 */
final class IdentityProviderService
{
    private const RESERVED_KEYS = ['google', 'apple', 'github'];

    public function __construct(
        private Database $db,
        private IdentityProviderRepository $providers,
        private SecretVault $vault,
        private OidcDiscovery $discovery,
        private JwksCache $jwks,
        private ReauthGate $reauth,
        private ModerationLogRepository $log,
        private FeatureFlags $flags,
    ) {
    }

    /** @param array<string,mixed> $input @return int the new provider id */
    public function create(User $admin, string $currentPassword, array $input): int
    {
        $this->reauth->requirePassword($admin, $currentPassword);

        $errors = [];
        $key = strtolower(trim((string) ($input['provider_key'] ?? '')));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,31}$/', $key)) {
            $errors['provider_key'] = 'Use 2–32 lowercase letters, digits, hyphens, or underscores.';
        } elseif (in_array($key, self::RESERVED_KEYS, true)) {
            $errors['provider_key'] = 'That key is reserved for a builtin provider.';
        } elseif ($this->providers->keyExists($key)) {
            $errors['provider_key'] = 'That provider key already exists.';
        }

        $name = trim((string) ($input['display_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 190) {
            $errors['display_name'] = 'Display name is required (up to 190 characters).';
        }

        // Stored VERBATIM (trimmed only): discovery and the id_token `iss`
        // claim must byte-equal the pin, and spec-legal issuers can carry a
        // trailing slash (e.g. Auth0 tenants) — stripping it would make such
        // an IdP permanently fail `issuer_mismatch`.
        $issuer = trim((string) ($input['issuer'] ?? ''));
        if (!self::validIssuer($issuer) || strlen($issuer) > 512) {
            $errors['issuer'] = 'Issuer must be a clean HTTPS URL (up to 512 characters) — no query string or fragment.';
        }

        $clientId = trim((string) ($input['client_id'] ?? ''));
        if ($clientId === '' || strlen($clientId) > 255) {
            $errors['client_id'] = 'Client ID is required.';
        }

        $secret = (string) ($input['client_secret'] ?? '');
        if ($secret === '') {
            $errors['client_secret'] = 'Client secret is required.';
        }

        $claimMap = trim((string) ($input['claim_map_json'] ?? ''));
        if ($claimMap !== '' && (strlen($claimMap) > 65535 || !is_array(json_decode($claimMap, true)))) {
            $errors['claim_map_json'] = 'Claim map must be a JSON object of at most 64 KB (or left empty).';
        }

        // §E hard sequencing rule 1: no vault, no providers.
        if (!$this->flags->enabled('service_secrets')) {
            $errors['client_secret'] = 'Enable the service_secrets flag first — provider client secrets are stored only in the encrypted vault.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        // The vault owns its own transaction; a failure after this point leaves
        // an orphaned (revocable, prunable) secret, never a plaintext anywhere.
        $ref = $this->vault->store('identity_provider', null, $key . ' client secret', $secret, $admin);

        return $this->db->transaction(function () use ($key, $name, $issuer, $clientId, $claimMap, $ref, $admin): int {
            $id = $this->providers->create([
                'provider_key' => $key,
                'display_name' => $name,
                'issuer' => $issuer,
                'client_id' => $clientId,
                'client_secret_ref' => $ref,
                'claim_map_json' => $claimMap !== '' ? $claimMap : null,
            ]);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'identity_provider_created',
                'target_type' => 'identity_provider',
                'target_id' => $id,
                'after' => ['provider_key' => $key, 'issuer' => $issuer, 'client_id' => $clientId, 'secret_ref' => $ref],
            ]);
            return $id;
        });
    }

    /** @return array<string,mixed> the row, with is_enabled updated */
    public function setEnabled(User $admin, string $currentPassword, int $id, bool $enabled): array
    {
        $this->reauth->requirePassword($admin, $currentPassword);

        $row = $this->providers->find($id);
        if ($row === null) {
            throw new NotFoundException('Provider not found.');
        }
        if ((string) $row['type'] !== 'generic_oidc') {
            throw new ValidationException(['provider' => 'Builtin providers are configured through environment variables, not the console.']);
        }

        $this->db->transaction(function () use ($row, $id, $enabled, $admin): void {
            $this->providers->setEnabled($id, $enabled);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => $enabled ? 'identity_provider_enabled' : 'identity_provider_disabled',
                'target_type' => 'identity_provider',
                'target_id' => $id,
                'before' => ['is_enabled' => (int) $row['is_enabled']],
                'after' => ['is_enabled' => $enabled ? 1 : 0],
            ]);
        });

        $row['is_enabled'] = $enabled ? 1 : 0;
        return $row;
    }

    /**
     * Explicit connectivity probe: discovery + JWKS through the pinned path.
     * A pass primes both caches (so the first member flow needs no fetch);
     * any refusal or outage records `down` — never throws for connectivity.
     * Builtin rows are refused like every other console mutation: they are
     * env-configured reference data, and probing one would fire live fetches
     * and overwrite its health/caches.
     *
     * @return array{status:string, detail:string}
     */
    public function healthProbe(int $id): array
    {
        $row = $this->providers->find($id);
        if ($row === null) {
            throw new NotFoundException('Provider not found.');
        }
        if ((string) $row['type'] !== 'generic_oidc') {
            throw new ValidationException(['provider' => 'Builtin providers are configured through environment variables, not the console.']);
        }

        try {
            $discoveryUrl = (string) ($row['discovery_url'] ?? '');
            $doc = $this->discovery->fetch((string) $row['issuer'], $discoveryUrl !== '' ? $discoveryUrl : null);
            $this->providers->cacheDiscovery($id, (string) json_encode($doc));
            $this->jwks->refresh($row, (string) $doc['jwks_uri']);
            $this->providers->updateHealth($id, 'ok');
            return ['status' => 'ok', 'detail' => 'Discovery and JWKS verified; caches primed.'];
        } catch (OidcVerificationException $e) {
            $this->providers->updateHealth($id, 'down');
            return ['status' => 'down', 'detail' => 'Refused: ' . $e->reason];
        } catch (\Throwable) {
            $this->providers->updateHealth($id, 'down');
            return ['status' => 'down', 'detail' => 'Provider unreachable.'];
        }
    }

    private static function validIssuer(string $issuer): bool
    {
        $parts = parse_url($issuer);
        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && (string) ($parts['host'] ?? '') !== ''
            && !isset($parts['query'])
            && !isset($parts['fragment']);
    }
}
