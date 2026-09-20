# N5 review — frozen 5598277d..88d51dfe

**Spec compliance: NEEDS REVISION. Code quality: NEEDS REVISION.** One concrete mobile-navigation regression; the notification count/privacy architecture otherwise satisfies the N5 contract.

## Required correction

**P2 — Preserve a readable primary navigation label after adding the persistent bell.** `public/assets/app.css:16067` fixes the new bell at 40px inside the member right cluster (`templates/partials/topbar.php:139`). The existing mobile cluster cannot shrink (`resources/imladris/components.css:473`), so the existing shrinkable, horizontally clipped primary navigation (`public/assets/app.css:15916`) absorbs the extra width. At 320 CSS pixels the resulting viewport is narrower than even “Boards”: the initial header displays “Boar” with the rest clipped, and the other primary routes are out of view. Horizontal scrolling cannot show an entire label when the viewport itself is shorter than that label. This is a new loss of readable primary navigation, even though the bell, search, compose, account, and rail controls remain inside the viewport.

Evidence inspected directly:

- Before: `docs/evidence/unified-notifications-and-settings/n4/notifications/mobile/pane-dark-320.png` shows the full Boards label and the beginning of the following scrollable tab.
- After: `docs/evidence/unified-notifications-and-settings/n5/mobile/pane-dark-320.png` shows only “Boar”; the same defect is visible in `n5/mobile/11-bell-320-focus.png`. These captures have matching 320px CSS width and 2x device scale; this is genuine container clipping, not image scaling or an overlap artifact.
- The new mobile reachability loop at `tests/browser/unified-chrome.spec.ts:655` checks bell/search/compose/rail/account but omits `[data-primary-route]`. The supplied green result therefore does not disprove the regression.

Adjust the narrow header layout so the primary routes retain fully readable labels when navigated, along with the persistent bell and the other required controls. Extend the 320px regression check to cover primary-route label bounds within their visible navigation area and keyboard/touch navigation. Inspect a new narrow screenshot. Keep the correction scoped to member chrome; A5 owns the separate mobile settings repair.

## Contracts reviewed without another finding

- `src/Core/App.php:842` creates a guarded lazy closure; its per-instance static sentinel caches positive counts, zero, disabled-feature results, missing-schema failures, and DB connection failures. Captured features are resolved safely before the closure. It does not eagerly resolve the read service.
- The container is new for every `App::handle()` (`src/Core/App.php:354`, `:985`); its notification read/visibility bindings reuse request-local instances (`:1114`, `:1125`). `NotificationReadService::unreadCount()` caches zero with `??=`, and its page model and bell path use that same scope/count. The shell closure does not add a competing privacy predicate. Removing HomeController's scalar override preserves that shared model.
- Initial member/admin primary bells remain outside the account disclosure, contain real capped counts, and expose the full count through accessible names. Menu, directory tab, primary bell, and shared heading expose the count hook even at zero. CSS places primary badges locally and menu/tab badges in flow.
- `public/assets/app.js:116` updates all count/link/heading nodes and changes no rows, focus, history URLs, or mutation forms. Decorative counts are hidden from accessibility APIs; the accessible count is carried by the containing link/heading, without a new live region. The pre-existing visibility/backoff/404 lifecycle remains unchanged.
- Guest/disabled shells omit notification entry hooks. Plain/auth/health/unrelated JSON query budgets, laziness, failure caching, single bell scope/count, and repeated-request zero/nonzero behavior have focused HTTP assertions in `tests/Integration/Core/AppNotificationShellTest.php`.
- The unchanged `public/assets/tour.js:24` selector still reaches the visible primary bell. The replay test and inspected `n5/mobile/bell-tour.png` show the Notifications step with a closed menu.
- Inspected `n5/desktop/bell-nojs-admin-high-count.png`: the long admin name is ellipsized while brand, badge, and Log out remain visible. Inspected `n5/mobile/11-bell-320-focus.png`: bell focus ring is visible and the specified icon controls fit; the primary label issue above is the remaining limitation.
- N4 history, privacy, presenter, and row behavior are unchanged in the frozen diff, apart from the intended shared heading count hook. No reopening of the approved N4 gate.
- The two application digest values match in the frozen diff. Literal `reconciled_through_commit` remains `6d81da590a12bd09bb8d0e282c042aa03d755a94`; no unauthorized reconciliation claim was introduced. The report records successful build/check/diff checks; parent still owns final coherent build/reconciliation and canonical evidence promotion.

## Evidence and review limits

Read the 1006-line frozen review package once, plus N5 brief/handoff/report and the combined design's recipient visibility and count/shell contracts. Unchanged collaborators were inspected only for named risks: request lifecycle and caching, existing bell/phone CSS, bell endpoint, and tour selector.

Verified supplied durable log endings: `n5/n5-php-green.log` reports **98 tests / 1,018 assertions**, and `n5/n5-browser-final.log` reports **43 passed / 5 skipped**, both projects. The documented skips and simulated visibility/reflow limits remain explicit. These are supplied implementation-run results, not fresh reviewer executions. No suites were repeated, no browser server was started or used, and no source/index/HEAD/runtime state was mutated. Only this scratch review was written.

Parent final full PHPUnit, combined browser, docs, and canonical promotion gates remain pending independently of this N5 correction. Coordinate implementation with A5's active sole-writer ownership.

## Superseding disposition — correction eeb72a9c

**Spec compliance: APPROVED. Code quality: APPROVED.** The sole P2 above is resolved by frozen correction `9820a9c3..eeb72a9c`. This disposition supersedes the original NEEDS REVISION verdict; parent final integration/evidence gates remain separate.

Reviewed the correction package once and the supplied correction screenshots, geometry, and log endings. No new suites, servers, DB work, or source/index/HEAD mutations were performed.

- At widths up to 380px, `public/assets/app.css:15973` gives primary routes a full-width second row and sets member chrome's shared `--topbar-h` to 108px. `:root:has(.forum-bar)` confines the height change to layouts with member chrome. Existing drawer/scrim tops and scroll padding consume that variable. The correction test explicitly checks both drawer and scrim below the rendered header. Wider member chrome and admin/auth height rules are untouched.
- `tests/browser/unified-chrome.spec.ts:651` now exercises Boards, Inbox, and Messages via keyboard focus/Enter and pointer or touch. Its text Range bounds check rejects clipped label text, while control bounds preserve focus gutters inside the navigation viewport. `:725` adds native navigation with JavaScript disabled. The prior other-control reachability checks remain.
- The supplied `correction/mobile/13-primary-route-geometry.json` places the navigation between x=9 and x=311 at a 320px viewport. Full text bounds are Boards 82.23–119.66, Inbox 136.66–167.84, and Messages 184.91–232.80. Every control has substantial horizontal focus clearance. The desktop-project geometry likewise passes with its narrower scrollbar-adjusted content area.
- Directly inspected `correction/mobile/11-bell-320-focus.png` and `correction/mobile/pane-dark-320.png`: all three complete labels are visible on the second row; bell/search/compose/account/rail controls remain readable and positioned above them; the bell focus ring is visible. Inspected both projects' `12-primary-routes-320-nojs.png`: all three route labels remain readable without JS. These replace the original clipped header as correction proof; original before captures remain preserved.
- `tests/browser/notifications-unified.spec.ts:215` now opens A5's native mobile settings disclosure, when present and closed, before selecting visible Replay. The account disclosure stays closed. Inspected `correction/mobile/bell-tour.png`: the actual Notifications tour step highlights the visible bell while the settings section chooser is open. This is an evidence-flow adaptation, not a new runtime tour behavior.
- Supplied correction logs end in **59 PHP tests / 697 assertions** and **45 browser passes / 5 viewport skips** across the two affected specs. These are verified implementation-run log results, not fresh reviewer executions. Paired application digests were refreshed and the pinned reconciliation commit is unchanged.

Evidence caveat for the parent's already-active final capture gate: the mobile no-JS correction PNG contains blank compose/bell glyphs, whereas its desktop counterpart and the mobile JS screenshot show the glyphs. The earlier mobile no-JS PNG already showed blank compose. This correction does not alter SVG markup or glyph styling, and the label navigation assertions pass; this is not established as a correction regression. Inspect the final combined no-JS capture before canonical promotion rather than claim every glyph is proven by the mobile correction PNG. Parent was notified directly.
