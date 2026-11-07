---
description: "Forum visual design — review, implement and verify conventional accessible forum surfaces"
agent: agent
---

# Forum visual design session

Read `AGENTS.md`, `DESIGN.md`, the relevant surface specification and ADR 0056.
Use the user's requested scope to determine whether this is review, planning or
implementation work. State the intended visual rules before changing styles.
Preserve unrelated work and existing functional, authorization and data contracts.

## Sources and ownership

- `DECISIONS.md` and `PRODUCT_DESIGN.md` own product and technical decisions.
- `DESIGN.md` and `.impeccable/design.json` describe the active Forum presentation.
- `resources/forum/tokens.css` and `theme.css` own its tokens and visual rules.
- `resources/forum/components.css` retains compatible component structure.
- `public/assets/app.css` owns feature/layout rules in the `application` layer.
- PHP templates and external JavaScript define actual markup and behavior.
- `docs/design-system/forum-accessibility.md` separates WCAG requirements from
  reading-design choices and records the validation matrix.

Imladris authoring assets and the previous prompt in `docs/history/` are
historical. Their font, ornament and lexicon rules are superseded. Do not
reintroduce an old font preload or make an Imladris digest a release gate.

## Implementation

Use semantic tokens, familiar labels, native controls, and progressive
enhancement. Keep the strict CSP: external CSS and JavaScript, with no inline
scripts or style attributes. Preserve light/dark/system appearance, stored
preference keys, operator branding and package precedence.

Keep reading content at a comfortable relative size and measure. Do not hide
content to make a layout fit. Test narrow reflow, text spacing, focus, user
preferences and real foreground/background pairs. Respect reduced motion and
forced colors. Do not infer accessibility from appearance alone.

## Verification

Follow ADR 0051. Regenerate with `composer build:forum` and `npm run build` when
inputs change. Check generated drift, run the final application suite and obtain
focused rendered evidence at affected viewports/modes. Record what was actually
run, concrete remaining failures and unverified assistive-technology behavior.
Report implementation, merge and deployment as separate states. A screenshot,
passing axe scan or plan is not a conformance certification.
