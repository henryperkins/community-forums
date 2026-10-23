# Selective design-stash reconciliation — 2026-10-23

Reconciled the documentation in stash
`523fc269e4ee170e72db27fa8a3a50c39ca8e833` against main
`9178f170f65324edd73ce683331db3cc9c500119`. The stash remains intact.
This change modifies `DESIGN.md` and `.impeccable/design.json`; it changes no
application source, template, generated asset, or feature availability.

Retained from the stash:

- Delivered font-weight guidance, checked against the shipped font faces. The
  wording distinguishes requested weights from supplied faces and acknowledges
  older selectors that still need review; it does not claim all legacy weights
  were fixed by this documentation change.
- Updated topic-row anatomy and specimen, monogram sizing, tag/profile-rank
  descriptions, and the flat Study stream's post spacing.
- The invalid-field state, native confirmation/disclosure patterns, and the
  already-shipped guarded-toggle description.
- The sidecar's Chrome-On-Top Rule, which already exists in `DESIGN.md`.

Retained from current main:

- The exact switch description: 42×24 track, 18px knob, inset shadow and
  `--dur-base` transitions. The stash's 38×22 / 16px / fast-transition replacement
  was rejected after checking the final `.switch` rules in `app.css`.
- Account settings' 44px targets, pane-width responsiveness, linear organization
  sections, delivered label typography, subscription actions and masking.
- The current Ruled Section Navigation specimen, colour metadata, profile-cover
  guidance and Constant-Ground Rule.
- Keyboard-focus and reduced-motion rules in the topic-row specimen; the updated
  specimen also includes the author in its metadata, as the real partial does.

The four authored token files (`colors`, `typography`, `spacing`, `fonts`) were
compared byte-for-byte with their generated copies on 2026-10-23. JSON parsing,
unique named rules, exact switch preservation, current account guidance,
navigation-example preservation and colour-rule preservation were checked.
No new application tests were needed for this documentation-only follow-up; the
runtime qualification is recorded in [README.md](README.md).

The two original untracked review records in the primary checkout were preserved
byte-for-byte, with checksum-verified recovery copies outside the repository.
The old plan's deployment checkbox is historical: the guarded-toggles production
release is already recorded in
[its committed evidence](../features-guarded-toggles-2026-10-18/README.md).
The remaining admin critique items remain tracked by
[ADR 0024](../../adr/0024-imladris-admin-account-adoption.md).
The existing guarded-toggles worktree and its dependency symlinks were retained.
