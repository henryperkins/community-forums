# RetroBoards — UI kit

> **Reference-only survey, not a current executable UI.** The preview bundle it
> depended on was retired on 2026-08-27. Inbox, Conversation, and Profile are
> superseded by `templates/forum-inbox/`, `templates/thread-view/`, and
> `templates/user-profile/`; the Leaderboard remains the only screen without an
> owning template. Do not use this kit as production behavior or hand-sync it.

A high-fidelity, interactive recreation of **RetroBoards**, the Community Inbox, rendered in the **Imladris** design language. It composes the design-system primitives (`window.ImladrisDesignSystem_c3e027`) — it does not re-implement them.

The historical `index.html` is retained as source provenance. It depended on the retired `_ds_bundle.js` and is not executable in this mirror. The product layout was a survey; the reusable primitives remain in `styles.css` and `components.css`.

## What it shows

The **Council Inbox** — the product's three-pane shell:
- **Top bar** — brand star + wordmark, "Search the council…", notifications bell, identity (avatar + presence + gear + log out). Flips to a **Guest** pill + Log in / Sign up when signed out.
- **Rail** — quick filters (Inbox, Mentions, Watching, Drafts, Top contributors) with the inset-gold active rule, board categories (`THE COMMONS`, `VILYA · EXPOSE`) as `#channels`, and a who's-online widget.
- **Inbox list** — "FOR YOU / The Council Inbox" (or a board header), the **Hall / Watch** density toggle, sort tabs (Active / Newest / Unanswered), filter pills (All / Unread / Starred / Mine), and topic rows with status left-rules, chips, snippets, commend counts, and stars.
- **Conversation** — a faint star-watermarked header (breadcrumb, title, byline, participant stack, star), the post stream (OP + accepted-answer + reactions), and the **composer** (member) or the **join-bar** (guest).

Plus two more product screens, reachable from the rail / identity:
- **Profile** — the twilight identity cover (gilt avatar, tier pill, regard, Follow / Message), **Marks of esteem** (including a locked one), and tabbed activity.
- **Top contributors** — the leaderboard (roman-numeral top-3 cards, then compact rows) with the italic footnote.

## Historical prototype interactions (not runnable here)

- Click a topic to open it in the reading pane; click the brand or rail to navigate.
- **Hall / Watch** switches comfortable ↔ compact density; sort + filter tabs filter the list live.
- **Star** a topic (rail "Watching" + Starred filter reflect it); **reply** appends a post; identity area **logs out** → guest state (composer becomes the join-bar, New topic hides).
- Open a member's **Profile** from the leaderboard or the top bar.

All state is in-memory React; there is no backend. Seed content lives in `data.js`.

## Files

- `index.html` — historical app mount; it refers to the retired `_ds_bundle.js` and is not runnable in this mirror.
- `kit.css` — product-screen layout (shell, top bar, rail, inbox panes, conversation header, profile cover, leaderboard, responsive collapse).
- `data.js` — seed council (users, boards, threads, posts, leaderboard, badges) on `window.RB`.
- `Topbar.jsx` · `Rail.jsx` · `Inbox.jsx` · `Conversation.jsx` · `Profile.jsx` · `Leaderboard.jsx` · `App.jsx` — the screens (loaded via Babel; they share scope on `window.RB*`).

## Fidelity notes

This recreates the **existing** product — it does not invent new screens. Repeated content is abbreviated (a handful of threads stand in for many). Surfaces the source marks as planned/mock (DMs, settings, admin) are omitted here rather than invented. The exact paddings, radii, and type come from the live `app.css`; the layout from the Engineering Handoff figures.
