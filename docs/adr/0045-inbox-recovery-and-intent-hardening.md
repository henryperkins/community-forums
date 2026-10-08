# ADR 0045: Recoverable Inbox previews and explicit action intent

**Date:** 2026-10-08
**Status:** Accepted
**Refines:** The enhanced preview fallback in ADR 0042 and the controls in ADR 0044.

## Context

The owner requested a hardening pass after control polish and motion. Rendered
inspection confirmed that transient preview errors navigate away from the queue,
an unanswered fetch has no deadline, and reading the last Unread row leaves
empty selection controls. Server inspection also found missing or malformed
action intent mutating personal state and read/star paths missing the canonical
held-topic privacy gate.

## Decision

- Keep the queue, selection and current preview or typed reply when a transient
  preview request fails. Show an announced loading state, then offer **Try again**
  and a native **Open full topic** link for HTTP errors, network failures,
  malformed or mismatched previews, and a 15-second deadline. Explain offline
  and rate-limited requests. Redirected responses and 401/403/404 continue to
  navigate to the canonical topic for server-owned authentication/access handling.
- When a previous preview exists, also offer **Return to previous topic**. Reveal
  that existing preview and focus its heading without fetching or replacing its
  editor, including when its read topic has left the Unread queue. If a failed
  Back/Forward load changed the URL, replace that history entry with the retained
  topic while preserving view parameters; ordinary recovery does not change history.
- The latest preview request wins. Superseding navigation, native form submission,
  timeout and page exit invalidate the prior request even without AbortController.
  Enhanced submissions that prevent navigation, including pane preferences,
  preserve the pending preview and any failure recovery commands.
  No failed or stale response reconciles the queue. History unavailability does
  not discard a successfully loaded preview. Native topic links remain complete
  when enhancement is absent.
- When the last Unread row on the current page is read, remove obsolete selection
  and count controls. Reuse the server's caught-up state if no unread topics remain;
  otherwise offer **Load remaining topics**. Returning from the preview restores
  focus to the meaningful empty state after the originating row disappears.
- Reject missing/malformed read or hiding intent without writing. Empty hiding
  intent remains an explicit restore action. Read and star actions use the same
  read gate as the canonical topic, including held-topic privacy. Star forms
  submit their desired state so repeated submissions are idempotent; retained
  clients that omit that field retain the existing toggle API.
- Re-render rejected bulk actions with HTTP 422, an inline error and only the
  submitted, readable selections still on the current page. Do not persist IDs
  in the URL or session. Successful actions retain POST/redirect/GET behavior.
- Bound creation menus with the same viewport-height scrolling as the other
  menus. In forced colors, keep a system-colored switch thumb and disable opacity
  reveals. Preserve the established layout, semantic tokens and native forms.

## Validation and limits

Integration and unit evidence covers malformed input, authorization, repeated
intent and selection recovery under both PDO prepare modes. Rebuilt browser
evidence belongs in `docs/evidence/inbox-harden-2026-10-08/`, with engine, text
scaling and device limits recorded. Review fixes and their final release checks
are recorded in `docs/evidence/inbox-preview-recovery-2026-10-08/`. Mixed Arabic,
Hebrew, CJK and emoji content
must remain contained; full localized shell RTL remains outside the shipped
English-shell contract and the Phase 7 localization carryover.
