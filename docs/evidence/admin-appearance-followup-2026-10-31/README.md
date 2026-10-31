# Admin appearance follow-up — local qualification

All five findings from the 2026-10-31 branding/theme review are addressed in a
local, uncommitted candidate based on `97bef6cf`. No live branding or deployment
was changed. Runtime scope is recorded in ADR 0053 and the branding/package-theme
runbooks; implementation decisions are in
[`2026-10-31-admin-appearance-followup.md`](../../superpowers/plans/2026-10-31-admin-appearance-followup.md).

## Final results

| Check | Result |
|---|---|
| Final `composer test` | 3,432 tests, 30,873 assertions; zero failures; one intentional skip |
| Chromium focused follow-up + existing theming regressions | 32 passed, desktop and mobile |
| WebKit follow-up regressions | 12 passed, desktop and mobile |
| Existing theme lifecycle and branding/tour journeys | Four distinct viewport cases qualified across reconciled runs |
| Generated assets | Rebuilt; `npm run check:assets` passed |
| Asset/Worker tests | 35 passed; zero failures/skips |
| Independent code review | Transport/CSRF and selection behavior accepted; identified wrapping and stale journey assertions were corrected |
| Diff whitespace | Passed |

The PHP skip is
`AppThreadIntelligenceMigrationTest::test_0077_down_and_up_rehearsal_on_fixture_free_schema`.
It intentionally requires its separately named destructive-rehearsal database;
no migration inputs changed.

Final logs are under [`logs/`](logs/). Exact application/test/document inputs and
the generated asset identity are in [`qualified-files.json`](qualified-files.json).
Rendered boundary measurements and disabled-branding recovery copy are retained
in [`rendered-measurements.json`](rendered-measurements.json).

| Built-in palette | Light minimum | Dark minimum |
|---|---:|---:|
| Classic | 3.69:1 | 4.71:1 |
| Retro | 4.29:1 | 5.94:1 |

The [independent review record](independent-review.md) lists the findings and
their verified corrections.

## What the regressions prove

- PHP size, partial, storage-intake and malformed-metadata errors return 422
  before settings, other files or removals are saved. Submitted values and
  removal choices survive; errors identify the affected asset and explain file
  reselection. A real 9 MiB file exercises the deployed 8 MiB PHP file cap.
- An entirely discarded 11 MiB POST exercises the 10 MiB request cap. It returns
  a specific 413 from the failed-CSRF path, authorizes no mutation, and states
  that values the server never received cannot be recovered. Normal invalid
  CSRF tokens still return 403.
- Branding and native file field boundaries meet the asserted 3:1 minimum in
  Classic/Retro and light/dark. Forced-colour focus is captured. Existing
  contrast-warning acknowledgement and audit behavior remains intact.
- Package metadata, Preview, password entry and activation fit at 900, 390 and
  320px, while the 1280px table remains usable. A valid 180-character unbroken
  package name and full build digests wrap in the table and preview summary.
  A 200% root-text stress check at 320px also keeps admin controls in bounds.
- Native filenames remain visible without JavaScript. Saved thumbnails,
  pending favicon/inactive-logo previews, unusual filenames and Clear selection
  are exercised; clearing a selection preserves stored files. Existing
  independent-removal and fallback tests still pass.
- Branding reset retains the community name and active package state. Package
  safe mode retains branding, and the guest page demonstrates the retained
  Retro palette. Recovery copy also qualifies the branding-disabled case.
- The existing package-preview isolation, shared active assets, rollback,
  logo removal, no-JS validation and typed reset regressions remain green.

## Representative captures

- [Desktop branding and stronger boundaries](chromium/desktop/field-contrast-classic-light.png)
- [WebKit mobile pending favicon and dark logo](webkit/mobile/pending-favicon-and-dark-logo.png)
- [Native filename without JavaScript](webkit/mobile/native-filename-no-js.png)
- [Mobile package actions without JavaScript](chromium/mobile/package-actions-no-js.png)
- [Upload error preserves the draft](chromium/mobile/php-upload-error-preserves-draft.png)
- [Entirely discarded request](webkit/mobile/discarded-request-no-js.png)
- [Recovery explains retained branding](webkit/mobile/safe-mode-explains-retained-branding.png)

The synthetic signed texture package has deliberately incomplete palette tokens
and can paint a red background/unreadable sample in populated-preview captures.
Those captures prove layout and private-preview behavior. Axe runs after ending
that preview against the built-in operator surface; they do not certify arbitrary
packages or custom CSS. Logo fixtures are generated pixels, not production marks.
Browser emulation and forced-colour simulation are not physical-device or
screen-reader qualification.

## Reconciliation and preservation

The PHP intake regressions first failed with successful redirects; all passed
after explicit error handling. The browser regressions first reproduced 1.14:1
boundaries, horizontal package scrolling and absent pending-selection feedback.
Independent review found stale assertions and inherited wrapping rules. The final
Chromium 32-case and WebKit 12-case runs each passed uninterrupted on the corrected
application candidate.

The first full PHP attempt reused the browser URL while the developer's RP-ID
still named the local tunnel, causing 66 unrelated account/passkey failures and
revealing one stale safe-mode assertion. A test-only empty RP-ID override lets
each configured test URL supply its own RP-ID; developer configuration was not
edited. The final full PHP run then passed as shown above.

Both existing theme lifecycle journeys passed. The older branding journey first
encountered a favicon left by another fixture, then exposed its pre-ADR-0053
assumption that unsafe colours could save without acknowledgement. After resetting
the isolated fixture and updating the journey to exercise contrast review and
file reselection, both branding/tour viewport cases passed. This is four distinct
qualified journey cases across reconciled runs, not one uninterrupted four-case run.

All browser and PHP data, package artifacts, media and rate-limit state used
dedicated throwaway locations. No unrelated source changes were present at start.
During publication preparation, live byte comparisons confirmed that the preceding
release `108dd69071d95f7a` was serving but had not been pinned. Its verified files
were retained and the oldest recorded pin retired under the existing three-release
policy. The current application bundle stayed `1b23e2c998991678`; application/PHP
test sources were unchanged. Generated-asset and all 35 asset/Worker checks passed
again after the retention update. This records the preceding verified live release,
not the new candidate as deployed. The changelog heading was dated for publication.
