# Admin Console UI kit — the operator's desk

An interactive, faithful recreation of RetroBoards' **admin console**, rendered in the Imladris language's *working* (utilitarian) register — dense data tables and config cards, not the ceremonial lapidary treatment. It composes the design-system primitives (`window.ImladrisDesignSystem_c3e027`).

Open **`index.html`**. Product layout lives in `kit.css`; primitives come from `styles.css` + `_ds_bundle.js`.

## What it shows
A horizontal **subnav** (the product's `.subnav`, gold active underline) over 11 sections + a drill-in:
- **Dashboard** — site name, trust & safety (registration / anti-abuse / blocked words), recent-activity **audit table**.
- **Boards & categories** — category cards with rename + reorder, board rows (visibility tags, archive/delete), add-category and add-board forms.
- **Users** — search + an `.audit` table (role/state/regard/joined), pager; click a user → **user record** (identity, cosmetic title, badge grant/revoke).
- **Badge rules** — create-rule form + the rules link-list (enable/disable/backfill/revoke).
- **Tags · Announcements** — catalogue + banner publish.
- **Email** — sending-domain status, queue **stat-cards**, delivery log, requeue.
- **Webhooks · API tokens** — register/create forms (events / scopes fieldsets, the one-time **secret flash**) + endpoint/token tables.
- **Extensions** — sandbox probe, handlers + run-history tables.
- **Branding** — name + hex colour inputs, theme/preset, logo/favicon, custom CSS, and a **live preview** that updates as you type.

## Interactive (faked)
Subnav switches sections; Users drills into a record and back; the branding preview is live. State is in-memory React; seed data is in `data.js`.

## Files
`index.html` · `kit.css` (admin shell, `.audit` tables, stat-cards, flash, structure rows, brand preview) · `data.js` (`window.RBAdmin`) · `AdminSections.jsx` (the 12 panes) · `AdminApp.jsx` (subnav + routing).

## Fidelity
Recreates the existing `templates/admin/*.php` screens — tables, fields, and copy match the product. Repeated rows are abbreviated to a representative few.
