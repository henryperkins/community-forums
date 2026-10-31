# Branding keyboard recovery and appearance guidance — local qualification

This local, uncommitted candidate is based on f0bbfc03 and implements all five
findings approved after the rendered review. The plan is
[`2026-10-31-admin-branding-recovery.md`](../../superpowers/plans/2026-10-31-admin-branding-recovery.md),
and runtime behavior is documented in ADR 0053 and the branding runbook.
No production setting, publication or deployment is part of this work.

This records the earlier five-fix candidate. The subsequent combined candidate
is qualified in [public theme coverage](../branding-public-theme-coverage-2026-10-31/README.md).

## Results

| Check | Result |
|---|---|
| Focused PHP branding suite | 34 tests, 573 assertions, passed |
| Chromium appearance and existing theming regressions | 42 distinct viewport cases qualified: 40 passed initially, two corrected stale test cases passed in a targeted rerun |
| Existing branding/product-tour journey | 2 passed after resetting the isolated fixture |
| WebKit appearance regressions | 22 passed uninterrupted, desktop and mobile |
| Generated assets | Rebuilt; check:assets passed |
| Asset/Worker tests | 35 passed, no failures or skips |
| Independent read-only code review | No Critical, Important or Minor introduced defects; see independent-review.md |
| Final full PHP suite | 3,433 tests, 30,881 assertions, zero failures; one intentional migration-rehearsal skip |

## Behaviors exercised

- Enter in the reset confirmation clears branding without publishing a pending
  site name or submitting pending files. An invalid confirmation stops navigation
  and keeps the browser draft. The integration suite retains raw-request server
  validation and draft-preservation coverage.
- A Dark admin on a light device gets the System/Classic visitor sample after
  reset without JavaScript. Cleared and invalid saved enum values normalize
  before the saved template renders.
- Mobile native preview/return links move focus at 390px and 320px without
  JavaScript. Desktop retains its adjacent sample and hides the mobile shortcuts.
- Colour guidance follows preset/mode, shows inherited defaults, and does not
  replace a typed override. No-JavaScript System guidance lists Light and Dark
  values; enhancement resolves device preference and displays a swatch.
- The existing upload-intake, selection feedback, independent removal,
  contrast acknowledgement, private package-preview, activation, safe-mode,
  rollback, field-boundary, enlarged-text and forced-colour checks remain covered.
- The Custom CSS notice explains its existing recovery restriction.

## Representative renders

- [Keyboard reset retains the saved identity](chromium/desktop/keyboard-reset-keeps-identity.png)
- [Saved no-JS visitor sample beside a Dark admin form](chromium/desktop/no-js-reset-visitor-preview.png)
- [Mobile inherited colour guidance and preview shortcut](chromium/mobile/inherited-colour-guidance.png)
- [WebKit no-JS return focus at 320px](webkit/mobile/mobile-preview-return-no-js.png)
- [WebKit keyboard reset](webkit/mobile/keyboard-reset-keeps-identity.png)

The application/test input hashes and generated asset identity are recorded in
qualified-files.json. Full logs are under logs/.

## Reconciliation and boundaries

The two new defect regressions failed on the original implementation: Enter
published the pending name, and the saved no-JavaScript sample emitted empty
mode/preset attributes. The helper/navigation checks also failed before their
implementation.

The initial Chromium run passed 40/42 cases. Two older tests waited for a 422
response from an empty reset confirmation; native validation now correctly
prevents that request. Their assertions now check the blocked validation and
preserved draft; both cases passed in a focused rerun. Application inputs did
not change. This is a reconciled result, not an uninterrupted 42-case pass.
The broader branding journey's equivalent old assertion was updated as well. Its first attempt started with an asset left by a different fixture because the reset command used the wrong working-directory-relative path. The final journey run resets the isolated fixture first; see logs/branding-journey-final.txt.

The first full PHP attempt inherited the developer's WebAuthn relying-party
domain because the initial test environment used the wrong override variable
name. It completed with 66 account/passkey failures. The final run uses an empty
WEBAUTHN_RP_ID so each test URL supplies its own host; developer configuration
and application inputs were unchanged. Results of that final run are above.

All browser/PHP data, media, package files and rate limits use private throwaway
locations. Mobile and forced colours are browser simulations, not physical-device
or screen-reader qualification. Synthetic package fixtures deliberately include
harsh/incomplete palettes; the runs establish operator behavior and layout,
not accessibility or visual quality of arbitrary package/custom CSS.
