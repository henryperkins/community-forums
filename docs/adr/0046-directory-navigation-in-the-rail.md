# ADR 0046: Directory navigation belongs in the side rail

**Date:** 2026-10-08
**Status:** Accepted
**Refines:** The rail ownership in ADR 0042 and shared row in ADR 0043.

## Context

The owner requested that Boards, Tags and Connections move from the directory's
horizontal pane strip into the side rail, with Notifications removed from that
group. The existing strip overlaps creation at 320px. This request supersedes
the earlier restriction that the rail contains no directory route links.

## Decision

- An Explore group leads the existing side rail, before personal folders, saved
  feeds, categories and public presence. Its native links use the existing
  `/?pane=boards`, `/?pane=tags` and `/?pane=connections` URLs. The same group is
  available on every application rail, including the phone drawer and composer.
- Boards is always available. Tags is gated by `tags`; Connections by `community`.
  Guests retain the existing public tag catalogue and Connections sign-in state.
  Adding these links performs no member-list, private-board or count queries.
- Forward the controller's normalized home pane explicitly into the rail and
  topbar. Invalid, malformed and disabled panes still resolve to Boards. Tags
  stays active on its standalone index and topic-filter routes. A member's
  profile Connections view is independent from their own directory Connections.
  Board-row active state and composer destination selection remain independent.
- Remove the horizontal pane strip and its Notifications entry. The bell remains
  the notification entry point. Existing `/?pane=notices` deep links, list forms
  and return URLs remain functional, with the bell current on that surface and
  the primary Boards link inactive.
- For signed-in members, Tags and Connections have one H1 in the shared creation
  row. Connections retains its linked username there; long text wraps while
  reserving the creation target. Boards uses a quiet context label in the row
  and retains its content hero. Guests retain their content headings and acquire
  no empty creation row.
- Reuse the BoardRail link vocabulary, current marker, focus treatment and
  semantic spacing/border tokens. Directory links have 44px targets. Existing
  drawer dismissal/focus behavior, native fallback, privacy gates, board unread
  pills and public presence remain the same.
- At extreme phone text enlargement, primary route links and the Connections
  list choices wrap instead of widening the document. The member bar owns its
  content height; enhancement measures that height for drawer/pane offsets,
  independently of its CSS minimum so text reduction and desktop resizing can
  restore the smaller bar.

## Evidence

Independent source and rendered layout assessments informed the change. Server
and rebuilt Chromium/WebKit evidence belongs in
`docs/evidence/directory-rail-2026-10-08/`, including feature-off, guest/native,
responsive, current-state and creation behavior. Earlier design references and
evidence remain historical; this ADR records the owner's current direction.
