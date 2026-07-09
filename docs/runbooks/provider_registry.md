# Runbook — Provider registry / generic OIDC (P5-12)

## What the flag gates

`features.provider_registry` graduated to default-ON on 2026-07-09 —
operator-reversible via `features.provider_registry=false`. It gates the whole
P5-12 surface:

- `/admin/providers` — the operator console (list, add, test, enable/disable).
- Registry-backed generic-OIDC providers joining `ProviderRegistry`: their
  `/auth/{key}/redirect` + `/auth/{key}/callback` routes, the sign-in buttons
  on `/login`, and their rows on `/settings/connections`.

The **builtin** Google/Apple/GitHub sign-in is *not* gated by this flag — it
stays on the accepted Phase-2 path (`config('oauth')` from `OAUTH_*` env vars,
under the Phase-2 `oauth` flag). The `oauth` flag is the master switch: if it
is off, generic providers are unavailable regardless of `provider_registry`.

## Hard sequencing rule (§E rule 1)

**`service_secrets` must be enabled before `provider_registry`.** Provider
client secrets are stored only as `svcsec_*` references in the encrypted
vault; the add-provider form refuses (422, field error naming the flag) while
the vault is dark. Rotation/revocation of a client secret goes through the
vault (decision #35), never through the provider console.

## Roll back (disable)

1. Set `features.provider_registry=false` (admin → Feature flags, or the
   `features` settings JSON).
2. Effect is immediate and config-only: generic providers disappear from
   sign-in, their `/auth/{key}/*` routes 404, and `/admin/providers` goes
   dark. **No identity rows are touched** — `oauth_identities` (including
   `provider_config_id` linkage) and `identity_providers` rows are retained,
   so re-enabling restores sign-in unchanged.
3. Builtin Google/Apple/GitHub sign-in continues unaffected.

## Disable ONE provider (not the whole flag)

Use the per-provider path — this is the §9 "Provider disable" procedure:

1. `/admin/providers` → the provider row → **Disable…**
2. The confirm page lists every account whose **only** sign-in method is this
   provider (no password, no passkey, no other provider — TM-ID-09 clause 2).
   **Contact those members first**: after disable they can regain access only
   via password reset to their listed email, or by you re-enabling the
   provider.
3. Confirm with your password. Identities are retained; the sign-in button and
   `/auth/{key}/*` routes go dark for that provider only.

Sole-method counts are also visible per row on the index, including for the
builtin providers (whose "disable" is removing their `OAUTH_*` env vars —
check the count before doing that, for the same reason).

## Add a provider (generic OIDC)

1. Prerequisites: `service_secrets` ON, `provider_registry` ON.
2. Register a **confidential** OAuth application at the IdP with redirect URI
   `{APP_URL}/auth/{key}/callback` and scopes `openid profile email`.
3. `/admin/providers` → *Add an OIDC provider*: stable key (immutable, used in
   URLs and identity rows), display name, **pinned HTTPS issuer** (no query or
   fragment; discovery resolves from
   `{issuer}/.well-known/openid-configuration`), client id, client secret
   (straight to the vault), optional claim map (renames the cosmetic claims
   only — the subject claim is always `sub`). Enter the issuer **exactly as
   the IdP publishes it** — a trailing slash is significant (Auth0-style
   issuers end in `/`; the discovery echo and `iss` claim must byte-match).
4. New providers land **disabled**. Run **Test connection** — it fetches and
   validates the discovery document (document issuer must equal the pinned
   issuer; endpoints HTTPS; JWKS URI same-origin with the issuer) and the
   JWKS, primes both caches, and records `ok`/`down` health on the row.
5. Enable (password reauth). The sign-in button appears on `/login`.

### GitLab.com (the accepted A2 first provider)

Per `docs/phase5/first-oidc-provider.md`: GitLab → User Settings →
Applications → new application with redirect URI
`{APP_URL}/auth/gitlab/callback`, scopes `openid profile email`,
confidential. Console values: key `gitlab`, issuer `https://gitlab.com`
(exact), the application's client id + secret. No claim map needed (GitLab
serves standard `email`/`email_verified`/`name`/`preferred_username`/
`picture` claims; `sub` is the numeric user id, treated as an opaque string).

## Verification model (what protects sign-in)

Every generic-OIDC callback verifies the id_token end-to-end: RS256 signature
against the issuer-pinned JWKS, `iss` exact-match, `aud`/`azp` against the
client id, the flow `nonce`, and `exp`/`iat`/`nbf` with a 300 s leeway —
plus `state` (signed cookie) and PKCE on the wire. A verified provider email
is never a merge key: a collision with an existing local account requires
logging in and linking explicitly (TM-ID-04).

## Outages and JWKS rotation

- **Discovery/JWKS outage:** flows fail soft (redirect back to `/login` with
  a retry message, never a 500). Fresh caches (24 h TTL) keep working; a
  stale cache is used as fallback when the fetch fails — transport errors
  *and* HTTP error responses without a JSON body (load-balancer maintenance
  pages, gateway errors) both count as failed fetches — so a brief IdP outage
  does not break sign-in for cached providers. A *successful* fetch of an
  invalid or wrong-issuer document still fails closed.
- **JWKS rotation:** an id_token with an unknown `kid` — or a signature
  failure against the cached key, which is what rotation looks like from an
  IdP that omits `kid` — triggers exactly one forced refresh from the pinned
  JWKS URL, then verification proceeds. No operator action needed. A fetch is
  never made to a URL that is not same-origin with the pinned issuer — even
  if a cached document says otherwise (TM-ID-03).
- **Secret rotation:** rotate the client secret at the IdP, then rotate the
  vault reference (`svcsec_*`) via the vault's rotate path with its grace
  window; the provider row itself does not change.

## Monitoring & known limits

- Health is recorded on explicit **Test connection** runs
  (`identity_providers.health_status` / `health_checked_at`), not passively.
- Provider create/enable/disable write `moderation_log` audit rows
  (`identity_provider_*` actions).
- Provider keys are immutable and builtin keys (`google`/`apple`/`github`)
  are reserved; builtin rows cannot be toggled from the console.
- Scopes are fixed at `openid profile email`; discovery URL is always derived
  from the issuer (custom `discovery_url` values are stored but not yet
  settable from the console).
- D11 budgets: `oidc.discovery_p95_cached` / `_cold` — MEASURED (PASS) in
  `docs/evidence/phase5/performance-budgets.md`.

## Acceptance evidence

See `docs/evidence/phase5/oidc-provider-registry.md` (tests, browser
evidence, budgets, threat fixtures TM-ID-01..04, migration reconciliation).
