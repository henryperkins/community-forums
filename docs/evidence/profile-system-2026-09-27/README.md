# Profile system handoff — local evidence, 2026-09-27

The supplied `design_handoff_user_profile/` is implemented locally. ADR 0040
records the decision to adopt Recent activity and the retained production
contracts. This record does not claim a deployment.

## Verification

| Check | Result |
| --- | --- |
| `MAIL_DRIVER=sendmail MAIL_FROM='' COMPOSER_PROCESS_TIMEOUT=0 composer test` | 3,108 tests, 23,549 assertions, no failures, one skip |
| `composer verify:imladris` | 24 tests, 305 assertions; generated mirror/runtime current |
| `npm run test:assets` | 15 passed |
| `npm run check:assets` and `git diff --check` | Passed |
| `profile-surface.spec.ts`, desktop and mobile | 18 passed; no unexpected browser errors or serious/critical profile axe findings |

The PHP skip is
`AppThreadIntelligenceMigrationTest::test_0077_down_and_up_rehearsal_on_fixture_free_schema`:
that destructive migration rehearsal requires its own dedicated database. It is
unrelated to this profile change and was not run here. The suite's other tests
completed. Exact totals and scope are also in [results.json](results.json).

Browser environment: local PHP 8.4 / MariaDB, Chromium, `http://localhost:8037`,
isolated `retroboards_profile_handoff_e2e` database. The Browser plugin was
unavailable, so the existing Playwright harness was used. Main viewports were
1160×900 and 390×844, with the existing 320px long-handle/menu checks. Tests cover
light and dark themes, guest/member/self/moderator states, and JavaScript-disabled
navigation. No physical-device or non-Chromium run is claimed.

Reproduce after preparing the isolated browser fixture:

```sh
cd tests/browser
DB_DATABASE=retroboards_profile_handoff_e2e E2E_PORT=8037 \
  RB_EVIDENCE_DIR=/tmp/profile-handoff-evidence \
  npx playwright test profile-surface.spec.ts
```

## What the checks establish

- Overview merges six eligible topics/replies in creation order, excludes
  opening-post duplicates before limiting replies, and preserves public-board,
  pending/deleted-content, anonymity, and members-only profile guards.
- All / Topics / Replies links navigate to server-rendered results with the
  section fragment. Without JavaScript, row links and all full-tab searches,
  sorts, connection switches, and pagers work; disclosure controls remain hidden.
- With JavaScript, Enter/Space toggle independently controlled excerpts and
  update Show/Hide names and `aria-expanded`. Reduced motion disables the reveal
  animation. Relative timestamps leave room for titles and retain precise UTC
  metadata.
- Current order/connection choices carry `aria-current="page"`. Computed styles
  match the specified input padding, focus, tab weight, selected-switch colors,
  and disabled pager treatment. Four disabled button variants retain their fill
  on hover in both themes.
- Existing copy-link, block confirmation, follower removal, pagination, privacy,
  mobile overflow-menu, long-name, and prose behavior remain covered.

## Fidelity and intentional adaptations

Only the four requested hunks changed mirrored `components.css`. Held-back
upstream differences remain intact, and the bounded member transfer block still
matches the application character for character. The generated delivery files
and recorded digests match the reviewed inputs.

The source `.dc.html` requires an absent upstream compiler, as documented in
`PREVIEW_STATUS.md`. The browser suite no longer treats that loader as a rendered
reference. Fidelity is checked against the supplied values and real PHP output.
Full lists retain 20-item pages; mobile touch targets retain their existing 44px
floor; the optional client-side activity filter is omitted. Ordinary GET
navigation owns filtering with and without JavaScript.

The mechanical Impeccable scan reported no new warnings on changed lines. Its
three changed-line size advisories (`.82rem`, `.6rem`, `.98rem`) match explicit
handoff values; unrelated existing stylesheet findings were left outside scope.

## Captures

- [Desktop overview](desktop/member-populated-light.png)
- [Desktop sort and focused search](desktop/system-controls-focused-light.png)
- [Members-only gate](desktop/guest-gated-light.png)
- [Mobile expanded activity, dark](mobile/activity-expanded-dark.png)
- [Mobile connections, dark](mobile/self-connections-dark.png)
- [Mobile activity without JavaScript](mobile/activity-no-js.png)
