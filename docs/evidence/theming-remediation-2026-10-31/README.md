# Admin theming release qualification — 2026-10-31

The approved appearance review and asset-removal follow-up are qualified in an
isolated release checkout based on `4162a90a`. The candidate excludes the primary
checkout's pending composer, draft-recovery, and mobile account changes. Exact
application/test inputs are recorded in `qualified-files.json`.

Publication and deployment receipts are retained separately from this local
qualification record so proof recording does not trigger another deployment.
No live branding settings were changed during local verification.

## Results

| Check | Result |
|---|---|
| Full final `composer test` | 3,384 tests, 30,203 assertions, zero failures; one intentional skip |
| Focused Chromium browser suite | 20 distinct cases passed: desktop 1280×800 and mobile 390×844 |
| Focused WebKit browser suite | 6 passed at 390×844 |
| `npm run check:assets` | Passed; generated assets and manifest current |
| `npm run test:assets` | 35 passed, zero failures/skips |
| Imladris generation/contracts | Covered by the full PHP suite |
| Final diff whitespace check | Passed |

The PHP skip is
`AppThreadIntelligenceMigrationTest::test_0077_down_and_up_rehearsal_on_fixture_free_schema`:
the pre-existing destructive migration rehearsal intentionally runs only on its
separately named throwaway database. No migration inputs changed in this repair.

The initial package regressions failed before implementation (five tests), as
did the unsafe-colour/Retro backend regressions and the browser's Dark preview
assertion. A further rendered regression measured the admin count at 1.30:1
with a black accent before correcting its ink token; that check now passes.

The initial isolated Chromium run passed the original 16 cases and failed the
four new removal cases while uploading light/dark logos. The upload used an
unsupported attachment purpose. After mapping both variants to the supported
`brand_logo` type, all four failed cases passed in a targeted rerun; the earlier
16 cases do not exercise the changed upload-purpose mapping. The six WebKit
cases then passed on the corrected candidate. This is a reconciled result, not
one uninterrupted 20-case Chromium run. A real-upload PHP regression reproduced
the original HTTP 500 and now verifies all four finalized assets. The full PHP
suite was rerun after that final application correction.

## Behaviors exercised

- Preview-only package assets require the specific admin session and use
  `private, no-store`. A guest and another admin cannot fetch them.
- Previewing another package sharing an active asset preserves guest asset
  delivery and the active build ID. Ending or disabling a preview removes its
  private asset access. PHP regressions also cover safe mode, feature gates,
  quarantine, and package-preview branding stylesheet isolation.
- Unsafe primary/accent colours return 422 without persisting the draft.
  Explicit acknowledgement is tied to the reviewed candidate and recorded in
  `branding_contrast_override`. Changing the colours requires a fresh review.
- Retro text, login fields, headings and links meet the asserted contrast
  thresholds in Light, Dark, and System with a dark device preference.
- The local sample changes mode/preset while the admin page remains unchanged;
  its rendered surface and link colours match the saved guest appearance.
- Unsaved logo variants preview before upload, System uses the base logo, and
  clearing a file selection restores the saved fallback.
- Each logo variant and favicon can be removed independently while retaining
  other assets and branding settings. Pending removal previews the logo fallback,
  survives validation, and is recorded in `branding_update.removed_assets`.
  Selecting removal and replacement for one asset returns 422 without writing.
- Suspended and banned admins can read branding but cannot save or reset it.
- Without JavaScript, validation preserves text, reset requires `RESET`,
  contrast acknowledgement saves, safe-mode rollback remains usable, and a
  successful rollback clears the old preview. Safe-mode recovery remains
  reachable with the feature flags off.

## Representative captures

- [Desktop pending dark-logo removal](chromium/desktop/pending-dark-logo-removal.png)
- [Mobile no-JS pending removal and contrast review](webkit/mobile/pending-removal-contrast-review-no-js.png)
- [Mobile individual removals completed](webkit/mobile/individual-marks-removed.png)

- [Desktop Retro/Dark candidate](chromium/desktop/retro-dark-live-preview.png)
- [WebKit mobile Retro/Dark candidate](webkit/mobile/retro-dark-live-preview.png)
- [WebKit mobile contrast review](webkit/mobile/contrast-warning-before-save.png)
- [WebKit mobile readable Retro/Dark login](webkit/mobile/retro-dark-login.png)
- [Mobile rollback available during safe mode](chromium/mobile/safe-mode-rollback-available.png)
- [Mobile rollback clears preview without JavaScript](chromium/mobile/rollback-clears-preview-no-js.png)
- [Mobile unsaved-logo preview](webkit/mobile/unsaved-logo-preview.png)

The warning captures intentionally include the candidate's unreadable sample:
the live branding is unchanged until the operator acknowledges the warning.
The browser fixture uses signed synthetic packages and generated logo pixels.
Mobile evidence is browser emulation, not physical-device or screen-reader proof.
The colour checks cover normal content surfaces and relevant action ink; they
do not certify arbitrary custom CSS or every specialized surface.

## Reproduction and preservation

Use `tests/browser/theming-remediation.spec.ts` with the standard browser seed,
an explicitly selected throwaway `DB_DATABASE`, isolated package/upload/rate
stores, and `RB_EVIDENCE_DIR` pointing to a new output directory. The fixture
refuses a normal application database. Run both standard viewport projects;
for the six focused WebKit cases set `E2E_LAYOUT_BROWSER=webkit`, select mobile,
and filter `branding contrast|Retro keeps|preview changes|preview shows|branding removes individual|branding removal survives`.

A verified pre-edit archive retained 867 pre-existing changed/untracked files.
Before publication, a second verified recovery snapshot retained 916 primary
checkout files, the original index, HEAD and stash inventory at
`/home/ubuntu/.local/state/community-forums/theming-release-20261031T125647Z/`.
Only scoped changes were transferred into the managed release worktree. Shared
app CSS/JS changes were derived against the original snapshot, and generated
assets were rebuilt from isolated sources. The primary checkout and its unrelated
work remain protected; the release does not include its draft/composer changes.
