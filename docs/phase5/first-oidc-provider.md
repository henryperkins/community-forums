# A2 — First Additional OIDC Provider (P5-12 / Inc 8 acceptance target)

**Status:** **Accepted 2026-07-02** by the product owner (direct in-session instruction).
**Owner:** Henry Perkins (product owner).
**Satisfies:** `PHASE_5_PLAN.md` §2 entry-gate artifact A2 ("at least one additional
approved provider configuration or package selected"); gates **P5-12 first-provider
acceptance** only — the generic-OIDC strategy itself is already approved (ADR 0004 D8).

## Decision

The first additional identity provider is **GitLab.com**, integrated as a pure
**generic-OIDC configuration** (no provider-specific code), exercising the full
P5-12 verification path: `OidcDiscovery` → issuer-pinned `JwksCache` →
`JwtVerifier` (iss/aud/azp/nonce/iat/exp/nbf, alg allowlist) → `ClaimMapper`.

## Why GitLab.com

- **Strict OIDC conformance with a single stable issuer** — the cleanest possible
  first target for an issuer-pinned verifier: real discovery document, published
  JWKS, RS256, verified-email claim.
- **Zero-cost, low-friction operations** — free application registration under an
  ordinary GitLab account; trivially repeatable for rehearsals and staged rollout.
- **Same demographic as the existing GitHub sign-in** (developer/maker
  communities), so it is a plausible member-facing option, not just a test rig.
- Its **private-email setting** naturally exercises the no-email/unverified-email
  flow (§9 "Provider collision"), which the first provider should prove.

## Why not the alternatives (recorded for the decision trail)

| Candidate | Reason not first |
|---|---|
| **GitHub** | **Ineligible**: no user-login OIDC (plain OAuth 2.0 — no ID token, no discovery document, no user JWKS; its OIDC issuer serves Actions workload federation only). Also already a builtin provider (`0052`); Inc 8 covers it via the identity **migration** path (`0075`), not A2. |
| Microsoft account | Big reach, but strict issuer pinning requires the consumers-tenant issuer (`login.microsoftonline.com/9188040d-…/v2.0`); the `common` endpoint breaks D8 pinning, and optional/unverified email stresses collision rules — better once the seam is proven. |
| Discord | Community-relevant; has a discovery document but minimal claims and known quirks — a good **second** provider. |
| Self-hosted IdP (Keycloak/Authentik) | Purest conformance and the seam's flagship use-case, but acceptance evidence would depend on standing infrastructure; better exercised by the P5-12 stub-issuer fixture + operator docs. |

## Concrete configuration (the acceptance target)

| Item | Value |
|---|---|
| Issuer (pinned) | `https://gitlab.com` |
| Discovery | `https://gitlab.com/.well-known/openid-configuration` |
| JWKS | resolved **only** via the pinned issuer's discovery document (never hardcoded, never followed cross-origin) |
| Algorithms | allowlist `RS256` |
| Scopes | `openid profile email` |
| Stable subject | `sub` (GitLab numeric user id, treated as an opaque string) — identity key is `(provider_config, sub)` per decision #32 |
| Claim mapping | `email` + `email_verified` (contact/collision signal only — **never a merge key**), `name`, `preferred_username`, `picture` (optional profile fields) |
| Client | confidential; registered at GitLab → User Settings → Applications; redirect URI `{APP_URL}/auth/gitlab/callback` (the existing `^/auth/[^/]+/callback$` CSRF exemption + `{provider}` route already cover the slug) |
| Client secret | stored write-only via `SecretVault` (`svcsec_*` reference), rotatable (decision #35); `service_secrets` must be enabled before `provider_registry` (§E hard sequencing rule 1) |

## Constraints carried into Inc 8 acceptance

- Email is never a silent merge key; collisions require explicit proof-to-link (decision #32; §9 "Provider collision").
- Issuer mix-up / wrong audience / invalid nonce/state/PKCE / stale time claims / untrusted JWKS negative tests run against this configuration (§9 "OIDC issuer mix-up").
- JWKS rotation recovers through issuer-pinned refresh only (§9 "OIDC key rotation").
- Provider disable retains identities and surfaces sole-login-method accounts first (§9 "Provider disable"; decision #40).
- Existing google/apple/github identities migrate to `provider_config_id` unchanged (§9 "Provider migration") — this artifact adds a provider; it relabels nothing.

## Sign-off

- [x] **Product owner acceptance — accepted 2026-07-02** (owner's direct
  in-session instruction). GitLab.com is the named A2 provider; the program-plan
  §A row and `PHASE_5_STATUS.md` flipped to **Recorded** in the same commit as
  this acceptance. The alternatives table above stands as the decision trail.
