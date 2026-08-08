# Slice 10 admin settings design QA

Status: complete for the Slice 10 General & intelligence boundary.

References:

- `docs/design-system/imladris/templates/admin-settings/AdminSettings.dc.html`
- `docs/superpowers/plans/imladris-admin-account-stage1/D-admin-settings.md`
- `docs/superpowers/plans/imladris-admin-account-stage1/R-admin-settings.md`
- `docs/superpowers/plans/imladris-admin-account-stage1/V-admin-settings.md`

Captured 2026-08-04 against the real PHP application and freshly seeded browser databases. The final `thread-intelligence.spec.ts` run passed 16/16 across desktop and mobile. The final `admin-remediation.spec.ts` run, with only the operator-verified baseline board-delete/composer case excluded by title, passed 16 tests with 14 expected project-specific skips. The dark-fixture `a11y.spec.ts` run passed 28/28. Light, twilight, mobile, 390px no-JS, and before/after captures were visually inspected.

Reviewed against the references:

- General and Thread Intelligence retain the area-owned `General & intelligence` heading and exact two-tab hierarchy. They remain separate crawlable routes with separate server-rendered `<h1>` elements under `C-31`; both routes remain `noindex`.
- General copies the design's two-card Identity and Registration anatomy, field spacing, native select, focus treatment, and primary action. Production's site name and registration mode remain two independent POST forms with independent CSRF fields, validation state, and anti-draft-loss rendering. A 422 response projects only the owning field, so one invalid form cannot contaminate its sibling.
- The design's `Invitations feature is enabled` checkbox is absent (`FR-23`). Feature enablement remains an intentional feature-settings write rather than dead settings-page chrome.
- Thread Intelligence starts with the design's warning and non-fiction introduction, then renders four stateful status cards, recovery controls, two budget meters, six canonical queue-state tiles, the generation contract, and generation evidence.
- The four status rules are state-driven (`FA-25`): unavailable generation and attention-needed provider states no longer inherit a false success rule. The production-added Product flags card stays honest about both required flags. Queue units remain correctly pluralised (`FA-26`).
- Both recovery controls remain native POST forms with CSRF fields (`C-33`). Pause/resume and provider retry work without JavaScript; no design-only client action was introduced.
- The contract renders the validated configured model and effort, the actual prompt version, a real worker heartbeat, and real generation evidence (`C-34`). The fictional council-approval claim is absent. The design's failed-only filter and Digest column are absent (`FR-24`, `FR-25`), and no request fingerprint is exposed.
- Production-owned evidence fields and actions remain: generation ID, contract, evidence, redaction state, Retry, Pause, and Resume. The accessible Actions header stays screen-reader text rather than the design's empty header (`C-06`).
- The evidence table retains labelled inner horizontal containment and the card's independent overflow boundary (`C-05`). Desktop and mobile captures have no document-level horizontal overflow.
- When both Thread Intelligence flags are dark, the route remains a truthful 200 status surface while its tab is disabled and not announced as the active page. The regression is covered in both PHP and Playwright.
- Browser fixture worker jobs are queued five seconds in the past to tolerate the observed native-PHP/MariaDB-container clock boundary. This is evidence infrastructure only; production scheduling behavior is unchanged.
- No inline script, inline style, event handler, fictional provider state, fictional generation, or design-only client state was imported (`C-09`).

Representative captures:

- `desktop/general-settings.png`
- `desktop/remediation-settings-422-draft.png`
- `desktop/thread-intelligence.png`
- `desktop/79-admin-thread-intelligence.png`
- `mobile/general-settings.png`
- `mobile/12-admin-settings-no-js.png`
- `mobile/remediation-settings-422-draft.png`
- `mobile/thread-intelligence.png`
- `mobile/79-admin-thread-intelligence.png`

Twilight equivalents are under `twilight/desktop/` and `twilight/mobile/`. Side-by-side light/twilight and before/after evidence is under `comparisons/` for both General and Thread Intelligence at desktop and mobile widths.

Focused backend verification passed 36 tests / 425 assertions. The final full PHPUnit run passed 2,546 tests / 18,455 assertions / 2 skips, and `composer verify:imladris` passed 18 tests / 254 assertions under the required temporary application-surface digest allowance. The allowance was then removed from both baseline files because runtime-baseline refreshes land only once per merge on `main`. PHP syntax, `git diff --check`, Playwright discovery (76 cases across the three named specs), and the CSP template scan passed; the CSP scan returned only `layout.php`'s permitted external script tags.

This evidence certifies only the General and Thread Intelligence bodies. Shared admin chrome was certified by Slice 2; later admin and account areas remain separate slice work.
