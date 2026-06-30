# Imladris fidelity — high-impact gap-closing pass

Browser evidence for the targeted fidelity pass that closed the verified
export↔repo gaps from the `imladris-design-system.zip` handoff bundle. The bundle
is a `/design-sync` re-export that **lags** the repo (Imladris already shipped via
PRs #21/#29/#31), so this was a *gap-closing* pass, not a re-import. Scope chosen
with the operator: the correctness + high-impact subset, plus the poll "Closes"
control.

All changes are CSP-safe (no inline `<script>`/`<style>`; inline `<svg>` only) and
token-derived (twilight-safe). Functional proof is in
`tests/Integration/Core/AppImladrisFidelityHighImpactTest.php` (10 tests asserting
the exact rendered markup) — the full suite is **819 green**. These screenshots
add the DESIGN §13 visual layer for the surfaces the canonical gate-a evidence
seed does not exercise.

| Shot | Gap(s) | What it shows |
|------|--------|---------------|
| `01-thread-in-council.png` | #1 | "IN COUNCIL" lapidary eyebrow above the participant avatar stack (3 participants) |
| `02-notifications-icons.png` | #2 | Per-type Lucide icon + `.notif-body` (text over italic thread) + trailing gold unread dot |
| `20-poll-closes-builder.png` | #20 | New "Closes" select (Never / In 1 day / In 3 days / In 1 week) in the poll builder; `PollService::create()` persists `closes_at` |
| `16-dm-gilt-head.png` | #16 | Gilt (gold-ring) monogram on the open-letter conversation head |
| `08-09-structure-cathead-flasherror.png` | #8, #9 | `.admin-cat-head` aligns the rename form left / reorder+delete right; a **real failed reorder** (422) now renders the **rust `.flash-error`** plate — no longer the green success `.flash` |

Role chips (#11) and `.state` status chips (#10) are visible in the regenerated
canonical set: `docs/evidence/browser/desktop/14-admin-users.png` and
`15-admin-user-record.png`. The connections `.handle` mono class (#3) is a
one-line template fix activating already-shipped CSS, proven by PHPUnit.

Captured against a dev server on the freshly-seeded `retroboards_e2e` DB
(seed users `admin`/`alice`/`bob`/`carol`, `password123`).
