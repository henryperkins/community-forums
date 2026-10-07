# ADR 0042: Inbox controls follow the reading task

**Status:** Accepted — 2026-09-28

The mobile inbox put introductory copy, scope, three order pills, a bulk action,
an appearance preference, and keyboard instructions before its topics. The
scope and order are independent product choices, but their presentation made
them look like competing filters. The primary topbar routes also scrolled
inside a narrow strip, clipping Messages.

## Decision

- Use one compact Inbox heading. Remove the kicker and introductory paragraph.
- Keep independent, URL-backed Show and Sort disclosures. Name the count as
  topics, including the singular case after an unread preview removes a row.
- Put the current-page read action and Help in an actions disclosure. The action
  reads “Mark this page read”; its submitted IDs and server validation are
  unchanged. Help explains the two choices and reveals keyboard shortcuts when
  JavaScript is available. Appearance remains the home for row density.
- Use native details, links, and POST forms as the baseline. JavaScript keeps
  one inbox menu open, positions it within the viewport, restores trigger focus
  on Escape, and dismisses it on outside clicks or surrounding-page scrolling.
  Scrolling a menu's own contents keeps it open.
- At the existing 860px drawer breakpoint, give all primary routes the second
  row previously reserved for widths below 381px. Use the existing 108px height
  token for the header, drawer, scrim, and scrolling offsets.
- Compact Search at the existing 1080px breakpoint. This also resolves a
  pre-existing 901px overflow when unread counts and the persistent controls
  exhaust the single-row header's width.

This supersedes ADR 0029's permanent density statement and order-pill treatment
for the inbox, and broadens ADR 0032's narrow-screen navigation adaptation.
Scope inclusion, ordering, read gates, pagination, row density, preview behavior,
and the board-only rail ownership remain the existing product contracts.

## Evidence

See [the browser and test record](../evidence/inbox-header/README.md). The focused
browser checks cover light/dark themes, 320–1440px widths, complete route labels,
filter/order preservation, action-menu focus, accessibility, and no-JavaScript
page actions. Existing PHP and browser suites cover the underlying inbox and
shared chrome contracts.

## Addendum — 2026-10-07: phone row details

The 2026-10-06 header critique proposed wrapping the phone bar only when
needed; the owner kept this decision and asked for its details to be fixed.
One clause changes: a bar with a single route (a guest's, or a member's with
Inbox and Messages switched off) no longer takes the second row for Boards
alone, which is where its lockup already leads. It keeps one 62px row, and the
height token follows it. Every bar with more than one route keeps the two rows
and the 108px token.

Within the rows, the first row's controls are 44px targets with 8px gaps, the
routes keep the desktop's 13px labels, and the reading and focus order follow
the rows. ADR 0032 ("Phone rows and the drawer — 2026-10-07") records the full
change, and [the evidence](../evidence/header-phone-rows-2026-10-07/README.md)
records how it was checked.

The subsequent [header review](0032-unified-member-chrome.md#header-review-regressions--2026-10-07)
refines the spacing budget: gaps and horizontal padding now follow the actual
bar width, including classic scrollbar gutters and the Large text preference.
Targets, complete route labels, row order and the 108px height stay intact.
