# Moderation UI kit — the warden's table

An interactive, faithful recreation of RetroBoards' **moderation triage** — the staff queues that are distinct from the admin *config* console. Translated from `templates/mod/{reports,approvals,appeals}.php` and `templates/appeals/index.php` into the Imladris register.

## What it is

The operator-desk chrome (top bar + head + horizontal subnav) over four queues, with **live counts** in the subnav that update as you work. Built from the design-system primitives in `styles.css` + `_ds_bundle.js`; `kit.css` carries only the triage layout.

## Surfaces

- **Reports** (`mod/reports`) — open & claimed reports. Each row carries a status badge, reason tag, reporter + time, the target (a **post in a thread** *or* a **DM message**), an excerpt, and the reporter's note. Actions: **Claim** (takes it off the shared pile), **Resolve**, **Dismiss** — all live. Harassment reports get an urgent (rust) left-rule and count.
- **Approval hold** (`mod/approvals`) — two sections, **topics** and **replies** held by anti-abuse / board rules. Approve publishes; reject removes — both clear the row and drop the count.
- **Appeals** (`mod/appeals`) — appeals review. Each row shows the appellant, the original action, a target summary, and the appellant's reason, with an inline **resolve form**: outcome (upheld / modified / reversed / dismissed) + resolution note. Resolving marks the row and records the note.
- **Member view** (`appeals/index`) — the appellant's own screen: appealable actions (removed posts, moderation-log entries) each with a reason form, plus "Your appeals" with statuses and resolution notes. Included so the moderation loop reads end-to-end.

## Files

- `index.html` — `@dsCard` entry; loads React + Babel, the DS bundle, then the kit scripts.
- `data.js` — seed queues (`window.RBMod`): reports, approvals, appeals, member appeals.
- `ModSections.jsx` — the four panes (Reports, Approvals, Appeals, MemberAppeal) + the appeal resolver.
- `ModApp.jsx` — shell; holds queue state so claim/resolve/approve actions update the queues and the subnav counts.
- `kit.css` — mod shell, subnav with counts, report rows, approval items, resolve forms.

## Conventions

- All chrome, type, color, and spacing come from design-system tokens — no new colors.
- Status is always a **word + color** (badges, the urgent rust rule), never an emoji — per the Imladris register.
- Mirrors the real templates' fields (reason codes, outcomes, 2000-char appeal limit, DM vs post targets) so it reads true to the product.
