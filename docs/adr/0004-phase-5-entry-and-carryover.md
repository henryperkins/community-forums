# ADR 0004: Phase 5 entry gate, carryover ledger, and Milestone-0 decision register

**Date:** 2026-06-28
**Status:** **Accepted as the Phase 5 Milestone-0 decision record.** The
product-owner instruction dated 2026-06-28 approves the defaults below as locked
decisions for the Gate A + Gate B release train unless a later owner scope-change
record explicitly overrides an item. The additive, deploy-dark foundation schema
(`database/migrations/0049`–`0053`, `SCHEMA.md` §5A) landed inert; `0054` adds the
approved TOTP/recovery prerequisite before passkey enforcement.

**2026-06-29 reconciliation:** Phase 5 B2 trusted prerequisites have since
landed deploy-dark: service secrets (`0055`), read-only API tokens (`0056`),
webhook delivery (`0057`), and code-only first-party hook producers
(`first_party_hooks`). Public/untrusted PHP execution remains disabled until the
Gate B sandbox runtime exists and is adversarially verified.

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
| Phase 4 Gate A/B product-owner acceptance recorded | **MET** | Product-owner instruction accepts the Phase 4 engineering closeout as the entry baseline for Phase 5, with the evidence caveats in `docs/history/PHASE_1-4_HISTORY.md#phase-4-status` preserved. |
| Every incomplete Phase 4 item has an explicit deferral | **MET** | `docs/adr/0003-phase-4-closeout-deferrals.md` records owner/destination/risk for each and is accepted as the carryover ledger. |
| Deployed DB reconciled against `SCHEMA.md` | **MET (for Phase 1–4)** | `SCHEMA.md` v1.14 reconciled migration `0048`; this increment adds §5A for `0049`–`0053`. |

**Decision:** Phase 4 entry is accepted for Phase 5 with ADR 0003 deferrals kept
as explicit carryovers, not silently reclassified as shipped behavior.

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
| **B1** | **RESOLVED — TOTP / recovery-code subsystem exists.** | Migration `0054`, `MfaService`, `Totp`, account/login templates, and `AppMfaTest` implement opt-in TOTP, hash-only recovery codes, one-time login challenges, replay protection, rotation, disable, and audit. | P5-11 passkeys can now depend on a real fallback. Ordinary users are still not required to enroll by default. | Build passkey registration/enforcement only after this fallback remains green in full-suite and browser/no-JS evidence. |
| **B2** | **RESOLVED as trusted prerequisites; public plugin runtime still deferred.** | Service secrets (`0055`), read-only API tokens (`0056`), webhook delivery (`0057`), and code-only first-party hook producers (`first_party_hooks`) now exist behind dark flags with focused evidence. | P5-04/P5-05 no longer depend on absent token/webhook/hook primitives, but no public/untrusted PHP runtime exists. | Continue with Gate A declarative/remote work only after capability and registry evidence. Keep public untrusted PHP disabled until Gate B sandbox acceptance. |
| B3 | **Storage layer landed; provider/package consumers deferred.** | `SecretVault` provides service-secret references, rotation, revocation, prune, audit, and redaction; webhooks consume `svcsec_*` references. | Provider/OIDC config and remote-app credentials still need consumer wiring before they can store secrets. | Use `SecretVault` for later provider/remote-app consumers; never store plaintext service credentials. |

---

## Part C — Milestone-0 decision register (APPROVED)

Each row is a decision `PHASE_5_PLAN` §2/§7 requires before the relevant
workstream's flag may be enabled. None are encoded in the foundation schema.

| # | Decision (plan ref) | Recommended default | Status |
|---|---|---|---|
| D1 | **Package trust classes & types** (§5 #4) | Adopt the six classes already modelled in `packages.trust_class`: `first_party` & `vetted` (trusted foundation only), `reviewed_declarative` & `reviewed_remote` (no local code), `isolated_server` (Gate B sandbox), `local_dev` (explicit dev mode). No trust implied by installability. | APPROVED |
| D2 | **Registry trust roots & signing-key custody** (§7, §8.2 #1) | One first-party registry to start. Offline Ed25519 signing root; private key held by the operator **out-of-band** (never in the app DB — only `registry_trust_keys.public_key`). Snapshot freshness/expiry default **24h**; key-rotation via signed transition; local blocklist (`local_package_blocks`) independent of registry availability. | APPROVED |
| D3 | **Advisory authority & emergency-disable policy** (§7) | Advisories may `warn` → `block_new` → `force_disable` → `revoke`; force-disable requires the approved emergency policy; every action audited; a registry-independent local control always works (safe mode, decision #15). | APPROVED |
| D4 | **Permission taxonomy, high-risk data classes, non-delegable list, consent vocabulary** (§7) | Seed the core capability catalogue from the existing route/permission matrix. Non-delegable protected set = site ownership, trust-root management, break-glass recovery, signature override, audit integrity (decision #22). Namespaced extension capabilities are subordinate to approved core capabilities. | APPROVED |
| D5 | **Isolation profile & host support matrix; unsupported-host result** (§7) | Gate B. Dedicated OS identity + read-only package image + denied-by-default network + CPU/mem/wall-time/process/output/storage quotas via the host's process controls (cgroups/namespaces). Unsupported host → `server_extensions` stays unavailable; declarative themes + remote apps + core forum remain usable (decision #11). | APPROVED (Gate B) |
| D6 | **Canonical HTTPS origin + WebAuthn RP ID; domain-change/DR policy** (§7, §5 #28) | RP ID = the registrable domain of the operator's canonical `APP_URL`; a single approved HTTPS origin. Domain/RP-ID change is a runbook migration, not a branding toggle. | APPROVED |
| D7 | **Privileged-auth & passkey recovery policy** (§5 #31) | Gate A: passkeys **augment** password/OAuth; no global password removal; recovery via verified email + operator support + TOTP/recovery codes. Privileged MFA enforcement only after enrollment + recovery grace, and only once full fallback evidence is stable. | APPROVED |
| D8 | **Generic OIDC + first additional provider** (§7, §5 #32/#33) | Generic OIDC (discovery/JWKS, issuer-pinned, PKCE, nonce/state/audience validation) as the primary seam. First additional provider = one owner-approved generic-OIDC configuration — **not** a provider-specific account-resolution fork. Email never a silent merge key. | APPROVED |
| D9 | **Invitation defaults** (§5 #36) | Admin-created; single-use; 7-day expiry; optional email/domain binding; rate-limited; **no privileged-role grant via token**. Member-created invitations only under an approved registration policy. | APPROVED |
| D10 | **Verified-link verification methods** (§5 #38) | DNS `TXT` challenge and HTTPS `/.well-known` challenge; SSRF-safe fetcher; recheck/expiry; "control verified," never endorsement. | APPROVED (Gate B) |
| D11 | **Numeric budgets** (§11.3) | Starting budgets are approved as release gates, to be measured on production-like fixtures before enablement: registry snapshot freshness 24h; registry fetch p95 2s; signature verification p95 250ms/package; install/update p95 10s for declarative packages; resolver p95 5ms; WebAuthn/TOTP ceremony p95 2s server time excluding authenticator UX; OIDC discovery/JWKS p95 2s cached and 5s cold; invitation redemption p95 500ms; webhook delivery timeout 5s; sandbox execution wall-time default 2s; no high-impact audit write may be skipped silently. | APPROVED |
| D12 | **Feature-flag set & staged-rollout order** (§13.1) | Flags landed dark (`package_registry`, `package_themes`, `capabilities`, `passkeys`, `provider_registry`, `invitations`, + Gate B reserves). Enablement order: schema/dark → TOTP/recovery → trusted hook/webhook/API-token/secret foundation → resolver shadow → staff catalogue → themes → declarative/remote → role editor → resolver switch → passkeys → OIDC → invitations → Gate A accept → sandbox/publisher/governance/service principals/verified links → Gate B accept. | APPROVED |

---

## Decision

1. The foundation schema (`0049`–`0053`) lands as additive, deploy-dark, inert
   tables (`SCHEMA.md` §5A).
2. TOTP/recovery (`0054`) is approved and built before passkey enforcement.
3. **B2 (no trusted hook/webhook/API-token/secret layer)** is a Gate A
   prerequisite, not an assumed foundation. Remote-app/ecosystem workstreams must
   not proceed until it exists and has evidence.
4. Gate B work remains committed unless a later product-owner scope-change record
   explicitly defers an item with owner, rationale, risk, and destination.

## Consequences

- This ADR clears the Milestone-0 decision gate, but it does **not** accept Gate A
  or Gate B behavior by itself.
- Phase 5 is no longer "foundation only" after `0054`; it is still not Gate A
  accepted until the trusted-extension prerequisite and every Gate A workstream
  have release evidence.
- The capability catalogue is intentionally unseeded so the permission taxonomy is
  a deliberate, reviewed artifact rather than an accident of migration order.
