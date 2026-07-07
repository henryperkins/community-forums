# Phase 5 Inc 8 — Provider registry / generic OIDC (P5-12) evidence index

Deploy-dark behind `provider_registry` (§E prerequisite: `service_secrets`).
Everything below is repeatable from a checkout; the browser PNGs are committed.

## Verification core (ADR 0004 D8 path)

- `src/Service/OAuth/Oidc/` — `OidcDiscovery` → issuer-pinned `JwksCache` →
  `JwtVerifier` → `ClaimMapper`, coordinated by the configuration-only
  `OidcProvider` through the shared Phase-2 OAuth core (state/PKCE/nonce
  cookie, `OAuthService` account resolution — no provider-specific fork).
- Unit: `tests/Unit/Auth/JwtVerifierTest.php` (25 tests — signature, alg
  allowlist incl. `none`/HS256-confusion, iss/aud/azp, nonce, time claims,
  kid selection/rotation), `tests/Unit/Auth/OidcDiscoveryTest.php` (pinned
  issuer, document-issuer equality, same-origin JWKS, HTTPS endpoints),
  `tests/Unit/Auth/ClaimMapperTest.php` (fixed `sub`, strict-bool
  `email_verified`, cosmetic-only claim map, https-only avatar).
- Integration: `tests/Integration/Service/JwksCacheTest.php` (TTL cache,
  forced kid-refresh, off-issuer refusal, stale-on-outage for keys()/never
  for refresh()).

## End-to-end flows (GitLab-shaped, scripted transport)

`tests/Integration/Core/AppOidcProviderTest.php` — 20 tests through the real
HTTP kernel via the App constructor's scripted-OAuth-HTTP seam:

- Happy path: redirect leg (state/PKCE/nonce, cached discovery = zero
  network), callback creates the account with `provider_config_id` linkage,
  verified email lands on the account, PKCE verifier hashes to the challenge,
  vault-resolved client secret on the exchange.
- TM-ID-01: cross-issuer / wrong-audience / wrong-azp tokens rejected.
- TM-ID-02: state mismatch (pre-exchange), replayed callback after
  completion, missing/wrong nonce rejected.
- TM-ID-03: off-issuer JWKS never fetched (even from a poisoned cache);
  rotated kid verified through exactly one pinned refresh.
- TM-ID-04: verified-email collision refuses sign-in/merge; explicit
  login-then-link succeeds.
- §9 arms: token-endpoint outage fails soft (no 500, no account); disable
  retains identities and 404s the routes + removes the sign-in button; dark
  flag hides enabled rows (`AppFeatureFlagTest` canonical pin);
  closed-registration parity; generic identities count toward sole-method
  protection and last-method unlink refusal.

## Provider migration (§9 "Provider migration")

- `database/migrations/0074_phase5_provider_identity_backfill.php` —
  discovery-cache columns + idempotent `provider_config_id` backfill via the
  0052 alias map.
- `tests/Integration/Core/AppProviderRegistryMigrationTest.php` — no
  duplication, no orphaning, alias indirection, already-linked untouched,
  unmapped-string tolerance, distinct subjects never converge, idempotence.
- `verify:upgrade` rehearses 0001→0075 on populated Phase-1 data (see
  PHASE_5_STATUS Inc 8 gates).

## Operator console (TM-ID-09 clause 2 handoff resolved)

- `src/Service/IdentityProviderService.php` + AdminProviderController +
  `templates/admin/providers.php` / `provider_disable.php`.
- `tests/Integration/Admin/AppAdminProvidersTest.php` — 11 tests: flag-dark
  404s, non-admin 403, vault-stored secret + audit row, §E sequencing
  refusal, validation (reserved/duplicate/malformed keys, non-HTTPS issuer,
  claim-map JSON) with anti-draft-loss 422s, health probe ok/down + cache
  priming, reauth on enable/disable, builtin immutability, and the
  sole-method listing on the disable-confirm page (password-holding accounts
  excluded).
- Migration `0075_phase5_provider_admin_audit.php` widens
  `moderation_log.target_type` (+`identity_provider`).

## Browser evidence (axe-clean, desktop + mobile)

`tests/browser/providers.spec.ts` → **2 passed** (2026-07-07): add → health
probe (`down` for the unreachable fixture issuer) → reauth'd enable →
sign-in button on `/login` → disable-confirm page (sole-method empty state) →
disable → button gone. WCAG 2.1 AA axe scans on `/admin/providers` and the
confirm page. PNGs: `docs/evidence/browser/{desktop,mobile}/66-admin-
providers-console.png`, `67-login-generic-provider-button.png`,
`68-provider-disable-confirm.png`.

## Budgets (D11)

`docs/evidence/phase5/performance-budgets.md`: `oidc.discovery_p95_cached`
**0.7275 ms** (target 2000 ms) and `oidc.discovery_p95_cold` **1.2766 ms**
(target 5000 ms; in-process transport — remote RTT excluded), both
**MEASURED (PASS)** via `BaselineMetricsService::measureOidcDiscovery`.

## Threat fixtures

`docs/phase5/threat-models/fixtures.json`: TM-ID-01..04 → `implemented`
(test: `AppOidcProviderTest`), machine-checked by `ThreatModelIndexTest`.

## Operations

`docs/runbooks/provider_registry.md` — rollback, §E sequencing, per-provider
disable procedure (sole-method step), GitLab.com walkthrough, outage/JWKS-
rotation behavior, secret rotation via the vault.
