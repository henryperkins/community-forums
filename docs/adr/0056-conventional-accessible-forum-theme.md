# ADR 0056 — Conventional accessible Forum theme

**Date:** 2026-11-07
**Status:** Accepted direction; implementation and deployment evidence reported separately.

## Context

Henry requested a conventional, accessible forum interface and the deprecation
of Imladris, supported by research into reading typography, contrast,
accessibility and current UI libraries. The existing product is server-rendered
PHP with progressive enhancement, strict CSP, operator branding, theme packages
and persisted member appearance settings.

The old all-serif, ornamental and fantasy-language constraints no longer match
the requested direction. Replacing only colors would leave small interface
labels, reading density, old defaults and contradictory design instructions.

## Decision

Adopt the built-in **Forum** presentation documented in root `DESIGN.md`:
system typography, neutral light/dark surfaces, blue actions, familiar forum
language, visible control boundaries, clear focus, readable discussion measure
and responsive wrapping. Target WCAG 2.2 AA and record product recommendations
separately from normative criteria.

Keep PHP-rendered HTML, native controls, existing routes and progressive
enhancement. Add no general UI runtime as part of the replacement. The library
assessment records where selective adoption might be useful later.

Generate `forum-base.css` from retained compatible component structure and
`forum-theme.css` from the active tokens/presentation. Put `app.css` in the
intermediate `application` cascade layer. Preserve later editor, package and
operator-branding precedence. Remove the old default font preload. Update the
package contrast baseline and branding previews together with the theme.

Retain established preference keys and stored community content. Rename
user-visible appearance labels to describe the current behavior. Do not rewrite
custom reaction names, reputation history, uploaded logos or authored posts.
Existing operator packages and colors keep their explicit precedence and
contrast-review controls.

## Deprecation and rollback

This decision supersedes earlier Imladris-only visual rules, font requirements
and mandatory fantasy terminology. Its authoring bundle, former design document
and generated assets remain historical. Retained published asset URLs are still
served according to the existing release-retention policy.

This is an application presentation change, with no schema migration. Reverting
the release restores the previous shell/assets without converting member data.
Do not add a second active legacy-theme toggle as part of this work.

## Evidence and limits

Follow ADR 0051: review the diff, run the final application suite, verify changed
generated assets and Worker inputs, and capture focused browser evidence.
Test actual rendered contrast and interactions in addition to token arithmetic.
The accessibility research includes a separate manual assistive-technology gate;
automated checks alone do not establish full WCAG conformance.

The implementation is reviewable before merge. Production deployment and
verification require their own recorded outcome.

## References

- [Forum accessibility and reading guidance](../design-system/forum-accessibility.md)
- [UI library evaluation](../design-system/forum-library-evaluation.md)
- [WCAG 2.2](https://www.w3.org/TR/WCAG22/)
- [USWDS typography guidance](https://designsystem.digital.gov/components/typography/)
