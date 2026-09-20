# ADR 0033: The chamfered frames are removed — a corner is a radius, not a cut

**Date:** 2026-09-13
**Status:** Implemented; verification results below.
**Relates to:** root `DESIGN.md` ("the lapidary register"); ADR 0024 obligation 4
(the runtime baseline) and its Slice 16 closeout item C-50 (the orphaned
`.gem-*` block); ADR 0027's two open deferrals on the octagonal composer title
field and on `field-error-a11y.spec.ts`; the 2026-08-27 member-surfaces
production transfer (the "compatibility bridge" pinned by
`AppImladrisFidelityTest`); the mirror's `LOCAL_RECONCILIATION.md` entries of
2026-08-09 and of this date; PRODUCT_DESIGN §13 (completion evidence).

## Context

Six frames in the product cut their corners at 45°. `DESIGN.md` calls the
treatment they belong to the **lapidary register** and has described it as
"drift, not doctrine" since it was written: *"do not extend it, and prefer the
plain equivalent whenever an engraved component is touched."* This ADR records
touching all of them at once, and removing the cut.

The construction was not a border. A border follows the border **box**, so under
a `clip-path` octagon its straight runs overshoot the chamfer tangents and get
sliced into four stubs — the "disconnected corners" defect that
`LOCAL_RECONCILIATION.md`'s 2026-08-09 entry was written to fix. The fix was to
draw the outline as **eight background layers**: four corner tiles carrying the
diagonal run, four stretched layers carrying the straight runs and stopping at
the tangents, positioned and sized by hand with irrational constants
(`chamfer × √½`, and `d(√2−1)` for an inset parallel octagon). Every state —
`:hover`, `:focus`, `:checked`, `:user-invalid`, `.is-invalid`,
`[aria-invalid]` — restated all eight layers in its own colour.

Three costs, two of them defects a user could see:

1. **The outer focus ring never rendered.** `clip-path` clips everything outside
   the octagon, including `outline` and outer `box-shadow`. By the time of this
   change only `.search-query-well:focus-visible` still *declared*
   `0 0 0 3px var(--focus-ring)`, and it had never once painted.
   `.input-engraved:focus` and `.choice-card:focus-within` had each already had
   theirs deleted — *because* it did not render, which their own comments say in
   so many words ("the `0 0 0 3px var(--focus-ring)` this rule used to carry had
   never rendered"; "an outer focus ring is impossible here"). Three source
   comments record the workaround rather than the cause. So the register had been
   quietly trading its focus indicator for its corners on every engraved control,
   and the trio's declaration is the receipt.
2. **`/compose` flooded gold on focus.** `.compose-title-input` and
   `.compose-board-select` set `background:` (the shorthand), which resets
   `background-position` and `background-size`. `.input-engraved:focus` matches
   them at the same `(0,2,0)` and re-supplies `background-image` — but nothing at
   that specificity restores the geometry, so the eight layers repainted at
   `auto` size: the whole control went solid `--gold-500`, with no focus ring.
3. **Ten declarations per state.** A colour change was a twenty-line edit in two
   files, and `.search-query-well` sat inside the byte-identical bridge block, so
   the two copies had to agree character for character.

`field-error-a11y.spec.ts` had also been **red** since that rewrite, and this
change fixes it. The spec asserts the `:user-invalid` `box-shadow` differs from
pristine. That was true and meaningful when it was written — the invalid rule
then added `inset 0 0 0 1.5px var(--danger)` to the inset, and an inset shadow
paints *inside* the clip-path, so it was the one edge signal the octagon did not
cut. The 2026-08-09 rewrite moved the danger signal to `background-image` and
left the invalid rule restating the *identical* `var(--shadow-inset)`, so
pristine and invalid became byte-equal and `expect(invalid).not.toBe(pristine)`
started failing. ADR 0027 recorded it as a pre-existing failure and deferred it;
the spec was never updated.

## Decisions

1. **The chamfer is removed from all six frames; no box moves.** Each keeps its
   ink, its padding and its outer box — `box-sizing: border-box` is global, so a
   frame that had `border: 0` and now has 1–1.5px does not grow, though its
   content box loses that much on each side. Each takes a real border on a system
   radius — except the trio, which never had a border and still draws its edge
   as an inset ring, now on a radius instead of under a clip:

   | frame | was | is |
   |---|---|---|
   | `.variant-auth .auth-card` | 16px octagon + a second octagon at `inset: 5px` | `1.5px solid var(--gold-400)`, `--radius-lg`, `--shadow-xl` |
   | `.input-engraved` / `.textarea-engraved` | 9px octagon | `1.5px solid var(--gold-200)`, `--radius-md`, `--shadow-inset` |
   | `.scribe-panel` | 14px octagon + a second at `inset: 4.5px` | `1.5px solid var(--gold-400)`, `--radius-lg`, no shadow |
   | `.field-row` | 8px octagon | `1px solid var(--gold-200)`, `--radius-md`, `--shadow-inset` |
   | `.choice-card` | 11px octagon | the plain definition already in `app.css` resumes |
   | `.search-query-well`, `.compose-title-input`, `.compose-board-select` | 8px clip over an inset ring | the same inset ring on `--radius-md` |

   Colour tokens are unchanged from what each frame already used. This is a
   change of geometry, not of palette.

2. **The doubled inner rule goes with it.** The `::before` octagons on
   `.auth-card` and `.scribe-panel` existed to trace a second outline parallel
   through the diagonals. With no diagonals there is nothing to trace, and
   `DESIGN.md`'s plain register is one border.

3. **The focus rings are restored, not re-invented.** `.input:focus` already
   supplies the accent outline and the gold halo; the engraved rules now restate
   only what they must (`--shadow-inset` under the halo, so a focused well keeps
   its depth) and no longer set `outline: 0`. `.choice-card:focus-within` takes
   the real `outline: 2px solid var(--accent)` it always declared and never got.

4. **`:user-invalid` becomes an honest `border-color: var(--danger)`** plus a
   danger halo, so the state changes more than one property and is legible
   without relying on hue alone. This closes ADR 0027's `field-error-a11y`
   deferral. That deferral offered two ways out — *"either the engraved
   `:user-invalid` rule is gone or its selector no longer matches"* — and this
   takes a third: the selector is kept and the rule is repaired, so the surface
   keeps a named error state instead of losing one. The spec is retargeted to
   read border, shadow and outline together rather than a single property, which
   is what let it drift red unnoticed.

5. **Class names are kept.** `.input-engraved` and `.textarea-engraved` stay,
   across 27 elements in 9 templates, as do `.scribe-panel` (46 occurrences) and
   `.field-row`. The
   register survives as *ink*; renaming it would be a second, larger change and
   would break `ImladrisRuntimeAssetTest`, `AppImladrisFidelityTest` and three
   browser specs for no visual gain.

6. **The remaining diamond ornament goes too, on the same reasoning.** The
   corners were the ask; these are the rest of the angular vocabulary, and
   leaving them would have meant a register that forbids a cut corner while
   still pinning a rotated square to one.

   - `.field-row .row-bullet` — an 8px gold square at 45°, pure decoration
     (`aria-hidden`) — becomes a 7px dot.
   - `.choice-card::after` — the selected-state marker, also a rotated square —
     becomes a dot, and its offsets stop dodging an 11px chamfer (`11px/12px` →
     `12px/13px`). It keeps carrying the state; only the shape changes.
   - The lapidary toggle register (`.gem-field`, `.gem-check`, `.toggle-stack`
     and the four jewel tones) is **deleted from `app.css`**. Slice 16 unified
     every boolean on the design system's Switch and left these with zero
     template consumers; ADR 0024's closeout recorded that as **C-50** and
     deferred it. **This closes C-50.** Nothing rendered them, and the gem glyph
     was the last `clip-path: polygon` in the file.

   The design system keeps its `.gem-*` copy — it is still a documented component
   there with a gallery card — so this is a production deletion of dead CSS, not
   a retirement of the component. The two star marks are brand, not ornament, and
   are untouched.

   `AppImladrisFidelityTest::test_lapidary_toggle_css_covers_gem_variants_and_captions`
   asserted those selectors were *present*, which pinned the dead CSS in place.
   It is replaced by
   `test_the_orphaned_lapidary_toggle_register_is_gone_from_the_application_css`,
   which asserts the inverse and additionally that no `clip-path: polygon` and no
   `rotate(45deg)` survives in any declaration.

7. **The design canvases keep the chamfer, deliberately.** The nineteen inline
   octagons in `templates/account-settings/AccountSettings.dc.html` and the five
   in `Compose` (2), `ReadingRooms` (2) and `Search` (1) — twenty-four in all —
   are upstream's record of what was
   designed; rewriting them would move `design_surface.sha256` and rewrite
   history the mirror exists to preserve. The divergence is recorded in
   `LOCAL_RECONCILIATION.md` so the next sync does not reintroduce eight-layer
   frames into `components.css`.

## Consequences

- Every engraved control gains a visible focus ring where it previously had
  none. This is a behaviour change, and an accessibility improvement.
- The compose title field and board select no longer paint gold on focus.
- `.scribe-panel.is-flush` (`overflow: hidden`) now clips its head strip to a
  12px radius rather than to an octagon.
- The account lifecycle delete panel retains its 3px danger rule on the left.
  It now replaces that side of the rounded gold border instead of sitting
  inside an octagonal frame: three gold sides and one red leading side.
  `account-lifecycle.png` records the accepted silhouette in both registers.
- A frame's state is one declaration, so `--gold-*` and `--danger` retints work
  by `border-color` in both registers. The frames remain painted from primitive
  gold tokens, which `DESIGN.md` discourages in application CSS — status quo
  preserved on purpose, so this diff is about corners only. Retinting them to
  `--rule-gold` is a separate, reviewable change.
- Two browser specs asserted the construction and are rewritten:
  `thread-view-study.spec.ts` counted `linear-gradient` layers, and
  `field-error-a11y.spec.ts` watched `box-shadow` alone.
- ADR 0027's deferral of "the design's borderless, octagonal `clip-path` title
  field" is **closed as obviated** — the octagon it deferred adopting no longer
  exists in either stylesheet.

## Evidence

`docs/evidence/chamfer-removal/` — notes plus captures of the repainted frame
families, including the lifecycle danger panel, at desktop and phone widths in
both registers. The 2026-09-20 review correction explicitly stamps and verifies
`data-theme="light"` / `"dark"` before style assertions and captures.

| run | result |
|---|---|
| `composer check:imladris` | `Imladris runtime assets are current.` |
| `vendor/bin/phpunit --testsuite unit` | 658 tests, 7427 assertions, OK |
| full integration suite, sharded 4× on private DBs | 2122 tests, 12911 assertions, 1 failure |
| `npm run evidence:chamfer` (desktop + mobile) | 24 passed |
| Original `RB_BROWSER_DARK_SURFACES=1` run | 8 passed in light; invalid as twilight evidence |

The original seven `twilight/desktop` images duplicated their light counterparts.
`RB_BROWSER_DARK_SURFACES` controls seed feature fixtures, not appearance.
The corrected spec runs both appearance registers by default. On 2026-09-20 the refreshed frame and
field-error run passed 66 checks, and the full PHPUnit suite completed 2780 tests
without failures (6 deprecations, 1 skip); see the evidence notes for the isolated
DB setup and explicit unconfigured-mail test environment.

The one integration failure is
`AppAdminEmailTest::test_unconfigured_transport_blocks_test_send_and_shows_blocked_banner`,
reproduced identically on a clean worktree at `5b516751`: it asserts the
behaviour of an *unconfigured* mail transport and the local `.env` configures
one. Two `account-console.spec.ts` failures were reproduced the same way and are
likewise pre-existing; the axe one is `aria-controls="sidebar-nav"` on
`.forum-bar-railtoggle`, a member-chrome ARIA defect unrelated to borders.

`tests/browser/chamfer-removal.spec.ts` is wired into `npm run evidence` (and a
standalone `npm run evidence:chamfer`), so CI runs it. That script also picks up
`field-error-a11y.spec.ts`, which no npm script had reached before — which is how
it stayed red without anyone noticing.
