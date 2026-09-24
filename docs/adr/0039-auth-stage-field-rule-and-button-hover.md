# ADR 0039: The auth stage's tokens, the engraved field rule, and a button hover that keeps its hue

**Date:** 2026-09-24
**Status:** Implemented on `harden/auth-login-logout`; verification below and in
`docs/evidence/auth-login-logout-polish/notes.md`.
**Relates to:** the 2026-09-24 `/impeccable audit` of the login and logout screens
and ADR 0038 (the harden pass on the same branch); root `DESIGN.md` (the
Semantic-Only Rule, Buttons, Inputs, the lapidary register); ADR 0024 obligation 4;
ADR 0033 (the chamfer, whose frames these are); ADR 0034 (application-only border
work); `docs/design-system/imladris/LOCAL_RECONCILIATION.md`.

## Context

The audit measured four register-specific defects on the auth screens, and one on
the index those screens hand back to:

- **Engraved frames.** Every field frame on the auth screens, account settings,
  `/appeals` and the Messages compose form drew its edge in the primitive
  `--gold-200`. That measured 1.30:1 against the parchment card and 1.22:1 against
  the field's own fill, where WCAG 1.4.11 asks 3:1 of a field's boundary. In
  twilight the same primitive drew a near-white 10.8:1 line.
- **The stage.** The auth stage, which is twilight in both registers, painted
  itself from primitives. Its focus ring took the page's `--accent`, which is
  evergreen by day: 1.75:1 around the home link and the skip link.
- **Auth links.** `.auth-links a` took `--brand`, a fill token that is green-500
  in twilight: 2.86:1 on the card, where the page's link colour reaches 7.30:1.
- **Button hover.** `.btn` rests on `--accent` but hovered on `--brand-hover`,
  which is evergreen in both registers and untouched by operator branding. A gold
  twilight button turned green on hover, and so did any branded button.
- **The guest note.** On the index, the guest note's "Log in" link was colour
  alone: 1.39:1 against the note's text by day and 1.02:1 by night (axe
  `link-in-text-block`).

## Decisions

### 1. Application-owned tokens live in `app.css`, not the layer

`--field-rule` and the `--stage-*` tokens are defined in a token block in
`public/assets/app.css`, before its two twilight registers. Primitives appear
only in those definitions.

They are not added to the design mirror's `tokens/colors.css`. On a branch that
already edits the application, `composer build:imladris` refuses to run until the
runtime baseline matches, and ADR 0024 forbids a slice branch from carrying that
baseline. This is the route ADR 0034's border work took. A later design sync can
adopt the tokens upstream.

### 2. `--field-rule` is gold-700 by day and gold-600 in twilight

Gold-700 is the lightest ramp step that clears 3:1 against both the card (3.92:1)
and the fill (3.69:1) by day. In twilight gold-700 would pass at 3.57:1 but reads
muddy; gold-600 keeps the gilt character at 4.71:1 against the card and 5.30:1
against the fill. The twilight value is restated in both `app.css` twilight
registers, as `test_both_application_dark_registers_declare_the_same_tokens`
requires. Focus and invalid states are unchanged: focus still gilds the edge to
gold-500 inside the halo, and invalid is `--danger`.

`/compose` is untouched. Its title field already overrides the engraved edge with
its own ink frame (14:1).

### 3. The stage paints from `--stage-*`, which never flip

The stage uses `--stage-ground`, `--stage-raised`, `--stage-ink` and
`--stage-accent`, plus the existing `--gold` for the star watermark. Every
computed colour on the stage is unchanged, so the stage is pixel-identical
before and after. The one exception is focus: `.variant-auth .auth-brand` and
`.skip-link` take `--stage-accent` as their outline colour, 8.20:1 in either
register.

The profile cover is the codebase's other always-twilight surface, and it still
paints from primitives. It can adopt these tokens in its own pass.

### 4. Auth links keep the page link colour

Deleting `.auth-links a { color: var(--brand) }` returns the links to the global
`a { color: var(--accent) }`. By day that is the same colour; by night it is
7.30:1.

### 5. A button hover deepens its own fill

The new rule is `.btn:hover { background: color-mix(in srgb, var(--accent) 82%,
#000) }`, the recipe `.btn.danger:hover` already uses. It needs no token, because
it follows `--accent` wherever that resolves, including operator branding.

- **By day:** #263D30 against today's green-800 #24402F. That is 3 levels or fewer
  per channel, and the before/after capture diffs to 0 pixels. Text contrast is
  10.91:1.
- **By night:** gold-400 deepens to #AC9050 rather than turning green (5.00:1).
  Text contrast is 5.57:1.

### 6. The guest note joins the shared underline rule

`.directory-guest-note a` is added to the one rule that underlines links in muted
prose (`.auth-links a, .callout a, .muted a, .empty a`).

### 7. A dead selector goes

`.dm-form:not(.composer-shell) .composer-input` was listed in the engraved-frame
rules for a standalone DM composer. Every DM form is now rendered through
`partials/composer_shell.php`, which always carries `.composer-shell` on the
server, so the selector matched nothing, with or without JavaScript. It is removed,
along with the comment that described it.

## Not in this pass

- **The design mirror.** `docs/design-system/imladris/components.css` still shows
  `--gold-200` on `.input-engraved` and `--brand-hover` on `.btn:hover`.
  Production overrides both, because `app.css` is unlayered. The divergence is
  recorded in `LOCAL_RECONCILIATION.md`, so the next sync does not restore the old
  values.
- **Disabled-button hover.** Nothing unlayered suppresses hover on a disabled
  `.btn`; the layer's `.btn:disabled:hover` loses to `app.css`. This was already
  true before this change.
- **The rest of the audit's layout and copy findings.** These are the passkey
  button's spacing, the legacy `.btn-oauth` margins, the small eyebrow text, the
  faux-bold labels, and the mixed log in / sign in vocabulary.

## Verification

- **Static.** `tests/Unit/Core/AuthGateStylesContractTest.php` (6 tests) pins:
  - no primitives in the stage rules;
  - `--stage-*` never flip;
  - focus on the stage uses `--stage-accent`;
  - the engraved edge is `--field-rule` in both registers;
  - no colour on `.auth-links a`;
  - the hover recipe.
- **Browser.** `tests/browser/auth-polish.spec.ts` checks both colour schemes at
  both widths, plus one case with an operator primary colour. It passes 19 cases,
  with 3 hover cases skipped on the touch project. On `main` the desktop run fails
  7 of 11, each at its defect; a branded `#7c3aed` button hovered to evergreen.
- **Regressions.** 20 existing spec groups ran, each on a fresh seed. Every
  failure reproduces on `main` except four `composer-expansion` timeouts. Those
  came from ADR 0038's `no-store` default, not from this CSS, and were fixed by
  scoping that default to HTML (see ADR 0038).
- **Before and after.** A pixel diff of 38 capture pairs shows changed pixels only
  on the engraved edges, the auth links by night, the stage focus ring by day, the
  guest-note underline, and the twilight button fill.
