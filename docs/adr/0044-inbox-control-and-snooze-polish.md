# ADR 0044: Readable Inbox controls and two hiding choices

**Date:** 2026-10-08
**Status:** Accepted
**Refines:** ADR 0042's Inbox controls and ADR 0043's shared page row.

## Context

The owner requested one physical row for page context and creation, then identified
cramped captions, uneven borders, oversized hidden menus and too many snooze
durations. The existing Imladris parchment, evergreen, gold and serif identity
remains the visual authority. Independent source and mockup reviews informed this
implementation; a raster mockup is not evidence of working controls.

## Decision

- Keep Inbox identity, unread information, current view/count, current sort,
  actions and creation on one physical toolbar row. At the existing 460px
  available-width container threshold, combine view and sort into one disclosure
  displaying both current values. Wider rows retain separate controls. Each axis
  retains its own query parameter and changing either preserves the other.
- Keep dropdown chevrons, full accessible names and readable wrapping. Use the
  existing fine/chip type and semantic spacing, border, surface and corner tokens.
  Controls and menu commands have a 44px minimum target. Menus share quiet corners,
  inset rows and dividers; the queue uses straight row separators. On phones,
  secondary row actions sit below the topic text to increase its reading width.
  Without JavaScript, open row menus expand within the list and scroll naturally;
  enhanced panels position within the usable viewport.
- Put Snoozed second in the queue group so recovery is easy to find. Bulk selection
  has a primary Mark read action, an Actions disclosure and an enhanced clear
  selection control. Native row checkboxes and POST forms remain functional;
  the JavaScript-only select-all control is omitted without enhancement.
- Help opens a bounded, nonmodal native dialog when supported, with a close button,
  Escape dismissal and focus restoration. Without JavaScript it remains a native
  disclosure. It explains view versus sort, hiding and the current shortcuts.
- Offer one timed choice, **Til tomorrow**, retaining the existing 24-hour UTC
  deadline. The **Show in Inbox** switch is ON when the topic is eligible for the
  normal Inbox scopes and OFF while timed or indefinitely hidden. Turning it OFF
  hides until restoration; turning it ON clears both hiding states. The switch
  changes personal Inbox visibility, not notification delivery or board access.
- Bulk actions use explicit **Hide from Inbox** and **Show in Inbox** verbs, with
  the same timed choice. The `#` shortcut uses tomorrow. Topic tools use the same
  controls and state descriptions as the Inbox row.
- Migration 0083 adds `thread_user.snoozed_indefinitely`. Manual hiding stores a
  NULL deadline plus this flag; timed hiding clears the flag; restoration clears
  both. Unknown, retired and malformed choices reject without changing prior
  state. Snoozed contains both kinds; normal scopes and unread counts exclude both
  while `topic_workflow` is enabled. Turning that feature off ignores hiding.
  Restoring a topic makes it eligible under normal filters; it does not force it
  into every scope.

## Validation and limits

Server tests cover authorization, write/CSRF gates, scope/count behavior, expiry,
malformed inputs, all-or-nothing bulk validation and shared switch markup. Browser
evidence must use rebuilt delivery assets, cover narrow/wide rows and native form
journeys, and record engine/device limits under `docs/evidence/` per
PRODUCT_DESIGN §13. A push or a mockup alone does not establish deployment.
