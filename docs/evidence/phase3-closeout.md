# Phase 3 closeout evidence

**Date:** 2026-06-28

This note records the evidence produced in the final Phase 3 engineering
closeout pass. It is not a product-owner acceptance record.

## Commands

| Command | Result |
|---|---|
| `vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php` | 8 tests / 35 assertions passed |
| `cd tests/browser && npx playwright test -g "phase 3 composer"` | 2 tests passed |
| `cd tests/browser && npm run evidence` | 10 tests passed; regenerated desktop/mobile screenshots |
| `composer test` | Passed: 430 tests, 1481 assertions |

Browser plugin tooling was not available in this Codex tool context, so the
repository's Playwright harness was used directly.

## Browser Evidence

`tests/browser/gate-a.spec.ts` now captures 19 named surfaces for both desktop and
mobile. The Phase 3-specific paths are:

- `15-reading-preferences`
- `16-drafts-view`
- `17-composer-upload`
- `18-branding-preview`
- `19-tour-replay`

The composer journey asserts:

- reading preferences default to 20/20;
- toolbar state and preview render bold Markdown and `:smile:` as an emoji;
- local drafts survive reload, appear in Drafts, and discard cleanly;
- image drop/upload creates a media Markdown reference and alt text can be edited;
- successful topic submission clears the submitted local draft.

The branding/tour journey asserts:

- admin branding preview updates name and colors client-side;
- replay-tour entry point is visible when the feature is enabled;
- the tour dialog exposes `aria-modal`, responds to Escape, and restores focus.

## Remaining Non-Code Sign-Offs

The following must be accepted by the appropriate owner before a production Phase
3 release is formally closed:

- product-owner Phase 3 acceptance;
- production-like load/soak evidence on the target deployment profile;
- formal accessibility/assistive-technology audit;
- formal security/privacy review.
