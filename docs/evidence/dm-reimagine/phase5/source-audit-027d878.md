# Messages audit, commit `027d878` (source audit, finding IDs added for the fix run)

**How it was tested:** a local build of `027d878` on seeded test data, driven in Chromium at 1440×1000, 1280×800 and 390×844 (touch), in day, twilight, and OS-dark mode on the default `system` theme, and again with scripts blocked. Time zone America/Chicago. Checked against WCAG 2.0/2.1 A and AA with axe-core 4.12. Not tested on physical phones, iOS Safari, or real screen readers.

Score 13/20 (A11y 2, Perf 3, Responsive 2, Theming 3, Integrity 3). Implementation Integrity verdict: Pass. Drift is in how it was built: the refinement was appended as a new block at the end of `app.css` instead of editing the original rules; some rules paint from raw palette values instead of semantic tokens. Detector: 1 warning (3px left border on `.reference-card`, `app.css:7822`) and 44 advisories (the Messages CSS uses 28 distinct font sizes, off the type ramp).

Why these got through: the Messages tests never opened the ··· menu, never Tabbed through the dialog, never zoomed, and only switched to dark by setting `data-theme="dark"` directly, so OS-dark on the default `system` theme was never tested.

## P1

**P1-1 The compose dialog doesn't keep focus inside it** — `app.js:1824–1852` · WCAG 2.4.3 (A)
The dialog has `role="dialog"` but no `aria-modal` and nothing keeping Tab inside. From the To field, 13 of 30 Tab presses landed on controls hidden behind the scrim (search, filters, conversation list), starting at the 18th press. The details panel already has the Tab loop this needs (`app.js:1783`).
Fix: reuse that Tab loop and add `aria-modal="true"`. Or open the JS version with `<dialog>.showModal()` and keep the plain `<details>` panel for no-JS.

**P1-2 The reading area shrinks to 34px when zoomed** — `app.css:16148` (the room is locked to the viewport height) · WCAG 1.4.4, 1.4.10
At 1280×800 zoomed to 200% (a 640×400 viewport), header 98px, composer 269px, letters 34px. Same 34px at 400%. Text-only zoom 200%: letters get 181px of 800. Phone at rest: the empty composer takes 285px of 844.
Fix: drop the fixed height on short screens (e.g. below 560px tall) so the page scrolls. Collapse the empty composer to one row until it's focused.

**P1-3 Links inside letters are marked by colour only** — `app.css:52–58`: the global `a` rule removes underlines and nothing adds them back in `.formatted-content` · WCAG 1.4.1 (A)
Links are 1.2:1 against surrounding text in day, 1.49:1 in twilight, no underline. "Earlier messages", the only way to page back, looks like plain body text. Forum posts use the same content styles, so they have the same problem.
Fix: add `text-decoration-line: underline` to `.formatted-content a`, using the existing 45% gold underline colour. Style "Earlier messages" and "Latest messages" as controls.

**P1-4 On the default theme with OS dark mode, the "+" icon fails contrast** — `app.css:16292` applies the fix only to `[data-theme="dark"]` · WCAG 1.4.11 (AA)
Members default to `data-theme="system"`. With the OS in dark mode, the new-message icon is 2.86:1; with theme explicitly dark, 7.3:1. The app already repeats twilight rules for the `system` theme elsewhere (`app.css:197`, `818`).
Fix: repeat the rule under `@media (prefers-color-scheme: dark) [data-theme="system"]`. Add an OS-dark (`colorScheme: 'dark'`) run to the Messages test.

**P1-5 The ··· menu is labelled as a menu but contains no menu items** — `templates/dm/show.php:52` · WCAG 4.1.2 (A)
axe rates this critical when the menu is open. Fix: delete `role="menu"`; the `<details>` disclosure is already the right pattern.

**P1-6 The All / Unread filters show which one is selected by colour only** — `app.css:7202–7204`, `16189` · WCAG 1.4.1 (A); fails DESIGN.md's Word-and-Colour test
In day mode the selected pill is 1.01:1 against the pane and 1.05:1 against the unselected pill. Same weight, no border. DESIGN.md's filter tabs fill evergreen when active; these don't.
Fix: use the system's filter-tab active style.

## P2

**P2-1 Once opened, the details panel reopens over every conversation** — `app.js:1752–1768`
The open/closed choice is saved per browser and applied at every width. Opened once at 390px, then a different conversation: the panel arrived covering the whole screen incl. the back button. Same at 1280, and at 1440 with the boards sidebar open.
Fix: only restore the saved choice when the panel is a real column; when it's an overlay, always start closed.

**P2-2 The Search and To fields lose the system's focus ring** — `app.css:7256–7266`, `16251` · WCAG 2.4.7
Both turn off the outline and use a faint river-blue ring: 1.34–1.36:1 against the background, borders 1.94–2.19:1. Every other control in a 59-stop Tab walk showed the evergreen outline (7.1–9.0:1) or the composer's gold ring.
Fix: use the shared focus style.

**P2-3 In a group, the composer says "Message @bob…"** — `templates/dm/show.php:118–128`
For groups the placeholder uses the first other member's name. Fix: "Message the group…" or the group's title.

**P2-4 Phone controls are 24–34px** — `app.css:7206, 7489, 7537, 16189, 16245, 16298`
Back, Details, More and New message are 34px; each letter's ··· actions button is 28px on touch; filter pills 34px tall; owner tools "Make owner"/"Remove" 24px tall, 8px apart. The app's own mobile rule sizes buttons and link buttons at 44px (`app.css:746`).
Fix: enlarge tap areas on touch screens without changing icon sizes.

**P2-5 Some Messages rules still use raw palette tokens** — `app.css:7222, 7551, 7622, 7760, 7802, 7828, 7959, 8047`
Raw values like `--gold-200`, `--green-200` don't change in twilight; the border on your own letters stays pale gold (#EAD9A8) in twilight, the brightest line on screen. Fails DESIGN.md's Semantic-Only test.
Fix: switch to semantic tokens (e.g. `--rule-gold`, `--border-*`).

**P2-6 Without JavaScript, a conversation opens at its oldest letter** — `ConversationController.php:244`
With scripts blocked the message area starts at "Beginning of your counsel" (390 and 1440). After sending, the page reloads without a jump link, so the new letter is out of view. Every letter has an `id="m{id}"` anchor, and the JS honours `#m…` links (`app.js:1574`).
Fix: redirect to `#m{newId}` after sending.

**P2-7 At 390px, the Messages link and its unread count are hidden** — `app.css:15966`
Top-bar links scroll sideways on phones; only Boards and Inbox fit. Messages sits just past the visible edge, even on /messages. The top bar is governed by ADR 0032, so this needs a decision there.

## P3

- **P3-1** Three generations of Messages CSS stacked in one file. Room colour tokens declared twice, the room column layout set 11 times with four different list widths, 12 selectors match nothing (e.g. `.dm-empty-star`, `.rail-hidden`, `.dm-day-new`). `app.css` 7133–8047, 9864–9912, 16134–16340. → distill
- **P3-2** List previews show raw Markdown ("```php", backticks, "- "), and screen readers read it aloud. `dm_list.php:117` → clarify
- **P3-3** Server and page cost: 10 queries plus an empty transaction per poll, where the brief says one after-id query per poll. Each letter from someone else renders its own report form, so 10 letters produce 12 forms. Opening the details column animates the layout. `ConversationReadService.php:47–81`, `dm_messages.php:53–64`, `app.css:7157` → optimize
- **P3-4** A new 1700px breakpoint, although the brief says no new breakpoints. A code comment explains it, but it isn't recorded in the brief or DESIGN.md. At 1440 with the boards sidebar open, the details panel opens as an overlay, not the column the brief describes. `app.css:16165`, `16330` → document
- **P3-5** Two `h1`s on desktop; the details toggle is a link that reports expanded/collapsed; "Make owner"/"Remove" don't say which member; list rows announce "Unread" only after the whole preview. `show.php`, `dm_list.php`, `dm_rail.php` → harden
- **P3-6** Times show as "05:40 PM" (leading zero); hover titles read "…Z UTC" (two time-zone markers); a stray "·" before the presence status in the details panel; three labels at 9.6–10.2px despite the mock's 11px minimum. `app.js:1492, 1545`; `dm_presence.php`; `app.css:7622, 7625, 8018` → polish
- **P3-7** For the open pill-shape decision (not a fix): the "New messages" button (`.dm-newpill`) and the room's pop-up notice are pill-shaped buttons too. `app.css:16221, 7932` → document

## Positive findings to preserve (regressions here are failures)

- axe: one violation in 44 runs (5 pages × 3 widths × day/twilight, plus no-JS and open menus/panels).
- No page errors. No horizontal scroll at 390/1280/1440 in either theme or without JS. Long words wrap; code blocks scroll inside a keyboard-focusable box.
- Day dividers follow the reader's time zone; without JS, UTC stays.
- The details panel keeps focus for 14 Tab presses; Escape returns focus to the toggle.
- Reduced motion turns off dialog and panel animations; the panel still opens and closes.
- Presence is word + dot. Unread is dot + bolder name + darker preview. New letters announced to screen readers. Recipient picker is an accessible autocomplete. Form errors keep the draft.
- Page load: 782 elements, 6 layout passes, 36ms script. A poll takes 11–43ms locally.
