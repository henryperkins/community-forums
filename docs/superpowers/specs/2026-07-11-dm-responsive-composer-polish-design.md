# Direct Messages Responsive and Composer Polish

**Date:** 2026-07-11

## Context

RetroBoards presents direct messages as a private-counsel reading room: the global navigation rail, a conversation list, an open conversation, and an optional details rail. A rendered audit at 1440×900 and 390×844 confirmed that the interaction model works, but the layout allocates too little space to the core reading and replying tasks.

At 1440px, the persistent details rail reduces the conversation to roughly one third of the content area. On mobile, the shared top bar, formatting toolbar, character counter, and draft action push the reply field below the initial viewport. Conversation-list timestamps also compete with participant names. An automated accessibility scan found that the shared composer enhancement assigns the invalid explicit role `combobox` to a `<textarea>`.

## Goal

Improve the direct-message reading and replying experience without changing its routes, backend behavior, private-counsel visual identity, progressive-enhancement posture, or the layout of non-DM composers.

Success means:

- the desktop conversation remains comfortably readable at 1440px;
- the mobile message stream and reply composer share one bounded viewport, with the composer available without page-level scrolling;
- DM formatting controls are disclosed on demand instead of occupying permanent space;
- conversation names remain readable beside compact timestamps;
- important DM controls have touch-friendly hit areas;
- the invalid textarea ARIA role is removed without breaking slash-command or reference suggestions;
- all DM forms and actions continue to work with JavaScript disabled.

## Chosen Approach

Use DM-scoped progressive enhancement over the existing server-rendered templates. CSS will protect reading width and bound the mobile reading room. The shared composer JavaScript will add a formatting disclosure only when `data-composer-context="dm"`; non-DM composers retain their current toolbar layout. The shared ARIA correction applies to every enhanced textarea because the invalid role is introduced by common suggestion code, but it produces no visual change.

This approach is preferred over a CSS-only patch because the toolbar and ARIA behavior require JavaScript changes. It is preferred over a DM shell rewrite because the existing routes, partials, focus behavior, native `<details>` fallbacks, and backend data are already sound.

## Experience Design

### Desktop reading width

The details rail will no longer consume a persistent grid column at the widths available inside the global application shell. The DM shell will remain a two-column layout—conversation list and conversation—and the details rail will open as a right-edge overlay with a scrim.

The rail is closed by default. Its header control reports `aria-expanded="false"`; activating it opens the same `#dm-rail` target used by the no-JavaScript fallback. Escape, the close control, and the scrim dismiss it. Opening and closing the overlay does not change the conversation width.

The existing identity, facts, mute, profile, member, owner, leave, and block actions remain unchanged inside the rail. The current local-storage preference for a persistent desktop rail is retired because the rail is no longer a layout column.

### Mobile reading room

DM templates will set the existing layout `route` block to `messages`, producing `body[data-route="messages"]`. At widths up to 900px, that route marker will hide the global search form while retaining the navigation toggle, brand, notification, identity, moderation/admin, settings, and logout controls.

The mobile DM shell will use a bounded dynamic-viewport height. The thread pane remains a column: header and composer are fixed-height flex children, while only `.dm-scroll` scrolls. This prevents the page itself from placing the composer beneath the viewport. The implementation must tolerate the natural wrapped height of the retained top-bar controls and must be verified at both 390×844 and 320×568.

### DM-only formatting disclosure

When the shared composer enhances a form whose `data-composer-context` equals `dm`, it will create one `Formatting` button before the Markdown toolbar. The button controls the toolbar with `aria-expanded` and `aria-controls`. The toolbar begins collapsed for both the new-message form and the conversation reply form.

Expanding the disclosure reveals the existing Bold, Italic, Strike, Code, Spoiler, Quote, Heading, List, Code block, Link, and Emoji controls without changing their behavior or keyboard shortcuts. Collapsing it returns focus to the disclosure button. The disclosure state is local to the current form and is not persisted.

Non-DM topic, reply, and edit composers keep the always-visible toolbar. With JavaScript disabled, no toolbar or disclosure is present and the textarea continues to submit normally.

The DM character counter stays available but is visually quiet and occupies no dedicated row while the field is empty. The existing `Discard draft` button remains governed by saved-draft state; in DMs it is styled as a quiet text action rather than a full-width secondary button.

### Conversation-list hierarchy

Add a deterministic compact UTC timestamp helper for DM list rows. It renders `M j · H:i`, for example `Jul 11 · 02:54`. The `<time>` element retains the machine-readable datetime, and its accessible label/title retains the existing full `human_datetime()` value.

Participant or group names retain priority in the first row. The preview occupies the second row, and the unread dot remains a separate labelled status. The full date continues to appear in the open conversation.

### Touch targets

DM new-message, back, details, overflow, close, send, and per-message action controls receive a minimum 44×44px interactive area. Icon artwork remains at its current visual size. Spacing and hover/focus treatments continue to use existing tokens.

## Accessibility Semantics

The shared slash-command and reference-suggestion code will stop assigning `role="combobox"` to a native `<textarea>`. The textarea keeps its implicit textbox role and the accessible name, `aria-autocomplete`, `aria-controls`, `aria-expanded`, `aria-haspopup`, and `aria-activedescendant` state needed by the suggestion menus.

Tests must prove that:

- Axe no longer reports `aria-allowed-role` for the composer textarea;
- the suggestion listboxes still open, expose options, support arrow-key movement, accept Enter, and close with Escape;
- the DM formatting disclosure is keyboard operable and returns focus when collapsed;
- the details overlay exposes correct expanded state and dismisses with Escape;
- focus remains visible and no control depends on color alone.

This work does not claim full WCAG compliance. Screen-reader announcements, contrast, zoom, and reflow remain part of browser evidence and manual verification.

## Error Handling and Progressive Enhancement

No routes, controller validation, draft APIs, CSRF fields, message eligibility rules, rate limits, or feature flags change.

- Invalid new messages and replies continue to re-render with existing 422 errors and preserved text.
- Draft restoration and discard continue to use the existing local/server draft code.
- If composer enhancement fails, the server-rendered textarea and submit button remain usable.
- If rail enhancement fails, the header menu's `href="#dm-rail"` opens the rail through `:target`, and its close link returns to `#`.
- Reduced-motion preferences continue to suppress non-essential rail transitions.

## Implementation Boundaries

Expected production files:

- `templates/dm/index.php`, `templates/dm/new.php`, and `templates/dm/show.php`: set the `messages` route marker and update compact timestamp/rail state markup.
- `templates/partials/dm_list.php`: render the compact timestamp with full accessible context.
- `src/Support/helpers.php`: add the deterministic compact UTC timestamp helper.
- `public/assets/app.css`: two-column DM shell, overlay rail, mobile bounded viewport, compact DM composer, and 44px hit areas.
- `public/assets/app.js`: simplify the details overlay state if required by the all-width overlay.
- `public/assets/composer.js`: DM-only formatting disclosure and shared textarea-role correction.

Expected test files:

- `tests/Unit/Support/*` or the existing helper test file: compact timestamp formatting and invalid input.
- `tests/Integration/Core/AppDirectMessageTest.php`: route marker, timestamp markup, no-JS forms, and preserved DM behavior.
- `tests/browser/dm-reimagine.spec.ts`: desktop/mobile layout, rail overlay, formatting disclosure, focus, and touch targets.
- `tests/browser/a11y.spec.ts`: composer semantics and suggestion-menu keyboard behavior.

No database migration, controller route, repository method, design token, icon set, or dependency is added.

## Test Strategy

Implementation follows red-green-refactor for each behavior:

1. Add failing unit and integration assertions for compact timestamps and DM route markup.
2. Add failing browser assertions for the two-column desktop shell, closed overlay rail, DM-only formatting disclosure, mobile bounded composer, and 44px hit areas.
3. Add a failing accessibility assertion proving the textarea no longer has an invalid explicit role while suggestion menus still work.
4. Implement the smallest changes that satisfy each test.
5. Run focused PHPUnit and Playwright tests, then the full PHP suite and the existing DM/no-JS/a11y browser evidence.
6. Capture fresh desktop and mobile screenshots at 1440×900, 390×844, and 320×568.

## Non-Goals

- Changing topic, thread-reply, edit, or administrative composer layouts.
- Redesigning the global top bar outside DM routes.
- Adding real-time messaging, WebSockets, typing indicators, or delivery behavior.
- Changing group-DM availability or membership behavior.
- Changing message storage, sanitization, pagination, blocking, reporting, or draft persistence.
- Introducing new colors, fonts, routes, tables, packages, or build steps.
