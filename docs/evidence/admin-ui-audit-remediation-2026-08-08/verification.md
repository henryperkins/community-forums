# Admin UI audit remediation verification

Status: complete for the 2026-08-08 Admin Console and Members-directory audit findings.

References:

- `docs/superpowers/specs/2026-08-08-admin-ui-audit-remediation-design.md`
- `docs/superpowers/plans/2026-08-08-admin-ui-audit-remediation.md`
- `ADMIN.md` §9.2 and §9.4

## Delivered

- The console identity row enters its compact state at 900px: search, account-name, and sign-out label collapse while the sign-out control remains present. The existing 860px mobile wrapping, touch-target, and content-layout behavior remains intact.
- A valid 80-character community name now truncates only its visible wordmark at 900px, while the full name stays in the brand link's accessible label and the header keeps its controls on-screen.
- The Imladris-owned tier uses a transparent scrollbar track and token-coloured thumb, preserving the honest horizontal-overflow signal without an opaque rail behind it.
- Members-directory search, role, and state remain visible. The five secondary filters are a native `details` disclosure that reopens on the server when an advanced value is active; no JavaScript is required.
- The `More filters` summary is a 44px target throughout the console's `max-width: 860px` range, including the 800px intermediate layout.
- The Members table now opts into the existing focusable overflow-cue enhancement and tells mobile operators: “Scroll for state, activity, and dates.” The cue and fade disappear at the real right edge.

## Browser evidence

Captured 2026-08-08 against a freshly seeded `retroboards_e2e` database. The final focused Playwright run passed 19 executed checks with 7 expected project-specific skips across the desktop and mobile projects. It included the native no-JavaScript route journey.
The final 900px long-name regression was replayed after its fixture-restoration hardening and passed.

Representative captures:

- `desktop/admin-header-900px.png` — compact header immediately before the existing 860px content breakpoint.
- `desktop/admin-header-900px-long-brand.png` — a valid 80-character brand name truncates visually without hiding its accessible name or causing document overflow.
- `desktop/members-directory-800px.png` — the native disclosure remains a 44px target through the intermediate console range.
- `mobile/members-directory-table-cue.png` — visible table continuation cue and focusable scroll region.
- `mobile/members-directory-no-js.png` — advanced disclosure open after a real GET filter submission with JavaScript disabled.

Visual inspection confirmed no document-level horizontal overflow, a visible compact header at 900px, clean long-name truncation, a usable closed mobile filter card, and a visible table-scroll signal before its terminal state.

## Verification

- Focused PHP regression: `tests/Integration/Admin/AdminUserBulkTest.php` passed 19 tests / 113 assertions.
- `composer build:imladris` rebuilt the generated assets from the design-system source.
- `composer verify:imladris` passed 21 tests / 261 assertions under the documented temporary application-surface digest allowance.
- Full PHPUnit verification passed 2,599 tests / 18,871 assertions / 2 expected skips using the repository’s deterministic non-secret test `APP_KEY` and an expanded Composer process timeout. With the protected baseline restored, the one expected Imladris digest guard fails; the same full suite passed under the documented local-only allowance.
- CSP scan found only `templates/layout.php`’s permitted external script tags; no inline script, style, or event handler was added.
- `php -l templates/admin/users.php` and `git diff --check` passed.

## Runtime-baseline handling

Only the local `application_surface.sha256` allowance was temporarily refreshed to build and verify this slice. It was restored after the verified full-suite run and before commit, as required by the Imladris merge rule. `resources/imladris/manifest.json` records the current application-surface digest; the merger must refresh `config/imladris-runtime-baseline.json` once after the slice is merged to `main`.
