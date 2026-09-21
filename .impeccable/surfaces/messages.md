---
version: 1
slug: "messages"
primary_target: "messages"
related_targets: ["templates/dm/index.php","templates/dm/show.php","templates/dm/new.php","templates/partials/dm_list.php","templates/partials/dm_rail.php","templates/partials/dm_compose_fields.php"]
---

# Surface brief: Messages (`/messages`, `/messages/{id}`, `/messages/new`, the compose dialog)

Status: **confirmed by the product owner on 2026-09-21**, all five decisions below resolved. Mode: **Operate**. Scope: **refinement** of the incumbent "private counsel" reading room inside the established Imladris world. Not a redesign. Shaped 2026-09-21 from fresh captures against the local e2e server; the committed evidence in `docs/evidence/dm-reimagine/phase1` predates the shared composer shell and is stale.

## 1. Job and audience

Registered members (any role) keeping private counsel with one other member or a bounded group of up to twelve. They arrive from the top-bar Messages link, a bell notification, a profile's Message action, or a habit of checking in, in one of three states of mind: *what moved since I was last here*, *finish reading this one and answer*, or *start one with a specific person*. Guests never reach the surface; staff have no window into a conversation except a member-filed report (ADR 0022). Scanability and continuity outrank expression: the room is quiet, and the only ceremony is the cool Bruinen tint that says "you are in the private room".

## 2. Outcome and proof

Primary task: find the conversations that moved, read one to its end, and answer without losing typed text. Success on a visit: unread state is cleared for what was read, the reply lands, and a new counterpart is reached on the first try. Product truth the surface must carry: only those named can read; a member added to a group later sees only what came after they joined; new accounts cannot start conversations until they have posted; a counterpart can decline messages or be blocked either way; the sender's last letter in a direct conversation reports Delivered or Read.

## 3. Selected direction

Visual authority is DESIGN.md plus the incumbent Bruinen register already scoped to `.dm-shell`. Structural thesis: **the conversation is the widest thing on screen.** The list is a narrow index, the details rail is on demand, and the reading column takes a prose measure at every width from 900px up. Sequence: list, then conversation, then rail only when asked. Focal moment: the newest letters above the composer, under a header that carries identity, presence, and a single lock eyebrow. Implementation consequence: the rail default flips to closed everywhere (the persisted per-browser choice stays); the three-times-repeated privacy statement collapses to the header eyebrow plus a first-page divider; every timestamp becomes short and relative with the exact instant on the `<time>` element; the day dividers return; the twilight register is re-pointed through semantic tokens so no DM surface or active row goes light.

## 4. Scope and boundaries

**In scope (production-ready, all states, desktop 1280 and 1440, laptop 1280 drawer, mobile 390, twilight, no-JS):**

- The list pane on `/messages` and beside every conversation: rows, search, All/Unread filters, empty states.
- The conversation pane for direct and group conversations, including the receipt line, group history, and the "no longer a participant" state.
- The details rail (direct and group, owner tools) and the header overflow menu.
- `/messages/new` and the compose dialog.
- Four behaviors: **live incoming messages** by short-poll; **unread count on the Messages nav link**; **presence in the conversation header**; **recipient picker on To**.
- Defect repairs: rail squeeze, timestamp truncation, twilight active-row wash, "Reputation" in the rail (lexicon says Regard), privacy stated three times, the vestigial left pane on `/messages/new`, inline code breaking mid-word in the narrow column, and eligibility failures rendering as a 403 page that drops the draft.

**Untouched:** every route and URL; the shared composer shell and its identical-everywhere feature surface (COMPOSER.md §15; sticky bottom dock, enter-to-send as a preference); `DirectMessageService` rules and caps; staff visibility posture (ADR 0022); the `dms` and `group_dms` flag gates; the mute, leave, rename, transfer, add-member, block, and report flows and their POST endpoints; the topbar and sidebar chrome vocabulary (ADR 0032); pagination semantics (newest page by default).

**Anti-goals:** no WebSockets or SSE; no optimistic send or reconcile (deferred, ADR 0020); no group read receipts; no emoji in chrome; no new breakpoint (900 and 1400 are the ones this surface already uses); no inline style or script; no private-message browser for staff; no change to the lexicon beyond the Regard repair.

## 5. States and ranges

- **List:** 0 conversations (first-run: the empty pane invites a first message and, when the new-account throttle applies, says so before the member types); 1 to 3 typical; 40+ scrolls inside the pane with search and the Unread filter. Names 3 to 40 characters, group titles to 120 on one line, untitled groups show participant names (the query already returns them), previews one line with "You:" when the last sender is the viewer. Unread rows: semibold name plus the river dot; the nav pill carries the count.
- **Conversation:** 1 to 50 messages per page, earlier pages reachable backwards; bodies 1 to 5000 characters of rendered Markdown under the shared formatted-content contract (code blocks, lists, quotes, inline code that must not break mid-word at any width); consecutive same-author runs grouped; own letters on the right, theirs plain; Delivered or Read under the viewer's last letter in a direct conversation on the newest page only; group rank Owner; members who left; group history as a closed disclosure; the join boundary for late-added members.
- **Header presence:** Here now, Away, or nothing (offline, hidden by `show_presence`, blocked either way, or the presence flag off). Word and colour together, never a dot alone. Groups: "N in counsel" plus "M here now" when M is above zero.
- **Live updates:** letters from others append at the bottom and join the last run when the author matches; if the viewer has scrolled up, nothing yanks and a quiet "New messages" pill scrolls down on tap; the receipt line and the nav count refresh on the same tick; the poll pauses when the tab is hidden and fetches once on return; a 404 (feature rolled back) stops it for good.
- **Errors:** unknown username, self-message, blocked, not accepting messages, suspended counterpart, new-account throttle, empty or over-long body all re-render the originating form at 422 with the field error in place and the body preserved. Rate limiting keeps its current handling. Tucked-away actions keep the toast flash.
- **No-JS:** every flow works by forms and reload; the picker degrades to the plain comma-separated field; the rail opens by anchor target; no polling.
- **Reduced motion** stays honoured.

## 6. Interaction and layout

- **Hierarchy and topology:** the same three-part shell. At 900px and wider, list plus conversation; the rail is a drawer over the conversation below 1400px and a real third column at 1400px and wider, in both cases only after the member opens it. At 900px and below, one pane at a time with the existing back control.
- **List rows:** display-face name, short time on the right (the elapsed form for the last week, a short date beyond, the exact UTC instant in the `<time>` title), one-line preview underneath. The active row keeps its wash and left rule.
- **Header:** monogram, name linking to the profile, sub-line with handle and presence word, the lock eyebrow, the rail toggle showing its pressed state, one overflow menu. Nothing else.
- **Stream:** day dividers as hairline plus label (Today, Yesterday, then a short date); each run opens with author and clock time; each letter carries its own `<time>`; the first page opens with one divider that says the counsel begins here and that only those named can read; earlier pages present as an "Earlier messages" link above the stream rather than a numbered pager.
- **Composer:** the shared dock, unchanged.
- **Rail:** identity, then facts (Joined, Regard), then quiet actions, then danger, as now, with the default closed.
- **Recipient picker:** To becomes a chip field. Typing queries the existing composer suggest endpoint for members; a chosen member becomes a removable chip; a hidden field keeps the canonical comma-separated `to` value so the server contract and the no-JS form are unchanged; the group-title field appears once two or more chips exist (always present without JS). Ineligible recipients are reported by the server at 422 in place.
- **Unread count:** the Messages nav link gets the same count pill Inbox has, server-rendered from the existing unread-conversation query and updated by adding one key to the bell's JSON so the existing bell poll carries it. No second timer.
- **`/messages/new`:** the reading-room shell with the list on the left and the form in the reading column, replacing the vestigial "Back to all messages" pane.
- **Feedback:** send is a full navigation (ADR 0020); 422 inline; toasts for tucked-away actions.

## 7. Constraints and resolved decisions

**Binding:** progressive enhancement floor; strict same-origin CSP; short-polling only, reusing the shared poll lifecycle in app.js; semantic tokens only (the DM tint variables must mix from register-aware surfaces so twilight flips); Word-and-Colour rule; all-serif; sentence case; lexicon (counsel, regard); WCAG 2.1 AA with the axe spec extended to the Messages routes; PHPUnit for every new endpoint and re-render path plus a Playwright evidence slice (desktop, laptop drawer, mobile, twilight, no-JS) under a new `docs/evidence/dm-reimagine/` phase, since "done" needs evidence (PRODUCT_DESIGN §13).

**Resolved decisions (confirmed 2026-09-21; a builder applies these, never reopens them):**

1. **The gold plate on the viewer's own letters stays** and is recorded in DESIGN.md as a named exception to the One Gold Rule, scoped to the DM register: "mine" letters wear the gold wash; nothing else on the surface may. The build's DESIGN.md update carries that sentence.
2. **Poll cadence:** 20 seconds while the conversation tab is visible, on the shared `shortPoll` lifecycle in app.js (same exponential backoff, pause on hidden tab, one immediate fetch on return, permanent stop on 404). One lightweight after-id query per tick.
3. **Timezone:** the server renders UTC labels with the exact instant in each `<time datetime>`; the enhancement layer relabels the visible short times and the day-divider labels to the browser zone from that attribute, and leaves the UTC instant in the title. Without JS the UTC labels stand.
4. **Picker eligibility:** the composer suggest service gains a DM-context filter so members who are blocked either way, not accepting messages from the sender, or suspended never appear as suggestions. The server's 422 in-place error remains the final screen for anything the filter cannot know.
5. **Marking read from the poll:** the poll endpoint marks the conversation read to the latest id it returns, exactly as a page view does, so a visible open tab never shows unread for the conversation on screen. Hidden tabs never poll, so they never mark read.

**Evidence the build owes (PRODUCT_DESIGN §13):** PHPUnit for the poll endpoint (participant gate, after-id, join boundary, flag-off 404, marks read), the bell payload key, the presence sub-line (show_presence, blocks, flag off), the suggest filter, and every eligibility 422 re-render; a Playwright slice under a new `docs/evidence/dm-reimagine/` phase covering desktop 1440, laptop 1280 with the rail drawer, mobile 390, twilight, and no-JS; the axe spec extended to the Messages routes.
