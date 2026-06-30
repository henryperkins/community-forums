# Phase 3 Assistive-Technology Audit

**Date:** 2026-06-30
**Status:** Pending formal manual audit

## Required Environments

- Keyboard-only Chrome
- NVDA + Firefox on Windows 11
- VoiceOver + Safari on iOS

## Required Surfaces

| Surface | Keyboard-only Chrome | NVDA + Firefox | VoiceOver + Safari | Notes |
|---|---|---|---|---|
| Home | Pending | Pending | Pending | |
| Board | Pending | Pending | Pending | |
| Thread | Pending | Pending | Pending | |
| Composer/upload | Pending | Pending | Pending | |
| Drafts | Pending | Pending | Pending | |
| Preferences | Pending | Pending | Pending | |
| Admin branding | Pending | Pending | Pending | |
| Admin email | Pending | Pending | Pending | |
| Admin webhooks | Pending | Pending | Pending | |
| Login | Pending | Pending | Pending | |
| Register | Pending | Pending | Pending | |

## Automated Support Evidence

The Playwright axe run is not a substitute for this formal AT audit. It is a
supporting check:

```bash
cd tests/browser
npm run a11y:prodlike
```

## Signoff

No Phase 3 accessibility/AT signoff is recorded until all required environments
and surfaces above are marked pass or accepted with documented residual risk.
