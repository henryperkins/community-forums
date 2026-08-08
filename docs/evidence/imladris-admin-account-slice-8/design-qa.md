# Slice 8 admin appearance design QA

Status: complete for the Slice 8 Branding & themes boundary.

References:

- `docs/design-system/imladris/templates/admin-appearance/AdminAppearance.dc.html`
- `docs/superpowers/plans/imladris-admin-account-stage1/D-admin-appearance.md`
- `docs/superpowers/plans/imladris-admin-account-stage1/R-admin-appearance.md`
- `docs/superpowers/plans/imladris-admin-account-stage1/V-admin-appearance.md`

Captured 2026-08-04 against the real PHP application and freshly seeded private browser databases. The final standard `admin-features.spec.ts` + `gate-a.spec.ts` run passed 56 tests with 2 expected project-specific skips across desktop and mobile. The final dark-fixture `a11y.spec.ts` run passed 28/28 after confirming the native PHP seed received `RB_BROWSER_DARK_SURFACES=1` through `WSLENV`; an earlier incorrectly forwarded fixture run was rejected rather than counted as evidence.

Reviewed against the references:

- Branding and Themes preserve the area-owned `Branding & themes` heading and exact two-tab hierarchy. The old page-owned headings are demoted to section anatomy, matching the design's accessible-name contract.
- Branding keeps one outer save form, the design's Site identity, Brand colours, Preview, Brand marks, and Custom CSS sequence, exact visible Upload/Replace controls, and inline save/error/reset status slots. Reset remains a real CSRF-protected server form, uses `formnovalidate`, and no longer depends on unrelated save-field validity before the request reaches PHP.
- The live preview updates the typed site name, primary/accent colours, and both computed contrast colours through external progressive-enhancement JavaScript. The no-JS document retains the saved preview and fully functional upload, save, and reset forms.
- A rejected brand-mark upload now renders a visible review callout and preserves the remaining submitted branding values. Desktop and mobile rejection captures were visually inspected.
- When Custom CSS is disabled, the CSS-only disclosure matches the design visually while the server ignores the hidden textarea, preserves stored bytes, and clears only the enabled bit (`FC-07`). The default-off feature gate and unavailable explanation remain (`C-20`).
- Themes uses the design's activation panel, active/preview summaries, built-in fallback card, installed-theme cards, state pills, reauthentication rows, and recovery actions. Preview, activation, safe mode, end-preview, and rollback remain real server-rendered POST flows with CSRF and inline passwords; no-JS operation is preserved. A validation error is rendered once at its matching theme row, including disabled rows, while a missing or stale theme identifier receives one visible fallback danger callout.
- Safe mode now blanks both Active and Preview summaries as decided in `FC-08`, while last-known-good recovery remains available. `/admin/themes/safe-mode` deliberately retains its plain, theme-independent recovery shell and receives no admin tier (`C-21`).
- Appearance was explicitly exercised with `<html data-theme="system">` under `prefers-color-scheme: dark`. The preview theme label, accent marker, disabled-theme Packages link, and rollback control retain their design semantics with the WCAG-preserving treatments recorded as `C-40` through `C-43`; the final axe run reported no serious or critical findings.
- Production's existing branding contrast hard block remains as `FA-06`. The design has no corresponding check, and ADR 0024 continues to own the separate warning/override policy gap.
- No inert theme deactivation control, fictional fixtures, inline script/style, or client-only state machine was imported. Production package identities, theme state, safe-mode semantics, upload policy, authorization, and reauthentication remain authoritative.

Comparison captures:

- `comparisons/design-branding-desktop.png` ↔ `comparisons/runtime-branding-desktop.png`
- `comparisons/design-branding-mobile.png` ↔ `comparisons/runtime-branding-mobile.png`
- `comparisons/design-themes-desktop.png` ↔ `comparisons/runtime-themes-desktop.png`
- `comparisons/design-themes-mobile.png` ↔ `comparisons/runtime-themes-mobile.png`

The authoritative design HTML was rendered standalone for those comparisons. Its imported `AdminNav` custom element has no runtime in that standalone renderer and therefore appears as an inert top placeholder; comparison review focuses on the Branding and Themes bodies. The real shared console chrome is present in the runtime captures and was separately certified by Slice 2.

Representative runtime captures:

- `desktop/18-branding-preview.png`
- `desktop/18-branding-upload-rejection.png`
- `desktop/39-admin-themes-built-in.png`
- `desktop/40-admin-themes-active-summary.png`
- `desktop/41-admin-themes-safe-mode-summary.png`
- `desktop/42-admin-theme-rollback.png`
- `mobile/18-branding-preview.png`
- `mobile/18-branding-upload-rejection.png`
- `mobile/39-admin-themes-built-in.png`
- `mobile/41-admin-themes-safe-mode-summary.png`

Focused backend verification passed 30 tests / 206 assertions. The final full PHPUnit run passed 2,534 tests / 18,260 assertions / 2 skips, and `composer verify:imladris` passed 18 tests / 254 assertions under the required temporary application-digest allowance. The allowance was then removed from both baseline files because runtime-baseline refreshes land only once per merge on `main`. PHP syntax, JavaScript syntax, `git diff --check`, Playwright discovery, and the CSP template scan passed; the CSP scan returned only `layout.php`'s permitted external script tags.

This evidence certifies only the Appearance area Branding, Themes, and plain recovery bodies. Shared admin chrome was certified by Slice 2; later admin and account areas remain separate slice work.
