# ADR 0053: Branding contrast, previews, and protected theme recovery

Date: 2026-10-31. Status: accepted implementation of the approved admin appearance review.

## Decision

- Resolve package assets within an eligible active build before looking in the
  current admin session's eligible preview build. Public active assets retain
  immutable caching; preview-only assets use `private, no-store`. Safe mode,
  feature gates, and package eligibility apply to both paths.
- Keep last-known-good rollback available while safe mode hides the active and
  preview summaries (ADR 0024 FC-08). Rollback keeps safe mode on and requires
  the current password. Successful rollback clears the session preview.
- Define complete Retro light/dark palettes and share resolved palette data
  between stylesheet delivery, the branding sample, and contrast review.
  System follows the device colour preference; existing logo selection remains
  explicit light/dark variant or base logo for System.
- Measure operator colours as foreground links and indicators on normal content
  surfaces in both modes, as well as button ink. A warning returns the form
  with its draft intact. Saving a warning requires acknowledgement bound to the
  exact colours, preset, resolved public palette, and warnings. A changed
  candidate or intervening active-package change requires another review.
- Commit the branding settings and `branding_contrast_override` audit record
  together. This completes ADR 0009's override-and-audit requirement and resolves
  the policy gap recorded in ADR 0024; it is not a claim that arbitrary custom
  CSS, every specialized surface, or every package is accessible.
- Keep sample variables local to the preview, leaving the form and recovery
  controls in the operator's existing appearance. With JavaScript, candidate
  modes/presets/colours and local logo files update before saving. The no-JS
  form remains usable and labels its saved-colour/logo preview accurately.
- Brand stylesheet identity includes the serving package state. An explicitly
  requested session-preview variant is private and never enters shared caches.
- Provide independent removal checkboxes for each saved logo variant and the
  favicon within the branding save form. Validation preserves pending removals;
  a removal and replacement for the same asset is rejected. Clearing a saved
  path is audited with the save and retains its stored media file, matching
  full-reset retention. JavaScript previews the resulting logo fallback.

## Scope and evidence

Custom CSS remains safety-blocked. The full token editor and a standalone
package Deactivate action are outside this repair.
Runtime instructions are in the branding and package-themes runbooks. Local
regressions and rendered evidence are linked from
`docs/evidence/theming-remediation-2026-10-31/README.md`; they do not establish
production deployment or physical-device qualification.

## Follow-up operator review (2026-10-31)

The owner subsequently authorized all five findings from a fresh rendered review:

- Check PHP upload error metadata before saving any submitted branding. Return
  the intact draft and pending removals with per-asset errors and a reselect
  notice. Keep the established, explicit partial-success contract for images
  rejected later by content processing. An entirely discarded POST gets a 413
  diagnostic inside the existing failed-CSRF branch; it authorizes no write and
  states that its unreceived draft cannot be restored.
- Use the semantic field-rule colour for branding and native file boundaries,
  with measured 3:1 minimum contrast against adjacent built-in surfaces in
  Classic/Retro, light/dark. Keep danger and keyboard-focus states.
- Retain the desktop package table and its semantics while stacking package
  metadata/actions at the existing 900px breakpoint. Remove the console's table
  minimum only for this surface and wrap long build digests.
- Make both recovery surfaces state their exact scope: branding reset retains
  the community name and package state; package safe mode retains branding.
- Expose native filename feedback, saved thumbnails, configured requirements,
  enhanced pending previews/status for every asset, and local selection clearing.

The implementation record is
`docs/superpowers/plans/2026-10-31-admin-appearance-followup.md`; current validation
is retained in `docs/evidence/admin-appearance-followup-2026-10-31/README.md`.

## Keyboard recovery and appearance guidance (2026-10-31)

The owner approved the five findings from the subsequent rendered review:

- Give reset an independent form with a hidden action, native typed-confirmation
  constraints and the existing server validation. Enter resets without saving
  pending branding edits/files; an ordinary invalid attempt stays on the page.
- Normalize cleared/invalid mode and preset values to System/Classic before
  rendering the saved preview, including without JavaScript.
- Keep one preview and the desktop arrangement; mobile preview/return links
  place both destinations within reach and move keyboard focus without JavaScript.
- Show resolved colour defaults, explain inheritance, and enhance the helper
  with device-aware values/swatches while preserving typed hex overrides.
- Explain the existing Custom CSS recovery restriction in operator language.

The implementation record is
`docs/superpowers/plans/2026-10-31-admin-branding-recovery.md`; evidence is retained
in `docs/evidence/admin-branding-recovery-2026-10-31/README.md`.

## Public component coverage (2026-10-31)

A real admin save followed by fresh guest/member views confirmed that dynamic
settings and cache versioning worked, but selected navigation/menu states and
rail indicators still consumed the built-in brand/gold families. The owner
approved comprehensive remediation of that propagation review.

- Resolve primary actions, links, focus, hover/press and selected fills/ink as one
  family. Resolve accent highlights, unread counts, selection rules and semantic
  gold ink/washes as the second. Preserve primitive scales and independent
  status, presence and constant-ground families. Move the decision/moderator and
  draft-conflict consumers off mutable brand washes; use derived selection ink
  for active icons.
- Derive opaque soft fills from the resolved raised surface, with readable
  foregrounds. Cap action darkening where needed to keep the original label ink
  readable. Retain exact chosen link/indicator colours and their explicit
  contrast warnings across light/dark content surfaces. Review actual selected,
  hover/pressed and unread-count text pairs as well.
- Deliver only changed override roles after package CSS, except for the complete
  built-in Retro preset. Include the resolver version in the brand stylesheet
  URL. Clearing colours restores the underlying package/preset roles.
  Retro dark explicitly pairs its amber selected fill with dark text; reusing
  Classic's parchment ink gave only 2.22:1 contrast after clearing overrides.
- Render real public component classes in the scoped preview. Show the feed
  selection persistently, rather than relying on a disclosure that closes while
  the operator edits. PHP and JavaScript use equivalent deterministic colour
  recipes with browser parity checks, including clearing already-saved overrides.
- Explain next-page-load timing, member appearance preference and package/base
  precedence beside the controls. Preserve private package preview and public
  contrast-review separation, no-JavaScript saving and recovery.

The implementation plan is
`docs/superpowers/plans/2026-10-31-branding-public-theme-coverage.md` and the
combined local qualification is in
`docs/evidence/branding-public-theme-coverage-2026-10-31/README.md`.
