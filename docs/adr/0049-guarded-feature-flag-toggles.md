# ADR 0049: Guarded feature-flag toggles on /admin/features

**Date:** 2026-10-18
**Status:** Accepted
**Supersedes in part:** the 2026-07-14 "read-only, deliberately no toggles"
stance of the `/admin/features` inventory (`docs/evidence/deploy-dark-features.md`,
the `AdminFeatureController` readiness docblock). The readiness classification
itself is unchanged and stays read-only.

## Context

The 2026-10-18 admin critique (`.impeccable/critique/2026-10-18T08-18-16Z__admin.md`)
rated Features read-only as a P1. The inventory could diagnose but not treat:
turning a subsystem off — the runbook's first response to a logic defect
(`docs/runbooks/operations.md` §2) — meant hand-running a PHP read-modify-write
of the `settings.features` JSON, mid-incident, outside the console. The owner
confirmed a shape brief for this round: add guarded toggles, use a confirm plus
audit ceremony, and touch the flags list only.

## Decision

- **One write affordance per row**, inside the Flag cell (the one column on
  screen at 390px, where the table scrolls sideways). It is a native
  `<details>` disclosure: **Turn off…** / **Turn on…** opens an inline
  confirmation in place, with no request, no modal and no JavaScript. The
  confirmation names what the change does, any side effect to weigh, and the
  exact write. The commit posts one flag. `name` makes the panels an
  exclusive accordion where browsers support it; one flag per POST is the
  guarantee. Columns, filters, tiles and the unknown-overrides table are
  unchanged.
- **Explicit target, idempotent write.** `POST /admin/features/{flag}` carries
  `enabled=0|1`, never a flip. A flag already in that state is left untouched
  and unaudited, and the flash names the last audited console change when the
  stored override still matches it (direct SQL/CLI edits leave no audit row, so
  attribution is best-effort), so a double submit reads as "already done by
  you", not as a failure.
- **The runbook's write, nothing new.** Returning a flag to its code default
  removes its key; anything else stores a JSON boolean; every other key —
  declared, unknown or hand-shaped — is preserved through the JSON decode/encode
  round trip. An empty map is stored as `{}`. `FeatureFlags` remains the only
  reader.
- **Serialized.** The service locks the `settings` row (a no-op upsert, then
  `SELECT … FOR UPDATE`) before reading. Two concurrent console writes to
  different flags queue and both survive, including when the row did not exist.
  Measured on MariaDB 11.8 with two connections: a plain `FOR UPDATE` on a
  missing row deadlocks (1213), and an unlocked read-modify-write fails there
  (1020, snapshot isolation) and would silently lose a write on MySQL.
- **Audited in the same transaction**, mirroring the Features area's siblings
  (`badge_rule.enable`, `custom_emoji.disable`): `feature_flag.enable` /
  `feature_flag.disable`, target `setting` #0, reason `features.<flag>`, with
  before/after `{flag, override, effective}` (the raw stored override before).
- **What the console will not do.** `FeatureFlags::CONSOLE_ENABLE_BLOCKED`
  lists the flags it may switch off but never on: `custom_css`
  (Safety-blocked), `expanded_files` (Missing user UI) and the four Gate B
  flags (Reserved, ADR 0018). Enabling one stays a deliberate
  `settings.features` write. A test pins this list equal to the inventory's
  not-shipped readiness rows. Rows render locked, with the reason in words,
  when the flag is blocked, when `settings.features` is not a JSON object (a
  JSON list, a scalar or JSON null: the loader ignores it whole, and a fresh
  object would silently discard what the operator stored), or when the viewer
  cannot write (`WriteGate`); the service refuses the same cases. An object
  holding a number PHP cannot re-encode (`1e999` decodes to `INF`) is a
  different case: the loader still applies all of it, so the rows stay
  unlocked and no effective state changes, and only the write is refused (422,
  not a 500).
- **Refusals re-render as a 422** with a focused `role="alert"` banner and the
  operator's filters, because the console flash only speaks success.
- **Success** is a 303 to the inventory with the operator's name and readiness
  filters (the On/Off filter is dropped since the write inverts it, and the
  readiness filter is dropped when a live-computed row no longer matches it) at
  `#flag-<name>`, clear of the sticky admin bar. The row shows its live cells,
  the reverse toggle, and a one-shot marker derived from the flash that renders
  only while the live Effective cell agrees with it. The audit log is linked.
- **Consequence copy** lives in `App\Support\FeatureFlagCatalog`, one entry per
  declared flag, traced to the code that reads each flag on 2026-10-18
  (citations in `docs/evidence/features-guarded-toggles-2026-10-18/`). A test
  fails when a declared flag has no entry.

Not chosen, per the confirmed brief: reauthentication or typed confirmation
(ADMIN §9.4 reserves typed confirmation for irreversible actions; a flag flip
is reversible without a data change), instant toggles, bulk flips, per-board
overrides, and a JSON editor.

## Consequences

- Turning `oauth`, `provider_registry` or `passkeys` off can lock out members
  whose only sign-in method that is. The confirmation warns, but it neither
  counts them nor asks for the password the provider console's sole-method
  confirm requires. Whether these three need reauthentication or a live count
  is an open owner decision.
- With `email` or `notifications` off, running mail workers mark queued mail
  suppressed; `worker:purge-accounts` keeps purging with `account_lifecycle`
  off. The confirmations and the runbook say so; pausing those crons stays an
  operator step.
- Back to default removes an explicit `true` pin, as the runbook's "remove the
  key" does; the confirmation says "Removes the … override". Whether it
  should instead restore the prior explicit value is an open owner decision.
- `FeatureFlagCatalog` must move with its subsystems: a change to what a flag
  gates updates its sentence in the same change.
- In the narrow (30ch) confirmation, the longest `features.<flag>=…` tokens
  wrap mid-identifier, because the Flag cell inherits `overflow-wrap: anywhere`
  from `.audit td`. They stay readable and unclipped at 390px; a cleaner break
  (for example a `<wbr>` after the dot) is a cosmetic follow-up, not done here.
