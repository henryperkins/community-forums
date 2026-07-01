# Evidence — Phase 5 Foundation F3 + F5 (authorization spine, deploy-dark)

**Increment:** Phase 5 Gate A · Foundation **F3** (code-owned capability catalogue +
coverage) and **F5** (protected-owner seed + shared `LastOwnerGuard`).
**Date:** 2026-07-01 · **Branch:** `main` · **Flag:** `capabilities` (default `false`).
**Plan:** `docs/superpowers/plans/2026-07-01-phase5-foundation-f3-f5.md`.
**Authoritative source (A1):** `docs/phase5/capability-taxonomy.md` (ADR 0012).

This is a **dark, no-UI** increment. It ships the authorization *policy data* and the
owner-invariant *seam* behind the `capabilities` flag; nothing resolves against it until
the resolver (P5-08, Increment 1) lands. Per DESIGN §13 "inert schema is not evidence",
F3/F5 ship **enforcing tests**, not just seeds. Because no member- or operator-facing
surface is introduced, there is **no Playwright/axe evidence** for this increment — all
evidence is PHPUnit + `bin/console verify:upgrade` + `bin/console repair`.

## Source of truth ⟺ code

`src/Security/CapabilityCatalog.php` transcribes the A1 taxonomy §4 verbatim: 54 `core.*`
keys, the 5-key non-delegable protected set (§4.5 / decision #22), and the cumulative
`system.guest/user/moderator/admin` role increments (§6). Where code and taxonomy drift,
the coverage/invariant tests fail and the taxonomy wins.

## Test files and what each proves

| Test | Proves |
|---|---|
| `tests/Unit/Security/CapabilityCatalogTest.php` | Catalogue has exactly **54** keys; every key is `core.*` with a valid scope/risk; the `risk='protected' ⇔ is_protected ⇔ ¬delegable` invariant; every non-protected key has a non-empty consent string and protected keys have none; role maps are cumulative with counts **1 / 15 / 28 / 49** and never map a protected key. (5 tests, 534 assertions) |
| `tests/Integration/Core/CapabilityInventoryCoverageTest.php` | Every non-protected catalogued key has ≥1 authoritative call-site anchor in the golden matrix; the matrix references no uncatalogued/protected key; exclusions use only the recorded §8 reason codes — the catalogue⟺matrix parity that enforces A1. (3 tests) |
| `tests/Integration/Core/AppPhase5CapabilitySeedTest.php` | Migration `0066` seeds all 54 rows matching `CapabilityCatalog` (scope/risk/delegable/protected); `role_capabilities` reproduces the cumulative 1/15/28/49; no protected capability is role-mapped; seeding does **not** enable the `capabilities` flag. (4 tests) |
| `tests/Integration/Repository/ProtectedOwnerRepositoryTest.php` | `ProtectedOwnerRepository` designate/query/exclude-count behavior + `INSERT IGNORE` idempotency on the unique `user_id`; **and that owner activeness derives from `users.status='active'`, not the write-once `is_active` flag** — a deactivated co-owner stops counting as recoverable. (5 tests) |
| `tests/Integration/Service/RepairProtectedOwnersTest.php` | `RepairService::repairProtectedOwners()` designates the earliest active admin when admins exist but no owner is designated; is idempotent once an owner exists; is a no-op with no active admin; `repairAll()` includes a `protected_owners` key; **and it reconciles when the only owner row is a deactivated account** (the stale row does not mask a lost invariant). (6 tests) |
| `tests/Integration/Core/AppProtectedOwnerTest.php` | `LastOwnerGuard` parity fallback (blocks last admin when owner set empty; allows when another admin exists) and owner-set logic (blocks sole active owner even when another *admin* exists; allows when a second owner exists); wired through the account-lifecycle path — **capabilities dark → legacy behavior unchanged (422 from the legacy check)**, **capabilities on → `LastOwnerGuard` enforces the owner invariant** on deactivate (422 when sole owner; 3xx once a second owner is designated); **and the deactivated-co-owner regression** — with a stale owner row present the guard still blocks the last recoverable owner (both guard-direct and via the 422 lifecycle path). (10 tests) |

Stale-assertion reconciliation: `tests/Integration/Core/AppPhase5FoundationSchemaTest.php`
`test_capability_catalogue_is_not_seeded` (asserted `capabilities = 0`) was renamed to
`test_capability_catalogue_is_seeded_by_0066` and now asserts `= 54`, since `0066` seeds
the catalogue by design. `test_system_roles_seeded_as_protected_anchors` is unaffected —
`0066` does not touch the `roles` table.

## Full suite

`./vendor/bin/phpunit` → **857 tests / 4456 assertions, green** (+28 over the 829
post-`topic_workflow` baseline; the last +4 are the owner-status regression tests below),
verified on **two consecutive plain runs** — both the fresh-`migrate:fresh` bootstrap path
*and* the reused-schema path that `composer test` takes when the migration fingerprint is
unchanged.
`AppFeatureFlagTest::test_phase5_foundation_flags_default_dark` still proves `capabilities`
(and every Phase 5 flag) dark.

> Reused-schema fix: `AppSearchTest::resetDatabase()` `TRUNCATE`s every table outside a
> preserve allowlist, and TRUNCATE auto-commits — so a wiped table leaks an empty seed into
> every later test and into the reused test DB. The allowlist predated `0066`, so it
> truncated the `capabilities` / `role_capabilities` reference rows and turned the suite red
> on any non-`RB_TEST_FRESH` run (`capabilities` seen as 0, not 54). Fixed by adding both
> tables to the allowlist (commit `203b91d`), matching the same protection the code already
> applied to `badges`/`roles`/`identity_providers`/`provider_aliases`.

## Populated-upgrade rehearsal (authoritative owner-backfill proof)

The PHPUnit bootstrap migrates an **empty** `users` table, so the `0066` owner backfill is
a no-op in-suite. `verify:upgrade` seeds a populated Phase-1 DB (incl. `legacy_admin`,
role=admin/active) and applies every migration through `0066`:

```
DB_DATABASE=<scratch> php bin/console verify:upgrade --force   → PASS ✓ (17/17 checks)
  … 0066_phase5_seed_capabilities_owners applied; 90 Phase-1 columns intact; zero data loss
```

Post-run queries against the scratch DB (F3/F5-specific, beyond the rehearsal's built-in checks):

```
capabilities                       = 54
role_capabilities by role          = system.guest 1 · system.user 15 · system.moderator 28 · system.admin 49
protected_owners (is_active=1)     = 1   (user_id=1 legacy_admin, admin/active, designated_by=NULL)
protected caps role-mapped         = 0
```

> Environment note: the plan names a dedicated `retroboards_upgrade_verify` scratch DB. In
> this environment the `retro` DB user only holds grants on `retroboards`,
> `retroboards_test`, and `retroboards_e2e`, so the rehearsal was run against the throwaway
> `retroboards_e2e` DB (a drop-all-tables target the user fully controls) — same guarantee,
> different scratch name.

## Repair convergence

```
DB_DATABASE=retroboards_test php bin/console repair   (with one active admin present)
  pass 1 → repaired protected_owners (1 rows)
  pass 2 → repaired protected_owners (0 rows)   # idempotent
```

`protected_owners` surfaces via `repairAll()` (the main `repair`/`repair:all` command
auto-prints every returned key); it is intentionally **not** added to `repair:counters`,
which is a denormalized-counter path — the owner invariant is structural, not a counter.

## `LastOwnerGuard` wiring status (deferrals are explicit, not silent)

Wired now (behind `capabilities`): account-lifecycle **deactivate / delete-request**.
Documented future hooks (public API present, one-line `assertNotLastOwner($user, $field)`
at each mutation site when its subsystem lands): **role revoke/demote** (Increment 6),
**passkey removal** (Increment 7), **sole-provider unlink** (Increment 8, alongside
`OAuthService::unlink`'s existing login-method guard), **invitations** (Increment 9).

## Adversarial review (post-implementation)

After the nine plan tasks landed, the full ~1,000-line F3/F5 diff was put through a
three-lens adversarial review (guard live-path · migration/repair durability · catalogue
invariant-test strength). The catalogue enforcement tests were mutation-tested and **bite**
(adding a key without the matrix, mis-tagging a protected key, dropping/duplicating a role
mapping, or emptying a consent string each turn the suite red); the migration is idempotent
with WHERE-parity across seed/repair/guard, UTC-clean, and free of `EMULATE_PREPARES`
hazards.

One **substantive, reachable** defect surfaced and was fixed (commit `35dcd39`):
`ProtectedOwnerRepository` judged owner activeness from the write-once
`protected_owners.is_active` flag alone, which no path clears. Since `users.status` gained
`deactivated/pending_deletion/deleted` states (migration `0059`), a designated owner who
later deactivated left a stale row that still counted as a live owner — so with a third
active admin satisfying the legacy last-admin check, the account-lifecycle path returned
**303 (allowed) instead of 422** when the last *recoverable* owner deactivated, violating
decision #27, and `repairProtectedOwners()` shared the blind spot. Fixed by deriving owner
activeness from `users.status='active'` (JOIN `users`) in all three read methods and the
repair EXISTS check, mirroring the legacy `activeAdminCountExcluding` predicate; four TDD
regression tests (red→green) now pin it. Two lower-severity items were reviewed and
consciously **not** changed: `0066` `down()` over-deletes `designated_by IS NULL` owners
(self-capped — migrations are forward-only and `migrate:rollback` is greenfield-only, where
`0050`'s `down()` drops the table wholesale), and the coverage test cross-checks the matrix
but not the role map (the unit `CapabilityCatalogTest` covers role↔catalogue parity, so
`composer test` enforces it in aggregate).

## Schema doc

`SCHEMA.md` → **v1.25**: §5A `capabilities(...)` note updated (seeded by `0066` from
`CapabilityCatalog`; role map + owner backfill); §9 changelog entry added; header status
bumped v1.24 → v1.25.
