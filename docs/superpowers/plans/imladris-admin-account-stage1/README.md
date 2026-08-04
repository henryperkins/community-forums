# Stage 1 working reports — Imladris admin/account adoption

Raw analysis behind [ADR 0024](../../../adr/0024-imladris-admin-account-adoption.md), the
[plan](../2026-08-03-imladris-admin-account-adoption.md) and the
[deviation ledger](../2026-08-03-imladris-admin-account-ledger.md). Produced 2026-08-03 by 44
subagents across three workflows; every screen diff was adversarially verified.

**The ledger and the plan are the summaries. These are the workings** — Stage 2 slices 5–17 read
the per-screen `D-*` + `V-*` + `R-*` triples for the detail the ledger deduplicates away.

## Reading order

| File | What it is |
|---|---|
| `F1-design-foundations.md` | Token inventory, component vocabulary, microcopy register, asset-build mechanics, cascade layering |
| `F2-binding-decisions.md` | Every ADR/spec decision a verbatim copy would violate; ADMIN.md/USER.md requirements the design omits |
| `F3-production-inventory.md` | Route → controller → template → flag map for all of `/admin/*` and `/settings/*` |
| `F4-mirror-sync.md` | What the 2026-08-03 design-mirror refresh pulled in |
| `F5-mirror-drift.md` | Which mirrored files were stale, and the three where the mirror is **ahead** of upstream |
| `D-<screen>.md` | Per-screen structured diff: section order, difference table, fiction strings, state inventory, slice proposal |
| `V-<screen>.md` | Adversarial verification of that diff — refutations, reclassifications, misses |
| `R-<screen>.md` | Re-anchor addendum for the seven screens whose design source was overwritten mid-pass |
| `R-cross-cutting.md` | The twelve unowned cross-cutting decisions, settled with evidence |
| `S-admin-ia.md` | The authoritative area → tab → route → template → flag table (basis for `templates/admin/_console.php`) |
| `S-synthesis.md` | Completeness critic over the whole pass |

## Two caveats when reading `D-*.md`

1. **Seven screens were diffed against a design revision that was overwritten mid-pass.** Their
   design-side line citations are wrong; `R-<screen>.md` carries the corrected index. Always read
   the `R-` addendum alongside the `D-` report for: `admin-overview`, `admin-people`,
   `admin-content`, `admin-appearance`, `admin-notifications`, `admin-settings`, `account-settings`.
2. **Where a `V-` report reclassified a row, the verified classification wins.** The ledger already
   applies this; the raw `D-` files do not.

`S-synthesis.md` §1.1 claims two verification passes are missing. That was stale when written —
`V-admin-members.md` and `V-admin-integrations.md` both exist here.
