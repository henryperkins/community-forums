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

The `/messages/new` fallback route uses the same bounded shell. Its form pane scrolls internally when the recipient, optional group title, body, validation errors, or draft state exceed the available height; fields and submit actions must not clip.

Hiding the global search form on mobile DM routes intentionally prioritizes the message task. Site-wide search is not available from the compact DM top bar; users can leave the DM surface through the retained navigation control. The conversation-list search remains available for finding messages.

### DM-only formatting disclosure

When the shared composer enhances a form whose `data-composer-context` equals `dm`, it will create one `Formatting` button before the Markdown toolbar. The button controls the toolbar with `aria-expanded` and `aria-controls`. The toolbar begins collapsed for both the new-message form and the conversation reply form.

Expanding the disclosure reveals the existing Bold, Italic, Strike, Code, Spoiler, Quote, Heading, List, Code block, Link, and Emoji controls without changing their behavior or keyboard shortcuts. Collapsing it returns focus to the disclosure button. The disclosure state is local to the current form and is not persisted.

Non-DM topic, reply, and edit composers keep the always-visible toolbar. With JavaScript disabled, no toolbar or disclosure is present and the textarea continues to submit normally.

The default-on WYSIWYG posture is part of this behavior. DM replies and the standalone `/messages/new` route may mount Milkdown; the list-pane compose dialog keeps its existing `data-no-wysiwyg` fallback. The formatting disclosure wraps the shared `.composer-toolbar`, so its buttons must continue to drive both the rich editor and source textarea. Browser coverage must exercise a DM reply with WYSIWYG enabled, not only the source-mode fallback.

The DM character counter stays available but is visually quiet and occupies no dedicated row while the field is empty. Its denominator comes from the enhanced field's `maxlength`, with the existing 20,000 default only when no valid maximum is present; DM fields therefore report the server-aligned 5,000-character limit. The existing `Discard draft` button remains governed by saved-draft state; in DMs it is styled as a quiet text action rather than a full-width secondary button.

### Conversation-list hierarchy

Add a deterministic compact UTC timestamp helper for DM list rows. It renders `M j · H:i`, for example `Jul 11 · 02:54`. A semantic `<time>` element is introduced with the machine-readable datetime, while both `aria-label` and `title` expose the existing full `human_datetime()` value.

Participant or group names retain priority in the first row. The preview occupies the second row, and the unread dot remains a separate labelled status. The full date continues to appear in the open conversation.

### Touch targets

DM new-message, back, details, overflow, close, send, and per-message action controls receive a minimum 44×44px interactive area. Icon artwork remains at its current visual size. Spacing and hover/focus treatments continue to use existing tokens.

## Accessibility Semantics

The shared slash-command and reference-suggestion code will stop assigning `role="combobox"` and `aria-expanded` to a native `<textarea>`. The textarea keeps its implicit textbox role and the accessible name, `aria-autocomplete`, `aria-controls`, `aria-haspopup`, and `aria-activedescendant` state needed by the suggestion menus. Popup open state is conveyed by the visible listbox and active-descendant state instead of an attribute that is invalid for the textarea's implicit role.

The current `role` and `aria-expanded` attributes are load-bearing internal state in `comboboxReady()`, `setExpanded()`, and reference-picker close detection. Their removal requires a small state refactor: slash readiness uses the existing closure `ready`/configuration state, and reference-picker open/close logic uses `menu.hidden` plus the controlled popup id. The implementation must not substitute another DOM attribute merely to preserve the old control flow.

Tests must prove that:

- an explicit Axe run with `withRules(['aria-allowed-role'])` no longer reports the best-practice role violation, rather than relying on the existing WCAG-tagged serious/critical helper that excludes this rule;
- the existing WCAG-tagged scan continues to report no serious or critical `aria-allowed-attr` violation after `aria-expanded` is removed;
- the suggestion listboxes still open, expose options, support arrow-key movement, accept Enter, and close with Escape;
- the DM formatting disclosure is keyboard operable and returns focus when collapsed;
- the details overlay exposes correct expanded state, dismisses with Escape/scrim/close, and returns focus to the details toggle when closed;
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
- `COMPOSER.md`: document the DM-specific mobile formatting disclosure as an intentional exception to the shared essential-controls-plus-overflow pattern.

Expected test files:

- `tests/Unit/Support/*` or the existing helper test file: compact timestamp formatting and invalid input.
- `tests/Integration/Core/AppDirectMessageTest.php`: route marker, timestamp markup, no-JS forms, and preserved DM behavior.
- `tests/browser/dm-reimagine.spec.ts`: desktop/mobile layout, rail overlay, formatting disclosure, focus, and touch targets. Its current wide-rail assertions are replaced rather than extended; before implementation, repair the already-red stale copy assertion for `Beginning of your counsel`, which has no corresponding production text.
- `tests/browser/a11y.spec.ts`: explicit best-practice role scan, WCAG attribute scan, and suggestion-menu keyboard behavior.
- `tests/browser/gate-a.spec.ts`: replace the slash-picker textarea's `aria-expanded` contract with visible-listbox and active-descendant assertions.
- `tests/browser/wysiwyg-composer.spec.ts`: replace the reference-picker `aria-expanded` assertion and add a default-on DM WYSIWYG formatting-disclosure assertion.

No database migration, controller route, repository method, design token, icon set, or dependency is added.

## Test Strategy

Implementation follows red-green-refactor for each behavior:

1. Add failing unit and integration assertions for compact timestamps and DM route markup.
2. Record the known stale `dm-reimagine.spec.ts` copy failure, repair that baseline expectation, then add failing browser assertions for the two-column desktop shell, closed overlay rail, DM-only formatting disclosure, mobile bounded composer, internally scrolling `/messages/new`, and 44px hit areas. The 1440×900 and 320×568 checks use explicit browser contexts because the configured desktop project is 1280×800.
3. Add a failing explicit `aria-allowed-role` assertion and update the existing WCAG-tagged scans to prove the textarea has neither the invalid role nor invalid `aria-expanded`, while suggestion menus remain keyboard-operable.
4. Implement the smallest changes that satisfy each test.
5. Run focused PHPUnit and Playwright tests, then the full PHP suite and the existing DM/no-JS/a11y browser evidence.
6. Capture fresh desktop and mobile screenshots at 1440×900, 390×844, and 320×568.

The rail implementation also removes dead column-era state: `.rail-hidden` CSS, the grid-column transition, the `rb-dm-rail-collapsed` storage key, and the width-gated Escape path. These are deleted only after the overlay tests are green.

## Non-Goals

- Changing topic, thread-reply, edit, or administrative composer layouts.
- Redesigning the global top bar outside DM routes.
- Adding real-time messaging, WebSockets, typing indicators, or delivery behavior.
- Changing group-DM availability or membership behavior.
- Changing message storage, sanitization, pagination, blocking, reporting, or draft persistence.
- Introducing new colors, fonts, routes, tables, packages, or build steps.
