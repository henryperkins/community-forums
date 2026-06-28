# ADR 0004: Phase 5 entry gate, carryover ledger, and Milestone-0 decision register

**Date:** 2026-06-28
**Status:** **Draft for product-owner approval.** This is the Milestone-0 entry
record required by `PHASE_5_PLAN.md` §2/§7. It does **not** itself approve
anything — it inventories what must be decided/accepted before Phase 5 *feature*
implementation may begin, and records the recommended defaults. The additive,
deploy-dark foundation schema (`database/migrations/0049`–`0053`, `SCHEMA.md` §5A)
is allowed to land ahead of these approvals **because it is inert** (no behavior,
all flags dark) and encodes none of the trust decisions below.

## Context

`PHASE_5_PLAN.md` is a release train for the public ecosystem, passkeys, generic
identity expansion, and database-backed least-privilege governance. Its §2 entry
gate forbids Phase 5 *implementation* until Phase 4 is closed/accepted and the
Milestone-0 trust boundaries are approved. Several of those gate items are
genuine product-owner decisions or pre-Phase-5 dependencies that cannot be
derived from code. This ADR makes them explicit so they are decided deliberately
rather than implied by code.

---

## Part A — Phase 4 entry status

| Entry-gate item (§2) | State | Note |
|---|---|---|
| Phase 4 Gate A/B product-owner acceptance recorded | **OPEN** | `PHASE_4_STATUS.md` records *engineering* closeout only; no product-owner acceptance is on file. `docs/evidence/phase4-gate-a.md` lists missing production a11y/SEO/load/backup/rollback artifacts. |
| Every incomplete Phase 4 item has an explicit deferral | **MET** | `docs/adr/0003-phase-4-closeout-deferrals.md` records owner/destination/risk for each. |
| Deployed DB reconciled against `SCHEMA.md` | **MET (for Phase 1–4)** | `SCHEMA.md` v1.14 reconciled migration `0048`; this increment adds §5A for `0049`–`0053`. |

**Recommendation:** record product-owner acceptance of the engineering-closed
Phase 4 Gate A slice **and** acceptance of the ADR 0003 deferrals as Phase 5
carryovers (Part B). Until that is on file, the §2 gate is not formally cleared.

---

## Part B — Phase 5 carryover ledger

Carried from `docs/adr/0003-phase-4-closeout-deferrals.md`. A carryover may block
Phase 5, be completed before Gate A, or be explicitly moved later — it must **not**
be silently renamed as new Phase 5 work (`PHASE_5_PLAN` §2 last bullet, decision #1).

| # | Carryover | From | Default destination | Blocks? |
|---|---|---|---|---|
| C1 | Custom badge rule engine / preview / backfill / revoke UI | ADR 0003 | Phase 5 content-polish (own scope record) | No |
| C2 | Reference cards + persisted `content_references` resolution | ADR 0003 | Phase 5 content-polish, or Phase 6 if automated | No |
| C3 | Moderator split/merge services + redirects | ADR 0003 | Phase 5 moderator-operations | No |
| C4 | Link previews/embeds, expanded attachments, polls, custom emoji, slash/GIF | ADR 0003 | Phase 5/6 by separate scoped adoption | No |
| C5 | Automated since-last-read context, scheduled related-topic refresh | ADR 0003 | Phase 6/7 knowledge automation | No |
| C6 | Avatar uploads, safe signatures, board folders, saved feed filters | ADR 0003 | Phase 5 profile/organization, unless Phase 7 absorbs | No |
| C7 | Production browser / a11y / load / SEO / backup-rollback evidence | ADR 0003 | Release-operations evidence before broad deploy | Gates broad rollout |

### Blocking pre-Phase-5 dependencies (new findings — not in ADR 0003)

These are **R0 conflicts** (`PHASE_5_PLAN` §11.1): Phase 5 workstreams name an
"accepted" earlier foundation that **does not exist in the codebase**. Per §4
("conditional carryovers") and decision #1, Phase 5 must not relabel this unfinished
earlier work as Phase 5 scope without an explicit decision.

| # | Conflict | Evidence | Impact | Recommended resolution |
|---|---|---|---|---|
| **B1** | **No TOTP / recovery-code subsystem exists.** | Repo audit: no TOTP/recovery-code/MFA second-factor subsystem in `src/`, `database/`, or `templates/` (the only `authenticator` reference is the unrelated WebAuthn AAGUID comment in migration `0051`). Auth today is password + OAuth only. | P5-11 passkeys list "**Accepted Phase 3 TOTP/recovery**" as a dependency; def-of-done requires passkey loss to fall back to "TOTP/recovery-code" and privileged MFA to be "passkey-**or-TOTP**." That fallback has nothing to fall back to. | Decide at M0: either (a) build the Phase-3-carryover TOTP/recovery-code subsystem **before** passkey enforcement, or (b) scope Gate A passkey recovery to email-verification + operator support only and record the TOTP dependency as deferred. Do **not** enforce privileged MFA until a real second factor exists. |
| **B2** | **No in-process plugin runtime, webhooks, or API tokens exist.** | Repo audit: no `plugins`/`webhooks`/`api_tokens` tables or runtime code. `docs/adr/0002` routed "internal hooks/plugins, webhooks, admin API tokens" to the "Phase 5 ecosystem foundation." | §2 entry gate requires "the Phase 3 first-party/vetted extension runtime is accepted." P5-04 remote apps map onto "**accepted webhooks/API scopes**" that were never built. The public ecosystem would be built on an absent foundation. | Decide at M0: treat the Phase-3 trusted hook/webhook/API-token runtime as **explicit Phase 5 Gate A prerequisite work** (own workstream + acceptance), not an assumed foundation. Sequence it before P5-04/P5-05. The `0049` package tables already model installs/permissions; the runtime + webhook/API-scope layer is still to build. |
| B3 | Encrypted secret service for provider/package secrets. | `identity_providers.client_secret_ref` and package secrets assume "the accepted encrypted secret service" (decision #35). Confirm it exists/with what guarantees. | Provider/OIDC config and remote-app credentials cannot store plaintext. | Confirm the secret-store seam at M1 before any provider/remote-app code writes a secret. |

---

## Part C — Milestone-0 decision register (PENDING OWNER APPROVAL)

Each row is a decision `PHASE_5_PLAN` §2/§7 requires before the relevant
workstream's flag may be enabled. None are encoded in the foundation schema.

| # | Decision (plan ref) | Recommended default | Status |
|---|---|---|---|
| D1 | **Package trust classes & types** (§5 #4) | Adopt the six classes already modelled in `packages.trust_class`: `first_party` & `vetted` (in-process, Phase 3 path), `reviewed_declarative` & `reviewed_remote` (no local code), `isolated_server` (Gate B sandbox), `local_dev` (explicit dev mode). No trust implied by installability. | PENDING |
| D2 | **Registry trust roots & signing-key custody** (§7, §8.2 #1) | One first-party registry to start. Offline Ed25519 signing root; private key held by the operator **out-of-band** (never in the app DB — only `registry_trust_keys.public_key`). Snapshot freshness/expiry default **24h**; key-rotation via signed transition; local blocklist (`local_package_blocks`) independent of registry availability. | PENDING |
| D3 | **Advisory authority & emergency-disable policy** (§7) | Advisories may `warn` → `block_new` → `force_disable` → `revoke`; force-disable requires the approved emergency policy; every action audited; a registry-independent local control always works (safe mode, decision #15). | PENDING |
| D4 | **Permission taxonomy, high-risk data classes, non-delegable list, consent vocabulary** (§7) | Draft the **core** capability catalogue from the existing route/permission matrix (engineering can produce a proposal). Non-delegable protected set = site ownership, trust-root management, break-glass recovery, signature override, audit integrity (decision #22). `capabilities` stays **empty** until this is approved; the resolver seeds it on approval. | PENDING |
| D5 | **Isolation profile & host support matrix; unsupported-host result** (§7) | Gate B. Dedicated OS identity + read-only package image + denied-by-default network + CPU/mem/wall-time/process/output/storage quotas via the host's process controls (cgroups/namespaces). Unsupported host → `server_extensions` stays unavailable; declarative themes + remote apps + core forum remain usable (decision #11). | PENDING (Gate B) |
| D6 | **Canonical HTTPS origin + WebAuthn RP ID; domain-change/DR policy** (§7, §5 #28) | RP ID = the registrable domain of the operator's canonical `APP_URL`; a single approved HTTPS origin. Domain/RP-ID change is a runbook migration, not a branding toggle. | PENDING |
| D7 | **Privileged-auth & passkey recovery policy** (§5 #31, depends on **B1**) | Gate A: passkeys **augment** password/OAuth; no global password removal; recovery via verified email + operator support (+ TOTP/recovery codes **once B1 is resolved**). Privileged MFA enforcement only after enrollment + recovery grace, and only once a real second factor exists. | PENDING |
| D8 | **Generic OIDC + first additional provider** (§7, §5 #32/#33) | Generic OIDC (discovery/JWKS, issuer-pinned, PKCE, nonce/state/audience validation) as the primary seam. First additional provider = a generic-OIDC configuration chosen by the owner (e.g. Microsoft Entra or GitLab) — **not** a provider-specific account-resolution fork. Email never a silent merge key. | PENDING |
| D9 | **Invitation defaults** (§5 #36) | Admin-created; single-use; 7-day expiry; optional email/domain binding; rate-limited; **no privileged-role grant via token**. Member-created invitations only under an approved registration policy. | PENDING |
| D10 | **Verified-link verification methods** (§5 #38) | DNS `TXT` challenge and HTTPS `/.well-known` challenge; SSRF-safe fetcher; recheck/expiry; "control verified," never endorsement. | PENDING (Gate B) |
| D11 | **Numeric budgets** (§11.3) | Engineering to draft starting p50/p95/p99 + size/staleness budgets for registry fetch/verify, install/update, resolver latency, WebAuthn ceremonies, OIDC/JWKS, invitation redemption, audit throughput, and per-row DB growth, measured on a production-like fixture. | PENDING |
| D12 | **Feature-flag set & staged-rollout order** (§13.1) | Flags landed dark this increment (`package_registry`, `package_themes`, `capabilities`, `passkeys`, `provider_registry`, `invitations`, + Gate B reserves). Enablement order per §13.1: schema/dark → resolver shadow → staff catalogue → themes → declarative/remote → role editor → resolver switch → passkeys → OIDC → invitations → (Gate A accept) → sandbox/publisher/governance. | **Flags MET; order PENDING** |

---

## Decision

1. The foundation schema (`0049`–`0053`) lands as additive, deploy-dark, inert
   tables (`SCHEMA.md` §5A). This is the only Phase 5 code permitted before the
   register above is approved, and it changes no behavior.
2. Phase 5 *feature* implementation for each workstream is blocked until its
   Part C decisions are approved and its Part B dependencies are resolved or
   explicitly deferred.
3. **B1 (no TOTP/recovery)** and **B2 (no plugin/webhook/API runtime)** are
   recorded as blocking conflicts: passkey recovery/privileged-MFA and the
   remote-app/ecosystem workstreams must not assume an accepted foundation that
   does not exist.

## Consequences

- Approving this ADR (with chosen defaults or overrides) clears the §2 entry gate
  and unlocks Milestone-2 onward per the staged order.
- Leaving it in draft keeps Phase 5 at "foundation only": the tables exist and are
  shape-tested, but no subsystem may be enabled.
- The capability catalogue is intentionally unseeded so the permission taxonomy is
  a deliberate, reviewed artifact rather than an accident of migration order.
