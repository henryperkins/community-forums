---
description: "Move New topic out of the member header into a shared row under it, as one create control offering New topic and New message"
agent: agent
---

# Create control in a shared row under the header

## Intent

Members start two kinds of things: topics and private messages. Today the header's New topic button (a bare `+` on phones) starts only topics, and New message is a separate `+` beside the Messages title. Creation moves to one place: a shared row (the sub header) directly under the member header on every member page, holding one create control that offers New topic and New message. The header loses a control, which matters most on the phone's crowded first row.

The owner settled the decisions below on 2026-10-07. Build them as written; they supersede the clauses listed under Docs. Carry the work through to done before asking anything, and ask only at a stop condition under Boundaries.

## What to build

**Header.** Remove New topic (`.forum-bar-compose`) from `templates/partials/topbar.php`. Everything else in the bar stays, including its heights (62px; 108px for the two-row phone bar, ADR 0042). The phone's first row becomes drawer opener, lockup, search, bell, seat.

**The sub header.** One partial (suggested `templates/partials/subheader.php`), rendered by `templates/layout.php` at the top of the content column: inside `<main id="main">`, ahead of the flash slot, so it lines up with the page, the rail keeps its place, and the skip link lands on it.
- It renders on every page that renders the member header (the `app` and `plain` layout variants) for a signed-in member, including `/compose`, `/messages/new` and error pages. No page loses the ability to create.
- It scrolls with the page. Messages and Inbox are height-bounded rooms whose panes scroll internally, so there it stays in view and the room is shorter by its height.
- Leading side: where a page opens with a breadcrumb or tab row (the board page and topic breadcrumbs, the Boards index pane tabs `nav.forum-directory__tabs`), that row moves into the sub header and is drawn once. Page `h1`s stay in their pages. Elsewhere the leading side is empty.
- Guests get no create control. Their sub header renders only where it has leading content, and never as an empty band.

**The create control** (trailing side).
- Above 860px: a "+ New" trigger with a chevron, opening a menu: New topic, then New message.
- At 860px and below: a bare `+` trigger, a 44px target whose accessible name says what it does (suggested "New topic or message"). The row shows no label, chevron or select. The topic/message choice exists only in what the `+` opens; a menu anchored to it is enough.
- New topic goes to `/compose?board=<slug>` using the `compose_board` value the layout already computes for board pages and authorized topics. That is today's header behavior.
- New message goes to `/messages/new`. On Messages pages with JavaScript it opens the existing new-message dialog in place.
- With the `dms` flag off there is no menu: the control is a direct New topic link (on phones, the bare `+`).
- On `/compose` and `/messages/new` the matching item carries `aria-current="page"`.
- Suspended, banned and DM-throttled members see the same control. The destinations and their POST handlers already enforce and explain (`WriteGate`, `DirectMessageService::isThrottledNewUser`). Add no per-request queries to the global shell for this.
- Style the trigger quietly. The board page's slab New topic stays that page's one accent action (DESIGN.md accent discipline).

**Messages.** Retire the `+` beside the Messages title (`summary.dm-new-btn` in `templates/partials/dm_list.php`). The dialog stays and opens from the menu item. A failed send from the dialog re-renders `/messages` with the dialog open and the typed text kept (`origin=dialog` in `ConversationController::create`); that path must still work with and without JavaScript.

**Board page.** Its own New topic controls stay: the slab button, the condensed sticky echo, the phone FAB and the `#new-topic` composer.

## Facts that will bite

- `.dm-shell` and `.inbox-shell` set `height: calc(100dvh - var(--topbar-h))`, and `.dm-shell` cancels `.main`'s padding with negative margins (`margin: -24px auto -64px`). A row above them makes the document scroll by the row's height, slides the shell over the row, and unseats the docked DM composer and its new-messages pill. Rules also select by adjacency (`.main > .flash:has(+ .dm-shell)`).
- `--topbar-h` and `scroll-padding-block-start` describe the sticky header alone. The sub header doesn't stick, so leave both as they are.
- A `<details>` with no `<summary>` draws the browser's default "Details" summary. Retiring `summary.dm-new-btn` means rethinking the dialog's container.
- Menus use `<details>`/`<summary>` as the no-JS baseline (`details.identity-menu`, the inbox menus). `app.js` already keeps one inbox menu open, positions it in the viewport, returns focus to the trigger on Escape, and dismisses it on outside click or page scroll (search `data-inbox-menu`). Reuse or generalize that code. Don't start a third menu system.
- Stacking follows DESIGN.md's Chrome-On-Top Rule: page content at 30 or below, the bar at `--z-chrome` (40), popovers anchored to page content from 42.
- `app.js` measures the phone bar: gaps ease from 8px to 4px with the available width, and it moves the routes row after the account controls. After removing a control, recheck 320px, the Large text preference and a classic scrollbar. Emulate phones with `isMobile: true`, because classic scrollbars shrink a 320px viewport to 305px.
- `.forum-bar-compose` is styled in two places. Generated `public/assets/imladris.css` comes from the design mirror, so leave it. `app.css`'s Member chrome block holds production's own compose rules; remove them with the control. Unlayered `app.css` beats the layer, so hand properties back with `revert-layer` instead of restating them.
- The design system's ForumNav (`docs/design-system/imladris/components/forum/ForumNav.jsx`) still draws compose in the bar. Record production's deviation in `docs/design-system/imladris/LOCAL_RECONCILIATION.md`.
- AGENTS.md: state the intended CSS rules in your response before editing styles, and keep new styling token-first.
- Tests pin the old control. `grep -rln "forum-bar-compose\|New topic" tests/` finds the PHPUnit and Playwright files to move to the new contract.
- Templates and `public/assets/` feed the Imladris surface digest (ADR 0024). On `main`, recompute it as the last step: `php bin/build-imladris-assets.php --print-application-digest` into `config/imladris-runtime-baseline.json`, then `composer build:imladris` and `composer check:imladris`. On any other branch leave the baseline alone; there a red `check:imladris` for that reason alone is expected.
- Browser specs mutate seeded data and write PNGs into other slices' evidence folders. Run `tests/browser/prepare.sh` before each spec group, pass spec names as escaped regexes (`'browser/a11y\.spec\.ts'`), and restore incidental PNGs with `git checkout --`.

## Docs

- `docs/adr/0043-create-menu-subheader.md` records these decisions. It supersedes, in part: ADR 0032 decision 1 (`.forum-bar-compose` in the bar), decision 3 ("New topic on a phone") and decision 4 ("the compose glyph"), and the first-row control list in ADR 0042's 2026-10-07 addendum. Add a one-line pointer to 0043 in both.
- `PRODUCT_DESIGN.md` §5.2 (the Top bar bullet) and §6.1 (the Top bar row): New topic leaves the bar; describe the sub header. Leave the §6.1 Mobile FAB row unchanged.
- `DESIGN.md`: the Topbar entry drops New topic, including from the phone first-row list, and a new entry describes the sub header and the create control.
- `CHANGELOG.md`: one entry.
- In docs and UI copy, avoid "delve", "leverage", "it's worth noting", "in short", "bottom line", and "X, not Y" framing.

## Boundaries

Do these without asking:
- edit templates, `app.css`, `app.js`, tests and docs in this repo;
- run PHPUnit (its database, `DB_TEST_DATABASE`, is disposable);
- run Playwright against a throwaway database prepared by `tests/browser/prepare.sh`, served by your own `php -S` on host `localhost` (WebAuthn rejects `127.0.0.1`);
- create the evidence folder.

Ask first before you:
- commit, push or open a PR;
- edit the design mirror's components, or regenerate the layer from a changed mirror;
- add a feature flag or change a flag default;
- run `npm run evidence` (it rewrites tracked PNGs across every slice).

Stop and ask when:
- a page can't take the sub header without breaking an accepted ADR beyond the clauses superseded above;
- a test you would have to change protects behavior this prompt doesn't mention.

## Done

- The behavior above holds on `/`, `/c/<slug>`, `/tags`, `/t/<id>`, `/inbox`, `/messages`, `/messages/<id>`, `/messages/new`, `/compose`, `/search`, `/notifications`, `/feed`, `/u/<name>`, `/drafts`, `/settings/account` and a 404, for a member, a guest, and a member with `dms` off.
- It holds at 320, 390, 860, 861, 1024, 1280 and 1440px, in parchment and twilight, by keyboard alone, without JavaScript, and with Large text. Nothing scrolls sideways, the header heights are unchanged, Messages and Inbox gain no page scroll, and the DM composer stays docked.
- Evidence per PRODUCT_DESIGN §13: the new contract is pinned wherever the old control was pinned, and captures with a README saying how they were made are in `docs/evidence/create-menu-YYYY-MM-DD/` (the capture date).
- `composer test` and `composer verify:imladris` pass, or each remaining failure also fails on unmodified `main`. Show that in a worktree at `HEAD` with its own database and port.
- The docs above are updated.
- Your final message lists the files changed, the evidence paths, failures that predate your change, and any decisions left open.

## Delegation

Make the template, CSS and JS change yourself; it is one connected change. Once it passes locally, you may hand the docs and the evidence capture to at most two parallel subagents. Give each its own database, server port and seeded accounts: login is limited to 10 attempts per account per 15 minutes, and pane state is stored per member.
