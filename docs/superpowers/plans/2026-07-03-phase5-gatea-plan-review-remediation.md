# Phase 5 Gate A Program-Plan Review Remediation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply the nine documentation-consistency fixes surfaced by the 2026-07-03 adversarial review of `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md`, so the program plan, ADR 0012, and the requirement ledger agree with each other and with the repo.

**Architecture:** These are **documentation edits only** — no code, no runtime behavior, no schema. Three files change: the Gate A program plan (7 fixes), `docs/adr/0012-phase-5-gate-a-entry-gate-artifacts.md` (1 fix, shared with the program plan), and `docs/phase5/requirement-ledger.json` (1 fix). Every fix reconciles stale/inconsistent status text against a verified repo fact; none alters any increment's scope, decisions, or sequencing. Each task ends with a `grep`/guard verification and a commit.

**Tech Stack:** Markdown + JSON docs. Verification via `grep`, `ls`, `php -r` (JSON validity), and the two repo guards that consume these docs: `tests/Unit/Core/MigrationLedgerTest.php` and the Phase 5 evidence-map test (`vendor/bin/phpunit --filter Phase5EvidenceMap`).

## Global Constraints

*Every task implicitly includes this section.*

- **Docs only.** No `src/`, no `database/migrations/`, no `templates/`, no behavior change. If a fix seems to require a code change, stop — the finding was mis-scoped.
- **Do not create the acceptance ADR.** ADR `0015-phase-5-gate-a-acceptance.md` is Increment 10's future deliverable. Task 2 only corrects the *forward-reference number*, it does **not** create the ADR file.
- **Do not alter increment scope/decisions.** Only reconcile status/consistency text. The "Landed" summaries added in Task 1 must describe what actually shipped (mirroring the Inc 1/Inc 2 markers already in the plan), not add new commitments.
- **Preserve the plan's own discipline.** ADR numbers are allocated the same gapless-unique way as migrations — always confirm the next-free number at execution time (`ls docs/adr/`) rather than trusting a hard-coded value.
- **`requirement-ledger.json` is machine-checked.** It is consumed by the Phase 5 evidence-map test; keep the Task 7 edit to the single free-text `notes` field, then re-run that test. Note the file is **already modified** in the working tree — do not revert unrelated in-flight changes.
- **Verify then commit.** Each task shows the exact `old → new` string, a `grep` that proves the stale text is gone (and the new text present), and a commit. Frequent commits, one per task.
- **Anchor line numbers loosely.** Line numbers below are as-of the 2026-07-03 working tree; match on the quoted string, not the line number, since earlier tasks shift later lines.

**Finding → Task map (all 9 review findings covered):**

| Review finding | Severity | Task |
|---|---|---|
| 1. Inc 3/4/7 §D bodies lack a "Landed" marker | minor | Task 1 |
| 2. Stale intro line 7 ("must be recorded before Foundation…") | minor | Task 1 |
| 3. Acceptance-ADR number `0013` already taken → `0015` (plan + ADR 0012) | minor | Task 2 |
| 4. Inc 5 `worker:advisories` three-way divergence | minor | Task 3 |
| 5. "fails CI" overstates a non-existent PHPUnit CI | minor | Task 4 |
| 7. §I flat-excludes P5-10 with no P5-10-A carve-out for GA-DOD-12 | minor | Task 5 |
| 8. §C has no Inc 7 row | nit | Task 6 |
| 9. "G3/G4 backfills" references undefined labels | nit | Task 6 |
| 6. Ledger F5 note still lists `passkey-removal` pending | minor | Task 7 |
| (all) final consistency sweep | — | Task 8 |

---

## Task 1: Program-plan landed-status consistency (Findings 1 + 2)

Make the plan's front matter and the Inc 3/4/7 §D bodies reflect the "landed" reality that the status snapshot, §G, §C, and the handoff already assert. All three increments landed **2026-07-02** and each has an existing sub-plan doc to cite.

**Files:**
- Modify: `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` (intro line ~7; Inc 3 line ~144; Inc 4 line ~150; Inc 7 line ~168)

**Interfaces:**
- Consumes: existing sub-plan docs `docs/superpowers/plans/2026-07-02-phase5-increment{3,4,7}-*.md` (confirmed present) and the Inc 1/Inc 2 "Landed" marker style already in the plan (lines ~132/~138).
- Produces: a plan where all five landed increments (1,2,3,4,7) carry an explicit dated `**Landed …** → … ; sub-plan …` marker, and no front-matter sentence implies Foundation is unstarted.

- [ ] **Step 1: Confirm the current stale state (test-before)**

Run:
```bash
cd /home/henry/community-forums
grep -n "must be recorded before the Foundation increment becomes an executable implementation plan" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md
grep -c "Landed 2026-07-02" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md   # expect 2 (Inc 1 + Inc 2 only)
```
Expected: the intro sentence is present; the count is `2`.

- [ ] **Step 2: Fix the stale intro sentence (Finding 2)**

Replace (line ~7):
```
This document sequences the work; it does **not** waive `PHASE_5_PLAN.md` §2. The remaining entry-gate artifacts called out in §A must be recorded before the Foundation increment becomes an executable implementation plan.
```
with:
```
This document sequences the work; it does **not** waive `PHASE_5_PLAN.md` §2 — the remaining §2 entry-gate artifacts were recorded and accepted via ADR 0012 (2026-07-01), so the Foundation increment is complete and this document now sequences the remaining increments (Inc 5, 6, 8, 9, 10).
```

- [ ] **Step 3: Add the Inc 3 "Landed" marker (Finding 1)**

Append to the end of the Inc 3 `**Migration:** … **Exit gate:** …` bullet (the line ending `… Tampered-local-files scenarios pass.`):
```
 **Landed 2026-07-02** → `manifest.v2` fail-closed validation + Gate-A type allowlist (reject `server_extension`) + install plan/preview + atomic install/consent + enable/disable + pin/unpin + update permission-diff/re-consent + rollback + uninstall/export + `PackageSecurityGate`/`package_review_decisions` enforcement + `worker:packages` health/quarantine, dark behind `package_registry`; sub-plan `docs/superpowers/plans/2026-07-02-phase5-increment3-package-lifecycle.md`.
```

- [ ] **Step 4: Add the Inc 4 "Landed" marker (Finding 1)**

Append to the end of the Inc 4 `**Migration:** `0072`. **Exit gate:** …` bullet (the line ending `… serves the system theme.`):
```
 **Landed 2026-07-02** → theme data model + approved-token-whitelist validator + local-asset scan/digest-pin + deterministic build + CSP-safe external-stylesheet serving + WCAG contrast hard-block + per-admin isolated preview + flag-independent safe mode + one-action LKG rollback, dark behind `package_themes`; sub-plan `docs/superpowers/plans/2026-07-02-phase5-increment4-declarative-themes.md`.
```

- [ ] **Step 5: Add the Inc 7 "Landed" marker (Finding 1)**

Append to the end of the Inc 7 `**Migration:** none. … **Exit gate:** …` bullet (the line ending `… no-JS fallback-to-TOTP journey.`):
```
 **Landed 2026-07-02** → `src/Security/WebAuthn/*` protocol core (CBOR/COSE/attestation parsers, RP-ID/UP/UV/origin/challenge checks, ES256) + enrollment/credential management + sign-in step-up (`AuthController`/`completeMfa` + OAuth) + TOTP-path recovery + `LastOwnerGuard` final-method block + CDP virtual-authenticator evidence, dark behind `passkeys`; sub-plan `docs/superpowers/plans/2026-07-02-phase5-increment7-passkeys.md`.
```

- [ ] **Step 6: Verify the fixes (test-after)**

Run:
```bash
grep -c "Landed 2026-07-02" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md   # expect 5 now
grep -c "must be recorded before the Foundation increment" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md   # expect 0
```
Expected: count is `5`; stale-sentence count is `0`.

- [ ] **Step 7: Commit**

```bash
git add docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md
git commit -m "docs(phase5): reconcile Gate A plan landed-status (Inc 3/4/7 markers + intro)"
```

---

## Task 2: Renumber the acceptance-ADR reference 0013 → 0015 (Finding 3)

ADR `0013` (wysiwyg-composer) and `0014` (member-notifications) already exist, so the reserved `0013` acceptance-ADR slot is stale in **two** files: the program plan (Inc 10 deliverable) and ADR 0012 itself (which reserves `0013`). Point both at the next free number. **Do not create the ADR file** — it is Inc 10 future work.

**Files:**
- Modify: `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` (Inc 10 scope, line ~185)
- Modify: `docs/adr/0012-phase-5-gate-a-entry-gate-artifacts.md` (lines ~10, ~13, ~105, ~116)

**Interfaces:**
- Consumes: the live ADR directory listing (next-free number).
- Produces: every acceptance-ADR reference reads `0015`, with a one-clause note that `0013/0014` were consumed.

- [ ] **Step 1: Confirm the next-free ADR number (test-before)**

Run:
```bash
cd /home/henry/community-forums
ls docs/adr/ | grep -E '^00(1[0-9])' | sort        # highest existing must be 0014
grep -rn "0013-phase-5-gate-a-acceptance" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md
grep -n "0013" docs/adr/0012-phase-5-gate-a-entry-gate-artifacts.md
```
Expected: highest existing ADR is `0014` (so `0015` is next-free); the program plan references `0013-phase-5-gate-a-acceptance`; ADR 0012 mentions `0013` at ~4 places. **If `0015` already exists**, use the true next-free number everywhere below.

- [ ] **Step 2: Fix the program-plan Inc 10 reference**

In the Inc 10 scope bullet, replace:
```
docs/adr/0013-phase-5-gate-a-acceptance.md
```
with:
```
docs/adr/0015-phase-5-gate-a-acceptance.md
```

- [ ] **Step 3: Fix the ADR 0012 acceptance-ADR references**

In `docs/adr/0012-phase-5-gate-a-entry-gate-artifacts.md`:
- Replace both occurrences of `**ADR 0013**, Increment 10` with `**ADR 0015**, Increment 10`.
- Replace `renumbered **`0013`**.` with `renumbered **`0015`** (ADR `0013`/`0014` were subsequently consumed by the wysiwyg-composer and member-notifications ADRs).`
- Replace `The Gate A program plan's acceptance-ADR reference moves `0012 → 0013`.` with `The Gate A program plan's acceptance-ADR reference moves `0012 → 0015` (`0013`/`0014` since consumed).`

- [ ] **Step 4: Verify (test-after)**

Run:
```bash
grep -rn "0013-phase-5-gate-a-acceptance" docs/          # expect 0 hits
grep -c "0015" docs/adr/0012-phase-5-gate-a-entry-gate-artifacts.md   # expect >= 3
grep -rn "ADR 0013\b" docs/adr/0012-phase-5-gate-a-entry-gate-artifacts.md   # expect 0 hits referring to acceptance
ls docs/adr/0015-phase-5-gate-a-acceptance.md 2>/dev/null && echo "ERROR: ADR file was created — remove it" || echo "OK: acceptance ADR not created (Inc 10 deliverable)"
```
Expected: no stale `0013-phase-5-gate-a-acceptance` reference; ADR 0012 now says `0015`; the acceptance ADR file was **not** created.

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md docs/adr/0012-phase-5-gate-a-entry-gate-artifacts.md
git commit -m "docs(phase5): renumber Gate A acceptance-ADR reference 0013->0015 (0013/0014 consumed)"
```

---

## Task 3: Reconcile Inc 5 `worker:advisories` (Finding 4)

The Inc 5 scope hard-asserts a new `worker:advisories`; the sub-plan hedges it to an "alias decision"; and the Inc 5 design spec (`docs/superpowers/specs/2026-07-03-phase5-increment5-package-integrations-security-response-design.md`, Open-Decision #3) recommends **not** building it — advisory ingest already lives in the landed `worker:registry-refresh` and enforcement in `worker:packages`. Make the plan agree with the design.

**Files:**
- Modify: `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` (Inc 5 scope line ~155; Inc 5 sub-plans line ~157)

**Interfaces:**
- Consumes: the landed workers `worker:registry-refresh` (`bin/console`) and `worker:packages`, and the Inc 5 design spec's Open-Decision #3.
- Produces: scope + sub-plan text that references the existing workers and drops the committed `worker:advisories` deliverable.

- [ ] **Step 1: Confirm the divergence (test-before)**

Run:
```bash
cd /home/henry/community-forums
grep -n "worker:advisories" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md
grep -rn "worker:advisories" bin/ src/    # expect 0 hits (command does not exist)
```
Expected: the plan mentions `worker:advisories`; it is unimplemented in `bin/`/`src/`.

- [ ] **Step 2: Fix the Inc 5 scope line**

Replace:
```
advisory ingest/escalate + `worker:advisories`
```
with:
```
advisory ingest/escalate on the landed `worker:registry-refresh` (fetch/reconcile) + `worker:packages` (enforce) — **no new `worker:advisories`** (Inc 5 design §12 Open-Decision #3)
```

- [ ] **Step 3: Fix the Inc 5 sub-plans line**

Replace:
```
SP3 advisory worker/alias decision
```
with:
```
SP3 advisory ingest/escalate folded into `worker:registry-refresh`/`worker:packages` (no new worker)
```

- [ ] **Step 4: Verify (test-after)**

Run:
```bash
grep -n "worker:advisories" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md
```
Expected: the only remaining hits are the negated `**no new `worker:advisories`**` phrasing (no committed-deliverable or open-"decision" phrasing left).

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md
git commit -m "docs(phase5): reconcile Inc 5 advisory worker with design (no new worker:advisories)"
```

---

## Task 4: Fix the "fails CI" overstatement (Finding 5)

The F1 gapless-unique guard is a PHPUnit test, but the repo has **no PHPUnit CI** (only `.github/workflows/browser-evidence.yml`). The plan claims it "fails CI" in two places, overstating an automated safety net that does not exist.

**Files:**
- Modify: `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` (Global Constraints Migrations bullet, line ~20; §C re-baseline note, line ~119)

**Interfaces:**
- Consumes: the CLAUDE.md fact "There is no PHPUnit CI" and `tests/Unit/Core/MigrationLedgerTest.php` (real, run locally).
- Produces: both references say the guard fails the **local** `composer test` suite.

- [ ] **Step 1: Confirm (test-before)**

Run:
```bash
cd /home/henry/community-forums
grep -n "fails CI" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md    # expect 2 hits
ls .github/workflows/    # expect only browser-evidence.yml (no phpunit CI)
```
Expected: 2 "fails CI" hits; no PHPUnit workflow.

- [ ] **Step 2: Fix the Global Constraints Migrations bullet**

Replace:
```
never re-grab an already-allocated number — the F1 guard fails CI if you do
```
with:
```
never re-grab an already-allocated number — the F1 guard (`tests/Unit/Core/MigrationLedgerTest.php`) fails the local `composer test` suite if you do; there is **no PHPUnit CI**, so run it before landing a migration
```

- [ ] **Step 3: Fix the §C re-baseline note**

Replace:
```
otherwise the F1 gapless-unique guard
> (`tests/Unit/Core/MigrationLedgerTest.php`) fails CI.
```
with:
```
otherwise the F1 gapless-unique guard
> (`tests/Unit/Core/MigrationLedgerTest.php`) fails the local `composer test` suite (no PHPUnit CI — run it before landing).
```

- [ ] **Step 4: Verify (test-after)**

Run:
```bash
grep -n "fails CI" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md    # expect 0 hits
grep -c "fails the local \`composer test\` suite" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md   # expect 2
```
Expected: no "fails CI"; two local-suite references.

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md
git commit -m "docs(phase5): correct F1 migration guard 'fails CI' -> local composer test (no PHPUnit CI)"
```

---

## Task 5: Add the P5-10-A Gate-A carve-out in §I (Finding 7)

§I flatly lists "P5-10 governance" as "Gate B correctly excluded", but GA-DOD-12 (protected-owner/last-admin + recent-reauth safeguards) is a Gate **A** DoD attributed to workstream P5-10 — and it *is* owned (F5/F7 landed; owner-loss wiring distributed to Inc 6/7/9, per the ledger). Add the same style of carve-out P5-07 already has (P5-07-A), so §I isn't self-contradictory. The plan's risk #2 (line ~234) is already accurate and needs no change.

**Files:**
- Modify: `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` (§I "Gate B correctly excluded" bullet, line ~248)

**Interfaces:**
- Consumes: `docs/phase5/requirement-ledger.json` GA-DOD-12 (`gate:A`, note "owner-loss paths … land in Inc 6-9"), and the P5-07-A carve-out precedent at §I line ~243.
- Produces: a §I exclusion that scopes P5-10 to its Gate B governance-groups/approvals/access-review slice and explicitly names GA-DOD-12 as the non-excluded Gate A slice.

- [ ] **Step 1: Confirm (test-before)**

Run:
```bash
cd /home/henry/community-forums
grep -n "Gate B correctly excluded" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md
grep -n '"id": "GA-DOD-12"' docs/phase5/requirement-ledger.json   # confirms gate:A / workstream:P5-10
```
Expected: the flat "P5-10 governance" exclusion is present; GA-DOD-12 is `gate:A`.

- [ ] **Step 2: Rewrite the exclusion bullet**

Replace:
```
**Gate B correctly excluded:** P5-05/06 sandbox runtime, P5-10 governance, P5-14 service principals, P5-15 verified links, restricted stylesheet modules, usernameless passkeys, privileged-MFA enforcement.
```
with:
```
**Gate B correctly excluded:** P5-05/06 sandbox runtime, **P5-10 governance groups / approvals / access review** — but *not* its Gate A protected-owner/last-admin + recent-reauth slice (GA-DOD-12), which ships via F5 (`LastOwnerGuard`) + F7 (`ReauthGate`) plus the owner-loss wiring distributed to Inc 6/7/9 (mirrors the P5-07-A carve-out) — P5-14 service principals, P5-15 verified links, restricted stylesheet modules, usernameless passkeys, privileged-MFA enforcement.
```

- [ ] **Step 3: Verify (test-after)**

Run:
```bash
grep -n "GA-DOD-12" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md   # now referenced in §I
grep -n "P5-10 governance groups / approvals / access review" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md
```
Expected: §I now names GA-DOD-12 and scopes the P5-10 exclusion.

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md
git commit -m "docs(phase5): add P5-10-A Gate A carve-out in §I (GA-DOD-12 owner-loss safeguards)"
```

---

## Task 6: §C Inc 7 row + "G3/G4" label nits (Findings 8 + 9)

Two nits in the program plan: §C has no Inc 7 row (jumps Inc 6→Inc 8) while Inc 9/10 get explicit "NO migration" rows; and §I references undefined "G3/G4" labels.

**Files:**
- Modify: `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` (§C table, after the `0075` Inc 8 row; §I §8 self-review bullet, line ~245)

**Interfaces:**
- Consumes: the §C "— … NO migration" row style already used for Inc 9/Inc 10; the concrete backfills (Inc 6 legacy-role migration, Inc 8 `0075` provider-identity repoint).
- Produces: a §C table with all increments represented and a §I bullet with resolvable references.

- [ ] **Step 1: Confirm (test-before)**

Run:
```bash
cd /home/henry/community-forums
grep -n "G3/G4" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md    # expect 1 hit (line ~245)
grep -n "NO migration" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md   # Inc 9 + Inc 10 rows, no Inc 7
```
Expected: one `G3/G4` hit; §C "NO migration" rows exist for Inc 9/10 only.

- [ ] **Step 2: Add the Inc 7 §C row (Finding 8)**

Immediately **before** the `| — | **P5-13 invitations: NO migration**` row, insert a new row:
```
| — | **P5-11 passkeys: NO migration** — activates the existing `0051` WebAuthn tables (privileged-MFA/usernameless policy is Gate B) | Inc 7 |
```

- [ ] **Step 3: Fix the "G3/G4" label (Finding 9)**

Replace:
```
§C allocation + F-seeds + G3/G4 backfills
```
with:
```
§C allocation + F-seeds + the Inc 6 legacy-role migration and Inc 8 provider-identity repoint (`0075`) backfills
```

- [ ] **Step 4: Verify (test-after)**

Run:
```bash
grep -n "G3/G4" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md    # expect 0 hits
grep -n "P5-11 passkeys: NO migration" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md   # expect 1 hit
```
Expected: no `G3/G4`; the Inc 7 row is present.

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md
git commit -m "docs(phase5): add §C Inc 7 row + resolve §I 'G3/G4' backfill labels"
```

---

## Task 7: Reconcile the requirement-ledger F5 note (Finding 6)

The plan is correct that Inc 7 wired passkey-removal into `LastOwnerGuard` (`src/Service/PasskeyService.php:350` calls `assertNotLastOwnerForUpdate($user, 'passkey')`), but `requirement-ledger.json`'s F5 `notes` still lists `passkey-removal` as pending. Fix the **ledger** (not the plan). This is the only JSON edit; keep it to the `notes` field so the evidence-map test still passes.

**Files:**
- Modify: `docs/phase5/requirement-ledger.json` (the `"id": "F5"` entry `notes` field, line ~33)

**Interfaces:**
- Consumes: `src/Service/PasskeyService.php:350` (passkey-removal LastOwnerGuard wiring, landed Inc 7).
- Produces: an F5 note whose pending-list matches reality (role-revoke / provider-unlink / invitations still pending, in Inc 6/8/9).

- [ ] **Step 1: Confirm (test-before)**

Run:
```bash
cd /home/henry/community-forums
grep -n "passkey-removal" docs/phase5/requirement-ledger.json    # F5 note lists it as pending
grep -n "assertNotLastOwnerForUpdate(\$user, 'passkey')" src/Service/PasskeyService.php   # proves it is wired
```
Expected: the ledger note lists `passkey-removal` pending; the code wires it.

- [ ] **Step 2: Fix the F5 note**

In the `"id": "F5"` object, replace the `notes` value:
```
Wired on account deactivate/delete; role-revoke, passkey-removal, provider-unlink, invitations pending.
```
with:
```
Wired on account deactivate/delete and passkey-removal (Inc 7, PasskeyService); role-revoke, provider-unlink, invitations pending (Inc 6/8/9).
```

- [ ] **Step 3: Verify JSON validity + the evidence-map guard (test-after)**

Run:
```bash
php -r 'json_decode(file_get_contents("docs/phase5/requirement-ledger.json"), false, 512, JSON_THROW_ON_ERROR); echo "valid json\n";'
grep -n "passkey-removal" docs/phase5/requirement-ledger.json    # now only in the "wired" clause, not the pending list
vendor/bin/phpunit --filter Phase5EvidenceMap
```
Expected: `valid json`; `passkey-removal` no longer appears in a "pending" clause; the evidence-map test passes.

- [ ] **Step 4: Commit**

```bash
git add docs/phase5/requirement-ledger.json
git commit -m "docs(phase5): mark F5 passkey-removal owner-loss path wired (Inc 7) in requirement ledger"
```

---

## Task 8: Final consistency sweep + guard run

Prove no stale strings survive and the repo guards that consume these docs still pass.

**Files:**
- No edits — verification only. (If the sweep finds a residual stale string, fix it in the owning task's file and fold the fix into this commit.)

- [ ] **Step 1: Stale-string sweep across all three files**

Run:
```bash
cd /home/henry/community-forums
echo "-- these must all return 0 hits --"
grep -rn "0013-phase-5-gate-a-acceptance" docs/ | wc -l
grep -rn "fails CI" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md | wc -l
grep -rn "G3/G4" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md | wc -l
grep -rn "must be recorded before the Foundation increment" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md | wc -l
grep -rn "SP3 advisory worker/alias decision" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md | wc -l
echo "-- these must be present --"
grep -c "Landed 2026-07-02" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md   # expect 5
grep -c "P5-11 passkeys: NO migration" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md   # expect 1
grep -c "GA-DOD-12" docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md   # expect >= 1
```
Expected: the first five counts are `0`; the presence checks are `5`, `1`, `>=1`.

- [ ] **Step 2: Run the repo guards that consume these docs**

Run:
```bash
vendor/bin/phpunit --filter 'MigrationLedger|Phase5EvidenceMap|AppFeatureFlag'
```
Expected: all pass (the doc edits touch nothing these guards assert beyond the ledger `notes` field verified in Task 7).

- [ ] **Step 3: Final review diff**

Run:
```bash
git log --oneline -8
git diff --stat main -- docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md docs/adr/0012-phase-5-gate-a-entry-gate-artifacts.md docs/phase5/requirement-ledger.json
```
Expected: seven remediation commits (Tasks 1–7); three files changed.

---

## Self-Review vs the review findings

- **Coverage:** all 9 distinct review findings map to a task (Findings 1+2 → Task 1; 3 → Task 2; 4 → Task 3; 5 → Task 4; 7 → Task 5; 8+9 → Task 6; 6 → Task 7; sweep → Task 8). ✅
- **Placeholder scan:** every edit shows the exact `old → new` string and a concrete `grep`/command with expected output; no "TBD"/"handle appropriately". Landed dates are fixed values (2026-07-02, from `git log`); the acceptance-ADR number is verified next-free at execution time in Task 2 Step 1. ✅
- **Consistency:** the acceptance-ADR number is `0015` in both the program plan (Task 2 Step 2) and ADR 0012 (Task 2 Step 3); `worker:registry-refresh`/`worker:packages` are named identically in Task 3; the "Landed 2026-07-02 → … ; sub-plan …" marker shape matches the existing Inc 1/Inc 2 markers. ✅
- **No scope creep:** Task 2 explicitly does not create the acceptance ADR; Task 7 touches only the ledger `notes` field; no code/schema/behavior changes anywhere. ✅

## Execution Handoff

**Plan complete and saved to `docs/superpowers/plans/2026-07-03-phase5-gatea-plan-review-remediation.md`. Two execution options:**

**1. Subagent-Driven (recommended)** — a fresh subagent per task with review between tasks. Overkill for 8 mechanical doc tasks, but clean.

**2. Inline Execution** — apply all eight tasks in this session with a checkpoint after Task 7 (the only test-affecting edit) and the Task 8 sweep. Fastest for doc edits.

**Which approach?**
