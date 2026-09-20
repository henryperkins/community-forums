# ADR 0034: One field register, and the border tokens that were drifting

**Date:** 2026-09-13
**Status:** Implemented; verification results below.
**Relates to:** ADR 0033 (the chamfered frames removed — this is the second half
of the same border audit); root `DESIGN.md` "Inputs and fields", "Cards and
containers", "Shapes" and the Twelve-Max Rule; ADR 0024 obligation 4 (the runtime
baseline); PRODUCT_DESIGN §13.

## Context

Removing the chamfer meant reading every border declaration in
`public/assets/app.css`. Five classes of drift fell out, none of them about
corners, and this ADR records what was done with each.

The root cause of the largest one is the same cascade fact ADR 0033 turns on:
**`app.css` is unlayered, so it beats the design system's `@layer imladris.*`
rules regardless of specificity.** A stale value in `app.css` does not merely
coexist with the system's — it *replaces* it, silently, everywhere.

## Decisions

### 1. `.input` carries the system's field frame (P1)

`.input, .composer-input` painted `border: 1px solid var(--border);
border-radius: 6px; background: var(--surface)` with no shadow. `--border` is the
legacy alias for `--parchment-300` — the system's **default hairline**, not a
control frame. `DESIGN.md` "Inputs and fields" is unambiguous: *"raised
parchment, 1.5px `--border-soft`, 7px radius, `--shadow-inset` … padded
`9px 11px`"*, and "Shapes" adds that interactive controls step up to 1.5px *"so
the outline reads as an affordance"*.

The design layer already shipped exactly that and lost to `app.css`. The only
thing upgrading anything was `.composer-input, textarea.input`, scoped to
textareas — so an `<input class="input">` and a `<textarea class="input">` in one
form wore **two different registers**, differing in width, radius, ground and
depth. 253 `class="input"` in `templates/`, of which 56 are `<select>`.

Three companions are mandatory to keep existing field frames stable:

- `.composer-header > .input` zeroed `border`, `border-radius` and `background`
  but **not** `box-shadow`. It is the `/compose` New Topic title, whose contract
  (`templates/partials/new_thread_form.php`) is to strip its own frame and
  inherit `.composer-box`'s inset. Giving the base an inset without this would
  stack a second one and print a shading band under the header hairline. No spec
  covers that path.
- `.input:focus` set `box-shadow: 0 0 0 3px var(--focus-ring)` **alone**, so
  focusing would strip the new inset for exactly as long as the field is focused
  — the identical failure `.input-engraved:focus` already documents. `DESIGN.md`
  asks for the halo *over* the inset.
- The admin content, roles, features and audit filters replace the generic focus
  shadow with a halo alone. Their resting rules now explicitly set
  `box-shadow: none`, preserving the flat operator field treatment already used
  by the general settings, member and integration forms. The browser spec checks
  rest → focus → blur on every visible field in these four surfaces, in both
  registers. Without the reset, the new inherited inset disappears on focus
  and returns on blur.

In the light register the `<select>` ground change is a literal no-op (`--surface`
and `--surface-raised` both resolve to `--parchment-50`); only twilight moves.

### 2. The empty-state frame takes the container token, not the control one (P3)

Six dashed boxes shared one visual role across three border tokens and three
radii. `DESIGN.md` "Cards and containers" states one token for this class of box
— `1px --border-hair` — and `--border-soft` belongs to the control register,
which `DESIGN.md` spends on a secondary button and on a field. `.profile-panel-empty`
and `.content-empty-state` move to `--border-hair`; `.living-brief-empty` and
`.org-empty` were already compliant. `.branding-mark-field` keeps `--border-soft`
— it is a field, not an empty state. The dashed usages `DESIGN.md` sanctions
(archived chips, locked badges, deleted posts, the post signature rule) are
untouched.

The unused `.bulk-bar` family is removed as part of this container-frame pass.
No template, script or test emits it; its dashed frame used the legacy
`--border` alias and a raw 10px radius. The live `.member-directory-bulk-bar`
is a separate class and retains its styling.

### 3. The `<select>` chevron takes a flipping token (P2, partially)

The custom chevron on `.compose-board-select` was painted `--gold-600`, a
primitive that does not flip. Measured: **2.97:1** on `--surface-raised` in the
light register, against the 3:1 WCAG 2.2 SC 1.4.11 asks of a control affordance.
`--gold-ink` is the flipping semantic the system reserves for small gold marks:
**5.49:1** by day, **7.30:1** by night. This rule lives inside the byte-identical
production-transfer bridge, so the same bytes were applied to both files.

**Deliberately not done:** the blanket `appearance: none` + baked data-URI arrow
on `.admin-console select.input, .settings-pane select.input` (50 selects, 25
templates) is also register-blind — a `url()` data-URI is opaque to `var()` and
to `currentColor`, so its ink freezes to one register. Removing it and restoring
the native arrow is the right fix, but the proposed edit set would have **broken
the repo's only CI workflow** (`admin-remediation.spec.ts` pins the computed
style of `.settings-general-card select.input`) and would have orphaned four
padding gutters that exist only to make room for the baked arrow. Recorded here
as a follow-up rather than shipped half-right.

### 4. Off-token radii and literal colours (P4/P6)

Raw pixel radii are spelled as tokens where the value is identical. After the
pass `app.css` contains **zero** raw `4px`, `6px`, `7px` or `10px` radii and one
remaining raw `999px` (see decision 5).

**`var(--radius)` is NOT a duplicate of `var(--radius-md)`** and its 17 uses are
deliberately left alone: `[data-density="compact"]` redefines `--radius` to 6px,
so the alias is density-responsive and collapsing it would freeze those surfaces
at 7px in compact mode. This is worth knowing before the next person "tidies" it.

One proposed edit was **rejected**: rewriting `.resolution-note`'s inset ring as
a border would have authored a fresh violation of a stated `DESIGN.md` Don't.

`--scrim` was restated in **both** of `app.css`'s dark registers. The design
system declares it under `[data-theme="dark"]` only and carries no
`[data-theme="system"]` block at all, so a member on the default `system` theme
with a dark OS was getting the **light** scrim behind every overlay. Declaring it
in one block would have swapped that bug for an asymmetry, which is exactly what
`ImladrisRuntimeAssetTest::test_both_application_dark_registers_declare_the_same_tokens`
exists to catch — and did catch, mid-change.

The mobile navigation scrim now uses that shared token too: its day opacity
changes from `.5` to `.42`, and twilight uses `.58`. The slightly lighter day
overlay is intentional; both registers now share the overlay palette with the
composer and other dialogs.

### 5. The pill doctrine needs a ruling, not a sweep (P7)

74 pill-radius declarations were enumerated and classified against `DESIGN.md`'s
rule (*"don't make a button or card pill-shaped"*) and its one stated exemption
(*"A search field takes the pill variant"*):

| | count | verdict |
|---|---|---|
| tokens — chips, badges, tags, tiers, counts, dots, filter tabs, segmented shells, switch and progress tracks | 61 | correctly pill; `DESIGN.md` reserves the pill for exactly these |
| search fields | 4 | the sanctioned exemption |
| genuine violation | 1 | `.wf-btn` — **deleted**, see below |
| ambiguous | 8 | two are outright doctrine conflicts |

An earlier pass in this session reported "~10 pill-radius buttons against
DESIGN.md". That was an overstatement: rigorously, there is **one**.

`.wf-btn` was a button by name and role, wearing a pill that no design source
backs — the design system's own mock for that class draws it on a plain radius.
It was also **dead**: nothing in `templates/`, `src/`, the scripts or the tests
emits `.wf-*`. The whole family is deleted rather than corrected.

**The two doctrine conflicts are left for a human ruling and are not changed:**

- `.star-btn` / `.topic-tools-open` — real `<button>`s wearing a pill. Four
  authorities disagree: `DESIGN.md` and its front-matter say a button is 7px;
  the design system's `components.css` declares `.star-btn` at `--radius-md`,
  *agreeing* with `DESIGN.md`; but its own section header one line above reads
  "Star button (pill)"; and the thread-view handoff canvas draws both as `999px`,
  which `app.css` faithfully transcribed. The design system contradicts itself in
  adjacent lines.
- `.board-mute-toggle` — a `<button>` wearing a pill, with a source comment
  citing the design canvas, which does draw it as a pill that fills when on.

Both read as *states* rather than actions, which is the argument for the pill.
Resolving it means amending either `DESIGN.md` or the canvases; that is a design
decision, not a cleanup.

## Consequences

- Generic `.input` fields gain 0.5px of border, 1px of radius, an inset shadow
  and (in twilight only) a slightly raised ground. Surface-specific frames
  remain deliberate exceptions, including the flat operator controls above.
- Admin surfaces that re-applied the system values locally are now redundant with
  the base rule. They are left in place: deleting them is a separate, mechanical
  follow-up and folding it in here would have made this diff unreviewable.
- Two dead CSS families (`.bulk-bar`, `.wf-*`) and one orphaned register
  (`.gem-*`, ADR 0033) are gone from `app.css`.

## Evidence

`docs/evidence/chamfer-removal/` carries the captures; the border pass shares
them because it repaints the same surfaces. Runs are recorded in that directory's
notes. The 2026-09-20 review correction adds actual twilight notification-select
captures and admin rest/focus/blur guards: 66 frame/field-error checks passed,
26 existing admin checks passed (22 viewport skips), and full PHPUnit completed
2780 tests without failures (6 deprecations, 1 skip).
