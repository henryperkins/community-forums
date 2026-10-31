# Qualification logs

All commands ran from the isolated `fix/admin-branding-recovery` worktree on
2026-10-31. PHP and browser runs used separate throwaway databases; neither
production settings nor the primary checkout were modified. Test databases,
their temporary grants and the two dependency symlinks were removed after the
passing runs. The Playwright server on port 8040 was confirmed stopped.

## Final results

| Check | Command / scope | Log |
|---|---|---|
| Full PHP | `composer test`, after the final Retro fallback change | [3,437 tests / 31,139 assertions / one intentional skip](full-php-final-retro.txt) |
| Asset freshness | `npm run check:assets` | [Passed](asset-check-final.txt) |
| Asset/Worker tests | `npm run test:assets` | [35 passed](asset-tests-final.txt) |
| Imladris generation | `composer build:imladris` | [Build](build-imladris.txt) |
| Browser assets | `npm run build` | [Build](build-assets.txt) |
| Existing branding journey | `gate-a.spec.ts -g 'phase 3 branding preview and product-tour replay'` | [2 passed](branding-journey-qualified.txt) |

Browser commands use `npx --prefix tests/browser playwright test -c
tests/browser/playwright.config.ts` followed by the test files and optional
filter. `E2E_LAYOUT_BROWSER=webkit` selects WebKit; desktop and mobile are the
configured projects. Evidence output is selected with `RB_EVIDENCE_DIR`.

## Reconciled browser qualification

The combined runs selected `admin-appearance-followup.spec.ts`,
`theming-remediation.spec.ts` and `branding-public-theme.spec.ts`.

- Chromium: [60/62 combined](chromium-final.txt), then [2/2 corrected title-hover
  assertions](chromium-preview-rerun.txt). Both maintained the actual preview /
  public action-colour and 4.5:1 assertions.
- WebKit: [59/62 combined](webkit-final.txt), then [4/4 recovery cases](webkit-recovery-rerun.txt)
  covering all three failures. The helper waits for the activation-form animation
  and completed preview-link state. The same changes passed [4/4 Chromium
  recovery checks](chromium-recovery-sync.txt).
- After the final Retro-default correction, filter
  `cleared Retro|unsaved preview matches PHP|retro (light|dark):` passed
  [8/8 Chromium](retro-default-chromium.txt) and [8/8 WebKit](retro-default-webkit.txt).
  Each contributes two new viewport cases and repeats the six cases affected by
  the palette change. Distinct qualified cases per engine: 64.

The branding journey's original [two obsolete preview-bar expectations](branding-journey-final.txt)
were replaced by checks for the real raised header surface, branded identity
text and primary button. The final journey log above passes both viewport cases.
No browser timeout increase, forced click or application workaround was used
to resolve test synchronization.

## Regression and diagnostic history

- Palette propagation: [RED](palette-red.txt), [first GREEN](palette-green.txt),
  and [focused PHP delivery checks](focused-php-final.txt).
- Independent-review Messages finding: [rendered RED](messages-red-confirmed.txt)
  and [four passing rendered cases](messages-green.txt).
- Retro dark default: [failing 2.216:1 unit regression](retro-default-red.txt);
  the final PHP and browser logs above cover its correction to 7.315:1.
- Full-suite stale literal CSS contracts: [initial failure](full-php.txt),
  [31 corrected contract checks](style-contracts.txt), and
  [passing suite before the Retro-default addition](full-php-final.txt).

The one PHP skip is the deliberately separate migration rehearsal requiring
`retroboards_thread_intelligence_clean`. It is not a failing or silently omitted
branding check. Screenshots and computed pairs are in the parent evidence
directory; local browser emulation is not physical-device or screen-reader proof.
