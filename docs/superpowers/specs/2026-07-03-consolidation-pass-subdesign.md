# Consolidation pass (DM reimagine Phase 5) — focused sub-design

Applies the five consolidation principles (see `2026-07-03-dm-reading-room-design.md`)
to the three surfaces the reading room's polish now makes look heavier than they
are. Per the parent spec's scope caveat: **each surface is its own reviewable
commit, no blanket refactor**, all routes/forms/feature-gates unchanged.

## 5a — Topic rows become quiet rows (CSS only)

**Problem.** `.thread-row` is a full card — border, radius, raised background,
shadow, hover lift — repeated for every row on the inbox, boards, home, and
search. Stacked cards read as noise next to the de-boxed DM list (principle 4).

**Change** (`public/assets/app.css` only; `partials/thread_row.php` untouched):
- Rows lose the per-row border/radius/background/shadow/lift; the list gains
  hairline dividers (`.thread-list > li + li { border-top: 1px solid var(--border-hair) }`)
  and the 10px card gap collapses to contiguous rows.
- Hover becomes the DM-row treatment: `background: var(--surface-sunken)`.
- **Kept:** the 3px status rule (`::before` — pinned gold / solved leaf /
  needs-answer amber), the status **chips** (word + colour, principle 5), the
  unread dot + title weight, monogram, star.

**Risk:** low — markup untouched, so every pinned-markup test is unaffected.
**Evidence:** full PHPUnit suite (markup regression) — visual capture deferred.

## 5b — Topic view: controls on demand (`templates/thread.php`)

**Problem.** The topic header carries a standing action bar (Star, a
Notify-select + Save form, Clear accepted answer, Pin, Lock) and, for
moderators, a stack of permanently open panels (tags editor, summary, related
topics, poll creation, split/merge). This is the "always-visible controls"
disease the DM header ··· menu cured (principles 1–2).

**Change:**
- **Star stays on the line** — it is the one-tap engagement primary.
- The workflow status bar stays — it is information (word + colour), not a control.
- Notify (the select+Save form), Clear accepted answer, Pin/Unpin, Lock/Unlock
  move into one header `···` overflow using the existing
  `details.dm-menu`/`.dm-menu-pop`/`.dm-menu-item` popover — the exact forms,
  actions and fields relocate verbatim (no-JS: native `<details>`; the
  dismissal JS already targets `details.dm-menu` generically). Lock/Unlock and
  other destructive items take the `danger` item treatment.
- Each standing mod panel collapses into a closed `<details>` disclosure;
  forms inside are byte-identical. *As built:* Edit tags, Add poll, Curate
  topic memory, and Status history were already disclosures — only the
  split/merge panel needed collapsing. The Summary / Related / poll displays
  are information, not controls, and stay visible. The workflow actions bar
  (status/snooze/assign) is a staff surface with three select-driven forms —
  left as its own bar rather than forced into a popover.

**Risk:** medium — thread.php is heavily exercised by workflow/poll/tag tests.
Mitigation: forms keep identical `action`/fields/labels; suite runs per commit.
**Evidence:** full PHPUnit suite; no-JS = native details/forms.

## 5c — Profile: danger behind the menu (`templates/profile/show.php`)

**Problem.** Block renders as a standing control beside Follow/Message
(principle 2: destructive actions live behind the menu).

**Change:** Follow + Message stay primary buttons; Block moves into a small
`···` overflow (same `dm-menu` popover, `danger` item), leaving room for a
future Report-user item. Form unchanged.

**Risk:** low. **Evidence:** full PHPUnit suite.

## Explicitly not touched

Inbox tabs and reading pane (already a reading-room shell), notifications page,
admin/mod desks (own audit skills), auth surfaces, any DM behavior shipped in
Phases 1–4.

## Naming note

`dm-menu` becomes the app-wide popover-menu pattern by *use*; classes are not
renamed in this pass (a rename would churn Phases 2–4 evidence and tests for
zero behavior). A follow-up may alias `.rb-menu` if the pattern spreads further.

## Evidence policy for this pass

Full PHPUnit suite per commit. Playwright/browser captures are **deferred** per
the operator's instruction to skip the browser-evidence step for this stream;
the existing `tests/browser/dm-reimagine.spec.ts` harness remains ready if
captures are wanted later.
