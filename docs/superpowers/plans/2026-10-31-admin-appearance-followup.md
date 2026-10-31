# Admin appearance follow-up implementation plan

**Goal:** Resolve all five findings from the 2026-10-31 branding/theme review.

**Authority:** `DECISIONS.md`, `ADMIN.md` §6, ADR 0053, and the branding/package-theme runbooks. The user authorized comprehensive implementation after the rendered review. Work stays local and uncommitted.

**Approach:** Extend the existing PHP form and progressive enhancement. Preserve the Imladris register, the desktop package table, the separation between branding reset and package safe mode, and all permission/CSRF gates. No new dependencies or subsystem.

## Tasks

- [x] Upload failures: add failing integration/browser regressions for PHP size, partial, storage and malformed upload metadata; reject the form before settings/assets are written, retain submitted values/removals and tell the operator to reselect files. Preserve existing explicit partial-success handling for content-pipeline rejections.
- [x] Field contrast: use the existing semantic field-boundary token for branding fields and native file inputs; retain danger/focus states. Measure at least 3:1 against adjacent surfaces in Classic/Retro light/dark and check forced colours.
- [x] Mobile packages: retain the semantic table on desktop; stack each package's metadata and guarded actions at the existing 900px breakpoint. Keep no-JS behavior and prevent horizontal clipping at 390/320px and enlarged text.
- [x] Recovery copy: distinguish resetting branding from suppressing package themes, name the retained state, and cross-link the two recovery surfaces. Verify actual active-theme/branding state after each action.
- [x] Asset feedback: expose native filename feedback without JavaScript, saved thumbnails, selected previews/status and a clear-selection control with JavaScript. Show configured type/size/dimension/request limits and preserve server-side validation.
- [x] Qualify: rebuild generated assets; run the focused browser regressions in Chromium and WebKit, asset checks/tests, and one final `composer test`; review the complete diff and retain evidence. No commit, push or deployment is part of this request.

## Review focus

- One rejected upload must not save another selected asset or pending removal.
- File controls and removal/fallback behavior must remain usable without JavaScript.
- Pending favicon and inactive-mode logo selections must be visible, clearable, and safe with unusual filenames.
- Mobile package action labels, password fields and submit controls must fit together; table semantics and error associations must remain available.
- Recovery messages must describe actual state when branding and package themes coexist.

## Evidence ledger

- Baseline: clean checkout at `97bef6cf`; original review retained under `/tmp/retroboards-brand-review-20261031/`.
- Ruling: perform the authorized bounded fixes in the clean primary checkout and retain a fresh independent review at completion; no unrelated work is present.
- PHP transport regressions: three initially failed with 303; all passed after explicit intake-error handling (289 assertions).
- Browser regressions initially reproduced 1.14:1 boundaries, a 760px package table and absent selection feedback. The responsive override also must remove the console's more-specific table minimum, and the clear button needs an explicit hidden-state rule.
- Ruling: report an entirely discarded branding POST as 413 within the existing failed-CSRF branch. It never dispatches a controller or authorizes a write; ordinary invalid-token requests remain 403. Unlike a per-file error, a discarded body cannot preserve values the server never received, so the message states that limit explicitly.
- Ruling: the signed texture fixture has intentionally incomplete palette tokens. Use it to prove populated preview/layout behavior, then end the preview before running axe against the built-in operator surface; arbitrary package accessibility remains outside ADR 0053's guarantee.
- Independent review found stale journey assertions and inherited action-cell `nowrap`/wrapping rules. Correct both and pin a valid 180-character unbroken package name. The enlarged-text check also reproduced a 334px-wide admin control cluster at a 320px viewport; a local CSSOM probe proved that wrapping the existing cluster restores 320px without moving or hiding controls.
- The combined run also exposed the same unbroken name in the Preview summary; wrap summary text as well as table cells and measure after fonts are ready. Its first full PHP run had 66 unrelated WebAuthn failures because the browser-only `APP_URL` override conflicted with the developer's `.env` RP-ID, plus one old safe-mode wording assertion. Use an empty test-only RP-ID override so each test URL supplies its own RP-ID; do not change developer configuration.
- The optional existing branding journey also predated ADR 0053's contrast acknowledgement. Update it to exercise review and file reselection explicitly instead of assuming a one-submit unsafe-colour save. Reset isolated fixture assets before that journey; the separate theme lifecycle journeys already passed in both viewports.

- Final qualification: 3,432 PHP tests / 30,873 assertions (one dedicated migration-rehearsal skip), 32 Chromium cases, 12 WebKit cases, four reconciled legacy journey cases, and 35 asset/Worker tests. Application and deployed-release pins were reviewed; local artifacts are indexed in `docs/evidence/admin-appearance-followup-2026-10-31/README.md`.
