# Admin branding recovery and guidance implementation plan

> **For agentic workers:** Execute inline with superpowers:executing-plans. The owner approved the five concrete recommendations in the preceding rendered review; continue through implementation and qualification.

**Goal:** Correct keyboard reset and no-JavaScript preview defaults, and improve mobile preview access, inherited colours and Custom CSS guidance.

**Architecture:** Keep the existing PHP forms, brand palette resolver and progressive enhancement. Reset gets its own form with a hidden action and native confirmation constraints backed by existing server validation. Keep one preview and use the approved mobile jump-and-return option. Existing desktop positioning and tokens remain.

**Tech Stack:** PHP 8.2+, vanilla JavaScript, token-driven CSS, PHPUnit and Playwright.

**Spec:** ADMIN §6/§9.4, ADR 0053 and docs/runbooks/branding.md; approved review retained at /tmp/retroboards-appearance-review-20261031-fb5vBe/review.md.

## Global constraints

- Every POST retains CSRF and authorization/write gates; reset still requires RESET server-side.
- Preserve progressive enhancement, strict CSP, exact candidate contrast acknowledgement and package-preview isolation.
- Reuse valid existing evidence. Run one final composer test, changed-asset checks and focused Chromium/WebKit browser evidence under ADR 0051.
- Work remains local and uncommitted; publication is outside this approval.

## Review focus

- Enter in reset must never save pending edits or process pending files/removals.
- An invalid reset confirmation must retain pending edits in the browser; raw failed-reset requests still receive server-side validation.
- Cleared and invalid persisted mode/preset values must render meaningful System/Classic defaults without JavaScript.
- Colour guidance must reflect Classic/Retro, explicit/System mode and package palettes without overriding typed values.
- Mobile jump/return targets must be keyboard reachable with JavaScript disabled and must not introduce duplicate samples or live regions.

## Task 1: Recovery correctness

**Files:** templates/admin/branding.php; src/Controller/BrandingController.php; tests/Integration/Core/AppBrandingThemeTest.php; tests/browser/admin-appearance-followup.spec.ts.

**Interfaces:** Retain POST /admin/branding with reset=1/reset_confirm=RESET. The save form keeps data-brand-form; reset owns only its CSRF/action/confirmation fields.

- [x] Add regressions for keyboard reset with pending text/assets, invalid confirmation, and cleared/invalid preview settings. Observe the behavior regressions fail before implementation.
- [x] Separate reset and save forms. Use required/pattern confirmation to prevent ordinary invalid reset navigation, retaining server validation for raw requests.
- [x] Normalize form mode/preset after combining saved and submitted data.
- [x] Run focused integration/browser regressions; expected all pass.

## Task 2: Appearance guidance and mobile navigation

**Files:** templates/admin/branding.php; public/assets/app.js; public/assets/app.css; tests/browser/admin-appearance-followup.spec.ts.

**Interfaces:** Reuse data-brand-palettes and the existing selected mode/preset. Add one mobile preview link after controls and a return link from the single sample. Helpers describe theme defaults and retain exact hex entry.

- [x] Add browser coverage for preview access/return without JavaScript and resolved defaults changing with preset/mode, including a saved override.
- [x] Add token-based mobile-only link styling, native hash focus targets, server-rendered default values and progressive swatches/descriptions.
- [x] Explain that Custom CSS is unavailable until safe recovery is supported.
- [x] Verify mobile and desktop renders, both palettes/modes and enlarged text; expected no horizontal overflow and usable focus.

## Task 3: Qualification and record

**Files:** generated public assets/manifests; ADMIN.md; docs/runbooks/branding.md; ADR 0053; docs/evidence/admin-branding-recovery-2026-10-31/.

- [x] Rebuild assets and run generated-asset/Worker checks.
- [x] Run focused existing and new browser regressions in Chromium and WebKit using isolated databases/stores.
- [x] Obtain one fresh independent review of the final application diff, resolve material findings, then run one final composer test.
- [x] Record exact results, screenshots and limitations; inspect final diff and preserve the primary checkout.

## Execution ledger

- Baseline: clean main f0bbfc03. Implementation uses isolated worktree fix/admin-branding-recovery.
- Approved scope: the five review recommendations are already authorized; no repeated design/implementation permission gate is needed.
- Ruling: use the explicitly approved mobile jump-and-return alternative to retain a single preview and the established desktop arrangement.
- Ruling: ordinary invalid reset attempts are stopped by native required/pattern validation so unrelated pending edits remain in the page; the existing server reset validation remains authoritative.

- Recovery RED: new PHP preview regression failed on empty attributes; both browser defect cases failed on the original candidate. The keyboard case published the pending name. Focused PHP GREEN: 34 tests / 573 assertions.
- Guidance RED: missing colour helper and preview link reproduced; keyboard reset passed after Task 1.
- Chromium: 40/42 passed; two old invalid-reset assertions waited for a request now prevented by native validation. Update those assertions and rerun the two affected cases; application inputs are unchanged.
- Fresh independent review: no Critical, Important or Minor introduced defects. Physical-device/screen-reader/arbitrary package visual quality remain unqualified; current runtime checks are recorded separately.

- Final browser qualification: Chromium 42 distinct cases qualified across 40 initial passes and two targeted corrected-test passes; WebKit 22 cases passed uninterrupted. Generated assets and all 35 asset/Worker tests passed.
- Final PHP first attempt completed with 66 WebAuthn-domain failures: the setup used PASSKEYS_RP_ID instead of WEBAUTHN_RP_ID. Correct the test-only override and rerun fresh; no application/developer-config change is needed.

- Final full PHP GREEN: 3,433 tests / 30,881 assertions, zero failures, one intentional separate-database migration-rehearsal skip. The existing branding/product-tour journey passed both viewport cases after fixture reset.
- Final scope: all five approved findings implemented and documented; local/uncommitted on fix/admin-branding-recovery. Primary checkout remains clean.
