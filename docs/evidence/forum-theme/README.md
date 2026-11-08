# Forum theme: implementation and validation

**Date:** 2026-11-07
**Base revision:** `8a028592a09d07c269be9eb9a17c3c5969c1dc37` (incorporating `409532f55fd5a3e72e062cdf1ef94ba0a444edfb`)
**Final generated asset version:** `5dc1b40f3b23d46d`
**Release status:** merged onto `main` with `c66e3b55dba6da68` retained in `deployedReleases`.

The conventional **Forum** presentation replaces the active Imladris theme under
[ADR 0056](../../adr/0056-conventional-accessible-forum-theme.md). This record
covers the code and tests in this change, the final asset closure above, and
local synthetic fixtures. The target is WCAG 2.2 AA, including applicable Level A
criteria. The evidence is **not a full WCAG conformance claim**.

## What changed

The default interface uses system sans-serif typography, neutral light/dark
surfaces, blue actions, familiar forum labels, explicit control boundaries, and
visible focus. Discussion text is `1.125rem` with unitless `1.65` line height and
a reading measure up to `65ch`. Secondary text starts at `.875rem`.

The application remains PHP-rendered HTML with native forms and progressive
enhancement. No general UI runtime was introduced. The active shell stops
loading Imladris CSS and its font preload. Existing routes, uploaded logos,
operator settings, saved appearance keys, and authored content remain usable.
Legacy preference values are compatibility keys, not a second active theme.

The stylesheet ownership is explicit:

1. `resources/forum/components.css` generates `forum-base.css` in the
   `forum.components` cascade layer. It retains compatible component structure.
2. `public/assets/app.css` supplies feature/layout rules in `application`.
3. `resources/forum/tokens.css` and `theme.css` generate unlayered
   `forum-theme.css`, owning the active presentation.
4. Editor, declarative package, and operator-branding styles retain their later
   position and existing validation boundaries.

This is a presentation migration with a retained compatibility layer, not a
claim that the large historical application stylesheet has been fully refactored.
The strict CSP remains in place; no external UI runtime, font service, inline
style permission, or inline script permission was added.

The implementation also corrects concrete accessibility defects found during
review: native multiline textbox semantics for slash/reference suggestions;
a named formatting group; a redundant label on a paragraph; revealed-spoiler,
search-highlight and urgent/error colors; undersized messaging labels; and a
compact reply field that clipped the new reading type. The latter now fits both
normal and Large text while preserving the latest upstream Inbox behavior.

Primary-source decisions are documented in the
[reading and accessibility baseline](../../design-system/forum-accessibility.md)
and [UI library evaluation](../../design-system/forum-library-evaluation.md).

## Results

| Check | Recorded result |
| --- | --- |
| Forum CSS generation/drift | Passed `php bin/build-forum-css.php --check` |
| Application asset build/drift | Passed `npm run build` and `npm run check:assets` for the asset version above |
| Affected Worker asset/retention tests | **15 passed**, zero failed; [log](logs/worker-assets-final.log) |
| Theme and keyboard-control cases | **14 passed** across desktop/mobile; [log](logs/forum-final.log) |
| Latest Inbox behavior | **17 passed**, three desktop-only cases skipped on mobile; [log](logs/forum-inbox-final.log) |
| Native/rich composer suggestions | **12 passed** across desktop/mobile; [log](logs/forum-composer-final.log) |
| Automated accessibility matrix | **52 scans, zero violations**, with incomplete results retained for review |
| Full application PHP suite | **3,573 tests, 33,999 assertions, 0 failures, 1 skip** on PHP 8.5.4 / MariaDB 11.8.9 (`rb-mariadb`); **3,573 tests, 33,974 assertions, two failures, two skips** on MariaDB 10.11.14 ([log](logs/phpunit-final-mariadb.log)) |
| Clean-base comparison | Both MariaDB 10.11 reaction failures also reproduce on unchanged `409532f`; details below |
| Published asset retention | All **30 unique pinned files** (`c66e3b55dba6da68`, `3630440af0d45598`, `67ba8434286ff7e6`) match SHA-256 values in both delivery trees; [results](retention-results.json) |
| Production deployment and verification | **Not performed** for this candidate |

There are **43 passing browser cases** in total, not 46: the three inapplicable
mobile Inbox cases are skips. Repeated development runs are not added to that
count. [browser-results.json](browser-results.json) records the named cases,
project results, scan files, and incomplete-rule targets.

### Full-suite failures and baseline comparison

The full candidate suite ran on PHP 8.3.6, PHPUnit 11.5.56 and MariaDB 10.11.14.
It finished with exactly these two failures:

- `AppReactionTest::testStaticReactionsKeepVisibleGestureNamesWithoutReactorIdentities`
- `AppReactionTest::testReactionNamesAreBatchedAcrossPostsAndEmoji`

A separate clean worktree at `409532f`, with its own physical dependency copy,
reproduces both failures against the same MariaDB configuration. The
[baseline debug log](logs/phpunit-baseline-mariadb-debug.log) records both failed
assertions and normal completion. The reaction repository and these tests are
unchanged by the theme migration.

The local `reactions.emoji` column uses `utf8mb4_general_ci`. It equates distinct
supplementary emoji, so toggling a second emoji can remove the first instead of
creating a second reaction. The static test consequently sees one label instead
of nine; the batching test loses the first post's reactions. This is an existing
collation/identity problem in this configuration. No test, schema, or reaction
logic was weakened to produce a green result. **The full application suite is
not green.** A separate backend correction is needed for this configuration.

An auxiliary MySQL 8.0.36 run exposed existing SQL and JSON portability issues
and exited without a complete final suite summary; it is not counted as a
completed full-suite result. Two targeted tests also fail on the clean base:
[MySQL baseline log](logs/phpunit-baseline-mysql8.log). The queue uses
`LOCK IN SHARE MODE SKIP LOCKED`, whereas MySQL's shared locking syntax with
that option is `FOR SHARE SKIP LOCKED`. The audit test additionally expects
compact JSON whitespace while MySQL returns its normalized representation.
These backend paths were left outside this theme change. The baseline findings
explain the qualification limit; they do not turn failed tests into passes.

## Browser scope and environment

The browser evidence uses headless Chromium 153 on Linux. Desktop is
1280 × 800 CSS pixels; mobile is 390 × 844 with touch/mobile emulation and
device scale factor two. Specific Inbox cases use 1280/1440px desktop widths
and shorter viewports; the reflow scenario uses 320px. This is one browser
engine, not a physical-phone or Safari qualification.

The managed environment's browser download was unavailable, so a locally
extracted Chromium executable ran the repository's Playwright harness. Its
single-process launch required one selected case per invocation. No `--disable-web-security` flag or Playwright `bypassCSP` override was used.
The managed runtime used `--no-sandbox` and `--single-process`. The application
served its normal CSP and fingerprinted assets. All sends and created topics are disposable local fixture
data; no production messages or content were written.

### Scan matrix

Each route/state below was scanned in light and dark modes at both viewport
projects, using `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa` and `wcag22aa` tags.
The saved JSON files are in `desktop/` and `mobile/`; selected screenshots are
retained here, and the spec can reproduce the complete capture set.

| Session | Route or state | Artifact stem |
| --- | --- | --- |
| Guest | Board directory `/` | `boards` |
| Guest | Board `/c/general` | `board` |
| Guest | Topic `/t/1-welcome-to-retroboards` | `topic` |
| Guest | `/login`, `/register` | `login`, `register` |
| Guest | `/search?q=welcome` | `search` |
| Guest | `/u/alice` | `profile` |
| Member | `/inbox`, `/messages` | `inbox`, `messages` |
| Member | `/settings/appearance` | `appearance` |
| Operator | `/admin/branding` | `branding` |
| Member | `/compose` | `compose` |
| Member | Created conversation `/messages/{fixture-id}` | `conversation` |

For example, `desktop/boards-light-axe.json` records the light directory scan.
Screenshots capture the viewport, not the entire scrolling document. Light/dark
matrix states set `data-theme`; separate cases verify saved appearance and the
system-dark path. Arbitrary operator palettes and every package combination
are not covered by this default matrix.

### Interaction and content evidence

- Tab reaches the skip link and Enter transfers focus to `#main`. The mobile
  drawer contains Tab focus while the main content is inert and restores focus
  on Escape/close. Native account disclosure and emoji dialog paths verify
  initial focus, dismissal, appropriate containment, and focus restoration.
- At 320 CSS pixels, the topic tolerates the four WCAG spacing overrides
  together. The directory also remains within its viewport with 200% CSS text
  enlargement. **Actual 400% browser zoom was not performed.**
- System-dark and reduced-motion paths resolve correctly in the checked states.
  Forced-colors emulation preserves a visible focus outline and a native,
  keyboard-operable appearance checkbox.
- With JavaScript disabled, native login, appearance save/persistence, and
  direct-message submission succeed. The dedicated topic draft case does not
  submit; the separate native/rich suggestion cases do exercise local topic
  submission and insertion behavior.
- Native and rich suggestion tests preserve selection, arrow-key navigation,
  insertion, Escape, focus, popup ownership, and cleanup. A focused axe scan
  includes the native textarea and its popup. Formatting and emoji buttons
  retain their own disclosure states.
- The integrated Inbox retains independent pane scrolling, reachable replies,
  a 410px list column, row/control click separation, menu sizing, checkbox color
  scheme, pagination targets, login explanation, and tour placement. Normal
  and Large text fit a full line in the resting reply field; expand/minimize
  behavior remains functional.
- Conversation fixtures include a long URL, link, and spoiler. Revealed-spoiler
  contrast is measured in both modes. The opening metadata is brought into
  view and checked at or above 14px; author names are 16px. The retained
  conversation-opening captures show those previously offscreen labels.

## Contrast and incomplete results

[contrast.json](contrast.json) records **11 default token pairs in both modes**.
For example, ordinary primary text is 14.10:1 light / 16.96:1 dark; secondary text
is 7.58:1 / 11.95:1; primary action labels are 6.70:1 / 9.84:1; field boundaries
are 4.76:1 / 5.72:1. Revealed spoilers, highlighted search text, and urgent/error
states now use matched semantic foreground/background tokens.

These are palette calculations, not proof of every rendered state or custom
operator configuration. The final matrix has **32 `color-contrast` and eight
`aria-valid-attr-value` incomplete rule occurrences**. They remain in the raw
JSON. An unknown `contrastRatio: 0` means the checker could not determine a
ratio; it is not a measured 0:1 failure.

| Incomplete group | Review disposition |
| --- | --- |
| Short bell/profile counts | The checker reports insufficient short text. Default bell pairs calculate 7.57:1 light / 7.82:1 dark; white profile counts on the cover are 14.10:1. Captures show readable counts. |
| Profile cover background overlap | Inspected cover text is unobscured. Default source pairs range from 8.68:1 after metadata opacity to 14.10:1. This does not assess arbitrary custom imagery. |
| Directory rows, guest prompt and partially obscured content | Visible content and source pairs were reviewed. Scroll-position and viewport crops can prevent automatic background identification; the matrix does not prove every lower row or state. |
| Conversation opening metadata | Follow-up captures explicitly scroll to the opening labels and verify their reading sizes. The earlier offscreen overlap finding is not treated as a color failure. |
| Decorative empty-state star | It is hidden from assistive technology and sits beside a textual empty state. The non-BMP diagnostic does not identify independent text information. |
| Hidden popup control IDs | Source and focused opened-state tests verify existing targets and popup ownership. Hidden references alone are valid; native textarea semantics and the unsupported role/state were corrected separately. |

The former prohibited-label findings no longer occur after the paragraph label
was removed and formatting controls became a named group. Automated checks are
useful but cannot establish complete reading order, appropriate alternative
text, understandable announcements, or screen-reader usability.

## Visual comparison

The desktop and two-phone concepts establish direction; the captures show the
actual seeded application. Both were inspected. The neutral surfaces, blue
controls, sans-serif hierarchy, readable rows and responsive wrapping are
implemented. The concept's community names, counts and discussions were not
copied into production data. Guest captures correctly show guest actions;
signed-in captures retain the application's real Inbox, Messages, notifications,
creation controls and identity menu. Existing reactions, badges and composer
behavior remain, rather than being replaced with a static mockup.

Representative captures:

- [Desktop directory, light](desktop/boards-light.png) and [dark](desktop/boards-dark.png).
- [Desktop board](desktop/board-light.png) and [dark topic](desktop/topic-dark.png).
- [Mobile directory](mobile/boards-light.png) and [topic](mobile/topic-light.png).
- [Inbox reading split](inbox/desktop/split-1440.png), [compact reply](inbox/desktop/reply-at-rest.png), and [mobile pane at its reply](inbox/mobile/pane-scrolled-to-reply.png).
- [Dark conversation opening](desktop/conversation-opening-dark.png), [mobile conversation opening](mobile/conversation-opening-light.png), [forced-colors checkbox](mobile/forced-colors-switch.png), and [native dark-mode flow](mobile/no-js-dark.png).

## Remaining work and release boundary

Before claiming broad accessibility conformance, test actual 400% browser zoom
and real reading/contribution tasks with NVDA on Windows and VoiceOver/Safari on
Apple platforms. Spoken announcements, browse order, and full dynamic-update
behavior remain unverified. Comprehensive validation/error states, every
moderation/admin screen, every target, translations, alternate branding and
installed-package combinations also require their own coverage. These are
limits of this evidence, not assertions that those flows fail.

The retained compatibility component layer can be simplified incrementally as
individual surfaces are migrated. That cleanup is separate from removing
Imladris as the active built-in presentation. No schema migration or member-data
conversion is required for this change.

Rollback must restore the chosen application revision together with its matching
manifest and assets while preserving unrelated upstream fixes. Historical
published assets are retained for already-open pages; see
[asset-retention.md](asset-retention.md). The candidate is not marked deployed.
Merge, deployment, live asset-byte verification and production behavior checks
remain separate outcomes from this local implementation and review.

### Publication of `b1d2a59`

PR #93 merged to `main` as `b1d2a59`. The account that recorded this could not
read Cloud Build, Cloud Run or Workers Builds, so no build or revision receipts
are included. At 00:03 UTC on 2026-11-08 the public site served this release:
all 11 delivery files of `5dc1b40f3b23d46d` matched the manifest byte for byte,
the home page and its preload `Link` header referenced only those files (and no
Imladris stylesheet), and `/healthz` answered
`{"status":"ok","database":"ok"}`
([`live-verify-5dc1b40f3b23d46d.json`](production/live-verify-5dc1b40f3b23d46d.json)).
The live release was then recorded with
`npm run assets:record-release -- 5dc1b40f3b23d46d`, and `npm run check:assets`
passed (`production/asset-record-*-5dc1b40f3b23d46d.log`). That record retires
`67ba8434286ff7e6` and its one exclusive file (`app-style-BQrKPQLm.css`), and
rides with the next planned change (ADR 0051).
