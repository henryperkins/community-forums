# ADR 0040: Profile system components and Recent activity

**Date:** 2026-09-27
**Status:** Accepted for the user-profile handoff implementation.
**Authority:** USER §5.1, COMMUNITY §8, PRODUCT_DESIGN §13, the supplied
`design_handoff_user_profile/README.md` (§1–3), and ADR 0037.

## Shared presentation

The profile renders the existing system vocabulary: ruled underline links for
sections, segmented links for order and connections, pill inputs with decorative
search icons, secondary pager and empty-state links, and cards for the members-only
gate and details aside. Existing `profile-*` hooks remain. Selected links carry
`aria-current="page"`; they do not claim to be client-side tab panels.

Only the handoff's four component CSS hunks enter the mirror: disabled variant
hover, input affixes, segmented-link hover, and ruled underline tabs. The imported
preview files accompany them. The thread-row, AdminNav operator cluster, `.hash`,
and field-hint/link-preview differences remain held back. The existing member
transfer block remains byte-identical between mirror and application: this
profile work does not change that block.

Page layout and fixed twilight cover actions remain application-owned. Narrow
`revert-layer` rules return properties to the system where legacy unlayered
anchor, input, card, and button styles would otherwise win. The mobile touch
targets remain, including the 44px overflow menu trigger. The handoff's `.6rem`
kind markers and `.98rem` activity titles are intentional profile typography;
the cover's `.82rem` actions follow the shared small-button size.

## Decision: adopt one Recent activity list

The overview presents the six most recent publicly attributable topics and
replies in creation order. This meets USER §5.1's light recent-activity list and
reduces repeated headings. Topics and Posts keep their full searchable lists.

- The server fetches at most six eligible rows of each required kind, merges
  them by creation time, and takes six. Ties use kind then descending ID for
  stable output. Reply queries exclude opening posts **before** their limit,
  so a new topic does not appear twice or crowd out genuine replies.
- Existing public-board, deletion, pending-content, and anonymity guards apply
  to both the row and its excerpt. Profile visibility and connection privacy
  continue to apply independently.
- All / Topics / Replies are GET links using `activity=threads|posts` and
  `#recent-activity`. All omits the parameter; unrecognised values fall back to
  All. Filtering works with JavaScript disabled.
- Without JavaScript, each row links to its entry; details and disclosure
  buttons stay hidden. The full tabs still provide excerpts.
- JavaScript enables independent disclosure buttons with `aria-expanded`,
  `aria-controls`, and Show/Hide accessible names. Several details can be open.
  The optional in-place filter is not adopted; filtering remains ordinary
  navigation in either mode. The animation respects reduced motion.
- Relative timestamps use the existing helper; `datetime` and `title` retain
  the precise UTC instant. Production stores the actual thread title without
  adding the prototype's `Re: ` prefix.

The full Topics, Posts, and Connections lists retain **20 items per page**.
The handoff's five-item prototype example does not override existing pagination
or privacy contracts. No schema, feature-flag default, posting, or moderation
behavior changes.

## Evidence

See [the local verification record](../evidence/profile-system-2026-09-27/README.md).
Imported `.dc.html` and React previews remain source-only per
[`PREVIEW_STATUS.md`](../design-system/imladris/PREVIEW_STATUS.md). Browser checks
exercise real PHP routes and the generated CSS, rather than treating an
unresolved authoring loader as visual evidence.
