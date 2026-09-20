# N5 persistent bell and unified count surfaces

## Review correction — eeb72a9c (supersedes the initial completion claim)

The scoped N5 review found one P2: at 320px, the new fixed-width bell squeezed the old scrolling primary navigation so tightly that even one full route label could not fit. The initial implementation's control test omitted `[data-primary-route]`; its green result did not establish full label readability. This correction closes that gap after A5 source release at e8e41c9c. Parent documentation commit 9820a9c3 preceded the exact six-path correction commit **eeb72a9c**.

**Source and browser 8034 ownership are released.** Standard disposable schema preparation ran after the final browser pass, and `ss -ltnp` confirmed no 8034 listener. Only untracked N5 evidence remains for the parent's evidence commit.

### Minimal adaptation

At **380 CSS pixels and below**, member chrome gives the existing primary navigation a second full-width row with 44px controls and focus gutters. Header controls remain on the first row. No route or icon control was removed. The member header's shared `--topbar-h` becomes **108px**, so fixed board drawer, scrim, and existing scroll offsets clear both rows. The override is conditional on a `.forum-bar`; separate admin/auth shells retain their existing sizing. The template's design comment records the two-row adaptation. Parent should add this precise scope to ADR 0032/reconciliation; the prototype did not provide this two-row layout.

The new A5 mobile settings chooser is opened through its visible native summary before clicking Replay tour. Existing assertions still require the Notifications step, a visible highlighted primary bell, and a closed account menu.

### Focused RED/GREEN and actual bounds

- **RED:** the new full-label geometry test fails against the uncorrected header: the focused label begins at **102px**, outside its navigation viewport beginning at **105px**. This is a real clipping assertion using the text node's DOM Range, not merely `toBeVisible()` on the link. Log: `n5/correction/n5-primary-route-red.log`.
- **Focused browser GREEN:** 6/6 tests across both projects: keyboard/touch primary navigation and all other 320px controls; no-JS native primary links; tour replay through A5's chooser. All three routes retain their full text and actual destination behavior. Drawer/scrim top bounds are checked against the full header bottom.
- **Final affected browser specs GREEN:** **45 passed / 5 intentional viewport-specific skips**, both projects, 1.3m. This includes all prior notification/count/tour/privacy/no-JS/chrome assertions plus the new tests. Log: `n5/correction/n5-correction-browser-final.log`.
- **Focused PHP GREEN:** **59 tests / 697 assertions**, 0.969s; notification shell/read/privacy, forum-index remediation and Imladris asset/runtime coverage. Log: `n5/correction/n5-correction-php.log`. No full PHP suite run; parent owns final N6 gate.
- `composer build:imladris`, asset `--check`, staged/worktree diff checks passed. Literal reconciled-through commit retained.

Saved `13-primary-route-geometry.json` records actual post-fix label and control bounds for each project. At the 320px mobile viewport, the navigation spans **x=9..311px**. Full label ranges are **Boards 82.23..119.66**, **Inbox 136.66..167.84**, **Messages 184.91..232.80px**. All control rectangles also fit inside that navigation viewport with at least a 3px focus gutter. Each route was focused through real Tab/Shift+Tab, activated with Enter, then activated by mobile tap (desktop click); no-JS repeats native route activation.

Opened and inspected the final `n5/correction/mobile/11-bell-320-focus.png`, `12-primary-routes-320-nojs.png`, `pane-dark-320.png`, and `bell-tour.png`. They show all three complete labels, intact search/compose/bell/account/rail controls, visible bell focus, and the actual A5 chooser/tour state. Matching desktop captures and geometry are retained. The final no-JS capture visibly includes the plus and bell glyphs. No native-browser-zoom or real operating-system tab-switch claim is added; the earlier limits remain.

The parent's original **n5/primary-route-before.png was preserved untouched**. SHA256: `622eaaf488d38b345187c5c5392e4412c3e23581778a77d4177a164c16204456`. Correction logs/captures are separate under `docs/evidence/unified-notifications-and-settings/n5/correction/`; original N5 evidence is not overwritten.

Commands (worktree root, ArrayMailer/dummy From for browser, required flock for PHP):

```sh
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppNotificationShellTest|AppNotificationTest|AppNotificationPrivacyTest|AppImladrisRuntimeTest|ImladrisRuntimeAssetTest|AppForumIndexRemediationTest'
APP_ENV=test DB_DATABASE=retroboards_unified_e2e MAIL_DRIVER=array MAIL_FROM=notification-evidence@example.test E2E_BASE_URL=http://localhost:8034 E2E_PORT=8034 RB_EVIDENCE_DIR=.superpowers/sdd/2026-09-20-unified-notifications/browser-n5-correction npx --prefix tests/browser playwright test --config tests/browser/playwright.config.ts unified-chrome.spec.ts notifications-unified.spec.ts
APP_ENV=test DB_DATABASE=retroboards_unified_e2e MAIL_DRIVER=array MAIL_FROM=notification-evidence@example.test bash tests/browser/prepare.sh
```

The following initial implementation record is retained as history; its former 320px completion claim is superseded by the review correction and evidence above.

---

Committed **88d51dfe**, based on runtime 2c8a59cd plus parent documentation commits through 5598277d. Exactly 15 runtime/test/build paths committed. Source and browser 8034 / retroboards_unified_e2e ownership are released. No server listener remains. Parent N4 evidence and documentation were untouched.

## Implementation

- Member header now exposes one visible primary `data-bell` link outside the account disclosure. Admin header retains the same primary hook and gains its real initial count. The tour selector is unchanged.
- Server-rendered bell, account-menu shortcut, directory Notifications tab, and shared notification heading use `data-notification-count`. Positive counts cap visually at `99+`, while links and the heading expose the full unread count through their accessible names. Zero badges are present but hidden, so later polls can reveal them. The heading retains visible `unread` wording through its decorative badge's CSS suffix and its full accessible label.
- Polling updates every badge/link/heading, including hidden menu nodes, without replacing rows or adding a live region. Existing pause/resume, exponential throttle backoff, and permanent 404 stop behavior remain intact and have browser coverage.
- The shell closure guards disabled notifications before resolving the read service, memoizes zero as well as positive results, and memoizes lookup failure. HomeController no longer eagerly fetches/counts and shadows the closure with a scalar. The existing NotificationReadService and request-local scope remain the only count/read-policy path.
- Bell badges anchor within their 40px primary link; menu/tab badges remain in normal flow. Member focus styling includes the primary bell. At 320px, search, compose, rail opener, account control, and bell remain reachable with their focus gutters. An observed admin long-name overflow was fixed by allowing the right cluster to shrink and capping the identity width, preserving brand and Log out.
- Updated AppForumIndexRemediationTest's old dot assertion to the current numeric count/accessible label without changing its other contracts. Added AppNotificationShellTest for HTTP counts/query budgets/failure safety. Extended the existing notification fixture/spec and unified-chrome spec. No policy, row, history, mutation form, or tour implementation change.
- Refreshed reviewed application baseline and generated manifest, preserving literal `reconciled_through_commit`. Parent owns ADR 0032 / final reconciliation documentation and the A4 saved-feed/folder adaptations.

## RED and GREEN evidence

Durable logs and selected final captures are in **docs/evidence/unified-notifications-and-settings/n5/**, left untracked for the parent's final evidence commit. Full intermediate captures remain isolated under `.superpowers/sdd/2026-09-20-unified-notifications/browser-n5/`.

1. Initial PHP RED: 5 tests / 25 assertions, five failures. Three product failures were the primary bell inside the closed menu, repeated SQL after a failed lookup, and a feature-disabled count query. Two scope assertions initially attributed independent shell/authority membership reads to the notification scope; the test instrumentation was corrected to attribute only the scope's own direct Database calls. These two initial failures are not claimed as product defects.
2. Initial new browser group: 3 passed / 2 failed. One failure exposed the admin badge extending beyond its tiny primary link, fixed with shared 40px geometry. The other was a test using accessible-name calculation for a link in a closed native disclosure; it correctly has no accessible name while hidden. The polling assertion now checks the updated aria-label for all nodes, and the no-JS menu-open test verifies the actual accessible name and badge position.
3. Screenshot-led admin overflow RED: new assertion reproduced Log out ending at **1371.984375px** in a **1280px** viewport. The final capped/shrinking identity keeps brand and Log out visible in desktop and mobile captures.
4. Heading RED: the new four-count polling assertion found only three nodes because the old heading badge had no shared hook. Final polling updates all four counts through 105 → 7 → 0, checks the heading's accessible name at 7 and zero, and preserves the focused row and its identity marker.
5. Final expanded PHP GREEN: **98 tests, 1,018 assertions**, 1.987s, no failures. Includes notification shell/read/privacy, presenter, anonymous-author output, home remediation/viewing, pre-setup/health, Imladris runtime/assets, and fidelity contracts. No full PHPUnit suite was run; parent N6 owns that gate.
6. Final complete browser GREEN: **43 passed, 5 intentionally skipped**, 1.3m, both desktop and mobile projects, across `notifications-unified.spec.ts` and `unified-chrome.spec.ts`. The five skips are existing viewport-specific chrome cases: four phone-only cases on desktop and one desktop-only case on mobile. This final run follows the heading and admin fixes and includes all added tests.
7. `composer build:imladris`, `php bin/build-imladris-assets.php --check`, and `git diff --check` passed before commit.

## Measured query budgets

These are notification-specific counts, not total HTTP request SQL counts; existing session, shell, authority, and other feature work remains separate.

- `/`, `/notifications`, and `/admin` (admin only), for zero and 105 unread: **one notification COUNT query per request** despite multiple rendered count components.
- `/notifications/bell`: **one notification COUNT, one direct scope board-members query, one direct scope board-moderators query**. Independent authority checks may read memberships separately and are not misrepresented as repeat scope resolution.
- Guest `/login` and `/register`, member redirecting auth routes, `/healthz`, and unrelated `/presence` JSON: **zero notification COUNT and zero direct notification-scope reads**.
- Sharing the lazy closure without invoking it: **zero notification COUNT**. Calling it twice at zero: the first call counts once; the second adds **zero SQL**.
- Disabled-feature closure and rendered guest/disabled shells: **zero notification COUNT**; disabled bell route remains 404.
- A connection-local temporary incomplete notifications table reproduces missing-later-schema lookup failure without modifying the real schema. Both calls return zero; the second adds **zero SQL**, and account/health responses still succeed. Temporary table is dropped in `finally`.
- An unreachable notification DB remains untouched until closure invocation; failure returns zero and repeat invocation adds **zero connection attempts**. Existing AppTest also verifies actual DB-down health 503 with no secrets and pre-setup health/setup behavior.

## Browser coverage and reviewed captures

Added checks prove initial member/admin count with JavaScript disabled, zero/105 states, 64-character account name, closed-menu primary link, native notification navigation, menu-open full accessible count, locally anchored badges, every-count polling, focused-row preservation, guest/disabled no poll, permanent 404 stop, throttle backoff, simulated document visibility pause/immediate resume, and the actual replayed Notifications tour step highlighting the visible bell while account menu remains closed. The unchanged full notification tests cover both entry points, events/privacy, unread/history, no-JS POSTs, 320/390/1280/1440 widths, Parchment/Twilight, focus, wrapping, and axe rules.

Opened and inspected final captures include desktop and mobile member/admin zero/high-count bells, the menu-open badge, mobile tour, desktop 320px focus, and final dark 320px pane. Reviewed the admin overflow before and after correction; the final desktop capture shows both brand and Log out with the ellipsized long name. Selected captures are under n5/{desktop,mobile}:

- `bell-nojs-{member,admin}-{empty,high-count}.png`
- `bell-nojs-account-menu.png`
- `bell-tour.png`
- `11-bell-320-focus.png`
- `pane-dark-320.png`
- `01-chrome-light.png`

200% coverage remains N4's CSS reflow emulation: 640×500 CSS viewport with deviceScaleFactor 2, representing a 1280×1000 viewport at 200%; no native browser zoom was manipulated. Visibility testing uses controlled document.hidden and visibilitychange, not an operating-system tab switch. Browser mail is ArrayMailer/dummy From only; no external mail delivery claim.

## Commands and cleanup

From worktree root:

```sh
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppNotificationShellTest|AppNotificationTest|AppNotificationPrivacyTest|AppTest$|AppSetupTest|AppImladrisRuntimeTest|ImladrisRuntimeAssetTest|AppForumIndexRemediationTest|AppForumIndexViewingTest|NotificationPresenterTest|AppImladrisFidelityHighImpactTest|AppAnonymousPostingTest'
APP_ENV=test DB_DATABASE=retroboards_unified_e2e MAIL_DRIVER=array MAIL_FROM=notification-evidence@example.test E2E_BASE_URL=http://localhost:8034 E2E_PORT=8034 RB_EVIDENCE_DIR=.superpowers/sdd/2026-09-20-unified-notifications/browser-n5 npx --prefix tests/browser playwright test --config tests/browser/playwright.config.ts notifications-unified.spec.ts unified-chrome.spec.ts
APP_ENV=test DB_DATABASE=retroboards_unified_e2e MAIL_DRIVER=array MAIL_FROM=notification-evidence@example.test bash tests/browser/prepare.sh
```

The final command ran after browser completion to restore the standard disposable seed and clear test-generated notification users/content, role/name changes, rate-limit state, and package fixtures. No 8034 listener remains (`ss -ltnp` checked). Runtime assets checked current after cleanup. No historical/canonical screenshot was overwritten. The only untracked task output is the N5 evidence directory awaiting the parent's evidence commit.

No outstanding N5 blocker. Parent can review 5598277d..88d51dfe and proceed to A5 / final combined N6 proof.
