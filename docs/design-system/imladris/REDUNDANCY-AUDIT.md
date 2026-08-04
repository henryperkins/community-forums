# Redundancy audit — 2026-08-03

> **Status:** findings 1 and 2 executed 2026-08-03 — `ui_kits/admin/` and
> `feature-ui/{polls,tags,moderation}/` removed, all citing docs repointed.
> Finding 4 partly executed: the per-screen supersession is recorded, but the kit
> stays until a leaderboard template exists. Finding 3 and the provenance fix:
> provenance done, §3 open by choice.

Three families of artifact describe the same product: **19 `templates/`** (the owned,
shipped DC artifacts), **6 `ui_kits/`** (React survey kits), **6 `feature-ui/`**
(flag-activation surfaces). The kits and the activation surfaces were built *first*,
while the templates did not exist. As templates landed, most of them were superseded
in fact but not in the file tree.

Nothing here is a bug. It is drift between what an artifact is for and what still
points at it. Findings are ordered by how safe they are to act on.

---

## 1. `ui_kits/admin/` — superseded, and still cited as owner — ✅ REMOVED

Its own README opens with **"SUPERSEDED by templates"** and maps all ten destinations
to `templates/admin-*`. The screen map in `github.md` agrees. But two files still
treat it as live:

- **`PRODUCTION.md` row 50** ("OAuth, invitations, providers") names
  `ui_kits/admin` as the DS representation. Providers are owned by
  `templates/admin-integrations`; invitations by `templates/admin-members`.
  The row points at the superseded artifact for surfaces the templates own.
- **`manifest.json` → `unresolved_gaps[0]`** logs the ADR 0021/0023 remediation
  (grouped dashboard nav, typed ban confirmation, bulk-action confirm, PII
  handling, 422-with-draft-preserved, webhook delete re-auth, announcement
  history + 429, board-delete counts, email status facts) **against
  `ui_kits/admin`**. That work now lands in the ten templates. As written, the
  gap can never close — the artifact it names is frozen by its own README.

**8 files:** `AdminApp.jsx` `AdminPackages.jsx` `AdminParity.jsx`
`AdminSections.jsx` `data.js` `parity-data.js` `index.html` `kit.css` + README.

**Recommend** — retire the folder. If the side-by-side survey is worth keeping,
it is one `@dsCard` of section thumbnails, not 8 files of faked React routing.
Either way: repoint row 50 at the two owning templates, and re-file the ADR gap
against the templates so it can actually be closed.

---

## 2. `feature-ui/{polls,tags,moderation}/` — absorbed by `templates/thread-view` — ✅ REMOVED

`ThreadView.dc.html` already carries every surface these three specify:

| Activation area | Flags | Where it now lives in thread-view |
|---|---|---|
| `polls/` | `polls` | The poll section — choose-one, results with `<meter>`, hidden-until-voted, and **Close poll** in the warden menu |
| `tags/` | `tags` | Tag reads in the topic header; the composer's tag input |
| `moderation/` | `topic_workflow` `split_merge` | Assign / unassign, snooze intervals, escalate; the **Split or merge** modal |

This is the same call already made twice: `feature-ui/account` and
`feature-ui/conversation` were folded into `templates/account-settings`,
`templates/user-profile`, and `templates/thread-view` in the 2026-08-02 sync.
The activation index's closing note already documents that precedent — these
three now qualify under it.

**Recommend** — fold. Move each flag's note into thread-view's own description,
extend the index's closing note to name all five folded areas, and leave
`feature-ui/` as a two-card flag ledger.

---

## 3. `feature-ui/rail/` vs `feature-ui/organize/` — duplicates, by the index's own words — ⬜ OPEN

The activation index describes `organize/` as *"the same three rail features
gathered into the one Organize surface a member actually works in."* The overlap
is exact: `board_folders`, `saved_feeds`, `bookmark_folders`. Only
`expanded_feeds` is unique to `rail/`.

Unlike findings 1 and 2, **no template covers this** — `BoardIndex.dc.html`
(formerly `ReadingRooms`) has no folder, saved-feed, or bookmark UI at all. So this is one surface
described twice and owned nowhere.

**Recommend** — keep `organize/` (the surface a member works in), absorb
`expanded_feeds` into it, drop `rail/`. Better still, promote the pair to
`templates/rail-organize/`: on the evidence this is the largest genuine template
gap in the system, and it is currently hidden behind a duplicate.

---

## 4. `ui_kits/retroboards/` — 3 of 4 screens superseded — ◐ DOCUMENTED, kit retained

| Kit screen | Owning template |
|---|---|
| `Inbox.jsx` | `templates/forum-inbox/` |
| `Conversation.jsx` | `templates/thread-view/` |
| `Profile.jsx` | `templates/user-profile/` |
| `Leaderboard.jsx` | **none** |

Today's sync showed the cost of leaving this ambiguous: the kit's `.profile-*`
block is an older, divergent copy of the profile cover, and a reader cannot tell
from the tree which one ships. `PRODUCTION.md` row 36 still cites the kit for the
shell alongside `components/forum/*` and thread-view.

`Leaderboard.jsx` is the only screen here with no template — the same shape of
gap as finding 3.

**Recommend** — give it the `ui_kits/admin` treatment: a README that says
superseded-except-leaderboard and names the owning template per screen. Then
promote the leaderboard to a template and retire the kit. Do **not** converge the
kit's profile cover by hand; it is a survey, and hand-syncing two copies of one
screen is what produced the divergence.

---

## Not redundant — sole representation

`ui_kits/auth/` · `ui_kits/dm/` · `ui_kits/mod/` · `ui_kits/system/`

No template covers auth, DMs, moderation queues, or setup/errors/privacy.
Three carry live gaps in `manifest.json` (scoped mod panel, group-DM read
boundary). Leave them; they are the next four templates, not dead weight.

---

## Provenance is recorded twice, and disagrees — ✅ FIXED

`manifest.json` claims `inspected_commit: 3fa5704e2e42` / `inspected_at:
2026-08-02`. `github.md` records `commit: 3d317c770be4` / `2026-08-03`, three
syncs later. Two files assert what upstream state this system was built from and
they no longer agree.

`github.md` is the one the sync flow reads and rewrites. **Recommend** —
`manifest.json` should stop carrying `inspected_commit` / `inspected_branch` /
`inspected_at` / `previous_inspection` and point at `github.md` instead, keeping
only what is genuinely its own (`contract`, `production`, `changelog`,
`unresolved_gaps`).

Two smaller stale references worth the same pass: `github.md`'s screen map names
`templates/board-index/`, which is `templates/board-page/`; and the activation
index is titled *"Five designed feature areas"*, which findings 2 and 3 would
make two.

---

## Summary

| Finding | Artifact | Action | Files |
|---|---|---|---|
| 1 | `ui_kits/admin/` | Retire; repoint PRODUCTION row 50; re-file ADR gap | 9 |
| 2 | `feature-ui/{polls,tags,moderation}/` | Fold into `templates/thread-view` | ~8 |
| 3 | `feature-ui/rail/` | Drop, keep `organize/`; promote to a template | ~2 |
| 4 | `ui_kits/retroboards/` | Mark superseded per screen; promote leaderboard | 11 |
| — | `manifest.json` provenance | Defer to `github.md` | 1 |

Findings 1, 2, and 4 remove description without removing coverage. Finding 3
removes a duplicate and **exposes a real gap** — the rail's folders, saved feeds,
and bookmark folders have no template.
