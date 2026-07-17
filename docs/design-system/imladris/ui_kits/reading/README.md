# Reading-surfaces UI kit — the reading rooms

An interactive, faithful recreation of RetroBoards' **public & member reading surfaces** — the routes that sit alongside the inbox, messages, and admin. Translated from `templates/{home,feed,search,notifications,compose}.php`, `templates/tags/{index,show}.php`, and `templates/profile/connections.php`.

## What it is

The shared Imladris shell (topbar + sidebar, loaded from `../retroboards/kit.css`) around a routed main pane. Built from the design-system primitives in `styles.css` + `_ds_bundle.js` and the global `thread-row` / `chip` / `inbox-tab` primitives in `components.css`; `reading.css` carries only the per-surface layout.

## Surfaces

- **Home** (`home`) — the board index: categories → board rows with descriptions and thread/post counts.
- **Feed** (`feed`) — Following / Latest tabs; activity items (author, started/replied, board, excerpt).
- **Search** (`search`) — the search form + results (thread/post, board, highlighted snippet) and empty state.
- **Tags** (`tags/index`) — the public tag directory as discovery cards.
- **Tag** (`tags/show`) — a single tag: header, follow control, and its topic list (reuses the thread row). Board rows from Home land here too, as a board listing.
- **Notifications** (`notifications`) — the full list with the product's verb map, per-type icons, unread state, and mark-all / clear (the topbar bell count stays in sync).
- **Compose** (`compose`) — the full-page new-topic form: board, title, body, anonymous option, Markdown hint.
- **Connections** (`profile/connections`) — followers / following with rep and a remove action.

## Navigation

The chrome mirrors the real `partials/topbar.php` + `partials/sidebar.php`. Surfaces this kit owns (Home, Following, Tags, Search, Notifications, Compose, Connections) switch the main pane; **Inbox**, **Messages**, **Admin**, and **Settings** are real cross-kit links to the other kits, so the whole system navigates as one product.

## Files

- `index.html` — `@dsCard` entry; loads React + Babel, the DS bundle, the shared RetroBoards seed, then this kit's scripts.
- `reading-data.js` — feed, search, tags, notifications, connections (`window.RBReading`); roster/boards/threads come from `../retroboards/data.js` (`window.RB`).
- `ReadingChrome.jsx` — topbar + sidebar (route-aware).
- `ReadingSurfaces.jsx` — Home, Feed, Search, Tags, TagShow (+ the faithful ThreadRow).
- `ReadingExtras.jsx` — Notifications, Connections, Compose.
- `ReadingApp.jsx` — shell + routing; holds notification state.
- `reading.css` — per-surface layout (chrome is reused from `../retroboards/kit.css`).

## Conventions

- All chrome, type, color, and spacing come from design-system tokens — no new colors.
- Reuses global `thread-row` / `chip` / `inbox-tab` / `badge` primitives rather than re-styling them.
- Mirrors the real templates' fields and copy (verb map, 160/20000-char limits, anonymous posting, discovery-only tag follow) so it reads true to the product.
