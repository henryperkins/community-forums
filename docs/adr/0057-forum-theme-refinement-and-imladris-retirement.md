# ADR 0057 — Forum theme refinement and Imladris retirement

**Date:** 2026-11-07
**Status:** Accepted. Implementation and local browser evidence are in
[`docs/evidence/forum-refinement/`](../evidence/forum-refinement/README.md).
Production deployment and verification are separate outcomes.

## Context

ADR 0056 made the conventional **Forum** theme the active presentation. Its
unlayered `forum-theme.css` restyled the main reading surfaces, but the
retained `forum.components` and `application` layers still carried Imladris
typography wherever the new layer did not reach. An audit of the merged theme
on 22 routes in light and dark at desktop and phone widths, with keyboard,
320px reflow, text-spacing, forced-colour and a 320 × 256 viewport (equivalent
to 1280 × 1024 at 400% zoom) found:

- 160 metadata declarations (breadcrumbs, timestamps, counts, inbox meta) set
  in the code face, against DESIGN.md's "system monospace for code";
- 626 text size declarations that could compute below the 0.875rem secondary
  text floor DESIGN.md sets; 135 rendered elements measured 9.28–13.8px,
  including post badges (9.3px) and settings section labels (9.9px);
- 138 rules setting capitals, most with wide tracking (OPEN, NEW/OP/STAFF, THEME, admin
  table headers), one of which clipped the Online list's Staff tag;
- link-styled controls whose labels sat 2–9px above centre, including every
  option in the phone order sheet;
- a sticky reply/guest dock covering 57% of a 256px-high viewport, hiding
  focused post links (WCAG 2.2 SC 2.4.11);
- an unstyled default-blue Online heading link, a mid-word break in the
  composer's board picker, two prohibited ARIA labels (/drafts, /admin), the
  admin bar outside any landmark and an h1 → h3 jump on /admin/branding;
- Imladris marks still drawn as functional icons (the four-point commend star
  for Star and commends, the eight-point star for Topic tools); and
- the Imladris build pipeline, source bundle, generated CSS, digest baseline,
  web fonts and star asset still in the tree, the fonts and star still in the
  published static closure.

The palette, reflow and automated WCAG scans held up; these were contract
gaps and specific defects, not a failed direction.

Primary-source research on reading and contrast informed three further
choices. CSS Fonts 4 and MDN describe `system-ui` as intended for interface
text rather than long paragraphs, not customisable on some systems and liable
to pick unsuitable faces for some locales. Reading studies do not single out
one best face for everyone, and found readers differ in which face they read
fastest. WCAG 3's contrast method is still undetermined, so WCAG 2 ratios
remain the acceptance test.

## Decision

1. **Tokens.** Add `--font-meta` (the interface face) for metadata and keep
   `--font-mono` for code and exact identifiers such as keys, hashes, hex
   values, slugs, ids, shortcodes, paths, hosts and versions. Add
   `--text-floor: .875rem`. Set discussion prose, messages and the
   composer in `--font-prose: sans-serif`, the reader's own browser default.
2. **Retained layers.** Rewrite them at the source, not with overrides:
   metadata `var(--font-mono)` becomes `var(--font-meta)` (160 declarations),
   and every text size that can compute below the floor becomes
   `max(var(--text-floor), <size>)` (626 declarations). Decorative initials and
   icon glyph sizes are exempt.
3. **Case and tracking.** The unlayered Forum layer sets `text-transform: none`
   and `letter-spacing: normal` on `body` and makes every descendant, with its
   `::before` and `::after`, inherit both. That overrides the retained layers'
   capitals and tracking at the lowest specificity, while a Forum, package or
   branding rule that sets either property still applies to its element and to
   everything inside it. Labels are written in sentence case at the source;
   stored values shown as labels pass through `label_text()`. Counts, tables
   and times use tabular numerals.
4. **Controls.** Link-styled controls with a minimum height centre their labels
   with `align-content: center`, including the phone order sheet and search
   options. On viewports at most 480px high the thread dock joins the end of
   the topic instead of sticking, and the topic reserves only 12px of scroll
   padding.
5. **Marks.** One conventional five-point star for starring and commends (the
   icon keeps its `commend-star` name for compatibility), a settings mark for
   Topic tools, a sparkle only for generated Living Brief content, and neutral,
   muted empty-state marks. The eight-point star is removed.
6. **Semantics.** The admin bar is a banner landmark; labelled draft and queue
   sections have a group role; branding section headings are h2; page titles
   append the community name.
7. **Imladris retirement.** Delete the builder, its command and tests, the
   source bundle, generated `imladris.css`, the design digest baseline, the web
   fonts and the unused star SVG, and stop publishing the fonts and star. Keep:
   the design archive in `docs/design-system/imladris/` and `docs/history/`;
   stored compatibility values (`classic`, `imladris` type/corner keys, theme
   keys); infrastructure names such as the `imladris-boards` Cloud SQL instance
   and secret; and the fingerprinted files of the three deployed releases, which
   age out under ADR 0041.
8. **Libraries.** Keep owned HTML and CSS. Prefer Baseline platform primitives
   (`<dialog>`, popover, invoker commands, exclusive `<details>`) before a
   component library. Withdraw the Bootstrap 5.3 Sass subset as the named
   fallback: Bootstrap 6 reached alpha on 2026-10-08 and v5 is heading to an
   end-of-life date after 5.4. Re-evaluate a library only for a demonstrated
   maintenance or interaction problem. See the
   [library evaluation](../design-system/forum-library-evaluation.md).

## Consequences

Metadata, labels and chips render in the interface face at 14px or larger at
the default text size (the Small reading preference scales the floor to
12.25px), in sentence case, across member, operator and moderation surfaces. Dense views get
slightly taller; phone inbox rows wrap meta onto fewer lines because the
interface face is narrower than the code face. Reading text changes face on
some platforms (for example Arial instead of Segoe UI on Windows) and now
follows the reader's browser font setting.

Tests that pinned Imladris-only details were updated to the Forum contract.
Specs that read the deleted `imladris.css` now read the generated Forum base.
The general stylesheet guards of the deleted builder suite (token registers,
variable definitions, the staff badge pair, quiet row hover, 44px settings
targets, console class ownership) moved to `ForumRuntimeAssetTest`. The
member-surface bridge in `app.css` is maintained in place; its test reverses
the two mechanical rewrites and compares it with the archived source, allowing
only listed deliberate edits.

This is a presentation change with no schema migration. Reverting restores the
previous shell and assets without converting member data. The live ADR 0056
release `5dc1b40f3b23d46d` was verified on the public site and recorded as
deployed beforehand, so this build retains its files under ADR 0041.

## Evidence and limits

Follow ADR 0051. The new `tests/browser/forum-refinement.spec.ts`
(`npm run evidence:forum`) pins the typographic contract, including
`aria-hidden` text, `::before`/`::after` text and lower-case labels; label
centring; presence tags beside long names in server-drawn and poll-rebuilt
rows; and the short-viewport dock with and without scripting and with a
member's composer. It runs axe WCAG and best-practice scans on 20 routes in
both themes at desktop and phone widths. `ForumRuntimeAssetTest` guards the
floor, identifier faces and token registers statically, and `AppLabelCaseTest`
covers labels built from stored values. The audit scripts are in
`tests/browser/forum-type-audit/`. The 320 × 256 viewport approximates 400% zoom; it is
not a recording of browser zoom. No screen-reader session has been run.
WCAG 2.2 AAA criteria (1.4.6, 1.4.8) are tracked as information, not targets:
the light link colour measures 6.70:1, below 1.4.6's 7:1.

## References

- [ADR 0056](0056-conventional-accessible-forum-theme.md)
- [Forum accessibility and reading baseline](../design-system/forum-accessibility.md)
- [UI library evaluation](../design-system/forum-library-evaluation.md)
- [MDN: `font-family` (system-ui note)](https://developer.mozilla.org/en-US/docs/Web/CSS/font-family)
- [WCAG 2.2](https://www.w3.org/TR/WCAG22/)
