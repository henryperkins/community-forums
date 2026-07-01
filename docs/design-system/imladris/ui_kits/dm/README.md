# Messages UI kit — private counsel

An interactive, faithful recreation of RetroBoards' **direct & group messages**, rendered in the Imladris register. Translated from `templates/dm/{index,new,show}.php`.

## What it is

A two-pane reading room under the shared top-bar chrome: a **conversation list** (direct + group, with unread markers and last-message previews) beside the **open letter** — or the **new-message composer**. Built entirely from the design-system primitives in `styles.css` + `_ds_bundle.js`; `kit.css` carries only the product layout that composes them.

## Surfaces

- **Inbox** (`dm/index`) — conversation list, All / Unread filter, "New message".
- **Conversation** (`dm/show`) — message stream with mine/theirs plates, day divider, reference cards (linked topics/posts), and a per-message **Report** affordance with reason + details.
- **Group conversation** — members panel with roles; **owner tools** (add / remove / make owner / rename) shown only to the owner; mute / leave.
- **New message** (`dm/new`) — recipients (comma-separated → group), optional group title, body.

## Files

- `index.html` — `@dsCard` entry; loads React + Babel, the DS bundle, then the kit scripts.
- `data.js` — seed roster + conversations (`window.RBDM`).
- `Topbar.jsx` · `ConvoList.jsx` · `Thread.jsx` · `Compose.jsx` — screens.
- `App.jsx` — shell; holds open/read/reply state and routes list ↔ thread ↔ compose.
- `kit.css` — two-pane layout, bubbles, members panel, reference cards, report form.

## Conventions

- All chrome, type, color, and spacing come from design-system tokens — no new colors.
- Single-pane collapse under 860px (list ↔ thread with a back breadcrumb).
- Mirrors the real templates' fields (reasons, 5000-char limit, group rename/add) so it reads true to the product.
