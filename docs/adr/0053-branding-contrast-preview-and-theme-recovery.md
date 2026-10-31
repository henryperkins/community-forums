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
