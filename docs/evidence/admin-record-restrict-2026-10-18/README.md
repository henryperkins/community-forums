# Member record restrictions disclosure — 2026-10-18

The admin member record now shows History before the restrictions. Suspend and Permanent ban move to the end of the record, inside a native **Suspend or ban** disclosure in the unchanged `Account restrictions` card. The disclosure reuses the Members directory's More filters anatomy: a hairline row with a +/− glyph. It is closed for an active member. It opens itself while the account is suspended or banned, and on a suspend/ban 422 re-render. The Lift banner and the self/peer-admin explanations are always outside it. A record the viewer cannot govern gets no disclosure at all. This is a `feature-changed` deviation from `AdminMembers.dc.html`, recorded in ADR 0024.

A review of the first draft found that the summary had no affordance and wrapped the card's `<h3>`, which also orphaned the `.member-record-restrictions > h3` rule. It also found that non-governable explanations were hidden, and that the tests asserted presence rather than visibility. This version fixes all four. It also catches two cases the review missed:

- `admin-remediation.spec.ts` and the no-JS case in `members-console.spec.ts` drove the ban form while it was hidden.
- A `deactivated` or `pending_deletion` member would have opened the disclosure, which made `account-settings-repairs.spec.ts`'s summary click close it.

The shared glyph now carries empty CSS alt text (`content: "+" / ""`). Chromium's accessibility tree had named the summaries "SUSPEND OR BAN +" and "MORE FILTERS +"; after the change it names them "SUSPEND OR BAN" and "MORE FILTERS". A browser without CSS alt text falls back to the previous declaration. Lightning CSS keeps both declarations in the built bundle.

Screenshots in `screenshots/` are crops of the restrictions card from the full-page captures written by `members-console.spec.ts`:

- `*-active-closed`: the default state for an active member.
- `*-keyboard-open-*`: the disclosure opened with Enter, in the light and twilight registers.
- `*-banned-open-with-lift`: the record after a typed ban, with Lift outside and the forms open.
- `desktop-no-js-opened`: the disclosure opened by a plain click with JavaScript disabled.

Validation:

- `members-console.spec.ts`: 14 passed. Its 2 skips are the spec's own desktop/mobile gating. The new case pins the closed default, keyboard open, glyph and accessible name, the 44px target at 390px, axe in light, twilight and system-dark, and a ban carried through Lift.
- `admin-remediation.spec.ts`: 19 passed. The 17 skips are desktop-only or mobile-only cases.
- `account-settings-repairs.spec.ts`: the overlapping-suspension case and both suspension-expiry hold cases passed on both viewports (6 passed). That spec's unrelated, previously recorded failures were not re-run.
- Each group ran on a freshly prepared private database.
- PHPUnit: `AppAdminUserRecordTest` pins the `open` state for active, suspended and banned accounts and for each 422 context. It also pins that self and peer-admin records render no disclosure. The full suite passed on the final tree: 3,298 tests and 26,092 assertions, with one existing environment skip in `AppThreadIntelligenceMigrationTest`.
- `npm run check:assets` and `composer check:imladris` are current.

Reproduction: `cd tests/browser && DB_DATABASE=retroboards_unified_e2e_<suffix> E2E_PORT=<port> bash prepare.sh && RB_EVIDENCE_DIR=<dir> npx playwright test 'browser/members-console\.spec\.ts'`. Fixtures use a private local database and synthetic accounts. Browser viewports are emulated; these checks do not certify physical devices.
