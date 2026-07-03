# Phase 5 Gate A — Completion Program Plan

> **For agentic workers:** This is a **program plan**, not a single-feature task plan. Gate A spans ~12 workstreams; each *increment* below is a release-train slice that becomes its **own** detailed, bite-sized TDD plan (via `superpowers:writing-plans`) when it is scheduled. Do not try to execute this document task-by-task — execute one increment's sub-plan at a time with `superpowers:subagent-driven-development`. Increments and sub-plans use checkbox (`- [ ]`) tracking once expanded.

**Goal:** Complete and accept Phase 5 **Gate A** — the signed package ecosystem, declarative themes, database-backed least-privilege roles, passkeys, generic-OIDC provider expansion, and invitations — every subsystem shipped deploy-dark behind its flag, enabled only under staged rollout with shadow/parity and rollback evidence, then formally accepted by the product owner.

This document sequences the work; it does **not** waive `PHASE_5_PLAN.md` §2 — the remaining §2 entry-gate artifacts were recorded and accepted via ADR 0012 (2026-07-01), so the Foundation increment is complete and this document now sequences the remaining increments (Inc 5, 6, 8, 9, 10).

**Architecture:** RetroBoards' Gate A is a *release train of dark increments*. The inert foundation schema (`0049`–`0053`), TOTP/recovery (`0054`), and the B2 remote-integration seam (`0055`–`0057`) are already landed; PR #27 added a dark `server_extensions` (Gate B) scaffold. Gate A turns inert shape into enforced, tested behavior **behind already-reserved flags that default `false`**, following the established write path (thin Controller → Service-owns-rules-in-`$db->transaction` → final single-table Repository), strict CSP, CSRF-on-every-POST, no-JS-first, additive-only forward-only migrations, and "inert schema is not evidence." The central structural decision in this plan: a **Foundation increment** lands the cross-cutting primitives that many workstreams assume (capability catalogue + `protected_owners` seed, signed-artifact test harness, `CORE_VERSION`, `DataClasses`, a unified reauth gate, telemetry/redaction) **before** the dependent workstreams, and a single migration-number allocation table kills the "everyone grabs 0066" collision.

**Tech Stack:** PHP 8.2+, MySQL/MariaDB (PDO, `EMULATE_PREPARES=false`), libsodium (ed25519 / AES-256-GCM via the existing `SecretBox`/`SecretVault`), the in-process kernel test harness (`Tests\Support\TestCase`), Playwright + axe (`tests/browser/`), `bin/console verify:upgrade`, `tests/backup/rehearse.sh`. No application framework; no new runtime dependencies beyond libsodium (already in use).

---

## Global Constraints

*Every task in every increment implicitly includes this section. Values are copied from CLAUDE.md, `PHASE_5_PLAN.md`, and `docs/adr/0004`.*

- **Deploy-dark.** Every Gate A subsystem ships behind its existing `FeatureFlags` flag, **default `false`**, route-gated (controller throws `NotFoundException` when off), with a regression in `tests/Integration/Core/AppFeatureFlagTest.php`. No Gate A slice leaves dark-by-default status until it reaches **R4/R5** in the ledger. Pre-acceptance enablement is limited to the staged `PHASE_5_PLAN` / ADR-0004 order (shadow, staff preview/browse, constrained cohort), and **broad/public/default-on enablement** still waits for formal Gate A acceptance (`§11.1`, `§13.1`). The Gate A flags: `package_registry`, `package_themes`, `capabilities`, `passkeys`, `provider_registry`, `invitations`, plus the already-landed-dark `service_secrets`, `api_tokens`, `webhooks`, `first_party_hooks`.
- **Migrations** are additive-only / forward-only; allocate the **exact number from §C** (never re-grab an already-allocated number — the F1 guard (`tests/Unit/Core/MigrationLedgerTest.php`) fails the local `composer test` suite if you do; there is **no PHPUnit CI**, so run it before landing a migration); `up()` additive, `down()` drops FKs before columns; hand-update `SCHEMA.md` (shape + §9 changelog + version bump, currently v1.32) per landed migration. Seeds use `INSERT IGNORE` (pattern `0040_seed_badges.php`).
- **Strict CSP** (`SecurityHeaders`): `script-src 'self'; style-src 'self'`, **no inline `<script>`/`<style>`**. All PE JS in `public/assets/*.js`; **every flow works server-rendered, no-JS first.** Theme/built CSS is served as an external app-origin stylesheet, never inline.
- **CSRF** `_token` on every POST; **no GET mutates state**; the only exemption stays the OAuth callback. New high-impact admin actions require **recent reauthentication** (WriteGate + password) via the unified gate from §B-F7.
- **Write path & counters.** Multi-table mutations run in `$db->transaction(fn)`. Every denormalized counter/pointer added (`invitations.used_count`, `packages.latest_release_id`, theme active/LKG pointer, `advisory_status`) gets a matching recompute in `RepairService` with identical WHERE clauses.
- **Authorization is three orthogonal axes** — global role, account **state** ("state beats role", `WriteGate`), per-board authority (pure `BoardPolicy`). The new capability resolver is **union-then-narrow** (no deny rules, no role inheritance, no policy code), narrowed by state and the board read gate. Reputation/badges/profile fields **never** become capabilities.
- **PDO `EMULATE_PREPARES=false`:** never bind `LIMIT`/`OFFSET` (cast int + concatenate after clamping); never reuse a named placeholder; UTC everywhere; IPs packed via `inet_pton`.
- **Evidence (DESIGN §13, PHASE_5_PLAN §10/§11).** "Inert schema is not evidence." UI-visible work needs Playwright desktop+mobile **+** axe, in addition to PHPUnit. Authorization completion requires **direct-request evidence against the real resolver** (hidden controls / simulator output are insufficient). Passkeys require **real supported-browser/authenticator** evidence + protocol-negative fixtures. The R0–R5 gate-pass rule is **not** a percent average: a single failed signature/owner/authorization/passkey/provider invariant fails the gate.
- **Public packages are non-critical** (decision #10): the package/registry engine is **never** a synchronous dependency of core login/read/post/moderation/recovery; the global safe-mode kill is **flag-independent** and loads no package code.

---

## Status snapshot — what is already landed (all dark unless explicitly noted)

| Area | State |
|---|---|
| P5-00 Milestone-0 decisions | ADR 0004 Parts A/B/C — **D1–D12 accepted**, B1/B2/B3 resolved |
| Foundation schema `0049`–`0053` | Inert base shape for registry/packages, capabilities, WebAuthn, provider registry, and invitations; `0066` later seeded the capability catalogue/role map/protected owners |
| `0054` TOTP/recovery | **Enabled** opt-in account-security behavior; passkey fallback prerequisite; browser/no-JS retrofit landed with Inc 7 |
| B2 seam `0055`–`0057` + hooks | service-secret vault, read-only `/api/v1` tokens, webhook delivery, first-party hooks — **all dark, R3** |
| `0065` server_extensions | Gate **B** scaffold (PR #27), dark — out of Gate A scope |
| Foundation F1–F11 | Complete: migration ledger, `CORE_VERSION`, capability catalogue seed, `DataClasses`, `LastOwnerGuard`, signing harness, reauth, telemetry/redaction, budget harness, evidence map, threat models |
| Increments landed | Inc 1 resolver shadow, Inc 2 registry protocol, Inc 3 package lifecycle/review gate, Inc 4 declarative themes, Inc 7 passkeys |
| Migrations on disk | through **`0072`** (`0071_content_reference_tags`, `0072_phase5_theme_packages`); next allocated number is **`0073`** for Inc 5 |

**The gap is now remaining behavior + acceptance evidence, not foundation shape.** Per `PHASE_5_STATUS` §11.1, registry/package/theme/resolver/passkey work has deploy-dark implementation evidence at R3/R4 as recorded in `docs/phase5/requirement-ledger.json`. Inc 5 integrations, Inc 6 enforcement cutover, Inc 8 providers, Inc 9 invitations, and Inc 10 acceptance remain; B2 slices still need the listed R4/R5 retrofits before their flags broadly flip.

---

## §A. Remaining entry prerequisites and later owner decisions

ADR 0004 locked the policy defaults (D1–D12), and ADR 0012 accepted the remaining `PHASE_5_PLAN.md` §2 **entry-gate artifacts** before Foundation execution. Later rows gate a specific increment or final acceptance rather than the start.

| # | Decision | Gate timing | Recommended default (from spec/ADR) | Status |
|---|---|---|---|---|
| A1 | **Approved capability taxonomy** — the concrete `core.*` catalogue, scope/risk classes, non-delegable set, and consent/risk vocabulary | **Entry gate** — must be recorded before Foundation / F3 execution; then governs P5-02, P5-04, P5-08/09, and P5-13 | Derive from the route/permission inventory; non-delegable = site ownership, trust-root mgmt, break-glass recovery, signature override, audit integrity (D4 / #22) | **Recorded + accepted** → `docs/phase5/capability-taxonomy.md` (54 keys; ADR 0012 accepted 2026-07-01). |
| A2 | **First additional OIDC provider** (concrete issuer/discovery config) | P5-12 first-provider acceptance; the generic-OIDC strategy itself is already approved in ADR 0004 D8 | One owner-approved generic-OIDC configuration (D8); email never a silent merge key | **Recorded** → `docs/phase5/first-oidc-provider.md` (GitLab.com; **accepted by the owner 2026-07-02**) |
| A3 | **Numeric budgets measured** on a production-like fixture (canonical hardware/OS-isolation/PHP+DB profile) | Each staged enablement + P5-16 consolidation | D11 targets are the gate values; measure per §11.3 record | **Baseline generated** → `docs/evidence/phase5/performance-budgets.md`; current owned metrics are measured/pass where implemented, with future metrics pending their increments. |
| A4 | **Offline registry signing-key custody + rotation cadence** (operator action; private key never in DB) | **Entry gate** — record the custody/ceremony before Foundation / P5-01 execution | Offline ed25519 root, operator-held, 24h snapshot freshness (D2) | **Recorded + accepted** → `docs/phase5/registry-signing-key-custody.md` (ADR 0012 accepted 2026-07-01). |
| A5 | **Canonical APP_URL/origin + WebAuthn RP ID** + domain-change/DR runbook | **Entry gate** — record the concrete origin / RP ID before Foundation / P5-11 execution | RP ID = registrable domain of canonical APP_URL (D6) | **Recorded + accepted** → `docs/phase5/canonical-origin-and-rp-id.md` (ADR 0012 accepted 2026-07-01). |
| A6 | **Member-created invitations?** + the "approved registration policy" | P5-13 member-invitation path only | Safe default: **admin-only**, single-use, 7-day, no privileged grant (D9) | Optional; default keeps it admin-only |
| A7 | **Privileged-MFA enforcement in Gate A?** which capabilities, what grace | Optional later policy only; Gate A ships enrollment + step-up without enforcement | Ship enrollment + step-up only; **enforcement deferred to Gate B** (§13.1 step 14) | Optional; default OFF |
| A8 | **§2 product-demand review** justifying opening a public ecosystem | **Entry gate** — record before any further Gate A implementation planning/execution | Owner judgment; not derivable from code | **Accepted** → ADR 0012 approved the declarative-first Gate A ecosystem on 2026-07-01. |

D5 (sandbox host matrix) and D10 (verified-link methods) are approved **Gate B** items and need no Gate A action.

> **Plan posture:** A1, A4, A5, and A8 are no longer blockers: ADR 0012 accepted the remaining `PHASE_5_PLAN.md` §2 entry-gate artifacts on 2026-07-01. A2 is also recorded for Inc 8 (GitLab.com, accepted 2026-07-02). A3 now has the Foundation F9 baseline report; future metrics move from pending to measured only in the increment that owns them. A6/A7 default to the safe (off/admin-only) setting unless the owner opts in.

---

## §B. Foundation increment (the linchpins) — complete as of 2026-07-01

*Why this exists:* the completeness critique found a cluster of primitives that P5-01…P5-16 each assume but none own. Landing them first removes the highest-severity completion risk (the capability/role authorization spine) and the migration-number collision. **All of these ship dark/inert and change no live behavior.** This increment is `Milestone 1.5` between the landed foundation and the workstreams.

| Sub-plan | Deliverable | Notes |
|---|---|---|
| **F1 — Migration-number ledger** | Adopt the **§C allocation table**; add a CI/test guard asserting migration filenames are gapless and unique. **Landed 2026-07-01** → `tests/Unit/Core/MigrationLedgerTest.php` + §C re-baselined for the `0067` owner-lifecycle index. | Kills the "ten briefs grab 0066" collision. |
| **F2 — `CORE_VERSION`** | `App::CORE_VERSION` constant + surface to the compatibility resolver; `CompatibilityResolverTest` boundary cases. **Landed 2026-07-01** -> `App::CORE_VERSION` (`0.5.0-dev`) + `src/Support/CoreVersion.php`. | Today only the cosmetic `brand_version` exists. Required by P5-01/02/03. |
| **F3 — Capability catalogue (seed + code)** | `src/Security/CapabilityCatalog.php` (code-owned `core.*` enumeration + non-delegable protected set) generated from `src/Service/CapabilityInventoryService.php` (a route/permission **golden matrix**); seed migration into the empty `0050` tables (catalogue + `role_capabilities` reproducing Guest/User/Mod/Admin). Coverage test: **every authorization call site maps to exactly one catalogued key**; every key has scope_type + risk_class + consent string. | **Owned here, not split** (resolves the P5-00↔P5-08 ownership gap). Landed before P5-02/04/09/13 validation merges. Gated dark by `capabilities`; consumes accepted A1. |
| **F4 — `DataClasses.php`** | `src/Security/DataClasses.php` approved local data-class catalogue + human consent vocabulary (mirrors `ApiScopes`/`WebhookEvents`). **Landed 2026-07-01** -> `src/Security/DataClasses.php` (10 classes; section 5 #8 high-risk set). | Required by P5-02 manifest validation, P5-04 consent, P5-08 risk_class. |
| **F5 — `protected_owners` seed + `LastOwnerGuard`** | Seed migration designating initial protected owner(s) from existing admins; shared `src/Security/LastOwnerGuard.php` for owner-loss paths. Foundation wired account lifecycle behind `capabilities`; Inc 7 wired passkey removal; role revoke/demote, sole-provider unlink, and invitations wire it in their owning increments. | `protected_owners` is seeded/reconciled; remaining owner-loss wiring is tracked by Inc 6/8/9 and GA-DOD-12. |
| **F6 — Signed-artifact test harness** | `tests/Support/` Ed25519 test-key tooling minting signed catalogue snapshots, signed releases, signed advisories, signed key-rotation transitions, plus tampered/expired/revoked variants; a **dev/test-only** trust-root + first-registry seed (production roots stay out-of-band). **Landed 2026-07-01** -> `tests/Support/Phase5/{SigningHarness,RegistryFixtures}.php` + `ext-sodium`; `rb-*.v1` signed-doc contract. | Consumed by P5-01/02/03/04/07-A/16. Without it the supply-chain evidence corpus can't be generated. |
| **F7 — Unified reauth / step-up gate** | `src/Security/ReauthGate.php` consolidating the scattered recent-reauth logic (currently in `AccountLifecycleService`, `MfaService`, `ApiTokenService`, `AccountService`, `WebhookService`); single window/factor policy; passkey becomes a factor in P5-11. **Landed 2026-07-01** -> `src/Security/ReauthGate.php`; five services consolidated, behavior-preserving. | Required by P5-02/04/07-A/09/11/12/13 (decision #26). |
| **F8 — Telemetry + redaction seam** | `src/Core/Telemetry.php` (config-gated correlation IDs) + `src/Support/LogRedactor.php`; `TelemetryRedactionTest` proving secrets/challenges/tokens/PII/private content never logged. **Landed 2026-07-01** -> `src/Core/Telemetry.php` + `src/Support/LogRedactor.php`; kernel `http.request` emit, dark by default. | Each workstream **emits** at build time; P5-16 only verifies (avoids retrofitting 10 subsystems). |
| **F9 — Fixture + baselines + budget harness** | `src/Service/Phase5FixtureSeeder.php` (representative role/assignment/provider/moderator corpus) + `BaselineMetricsService` + a read-only perf-budget runner writing the §11.3 measured-vs-D11 report to `docs/evidence/phase5/`. **Landed 2026-07-01** → Phase5FixtureSeeder + BaselineMetricsService + Phase5Budgets + verify:phase5-budgets; A3 baseline in docs/evidence/phase5/performance-budgets.md. | Produces A3. Reused by P5-08 parity + P5-16 perf. |
| **F10 — Ledger + evidence map + rollback map** | Machine-checkable R0–R5 requirement ledger; `Phase5EvidenceMapTest` asserting every Gate A DoD item links to evidence and every flag has a documented rollback path; extend `AppFeatureFlagTest` to prove **core survives all-flags-off**. **Landed 2026-07-01** -> `docs/phase5/requirement-ledger.json` + `Phase5EvidenceMapTest` + all-flags-off regression. | §11.1 gate-pass evaluability. |
| **F11 — Threat-model dossier** | Reviewed `docs/phase5/threat-models/*.md` for extension/supply-chain, identity/account-takeover, privilege/role-escalation, theme-phishing, secret-handling, invitation-privilege, each producing a negative-fixture stub consumed downstream. **Landed 2026-07-01** -> `docs/phase5/threat-models/` (6 dossiers + `fixtures.json`, 48 stubs), recorded pending owner review. | Downstream increments consume the negative-fixture stubs and advance specific TM IDs as their behavior lands. |

**Foundation exit gate:** the entry prerequisites A1/A4/A5/A8 were already recorded and linked before execution; catalogue + `protected_owners` seeded (dark); `CORE_VERSION`, `DataClasses`, `LastOwnerGuard`, `ReauthGate`, telemetry/redaction, signing harness, fixture, baseline+budget report, requirement ledger, threat-models all landed; full suite green; `verify:upgrade` passes through the new seeds; `AppFeatureFlagTest` still proves all Phase 5 flags dark -- **Exit gate met 2026-07-01** (pending F11 owner review sign-off).

---

## §C. Migration-number allocation (authoritative — do not deviate)

All additive on already-inert tables; relative numeric order is safe because each is independent. Reserved rows stay gapless: if an increment discovers no DDL is needed for its slot, it lands a schema-neutral decision-marker migration instead of skipping the number.

| # | Migration | Increment |
|---|---|---|
| `0066` | `phase5_seed_capabilities_owners` — catalogue + role_capabilities + protected_owners seed | **F (Foundation)** |
| `0067` | `owner_lifecycle_user_index` — `users` `idx_users_role_status_id (role, status, id)` for last-owner/admin `FOR UPDATE` guards | **F (Foundation F5 / owner-lifecycle)** — *landed 2026-07-01* |
| `0068` | `phase5_registry_snapshots` — snapshot cache + `moderation_log.target_type` widen | Inc 2 (P5-01) |
| `0069` | `phase5_package_lifecycle` — `installed_packages` pin/update_policy/staged/settings/export cols + ENUM widens | Inc 3 (P5-02) |
| `0070` | `phase5_publisher_review_security` — publisher_signing_keys, package_review_decisions, package_transparency_log | Inc 3 (P5-02 / P5-07-A enforcement pre-req) |
| `0071` | `content_reference_tags` — Phase 4/composer carryover widening for tag references | Landed outside the Phase 5 Inc 4 slot on this branch |
| `0072` | `phase5_theme_packages` — theme package build/assets/state | Inc 4 (P5-03) |
| `0073` | `phase5_package_integrations` — `installed_package_settings` + remote-app credential linkage | Inc 5 (P5-04) |
| `0074` | `phase5_assignment_lifecycle` — Inc 6 assignment lifecycle delta **or a schema-neutral decision marker if no DDL is needed** | Inc 6 (P5-09) |
| `0075` | `phase5_provider_identity_repoint` — backfill `provider_config_id` + uniqueness change to include config/issuer | Inc 8 (P5-12) |
| — | **P5-13 invitations: NO migration** (`0053` + `settings` suffice); conditional only for member quotas | Inc 9 |
| — | **P5-16: NO migration** (policy/evidence/telemetry/SEO are code) | Inc 10 |

> **Current re-baseline (2026-07-03).** The owner-lifecycle support index took
> `0067`, Inc 2/3 landed `0068`-`0070`, a merged Phase 4/composer carryover took
> `0071_content_reference_tags`, Inc 4 landed `0072_phase5_theme_packages`, and
> Inc 7 activated the existing `0051` WebAuthn tables without a new policy
> migration. Therefore Inc 5 owns the next gapless slot (`0073`), Inc 6 owns
> `0074`, and Inc 8 owns `0075`. If Inc 6 concludes that `0050` already has all
> required assignment columns, it must still land a schema-neutral `0074`
> decision-marker migration (plus `SCHEMA.md` changelog) before Inc 8 lands
> `0075`; otherwise the F1 gapless-unique guard
> (`tests/Unit/Core/MigrationLedgerTest.php`) fails the local `composer test` suite (no PHPUnit CI — run it before landing).

Each group must pass clean-install + populated-upgrade (`verify:upgrade`) + rollback-compatibility + backup/restore + feature-disabled tests before its behavior enables (`§8.3`).

---

## §D. The sequenced increments

Each increment is **build-now** but **enable-later** (dark until staged rollout). "→ Sub-plans" become individual TDD plans. Sizes: S/M/L/XL per workstream brief.

### Increment 1 — Capability resolver in **shadow** (P5-08) · flag `capabilities` · L
**Depends:** Foundation (F3 catalogue, F9 fixture). **Build now, enable-shadow earliest** — shadow changes no decisions but needs the longest soak to accrue a clean parity corpus before any enforcement (Inc 6) or invitations (Inc 9).
- **Scope:** state-first union-then-narrow resolver `can(actor, capability, target, time)` enforcing `ends_at` directly; legacy-authority read projection (derive system-role membership from `users.role`/`board_moderators`/`post_min_role`/`protected_owners`); **shadow-comparison harness** recording mismatches to a parity ledger **without changing the decision**; old-vs-new **parity corpus** archived on the same fixture+commit; permission **simulator** on the real resolver with safe target redaction; role create/edit/clone + `roles.version` bump + impact count; protected-role guard; no-JS role editor; resolver p50/p95/p99 vs budget.
- **Migration:** none (uses `0050`; catalogue seeded in F). **Exit gate:** zero parity mismatch for built-in roles on critical fixtures; subsystem dark by default. **Landed 2026-07-02** → resolver+projection+shadow+parity corpus (zero mismatch)+role editor+simulator, dark behind `capabilities`; sub-plan `docs/superpowers/plans/2026-07-02-phase5-increment1-resolver-shadow.md`.
- **→ Sub-plans:** SP1 catalogue+role maps (in F) · SP2 resolver + legacy projection · SP3 shadow harness + parity corpus · SP4 simulator + redaction · SP5 role editor (no-JS) + audit + perf.

### Increment 2 — Registry protocol & package identity (P5-01) · flag `package_registry` · L
**Depends:** Foundation (F2 CORE_VERSION, F6 signing harness). **Parallel with Inc 1.** Enable = staff-only catalogue browse, install OFF.
- **Scope:** thin repos over `0049`; canonical `(source_id + package_uid)` identity; **pure** trust-chain + ed25519 detached-signature + sha256 digest verifier (constant-time, fail-closed; **public-key-only** — never read/write private root in DB); key rotation/revocation via signed transition; signed+expiring snapshots with **anti-replay** + offline cache; source-pinning / dependency-confusion refusal; compatibility resolution; advisory ingest/evaluate (warn→block_new→force_disable→revoke); **registry-independent local blocklist**; refresh worker `worker:registry-refresh` (EgressGuard-guarded fetch); staff-only read-only catalogue browse (no install).
- **Migration:** `0068` (snapshot cache + `moderation_log.target_type` widen). **Exit gate:** signature/tamper/replay/staleness/key-rotation/dependency-confusion fixtures pass; staff browse renders; install absent. **Landed 2026-07-02** -> trust-chain verifier + snapshot ingest (anti-replay, source pinning) + rotation/revocation + advisory ladder + local blocklist + `worker:registry-refresh` + staff read-only browse, dark behind `package_registry`; sub-plan `docs/superpowers/plans/2026-07-02-phase5-increment2-registry-protocol.md`.
- **→ Sub-plans:** SP1 repos+identity · SP2 trust-chain/signature/snapshot verifier · SP3 compatibility + source-pinning · SP4 refresh worker + offline cache (`0068`) · SP5 advisory + blocklist · SP6 staff catalogue UI.

### Increment 3 — Package manifest, install & lifecycle (P5-02) + review-enforcement seam (P5-07-A part 1 / schema pre-req) · flag `package_registry` · XL
**Depends:** Inc 2; Foundation (F3 catalogue for "reject invalid capability names", F4 DataClasses, F7 reauth). Enable = small cohort, auto-update OFF, manual pin/rollback.
- **Scope:** `manifest.v2` schema + **fail-closed validation**; Gate-A type allowlist (theme/automation/remote_app/local — **reject `server_extension`**); compatibility check; **install plan/preview** (resolve exact digest without executing); atomic install with full provenance + permission snapshot (`granted=0` until consent); enable/disable; pin/unpin; **update + permission diff + re-consent** (staged, old grant retained, reduction immediate); rollback to a verified digest; uninstall + export + retention; digest/tamper **health worker** `worker:packages` → quarantine; immutable history; local emergency-disable enforcement; no-JS admin surface. **Co-developed:** the `PackageSecurityGate` + `package_review_decisions` enforcement primitive (P5-07-A) so install **fails closed** on revoked/unapproved-digest/blocked — resolving the P5-02↔P5-07-A reverse dependency.
- **Migration:** `0069` (pin/update_policy/staged/settings/export cols + ENUM widens); `0070` review/security tables land here as the schema/enforcement pre-req for Increment 5's operator console and response flows. **Exit gate:** `§9` Manifest-validation, Install-rollback, Permission-increase/reduction, Review-digest, Tampered-local-files scenarios pass. **Landed 2026-07-02** → `manifest.v2` fail-closed validation + Gate-A type allowlist (reject `server_extension`) + install plan/preview + atomic install/consent + enable/disable + pin/unpin + update permission-diff/re-consent + rollback + uninstall/export + `PackageSecurityGate`/`package_review_decisions` enforcement + `worker:packages` health/quarantine, dark behind `package_registry`; sub-plan `docs/superpowers/plans/2026-07-02-phase5-increment3-package-lifecycle.md`.
- **→ Sub-plans:** SP1 manifest+compat+type policy (pure) · SP2 read-only catalogue · SP3 install+consent+enable/disable + `PackageSecurityGate` · SP4 update+diff+pin (`0069`) · SP5 rollback+uninstall/export + health worker + Repair reconcile.

### Increment 4 — Declarative theme packages (P5-03) · flag `package_themes` · L
**Depends:** Inc 2 (parallel with Inc 3). Enable = staff preview → reviewed themes; safe mode retained.
- **Scope:** theme data model (tokens, token-schema version, asset digests, validation, preview/build state, active/default + **last-known-good** pointer, safe-mode bypass); approved-**token-whitelist** validation (no selectors/JS/PHP/@import/remote/data:); local-asset scan via `AttachmentService` sniff/re-encode + digest pin (zero outbound at page load); **deterministic build** (content digest = cache key); **external-stylesheet** serving (CSP-safe, no inline); WCAG contrast/a11y validation (critical failures hard-block); **per-admin isolated preview** (never mutates global default); transactional activation invariant; **flag-independent safe mode** rendering the built-in baseline; one-action LKG rollback; theme-phishing structural guard.
- **Migration:** `0072`. **Exit gate:** Theme-safety/accessibility/phishing fixtures pass; preview isolation proven; safe mode works against a broken theme; `package_themes`-off serves the system theme. **Landed 2026-07-02** → theme data model + approved-token-whitelist validator + local-asset scan/digest-pin + deterministic build + CSP-safe external-stylesheet serving + WCAG contrast hard-block + per-admin isolated preview + flag-independent safe mode + one-action LKG rollback, dark behind `package_themes`; sub-plan `docs/superpowers/plans/2026-07-02-phase5-increment4-declarative-themes.md`.
- **→ Sub-plans:** SP1 schema+repo · SP2 policy+validator+asset-scanner (pure) · SP3 deterministic build + CSP-safe serving · SP4 lifecycle UI (preview/activate/safe-mode/rollback) + audit + browser/a11y. **Resolved in Inc 4:** `theme_state` is the single-row active/LKG pointer (`SCHEMA.md` §5A); cascade precedence is `/assets/app.css` baseline → active/preview package theme stylesheet → operator `/brand.css` overrides (`docs/phase5/registry-protocol.md` theme contract); the built-in system theme remains the safe-mode/default fallback.

### Increment 5 — Public declarative/remote integrations (P5-04) + security-response console (P5-07-A part 2) · flag `package_registry` · L
**Depends:** Inc 3 for deploy-dark implementation. The **B2 seam must itself reach Gate A release evidence/acceptance for the surfaces consumed here** (`api_tokens`, `webhooks`, `service_secrets`, `first_party_hooks` R4/R5) **before Inc 5 can advance GA-DOD-08/09 to R4/R5 or enable beyond dark/local test use**. Those B2 retrofits may be scheduled as Inc 5 Task 0 or run in parallel with deploy-dark Inc 5 implementation, but they are not optional. Enable = re-consent on expansion; `service_secrets` is a hard runtime predecessor / kill switch for secret-backed credential minting.
- **Scope:** install of `automation`/`remote_app` types on the P5-02 engine; manifest scope mapping (declared API scopes→`ApiScopes` read-only, events→`WebhookEvents`, data classes→`DataClasses`); permission+data consent committed with the grant; **remote-app install-scoped credentials** minted via `ApiTokenService`/`WebhookService` (never reuse the human token — `Human-token separation`); scope isolation at the real gate; secret storage via `SecretVault` (redacted); settings-schema form; disable/uninstall/export; registry-outage fail-closed. **P5-07-A console:** publisher verify/suspend/revoke + signing-key lifecycle; signed publication bound to exact digest; signed revocation/yank; advisory ingest/escalate on the landed `worker:registry-refresh` (fetch/reconcile) + `worker:packages` (enforce) — **no new `worker:advisories`** (Inc 5 design §12 Open-Decision #3); append-only **transparency log**; **global flag-independent emergency disable / safe mode**; `RepairService` advisory_status reconcile.
- **Migration:** `0073` (package settings + credential linkage); `0070` already landed in Inc 3. **Exit gate:** Remote-app-scope, Human-token-separation, Secret-redaction, Revoked-release, Global-emergency-disable, Publisher-compromise scenarios pass.
- **→ Sub-plans:** SP0 B2 release-evidence retrofits for consumed seams (API-token authorization/a11y, webhook SSRF+egress/idempotency, service-secret redaction/revoke coverage, first-party private-content absence proof) · P5-04 SP1 manifest profile + scope/data mapping · SP2 install+consent · SP3 remote-app credential provisioning · SP4 settings schema · SP5 disable/uninstall/export + emergency disable. P5-07-A SP1 publisher/key lifecycle · SP2 signed publication + exact-digest + transparency · SP3 advisory ingest/escalate folded into `worker:registry-refresh`/`worker:packages` (no new worker) · SP4 emergency console.

### Increment 6 — Scoped assignments + role editor + resolver **enforcement cutover** (P5-09 + P5-08 part 2) · flag `capabilities` · L
**Depends:** Inc 1. **Enable ONLY after shadow parity is zero**, with the compatibility-snapshot rollback wired **first**. **Entry also requires resolving capability-taxonomy §7 #5**: the pending-view divergence was recorded during the 2026-07-02 Inc 1 review, but the owner decision is still pending at the Inc 6 cutover (either state-exempt the `ApprovalController`/`MediaController` view routes to preserve legacy behavior, or accept the safer tightening as an approved behavior change). §7 #6 confirms content-state closes stay service-owned at cutover.
- **Scope:** writeable `role_assignments` with site/category/board scope, `starts_at`/`ends_at`, renew/revoke; **pure `GrantorAuthority`** (same-or-broader scope + delegable + must-hold); resolver scope-narrowing + version-keyed cache; **legacy migration** (`users.role` + `board_moderators` → scoped assignments, **non-broadening**, ambiguous held non-enforcing) + `boards.post_min_role` → board-scoped post capability (**enum kept as rollback source**, decision #41); `LastOwnerGuard` (F5) on revoke/demote; impact preview; no-JS assignment UI; the **enforcement switch** (staged: staff/test scopes → broader, only after zero mismatch; new grants go INACTIVE on fallback, never approximated as Admin).
- **Migration:** `0074` reserved. If `0050` is sufficient, land a schema-neutral `0074` decision-marker migration so the ledger stays gapless before Inc 8's `0075`; otherwise use `0074` for the additive assignment lifecycle delta. **Exit gate:** Built-in-role parity, Custom-role scope, Non-delegable, Grantor-authority, Temporary-grant, Last-owner scenarios pass via **direct requests on the real resolver**; rollback to compatibility snapshot rehearsed.
- **→ Sub-plans:** SP1 assignment repo+service · SP2 resolver narrowing + expiry + cache · SP3 protected-owner guard + reauth + fallback · SP4 legacy migration + post-gate translation + parity · SP5 assignment UI + impact preview + browser.

### Increment 7 — Passkeys (P5-11) · flag `passkeys` · XL
**Depends:** Foundation (F5 LastOwnerGuard, F7 reauth) + landed TOTP/recovery. **Parallel-capable** once Inc 1 is soaking. **Gate-A boundary: enrollment + step-up only; NO privileged-MFA enforcement** (A7 default off).
- **Scope:** `src/Security/WebAuthn/*` protocol core (CBOR/COSE/clientData/authenticatorData/attestation parsers, RP-ID/UP/UV/origin/challenge checks, ES256 mandatory); enrollment + credential management; sign-in (email-first challenge, `allowCredentials`) integrated with `AuthController`/`completeMfa` + OAuth step-up; recovery on the TOTP/recovery path; `LastOwnerGuard` final-method block; synced-counter as risk-signal (not auto-ban); CDP **virtual-authenticator** browser evidence; RP-ID/domain-change rehearsal; extend `AccountLifecycleService` export/delete for passkey metadata.
- **Migration:** none. Inc 7 activates the existing `0051` WebAuthn tables; privileged-MFA enforcement/usernameless policy remains Gate B or a later explicit scope change. **Exit gate:** real supported-browser ceremony + protocol-negative fixtures (replay/cross-account/wrong-origin/stale/altered-sig); no-JS fallback-to-TOTP journey. **Landed 2026-07-02** → `src/Security/WebAuthn/*` protocol core (CBOR/COSE/attestation parsers, RP-ID/UP/UV/origin/challenge checks, ES256) + enrollment/credential management + sign-in step-up (`AuthController`/`completeMfa` + OAuth) + TOTP-path recovery + `LastOwnerGuard` final-method block + CDP virtual-authenticator evidence, dark behind `passkeys`; sub-plan `docs/superpowers/plans/2026-07-02-phase5-increment7-passkeys.md`.
- **→ Sub-plans:** SP1 WebAuthn protocol core (pure) · SP2 enrollment + management · SP3 sign-in + step-up · SP4 recovery + final-owner + (off-by-default) privileged-MFA policy + staff pilot.

### Increment 8 — Provider registry & generic OIDC (P5-12) · flag `provider_registry` · L
**Depends:** Foundation; needs A2 (named provider) + `service_secrets` enabled for client-secret storage. **Parallel-capable.**
- **Scope:** provider-registry CRUD + admin console; **generic OIDC** verification library (`OidcDiscovery` + `JwksCache` issuer-pinned + `JwtVerifier` iss/aud/azp/nonce/iat/exp/nbf + alg allowlist + `ClaimMapper`); registry-driven provider resolution + live callback (test mode); client-secret storage/rotation via `SecretVault`; **identity reconciliation** repointing google/apple/github to `provider_config_id` (no duplicate/orphan/ban-bypass) + **uniqueness change** to include provider config/issuer + stable subject; provider disable/fallback; extend `AccountLifecycleService` for provider identities; stub-OIDC-issuer fixture (shared with P5-16).
- **Migration:** `0075` (backfill + unique-key swap on populated `oauth_identities` — pre-validate collisions, reversible while dark). **Exit gate:** issuer-mixup/JWKS-rotation/nonce/state/PKCE/audience tests; migration-identity reconciliation; the named additional provider works end-to-end.
- **→ Sub-plans:** SP-A registry data layer + admin CRUD · SP-B OIDC verification core (pure) · SP-C registry-driven resolution + callback + test/health · SP-D identity repoint migration + first provider.

### Increment 9 — Invitations (P5-13) · flag `invitations` · L
**Depends:** Inc 6 (role model stable — an invitation must not bind to a role whose meaning shifts mid-rollout) + accepted registration controls. Enable = admin-created first; member path only if A6 opts in.
- **Scope:** invitation lifecycle (create/revoke/redeem, hash-only tokens, **guarded consume UPDATE** for replay/concurrency safety, email/domain binding, expiry/use limits); admin surface (show-once); public redemption + **atomic** registration integration; optional onboarding board membership + **non-privileged** role grant (gated through `capabilities`, privilege-injection denied); **invitation email via the Mailer seam** (fail-closed; show-once link as fallback) + delivery state; `worker:invitations` expiry sweep; `LastOwnerGuard`/export coverage for own-created invitations; rate-limited + audited.
- **Migration:** none (conditional only for member quotas). **Exit gate:** token-entropy/hash/replay/concurrency, expired/revoked/domain-mismatch, privilege-injection-denial, no-JS flow.
- **→ Sub-plans:** SP1 core lifecycle + repos · SP2 admin surface · SP3 public redemption + registration · SP4 onboarding grants · SP5 member invitations (optional) · SP6 expiry worker.

### Increment 10 — Gate A hardening, staged release & acceptance (P5-16) · the gate · XL
**Depends:** all increments. **No flag flip of its own** — it is the acceptance gate; §13.1 step 12 makes Gate A acceptance the **hard barrier before any Gate B** item enables.
- **Scope (mostly *verification*, since each increment carries its own evidence per §B-F8/F10 and the distributed discipline below):** consolidate the **authorization direct-request matrix** + archived resolver-parity report; **adversarial supply-chain + auth-hardening** corpus under a new `tests/Security/`; **per-surface noindex** + `X-Robots-Tag` (each increment also asserts its own); verify telemetry redaction + correlation IDs; **performance budgets** measured on the F9 fixture (`docs/evidence/phase5/`); **operator runbooks** `docs/PHASE_5_RUNBOOK.md` (the ~22 §11.5 procedures) + rehearsal extending `verify:upgrade`/`rehearse.sh`; backup/restore reconciliation (revoked secrets **not** restored); the requirement→evidence index; `docs/adr/0015-phase-5-gate-a-acceptance.md`; full Phase 1–4 regression green with all public packages disabled; **product-owner sign-off**, then staged flag flips per §13.1.
- **→ Sub-plans:** SP-A SEO/noindex hardening · SP-B telemetry+redaction (in F8; verified here) · SP-C authorization matrix + parity harness · SP-D adversarial corpus · SP-E global safe-mode + flag-off regression · SP-F perf-budget harness · SP-G runbooks + evidence index + acceptance ADR.

---

## §E. Build order vs rollout order

**Build (parallelizable):** Foundation → then **Inc 1 (resolver shadow) and Inc 2 (registry) concurrently** (both depend only on Foundation — this parallelism is exactly how §7's "registry first" and §13.1's "resolver-shadow first" reconcile). Inc 3/4 follow Inc 2; Inc 5 implementation follows Inc 3, while its B2 release-evidence retrofits are scheduled as SP0/parallel prerequisites before Inc 5 can claim R4/R5 or enable; Inc 6 follows Inc 1; Inc 7/8 run in parallel once Foundation is in; Inc 9 follows Inc 6; Inc 10 last.

**Enablement (staged rollout, §13.1):** resolver **shadow** flips first (longest soak, no decision change) → registry staff browse → theme staff preview → cohort package install (auto-update off) → declarative/remote (after B2 + `service_secrets` on) → **resolver enforcement cutover only at zero parity, rollback wired first** → passkey staged enrollment (no privileged-MFA) → generic OIDC test→migrate→cohort → admin invitations → **Gate A acceptance** (hard barrier before Gate B).

**Hard sequencing rules:** (1) `service_secrets` enabled **before** `provider_registry`/remote apps. (2) B2 consumed surfaces reach R4/R5 before Inc 5 R4/enablement, even if Inc 5 code lands deploy-dark first. (3) Resolver enforcement (Inc 6) and invitations (Inc 9) are the **last** role-coupled flips, only after clean parity + accepted enforcement. (4) Compatibility-snapshot rollback exists and is tested **before** the enforcement switch. (5) Privileged-MFA enforcement does **not** ship in Gate A.

---

## §F. Gate A acceptance evidence — a *distributed* discipline (not a P5-16 funnel)

Per the critique, §11.1 forbids percent-average passes, so evidence is produced **per increment at build time**; Increment 10 only consolidates/verifies. Every increment must ship:

- **PHPUnit** unit (pure policy/crypto/validation) + integration (migrations + transactional write paths + flag-dark 404) via the in-process kernel.
- **Authorization** direct-request tests on the real resolver for its routes (guest/user/suspended/banned/mod/admin/custom-role/expired-grant/out-of-scope/protected-owner).
- **Browser** Playwright desktop+mobile **no-JS** journey + **axe** a11y for every UI-visible surface; **coordinate screenshot filenames/indices** (package/theme evidence now occupies 32–42; use new unique slots or feature-prefixed names for P5-04/P5-13, as P5-11/TOTP already do); ensure `APP_KEY` in the browser-evidence CI job.
- **Per-surface noindex** assertion; **telemetry** correlation-ID + redaction emitted; **perf budget** on its hot path (F9 fixture); its **migration** through `verify:upgrade` + `rehearse.sh`; its **runbook** entry.
- Its requirement linked in the evidence index, advancing its ledger state to R4/R5.

The **already-landed B2 dark slices** (`service_secrets`, `api_tokens`, `webhooks`, `first_party_hooks`) are **R3** and need this same package retrofitted before their flags flip or Inc 5 claims R4/R5 — notably api_tokens authorization-matrix (read-only denial) + a11y, webhooks formal SSRF/egress adversarial review + idempotency report, service-secret redaction/revoke coverage, and the `first_party_hooks` private-content-absence proof. TOTP/recovery is no longer in this bucket: its browser/no-JS retrofit landed with Inc 7 and the ledger records it at R4.

---

## §G. Dependency graph & critical path

```
Foundation (F: catalogue, owners, CORE_VERSION, DataClasses, harness, reauth, telemetry, fixture, ledger, threat-models)
   ├── Inc 1  P5-08 resolver SHADOW ──────────────┐
   │            └── Inc 6  P5-09 + enforcement cutover ──┐
   │                          └── Inc 9  P5-13 invitations
   ├── Inc 2  P5-01 registry ── Inc 3 P5-02 install (+P5-07-A gate) ── Inc 5 P5-04 + P5-07-A console
   │                          └── Inc 4 P5-03 themes
   ├── B2 R4/R5 retrofits (service_secrets/api_tokens/webhooks/first_party_hooks) ─► Inc 5
   ├── Inc 7  P5-11 passkeys (parallel)
   └── Inc 8  P5-12 OIDC providers (parallel)
                                  └────────── all ──────────► Inc 10 P5-16 hardening + ACCEPT ─► (Gate B unlocked)
```

**Critical path:** Gate A now has two independent acceptance-blocking branches. The role branch is Foundation → Inc 1 (shadow soak) → Inc 6 (enforcement at zero parity, with rollback rehearsed) → Inc 9 → Inc 10. The package branch is Inc 3 + B2 R4/R5 retrofits → Inc 5 → Inc 10. Foundation, Inc 1–4, and Inc 7 have landed; Inc 6 is the role-coupled long pole, while B2/Inc 5 is a separate Gate A blocker if the retrofit evidence lags.

## §H. Top risks (full per-workstream risk registers live in the briefs)

1. **Authorization spine cutover** — Foundation F3 seeded the catalogue and Inc 1 shadow is clean, but Inc 6 enforcement still risks privilege drift unless zero-parity soak + rollback snapshot are preserved. *Highest.*
2. **Owner-recovery wiring** — `protected_owners` and `LastOwnerGuard` exist; remaining role/provider/invitation owner-loss paths must call the guard in Inc 6/8/9.
3. **Supply-chain crypto** — verifier bugs are direct compromise; constant-time, fail-closed, public-key-only, real (not mocked) fixtures via F6.
4. **Enforcement cutover** — flipping before clean parity risks privilege drift; rollback snapshot wired first, new grants go INACTIVE on fallback.
5. **Migration collision** — ten briefs grabbed `0066`; resolved by §C + the F1 gapless-unique guard.
6. **Evidence big-bang** — distributed per §F; Inc 10 verifies, not produces.
7. **B2 still R3** — Inc 5 code may land deploy-dark, but Inc 5 cannot claim R4/R5 or enable until accepted webhooks/api_tokens/service_secrets/first-party-hook evidence reaches R4/R5.

## §I. Self-review vs spec

- **§4 Gate A DoD bullets** → P5-00..P5-13 + Foundation; the §4 "automated evidence" half of publisher review is explicitly scoped (manifest validation + SBOM display + supply-chain corpus) with the operator-facing scan pipeline deferred to Gate B via an ADR (§A note / Inc 5).
- **§9 acceptance scenarios** → mapped to increment exit gates (each increment lists its scenarios).
- **§8 data/migration** → §C allocation + F-seeds + G3/G4 backfills, all additive, legacy sources retained as rollback per decision #41/§8.4.
- **§10/§11 evidence** → §F distributed discipline + Inc 10 consolidation; R0–R5 ledger in F10.
- **§13 rollout** → §E enablement order with shadow/parity/rollback gates.
- **Gate B correctly excluded:** P5-05/06 sandbox runtime, P5-10 governance, P5-14 service principals, P5-15 verified links, restricted stylesheet modules, usernameless passkeys, privileged-MFA enforcement.

---

## Execution handoff

This program plan is saved to `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md`. Gate A is far too large for one session; it is executed **one increment at a time**, each expanded into its own bite-sized TDD plan.

**Current state:** the §2 entry-gate artifacts are accepted, Foundation is complete, and Increments 1, 2, 3, 4, and 7 have landed deploy-dark. The remaining executable Gate A work is Inc 5, Inc 6, Inc 8, Inc 9, and Inc 10.

**Recommended next step:** write and execute the detailed TDD sub-plan for **Increment 5** (P5-04 integrations + P5-07-A security-response console) using `superpowers:writing-plans`, with an explicit SP0 for the B2 release-evidence retrofits consumed by Inc 5. Inc 5 implementation can land deploy-dark after Inc 3, but no Inc 5 R4/R5 claim or staged enablement happens until the B2 SP0 evidence is green. In parallel, keep the Inc 6 resolver-enforcement soak/rollback evidence and the pending-view owner decision ready, because Inc 6 remains the role-coupled gate before invitations.
