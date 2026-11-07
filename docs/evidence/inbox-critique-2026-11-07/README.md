# Inbox critique remediation — 2026-11-07

Qualification for the `/inbox` reading-pane and triage hardening that answers the
2026-11-07 design critique
(`.impeccable/critique/2026-11-07T10-59-44Z__templates-inbox-php.md`, 24 of 40).
Decisions and deliberate non-changes: ADR 0029, "Follow-up resolution —
2026-11-07, the /inbox critique". Shipped notes: `CHANGELOG.md`,
"[2026-11-07] - Inbox reading pane and triage hardening".

## Environment

- Chromium 149.0.7827.55 through Playwright 1.61.1: the `desktop` project
  (1280×800, with tests setting 1280, 1440 or 1280×520 where they measure) and
  the `mobile` project (390×844, touch, with 320 and 390×560 where set).
- PHP 8.5.4 built-in server against MariaDB 11.8.9 (`rb-mariadb`), on a private
  database prepared by `tests/browser/prepare.sh` and seeded by
  `tests/browser/forum-inbox-fixture.php`.
- Delivery assets rebuilt with `npm run build`: candidate manifest
  `c66e3b55dba6da68` (`app-style-CFVEFxJL.css`, `app-107ef98522c229d0.js`,
  `composer-6daea7e3dfcc4d41.js`, `tour-4573ee743ebaf07c.js`).

```sh
DB_DATABASE=<private> bash tests/browser/prepare.sh
# serve: php -S localhost:<port> -t public public/index.php with the e2e environment
cd tests/browser
E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:<port> RB_BASE_URL=http://localhost:<port> \
  npx playwright test inbox-reading-hardening.spec.ts
```

## Results

- **`inbox-reading-hardening.spec.ts`**: 17 passed and 3 skipped (desktop-only
  checks on the mobile project) on the final tree, writing the screenshots below.
  Served from a worktree at the pre-fix commit `a2789ba0` against the same
  database, **all 17 fail**.
- **Companion specs on the final tree**: `forum-inbox-remediation.spec.ts`, the
  reading-pane formatting test in `chrome-consistency.spec.ts` and the tour-copy
  test in `header-polish.spec.ts`: 17 passed, 1 skipped.
- **Broad regression.** Nineteen specs touching the changed surfaces (the inbox
  specs, `chrome-consistency`, `community-inbox-theme`,
  `topic-row-consolidation`, `composer-expansion`, `composer-shell`,
  `shared-subheader-context`, `member-surfaces`, `header-polish`,
  `auth-hardening`, `passkeys`, `a11y` (which also selects
  `field-error-a11y`), `board-index-hardening` and `mobile-controls`) ran on both
  projects against a fresh database. As a baseline, the same list ran from a
  worktree at `a2789ba0`, HEAD's own specs against HEAD's own app, under
  identical conditions.
  - HEAD: 271 passed, 39 failed, 62 skipped. This tree: 286 passed, 41 failed,
    65 skipped, the difference including the twenty new hardening tests.
  - **37 failures are common to both**: pre-existing, outside this change, and
    listed below.
  - **2 fail only on HEAD**: `forum-inbox-remediation`'s reason-cue test, whose
    star assertion this tree updates (the cue has had no glyph since 2026-10-08).
  - **4 failed only on this tree**, each resolved and re-run green.
    `community-inbox-theme`'s pane reply-box test still assumed the old pane
    under 480px. `inbox-controls-polish`'s long-title test measured
    `.thread-row-main`, which renders no box beside the open pane, so it now
    measures the title line. `topic-row-consolidation`'s archived-contrast check
    failed on both projects: the narrower queue left a hovered row under the
    sign-in pointer, and axe sampled its background mid-transition after an
    in-place theme flip. The pointer now leaves the rows and the check waits
    for the row to come to rest. At rest the chip measures about 5.6:1 on the
    hovered twilight row.
  - Run alone on fresh databases, `community-inbox-theme.spec.ts` passes 13 of
    13 on both HEAD and this tree. Two of its tests (unread reconciliation and
    the composer lifecycle) depend on Alice's unread state. They failed only in
    an isolated run that followed another run on the same database, which had
    seeded the forum-inbox fixture and its global engagement cutover; they
    passed in both broad runs.
- **PHPUnit** (`composer test`, Composer's process timeout disabled): 3,571
  tests and 33,958 assertions, 1 failure, 1 skipped, 8 deprecations. The failure,
  `AppEmailChangeTest::test_request_keeps_the_old_contact_and_shows_pending_address`,
  fails identically on HEAD. It needs `MAIL_FROM`, because mail fails closed.
  This checkout has no `.env`, so the suite ran with a dummy all-zero `APP_KEY`
  in the environment, as the browser harness supplies one. Without a key every
  encryption- and HMAC-dependent class fails: OIDC, passkeys, TOTP,
  unsubscribe, webhooks and providers.
- **Generated assets**: `npm run check:assets` reports the manifest current, and
  `php bin/build-imladris-assets.php --check` reports Imladris current. The
  application stylesheet's generated bridge is byte-identical to HEAD's
  (`AppImladrisFidelityTest`), with every change restated after it.

### Pre-existing browser failures

These fail on HEAD and on this tree under identical conditions:

- `a11y.spec.ts`: admin dark-surface pages have no serious axe violations (desktop, mobile; `/admin/extensions` answers 404)
- `a11y.spec.ts`: member appeal and server-draft pages have no serious axe violations (desktop, mobile)
- `a11y.spec.ts`: phase 4 custom emoji surfaces have no serious axe violations (desktop, mobile)
- `chrome-consistency.spec.ts`: long subscription names fit the settings content with JavaScript on and off (mobile)
- `community-inbox-theme.spec.ts`: failed Inbox fetches fall back to the canonical topic route (mobile; since ADR 0045 a failed preview offers Try again in place)
- `community-inbox-theme.spec.ts`: mobile preview keeps its reply composer contained and expands it on focus (mobile)
- `community-inbox-theme.spec.ts`: mobile top bar stays one row and Search remains reachable (mobile; the member bar is 108px by design)
- `composer-shell.spec.ts`: mobile compact dock expands to a contained formatting overflow and anonymous disclosure (mobile)
- `field-error-a11y.spec.ts`: the shell reserves scroll padding for the sticky topbar and a stable gutter (mobile; 109px against 108px)
- `header-polish.spec.ts`: a focused control keeps its own corner and wears the gold halo (desktop, mobile)
- `header-polish.spec.ts`: counts carry their words on their links, as the bell's do (desktop, mobile)
- `header-polish.spec.ts`: on a phone the lockup holds only what it shows (mobile)
- `inbox-accessibility-hardening.spec.ts`: queue and reading-pane focus retain topic shortcuts (desktop, mobile)
- `inbox-controls-polish.spec.ts`: row hiding, tomorrow and restoration work enhanced (desktop, mobile)
- `inbox-controls-polish.spec.ts`: shared panels stay contained, expose recovery early and use consistent touch targets and dismissal (desktop, mobile)
- `member-surfaces.spec.ts`: member surfaces produce same-state visual evidence at the handoff and mobile viewports (desktop, mobile)
- `mobile-controls.spec.ts`: banned and suspended reaction counts keep visible names without reactor identities (desktop, mobile)
- `mobile-controls.spec.ts`: responsive reaction pickers stay inside the viewport and retain native submission (desktop)
- `mobile-controls.spec.ts`: mobile board drawer leaves room beside every member surface (mobile)
- `mobile-controls.spec.ts`: mobile reaction choices show distinct icons and visible names in labelled rows (mobile)
- `mobile-controls.spec.ts`: reply stays inside its frame and preserves a minimized draft, Markdown and rich (desktop, mobile)

Several of these depend on data that other specs in the same run change, so a
run of one spec alone on a fresh database can pass where the combined run fails.

## Screenshots

`desktop/`: `split-1280.png` and `split-1440.png` (queue rail and reading pane),
`pane-scrolled-to-reply.png`, `reply-at-rest.png`, `reply-open.png`,
`menu-data-inbox-scope-menu.png`, `menu-inbox-sort-menu.png`,
`menu-empty-scopes.png`, `queue-twilight.png`, `queue-pager.png`,
`login-from-inbox.png` and `tour-welcome.png`.

`mobile/`: `pane-scrolled-to-reply.png`, `reply-at-rest.png`, `reply-open.png`,
`reply-minimized.png`, `menu-inbox-compact-menu.png`, `queue-pager.png`,
`login-from-inbox.png` and `tour-welcome.png`.

## Production release record

`production/` holds the receipts for recording the deployed asset release
`3630440af0d45598`, the manifest of `a2789ba0`. At 12:08 UTC its 25 delivery
files on `https://forum.candidary.online` were byte-identical to that manifest
(`live-verify-3630440af0d45598.json`). The release was then recorded with
`npm run assets:record-release -- 3630440af0d45598` from a clean worktree of
`a2789ba0`, and `npm run check:assets` passed (`asset-record-release.log`,
`asset-record-check.log`). The record retires the oldest pinned release
(`545bd84edf0dc37c`) and its two exclusive files. `origin/main` was still
`a2789ba0` when re-checked at about 17:00 UTC; that record is `1975bfce`.

### Publication of `409532f5`

Pushed to `main` with `1975bfce` at 17:57 UTC (`deployment-409532f5.json`).
Cloud Build `c95b5371` succeeded at 17:59:53 UTC. Cloud Run revision
`retroboards-00074-dzr` is Ready, and all four cron jobs run
`app:409532f55fd5a3e72e062cdf1ef94ba0a444edfb`. Workers Builds `ef88c102`
succeeded at 17:58:20 UTC. The verifying account cannot read the service's
traffic split (`run.services.get` is denied), but at 18:00 UTC the public site
served what only this commit renders: all 25 delivery files of
`c66e3b55dba6da68` matched the manifest byte for byte, the login page
referenced them, `/healthz` answered `{"status":"ok","database":"ok"}`, and a
guest's `/inbox` led to the new sign-in note while a plain `/login` did not
(`live-verify-c66e3b55dba6da68.json`). The live release was then recorded with
`npm run assets:record-release -- c66e3b55dba6da68`, and `npm run
check:assets` passed (`asset-record-*-c66e3b55dba6da68.log`). That record
retires `0abd713b0ebf7bca` and its one exclusive file, and rides with the next
planned change (ADR 0051).
