# Phase 5 Increment 5 — Closeout-Readiness Audit + Follow-ups

**Date:** 2026-07-03
**Scope:** P5-04 integration runtime + P5-07-A security-response console, deploy-dark
behind `package_registry`, plus the four B2 predecessor slices
(`service_secrets`, `api_tokens`, `webhooks`, `first_party_hooks`).
**Question:** Is Inc 5 *done-with-evidence* per DESIGN §13, or are there gaps?
**Method:** 35-agent adversarial audit workflow (`wf_b6ab25a4-b42`) — contract
extraction, on-disk evidence inventory, six per-surface DESIGN §13 audits, an
empirical full-suite run, a security-invariant sweep, and a process/evidence
sweep; every candidate gap was then independently re-verified by a skeptic that
defaulted to refuting it (24 candidates → **14 confirmed, 10 refuted**).

## Verdict

**Closeout-ready.** The suite is green, all seven security invariants hold, the
machine-checked requirement ledger passes, and every deferral is recorded in an
ADR/ledger. The audit found **0 blockers**; the single content-boundary gap
(anonymous authorship leaking to package webhooks) plus its moderation-event
corollary and the actionable minors have been **fixed on this branch** under TDD.

Note on gate state: the Inc 5 ledger rows sit at R3–R4 by design (deploy-dark,
staged-enablement pending) and do **not** yet meet the §14 Gate-A pass bar
(R4/R5) — that is expected and unchanged by this work.

## Empirical verification (audit baseline)

| Check | Result |
|---|---|
| Full suite `vendor/bin/phpunit` | **1559 tests / 7948 assertions**, zero failures (status doc claimed 1558/7946 — a +1/+2 superset) |
| Closeout guards (EvidenceMap + ThreatModelIndex + MigrationLedger) | 12 tests / 53 assertions, green |
| `AppFeatureFlagTest` (every Inc5 route 404s while dark) | 28 tests / 263 assertions, green |
| Security invariants | **7 / 7 PASS** — incl. PR#36 `bool`/`getString` kill-switch footgun **fixed** (canonical `'1'`/`'0'`, all readers `==='1'`), CSRF on every POST, no inline script/style in 11 Inc5 admin templates, EgressGuard on every outbound path + IP-pinned, secrets sha256/AES-GCM at rest, flag-dark 404, reveal paths admin+reauth-gated |
| Process/evidence | 6 PASS, 1 CONCERN (verify:upgrade for migration 0073 unrecorded — now closed, see below) |

## Per-surface DESIGN §13 matrix

| Surface | Flag | Behavior | Tests | Browser | Readiness (audit) |
|---|---|---|---|---|---|
| Integration runtime panel | `package_registry` | pass | pass | pass | ready-with-caveats |
| Security-response console | `package_registry` | pass | pass | pass | ready-with-caveats |
| SecretVault | `service_secrets` | pass | pass | n/a | ready-with-caveats |
| API tokens + `/api/v1` | `api_tokens` | pass | pass | pass | ready |
| Outbound webhooks | `webhooks` | pass | pass | pass | ready |
| First-party hooks | `first_party_hooks` | partial→**fixed** | partial→**fixed** | n/a | not-ready→**ready** |

## Confirmed gaps and disposition

| # | Sev | Gap | Disposition |
|---|---|---|---|
| 1 | major | Anonymous public-board posts shipped the real `author_id` to package webhooks (all producers, unmasked; untested; no ADR) | **FIXED** — `WebhookEvents::maskAnonymousAuthor()` + 8 emit sites (topic/reply/edit/delete + approval, thread.solved, mod-delete); nulls author id, stamps `is_anonymous`, nulls a self-edit/self-delete actor id. Unit test (3 cases) + 6 integration tests. |
| 2 | minor | `provisionCredentials` 500s when `service_secrets` on but `api_tokens` off | **FIXED** — service guards api_tokens before the txn (fail-closed 422); symmetric `overview()` refusal banner; same guard on `rotateCredential`. |
| 3 | minor | Settings 422 dropped the operator's just-typed non-secret edits | **FIXED** — `saveSettings` wires `ValidationException::$old`; `detailView` overlays typed values over DB-loaded ones (secrets never repopulated). |
| 4 | minor | `rotate()` dark-fail enforced but untested | **FIXED** — `test_rotate_is_blocked_when_flag_dark` sentinel added. |
| 5 | minor | `moderation.auto_action` emitted for held/flagged content on hidden/private boards | **FIXED** — `AntiAbuseService::audit()` gains a `?boardVisibility` gate; emits only for `public`; 4 call sites pass visibility. |
| 6 | minor | No recorded `verify:upgrade` rehearsal covering migration 0073 | **FIXED** — rehearsal run: **17/17 checks passed**, migration list includes `0073_phase5_package_integrations` (63 migrations, data preserved). |
| 13 | nit | No test proved `moderation.auto_action` suppression on non-public boards | **FIXED** — `test_moderation_auto_action_is_suppressed_on_non_public_boards` (asserts audit row written but zero delivery). |
| 11 | nit | `prune()`/`usableSecrets()` dark-path untested | **FIXED** — two dark-path sentinels added. |
| 14 | nit | CLAUDE.md drift (missing `worker:webhooks`; stale next-migration number) | **FIXED** — worker list + next-number (`0074`) corrected. |
| 7 | nit | `suspendDelivery`/`exportSettings` skip WriteGate (suspended admin can pause/export) | **Deferred** — capability-reducing/read-only, deploy-dark; follow-up. |
| 8 | nit | `isExecutionDisabled()` predicate triplicated across 3 readers | **Deferred** — all correct today; DRY consolidation follow-up. |
| 9 | nit | AES-256-GCM uses no AAD binding | **Deferred** — authenticated encryption met; AAD is defense-in-depth hardening. |
| 10 | nit | `cipher`/`key_version` columns persisted but never read on decrypt | **Deferred** — inert crypto-agility scaffolding; no key-rotation path yet. |
| 12 | nit | Egress adversarial corpus omits obfuscated-literal IPs / real resolver | **Deferred** — delivery-time classify + curl IP-pinning still block them; add cases in a follow-up. |

Ten further candidate findings were **refuted** on verification (e.g. `/api/v1/*`
unknown-path HTML-404, `last_used_at` write-on-read, webhook `setActive/delete/replay`
intentionally skipping `assertEnabled`, transparency-log append-only being
"convention only"). One true fact worth keeping: the dark package browser specs
(`package-security`, `package-review`) are excluded from the CI `browser-evidence.yml`
job by design and run manually via `npm run evidence:integrations`.

## Fixes landed on `inc5-closeout-followups`

- `src/Security/WebhookEvents.php` — `maskAnonymousAuthor()` helper.
- `src/Service/PostingService.php`, `SolvedAnswerService.php`, `ModerationService.php` — anonymity masking at all producers; audit-visibility gate at posting call sites.
- `src/Service/AntiAbuseService.php` — `audit()` public-visibility gate for `moderation.auto_action`.
- `src/Service/Packages/PackageIntegrationService.php` + `src/Controller/AdminPackageIntegrationController.php` — api_tokens fail-closed 422 + settings anti-draft-loss.
- `CLAUDE.md` — worker list + next-migration-number drift.
- Tests: `WebhookEventsTest`, `FirstPartyHookPrivateContentTest`, `DomainWebhookProducerTest`, `PackageIntegrationServiceTest`, `AppPackageIntegrationTest`, `SecretVaultTest`.

## Post-fix verification

- `verify:upgrade --force` (scratch e2e DB, through 0073): **17/17 checks passed**.
- Full suite after fixes: `RB_TEST_FRESH=1 vendor/bin/phpunit` → **1573 tests / 8002 assertions**, zero failures (1559 baseline + 14 new regression tests).
