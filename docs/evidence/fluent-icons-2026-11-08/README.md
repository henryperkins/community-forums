# Fluent System Icons and the icon size choice: evidence

**Date:** 2026-11-08 (UTC) · **Base:** `7973a401`, which records the live
release `11b63fb564c36ce9` on `3a0f2c28` · **Decision:**
[ADR 0060](../../adr/0060-fluent-system-icons-and-icon-size.md) · **Asset
candidate:** `23ca787930a7a0a7`

Local evidence only: PHP 8.5.4, Node 22.22.1, Playwright 1.61.1 and Chromium
149.0.7827.55 at 1280 × 800 (desktop) and 390 × 844 (mobile). WebKit, Firefox,
physical devices and manual assistive-technology testing are not covered.
Production deployment and verification are separate outcomes.

## Results

| Check | Result |
|---|---|
| `composer check:icons`, `composer check:forum` | Current |
| `npm run check:assets` | Current. The live release `11b63fb564c36ce9` stays pinned, so `forum-theme-style-Ba2Ycb6L.css` stays published ([record](../asset-record-11b63fb564c36ce9/README.md)) |
| `composer test` | 3,671 tests and 35,654 assertions: 1 failure, which also fails on `main` (see Limits); 1 skipped; 8 deprecations |
| `fluent-icons.spec.ts`, `topic-row-consolidation.spec.ts` | 24 passed. 2 skipped by design: the desktop measurement at phone width and the phone touch target at desktop width |
| `thread-view-study.spec.ts`, star pill | Passed on desktop with the Fluent star pinned |
| Outline check, `icon-paint-crawl.cjs` | Before the theme rule: outlined icons in three containers. After it: none of 5,693 icons on 280 page loads |
| Comparison with `main`, 12 specs | 61 failures shared with `main`. The 9 failures only on this branch pass on both from fresh seeds |

At desktop width the spec measures menu and toolbar icons at 16px with standard
icons and 20px with larger ones, each drawing artwork made for that size. The
bell is 18px and then 20px. Topic-row stars stay at 18px and row chips at 9px.

## Screenshots

- Appearance → Icon size: `desktop-appearance-standard.png`,
  `desktop-appearance-larger.png`, `desktop-appearance-larger-dark.png` and the
  `mobile-` captures. Each choice card previews its own size. With larger icons,
  the rail's current section is drawn Filled.
- Post toolbar at both sizes: `desktop-post-toolbar-standard.png` and
  `desktop-post-toolbar-larger.png`.
- Inbox and topic pages at both sizes, light and dark, desktop and mobile:
  `{desktop,mobile}-{inbox,topic}-{standard,large}-{light,dark}.png`.

## Icons painted as outlines

Fluent glyphs are filled shapes. Older rules stroke any `svg` inside `.fab`,
`.chip`, `.badge`, `.accepted-flag` or `.nav-toggle-ic`, because the retired
glyphs were stroked. `icon-paint-crawl.cjs` makes GET requests only, as a guest,
a member and an admin at desktop width, and as the member and the admin at
phone width. It follows same-origin links and loads one page per route
pattern. It lists every `svg.icon` whose computed fill is `none` or that has a
stroke.

- Before the theme rule (`crawl-before-theme-rule.json`), it found three:
  - the phone menu toggle, both its link and its button (`.nav-toggle-ic`);
  - the Solved chip's check (`.chip svg`);
  - the board's floating New topic button (`.fab svg`).
- The crawl did not reach an accepted answer. On `main`, that flag's star has
  `fill: none` and a 2.6-unit stroke in a 100-unit box, about 0.34px at
  13px. On this branch it is Star Filled.
- After `svg.icon { fill: currentColor; stroke: none; }` was added to the
  unlayered Forum theme (`crawl-after-theme-rule.json`), the crawl found
  none. `fluent-icons.spec.ts` now checks each of these containers, and
  `IconsTest` keeps the rule in the theme.

## Comparison with `main`

`regression-comparison.txt` lists the results. Twelve icon-adjacent specs ran
with the same command against this branch, before the theme rule, and against
`main` (`3a0f2c28`). Each run used its own server and seeded database.

- **61 failures shared with `main`.** These specs still expect the retired
  Imladris type, colours and header geometry (ADRs 0056 and 0057). Also in
  this group: a settings fixture whose reset command fails, reactions
  rate-limited (429) late in the long run, and composer measurements and
  axe findings. They are not addressed here.
- **9 failures only on this branch.** One is the star pin in
  `thread-view-study.spec.ts`, now updated to the Fluent star. The other
  eight count or measure seeded data. This branch's database still had the
  inbox fixture from earlier runs: the Inbox showed 73 unread topics where the
  spec expects 1, the wider counts moved the 320px header controls by about a
  pixel, and profile counts changed. From fresh seeds, all eight pass on both
  this branch and `main`.

## Limits

- Chromium only. Forced colours, Windows High Contrast and screen readers
  were not checked for every icon. The icons are decorative (`aria-hidden`),
  and control labels are unchanged.
- Carryovers (ADR 0060): notification type icons, the composer's formatting
  buttons and the sign-in emblems still draw their own stroked SVGs.
- PHPUnit ran without a `.env`, using the browser runner's all-zero
  `APP_KEY`. Here,
  `AppEmailChangeTest::test_request_keeps_the_old_contact_and_shows_pending_address`
  gets a 422 instead of a redirect, and it fails the same way on `main`
  (`3a0f2c28`).
- Don't export `RB_TEST_FRESH=1` for the whole suite.
  `RecoveryEmailChangeRaceTest` starts
  `php -r "require 'tests/bootstrap.php'; …"`, which inherits the flag and
  tries to rebuild the schema. The suite's open transaction holds that
  schema, so both processes wait forever. To rebuild, run one test with the
  flag first.

## Reproduce

```sh
composer check:icons && composer check:forum && npm run check:assets
# The suite runs past Composer's 300-second default process timeout.
APP_KEY=<64 hex digits> DB_TEST_DATABASE=<private> COMPOSER_PROCESS_TIMEOUT=0 composer test
DB_DATABASE=<private e2e database> bash tests/browser/prepare.sh
(cd tests/browser && npx playwright test fluent-icons.spec.ts topic-row-consolidation.spec.ts)
node docs/evidence/fluent-icons-2026-11-08/icon-paint-crawl.cjs http://localhost:8011 crawl.json
```
