# Messages UI kit — private counsel  ·  v2 (reimagined)

An interactive, faithful recreation of RetroBoards' **direct & group messages**, rendered in the Imladris register. Translated from `templates/dm/{index,new,show}.php`.

## The reimagine

The previous version felt *everywhere*: several near-identical screens (inbox / empty / thread / a full-pane composer) that all led to the same place, controls (mute · leave · report · members) shouting at once, and **every** element boxed in its own bordered card. This version keeps the full ceremonial Imladris treatment but re-homes everything into **one reading room**, now **grounded inside the forum chrome** (aligned to the flagship *Community Inbox* templates).

- **One place in the product, not a floating island.** A compact **nav rail** (Home / Inbox / **Messages** · active / Following / Drafts + a *Direct* section) sits left of the list, exactly like the forum inbox — so DMs read as one destination, not a detached screen. On mobile it becomes a **bottom tab bar** with a center compose FAB.
- **One surface, not four.** The right pane is always *the conversation* (with a proper empty state). **New message** is a **dialog over** the room, and confirms (leave / block / report) reuse the same dialog — never co-equal screens.
- **A collapsible details rail** absorbs everything that used to be scattered: the person's identity / the group's members, owner tools, mute, and block / report. A real third column at wide widths, a right-edge drawer at medium widths, a full-screen overlay on mobile.
- **Tucked-away controls.** A single **···** overflow in the header; a **···** revealed on hover per message (copy / report). Nothing secondary is visible at rest.
- **Letters, not boxes.** Consecutive messages **group** under one author line. Theirs read as plain counsel on parchment; **mine wear the one ceremonial gold plate**. **Quoted replies** render as the gold-rule blockquote, and group authors carry a small **rank pill** — both lifted from the forum post treatment.
- **Findability.** A quiet conversation **search** + on-brand **All / Unread** filter chips in the list header.

## Surfaces

- **List** (`dm/index`) — one tidy header (title + a round "new message"), search, All / Unread, then calm rows (monogram · one-line preview · a lone gold unread dot).
- **Conversation** (`dm/show`) — grouped letters, per-message hover **···** (copy / report → inline report form), reference cards, a read receipt, and a calm auto-growing composer (Enter to send).
- **Details rail** — direct: the person (gilt monogram, tier, joined, presence) + mute + block / report. Group: the members list with roles + **owner tools** (add / remove / make owner / rename) + mute + leave. Collapsible from the header.
- **New message** (`dm/new`) — a dialog: recipients (comma → group), optional group title, body.

## Files

- `index.html` — `@dsCard` entry; loads React + Babel, the DS bundle, then the kit scripts.
- `data.js` — seed roster (with `joined` / `tier`) + conversations, incl. quoted replies (`window.RBDM`).
- `DMTopbar.jsx` · `NavRail.jsx` · `ConvoList.jsx` · `Thread.jsx` · `InfoRail.jsx` — shell + product nav + three panes.
- `Overlays.jsx` — the popover **menu**, the modal shell + its bodies (new-message, confirm), and a Lucide-style icon set.
- `DMApp.jsx` — holds all state; routes list ↔ conversation ↔ rail ↔ dialogs; the mobile tab bar; the toast.
- `kit.css` — the reading-room layout, nav rail, grouped letters, blockquote / rank pill, menus, rail, dialogs, mobile tab bar.

## Conventions

- All chrome, type, color, and spacing come from design-system tokens — no new colors.
- Responsive: nav + list + conversation → details drawer (≤1320px) → single pane (≤900px, list ↔ conversation, nav becomes a bottom tab bar, details as an overlay). 44px tap targets.
- Mirrors the real templates' fields (report reasons, 5000-char limit, group rename / add / remove / make-owner) so it reads true to the product.
