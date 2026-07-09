# Runbook: Package Integrations & Security Response (Phase 5 Increment 5)

Covers the `remote_app` / declarative `automation` integration runtime and the
local security-response console. All surfaces gate on `package_registry`
(`FeatureFlags::enabled('package_registry')` graduated to default-ON on
2026-07-09 and remains operator-reversible via
`features.package_registry=false`); no new flag.

## Hard runtime predecessors
- `service_secrets` **must** be enabled to mint any credential or write a secret
  setting. With the vault off, `PackageSettingsService::save()` and
  `PackageIntegrationService::provisionCredentials()` throw `ValidationException`
  (fail closed). Reveal / revoke / export still work while dark.
- Only installs of type `remote_app`/`automation` in state `enabled` with zero
  ungranted scopes/events/hosts and not blocked/quarantined/revoked/emergency-
  disabled can provision. Manifest = ceiling; `installed_package_permissions`
  (`declared=1 AND granted=1`) = authority.

## Provision / rotate / revoke credentials
- **Provision** (`/admin/packages/{id}/integration/provision`): reauth
  (`current_password`) + CSRF. Mints ≤1 read-only `api_token` (granted
  `api_scope`s) and ≤1 `webhook` (granted `event`s, URL host ∈ granted
  `outbound_host`s), atomically in one `$db->transaction`. Plaintext token +
  webhook secret render **once**, server-side; they are never re-derivable.
- **Rotate** (`/credentials/{credentialId}/rotate`): reauth. api_token =
  revoke+mint new; webhook = `WebhookService::rotateSecret`. New secret shown once.
- **Revoke** (`/credentials/{credentialId}/revoke`): WriteGate only (no reauth —
  defensive). Idempotent.

## Secret handling
Secret settings (`type=string, secret:true`) store into `SecretVault`; only the
`svcsec_*` reference persists. Plaintext is never returned by settings/overview
reads, never written to `moderation_log`/`package_history`/
`package_transparency_log`, never in exception messages (TM-SE-02/04/05).

## Emergency execution disable (flag-independent brake)
`/admin/packages/security/execution` toggles the `package_execution_disabled`
DB setting (OR'd with the `PACKAGE_EXECUTION_DISABLED` env break-glass). On
disable: every package-owned webhook endpoint is paused (`is_active=0`, so the
delivery worker/`dispatch()` skip it) and package-owned credential auth is
denied. Operators can still view, revoke, export, and uninstall.

## Publisher trust & key lifecycle
`/admin/packages/publishers/{id}` verify/suspend/reinstate (reauth). Suspension
cascades a force-disable across that publisher's installs via
`PackageHealthService::enforcePolicy()`. Keys: pin (base64 → exactly 32 bytes),
rotate (signed `rb-key-rotation.v1` envelope `{document,signature,key_id}`),
revoke.

## Outage / fail-closed
Registry/vault/remote unavailability yields coded
`RegistryVerificationException`/`PackagePolicyException`/`ValidationException` —
never a silent degraded grant. Revoked/blocked/unreviewed/disabled/quarantined/
uninstalled/emergency-disabled installs cannot receive events or authenticate.
