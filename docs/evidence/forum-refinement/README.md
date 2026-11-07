# Forum theme refinement: audit, implementation and validation

**Date:** 2026-11-07, revised 2026-11-08 after an independent review
**Base revision:** `b1d2a59` (PR #93 merged; ADR 0056), plus `b67e6ce`, which records its live release
**Candidate asset version:** `0ab71f5ca65e7fa0`
**Decision:** [ADR 0057](../../adr/0057-forum-theme-refinement-and-imladris-retirement.md)
**Release status:** local candidate. Not merged, deployed or verified in production.

This record covers an independent audit of the merged Forum theme, the fixes
and Imladris retirement that followed, a review of those changes, and the local
evidence for them. It is not a WCAG conformance claim.

## Environment

PHP 8.3.6, PHPUnit 11.5.56, MariaDB 10.11.14, Node 22.23.4, Playwright 1.61.1
driving Chromium 153 (headless) on Linux. Desktop is 1280 × 800 CSS pixels;
phone is 390 × 844 at device scale factor 2 with touch emulation. The PHP
built-in server served the normal CSP and fingerprinted assets from local,
disposable fixture databases (`tests/browser/prepare.sh`). One browser engine
only: no Firefox, Safari, physical device or screen reader.

## Audit method and results

The audit scripts are in [`tests/browser/forum-type-audit/`](../../../tests/browser/forum-type-audit/README.md).
`run.mjs` measured every visible text node, interactive target and link-styled
control on 22 routes (9 guest, 8 member, 5 operator), in light and dark, at
both viewports: 88 states. It recorded computed size, face, case, tracking,
WCAG 2 contrast (with APCA as information), target size and label centring,
and ran axe with the WCAG 2.0–2.2 A/AA tags; `targets.mjs` checked WCAG 2.5.8's
spacing exception. Both columns below were re-measured on 2026-11-08 with
those scripts on freshly prepared fixtures, the base on an unmodified worktree
of `b1d2a59`. Separate passes on 2026-11-07 covered keyboard traversal (70 Tab
stops per page), 320px reflow, the four WCAG text-spacing overrides, forced
colours, the phone order sheet and a 320 × 256 viewport (1280 × 1024 at 400%
zoom); the spec below re-checks the centring and 320 × 256 results.

Visible, non-decorative text instances across the 88 states, at the default
text size (`metrics.mjs`):

| Measure | Merged theme (`b1d2a59`) | Candidate |
| --- | --- | --- |
| Text below 14px | 1,708 (135 elements; smallest 9.28px) | 0 |
| Code face outside code and exact identifiers | 900 (42 elements) | 0 |
| Capitals (`text-transform: uppercase`) | 390 (42 elements) | 0 |
| Positive letter spacing | 1,398 (91 elements) | 0 |
| Link-styled controls with labels more than 1.5px off centre | 9 controls on the audited pages (2–5px), plus all 9 options of the phone order sheet (8.5–9px) | 0; the sheet options measure 0.3px (an admin tab sits 1.8px off beside its 2px current-tab border; a top-aligned card and a two-line notification row are not label controls) |
| Undersized targets failing 2.5.8's spacing exception | 0 | 0 |
| axe violations (WCAG tags) | 0 | 0 |
| axe `aria-prohibited-attr` incompletes | 8 | 0 |
| Pages with horizontal overflow at 320px | 0 | 0 |
| Focused post links covered at 320 × 256 | Both post author links, at all 12 of their Tab stops | 0 |

The contrast results were already sound: 0 computed failures other than a
disabled pager label (exempt) and an `aria-hidden` decorative star. The
remaining axe `color-contrast` incompletes (658 node checks) are the same
classes recorded for ADR 0056: short counts, overlapping covers and scroll
positions where axe cannot identify a background.

## Review follow-up

An independent review of the first candidate (`4fcbf8e`) found one blocker and
several defects. All are fixed in this candidate:

- **Release retention.** The ADR 0056 release `5dc1b40f3b23d46d` was live (all
  11 delivery files byte-identical on the public site) but not pinned, so this
  build would have dropped its three stylesheets and broken already-open pages.
  `b67e6ce` records it as deployed; receipts are in
  [`../forum-theme/production/`](../forum-theme/production/). This build retains
  it and retires `67ba8434286ff7e6`.
- **Lower-case labels.** Labels that had relied on CSS capitals rendered stored
  values in lower case once capitals were reset: board access in the rail,
  report status and reason, appeal statuses and actions, roles, ban periods,
  email delivery states and the draft revision chip. Templates now case them
  (`label_text()`, `moderation_action_label()`); `AppLabelCaseTest` and the
  spec's label check cover them.
- **Presence poll.** Rows the poll rebuilt lacked the name span and said
  "you"; `app.js` now mirrors the server markup.
- **Scroll padding.** At 480px high or less the topic still reserved the bar's
  and dock's height; it now reserves 12px.
- **Inheritance.** The case and tracking reset now inherits, so a Forum,
  package or branding rule reaches descendants.
- **Smaller items.** Identifier inputs, slugs and versions returned to the code
  face; monogram initials keep their size; the board picker caps long names;
  dead star rules and stale Imladris references were removed; DESIGN.md's 44px
  target rule was restored.
- **Tests.** The general stylesheet guards of the deleted builder suite moved
  to `ForumRuntimeAssetTest`, with new floor, identifier and reset guards; the
  member-surface bridge test compares exactly again after reversing the two
  mechanical rewrites; the spec now measures what it claims.

## Browser evidence

`tests/browser/forum-refinement.spec.ts` (`npm run evidence:forum`) passed **9
cases, 3 skipped by design** (phone-only skips for the two rail cases and the
zoom case):

- 20 routes (guest, member, a member with an appealable removal, moderation
  queues and operator screens) in light and dark at both viewports, with no
  text below 14px, including `aria-hidden` text and text drawn by
  `::before`/`::after`; no capitals, positive tracking or lower-case labels;
  and no code face outside code and identifiers. Post prose computes to
  `sans-serif` and interface text to `system-ui`; the page title reads
  `Welcome to RetroBoards · RetroBoards`;
- zero axe violations with WCAG 2.0–2.2 A/AA **and best-practice** rules on all
  80 scans (`desktop/*-axe.json`, `mobile/*-axe.json`, incompletes retained);
- no horizontal overflow on any of those states;
- topbar sign-in controls, the directory order and preview choices (in the
  phone sheet, which the test asserts is open), the guest search scope and
  order options, and operator link-buttons centre their labels within 1.5px;
- the composer's board picker keeps a long one-word name inside the rail;
- the Online list's You and Staff tags stay whole beside a long name that
  truncates, in rows the server draws and rows the presence poll rebuilds
  (`desktop/presence-long-names.png`);
- at 320 × 256, for a guest with and without scripting and for a member with
  the reply composer, the thread dock is `position: static`, scroll padding is
  12px and no focused post author link is covered
  (`desktop/topic-320x256.png`, `desktop/topic-320x256-member.png`).

Selected captures are retained in `desktop/` and `mobile/`; the spec reproduces
the full set. `compare/` holds before/after pairs from the audit: topic, inbox,
appearance settings, composer and member directory, a phone topic, the phone
order sheet, and the 320 × 256 topic before the fix.

### Regression comparison against `b1d2a59`

Broader specs ran on the candidate and, in parallel, on an unmodified worktree
of `b1d2a59` with its own server and fixture database. Many fail on both in
this environment; the comparison isolates differences.

First candidate (`4fcbf8e`):

| Run | Candidate | `b1d2a59` | Candidate-only failures |
| --- | --- | --- | --- |
| Inbox, profile, admin dashboard (3 specs) | 22 failed, 37 passed | 22 failed, 37 passed | None |
| Thread, header, chrome, menus (16 specs) | 53 failed, 148 passed, 34 did not run | 67 failed, 160 passed | Only in two fixture specs that require a `retroboards_e2e_*` database name; rerun below |
| Those two specs with private database names | 18 failed, 12 passed | 18 failed, 12 passed | None |
| Inbox, settings, branding, consoles, gate A (25 specs) | 135 failed, 218 passed, 28 did not run | 133 failed, 220 passed, 28 did not run | Two content-console cases pinned 13.44px inline code; updated to the 14px floor and now pass. One gate-A phone case fails alone on `b1d2a59` too (order-dependent) |
| Accessibility and package review | 6 failed, 30 passed | 6 failed, 30 passed | None |

This candidate, after the review fixes, on the surfaces they touch (moderation,
members, appeals, drafts, content, features, packages, admin, thread view,
polls, providers, presence, profile, both ADR 0056 specs, accessibility and
gate A):

| Run | Candidate | `b1d2a59` | Candidate-only failures |
| --- | --- | --- | --- |
| 19 specs, 362 tests each | 47 failed, 222 passed | 44 failed, 225 passed | Six. Four asserted the old behaviour (a lower-case appeal status; capitals on a profile heading) and were updated to sentence case. A presence case ran after its fixture member left the online window, and a members-console axe scan caught a colour transition; both passed on rerun, and the same members-console case failed on `b1d2a59`'s phone run |
| Rerun: refinement, appeals and presence specs | 43 passed, 3 skipped | — | None |
| Rerun: profile and members-console specs | 8 failed | 10 failed (in the batch) | None |

`thread-mobile-recovery.spec.ts` was excluded after its cases timed out
identically on both trees. The pre-existing failures on `b1d2a59` include
assertions of retired Imladris colours ("gold", "Bruinen"), capitals and
fixture prerequisites; they are outside this change and are not counted as
passes.

## PHP and asset checks

| Check | Result |
| --- | --- |
| Forum CSS generation/drift | Passed `php bin/build-forum-css.php --check` |
| Application asset build/drift | Passed `npm run build` and `npm run check:assets`; 45 public files staged for Workers, including the retained ADR 0056 stylesheets; the Imladris fonts, licences and star are no longer published |
| Worker asset and retention tests | **35 passed**, 0 failed (`npm run test:assets`) |
| Full PHP suite | **3,563 tests, 33,810 assertions, 2 failures, 2 skips** |

The two PHP failures are `AppReactionTest::testStaticReactionsKeepVisibleGestureNamesWithoutReactorIdentities`
and `AppReactionTest::testReactionNamesAreBatchedAcrossPostsAndEmoji`. Both
fail identically on the unmodified `b1d2a59` worktree against the same MariaDB
10.11, whose `reactions.emoji` column uses `utf8mb4_general_ci` and equates
distinct supplementary emoji (also recorded for ADR 0056). The suite lost the
25 tests of the deleted Imladris builder suite and gained 15 (Forum runtime
guards and label casing). A local `.env` with `MAIL_DRIVER=sendmail` and no
sendmail binary fails `AppEmailChangeTest` for environmental reasons; the run
above used the in-memory mailer.

## Limits

- One engine (Chromium 153). The 320 × 256 viewport approximates 400% zoom; it
  is not a recording of browser zoom.
- No NVDA, JAWS or VoiceOver session. Spoken output, browse order and dynamic
  announcements remain unverified.
- Operator palettes, installed theme packages and translated labels were not
  part of the matrix.
- The 14px floor holds at the default text size; the Small reading preference
  scales it to 12.25px, as intended. Dense rows (admin tables, the inbox queue)
  are taller; that was reviewed in the captures rather than measured.
- The label check covers labels that templates write. Names members and
  operators enter (categories, tags, titles) are shown as written.
- Production deployment, live asset bytes and production behaviour are separate
  outcomes.
