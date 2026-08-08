# Retired artifacts still present in this mirror

**Recorded 2026-08-03.** This mirror (`docs/design-system/imladris/`) is a local
copy of the live Claude Design project `c3e02753-607c-40b6-994c-9ba1a65bb367`.
The folders below **no longer exist upstream** — the live project deleted them —
but they are deliberately **kept on disk here** rather than deleted, so that
in-flight work and old links do not break mid-migration.

**They are reference-only.** Do not build against them, do not cite them as the
owner of a surface, and do not hand-sync changes into them. The authoritative
reasoning is in `REDUNDANCY-AUDIT.md` (findings 1 and 2, both marked
`✅ REMOVED`) and in the `CHANGELOG.md` entry *"2026-08-03 — Redundancy pass:
four superseded artifacts out"*.

---

## `ui_kits/admin/` — retired upstream 2026-08-03

9 files: `AdminApp.jsx` · `AdminPackages.jsx` · `AdminParity.jsx` ·
`AdminSections.jsx` · `data.js` · `parity-data.js` · `index.html` · `kit.css` ·
`README.md`.

**Superseded by:** the ten `templates/admin-*` templates, unified by
`components/admin/AdminNav` (`ADMIN_AREAS`). Its own README had already said
"SUPERSEDED by templates" and mapped all ten destinations.

Two things moved off it when it was retired, and both matter to anyone reading
the old kit:

- `PRODUCTION.md`'s **OAuth / invitations / providers** row no longer names this
  kit. Sign-in providers (and the disable path) belong to
  `templates/admin-integrations`; invitations belong to `templates/admin-members`.
- `manifest.json → unresolved_gaps[0]` no longer files the **ADR 0021 / 0023
  admin remediation** against this kit. It is now filed against
  `templates/admin-*`, per destination: grouped dashboard nav
  (`admin-overview`), typed ban confirmation + bulk-action confirm step +
  user-record PII handling (`admin-members`), 422-with-draft-preserved forms
  (all admin forms), webhook delete re-auth (`admin-integrations`), announcement
  history + 429 (`admin-notifications`), board-delete authoritative counts
  (`admin-content`), email status facts (`admin-notifications`).

Filed against the kit, that gap could never close — the artifact was frozen by
its own README.

---

## `feature-ui/polls/` · `feature-ui/tags/` · `feature-ui/moderation/` — retired upstream 2026-08-03

One `index.html` each.

**Superseded by:** `templates/thread-view/ThreadView.dc.html`, which already
carries every surface these three specified:

| Retired area | Flags | Where it lives now |
|---|---|---|
| `polls/` | `polls` | thread-view's poll section — choose-one, results with `<meter>`, hidden-until-voted, **Close poll** in the warden menu |
| `tags/` | `tags` | thread-view's topic header tag reads; the composer's tag input |
| `moderation/` | `topic_workflow` `split_merge` | thread-view's assign / unassign, snooze intervals, escalate; the **Split or merge** modal |

Upstream's rule: a flag belongs on the surface it changes, not in a gallery
beside it. `feature-ui/` upstream is now a **two-card flag ledger** — `rail/`
and `organize/` only — and those two are the one surface in the system with **no
owning template** (`REDUNDANCY-AUDIT.md` §3, still open by choice).

---

## Also retired upstream, also still on disk here

Recorded for completeness — the same reference-only rule applies.

- **`feature-ui/account/`** and **`feature-ui/conversation/`** — folded on
  2026-08-02 into `templates/account-settings`, `templates/user-profile`, and
  `templates/thread-view`. This is the precedent the 2026-08-03 fold of
  polls / tags / moderation was decided under.
- **`ui_kits/settings/`** — folded into `templates/account-settings` on
  2026-08-02 (its missing **Boards** and **Blocks** sections were added to the
  template first). **This is the retired artifact closest to the account
  migration: use `templates/account-settings/AccountSettings.dc.html`, not this
  kit.**
- **`ui_kits/reading/`** — folded into what was then `templates/reading-rooms`
  on 2026-08-02 (single-tag view and Connections added to the template first).
- **`templates/council-topic/`** — merged into `templates/thread-view/` on
  2026-08-02.
- **`templates/board-index/` (the copy in this mirror)** — this is the *older*
  merged board-index screen that was retired on 2026-08-02 and split into
  `templates/forum-inbox` + `templates/board-page`. Upstream now has a
  `templates/board-index/` again, but it is a **different artifact** (see the
  rename below). Treat the local folder as retired, not as a stale copy of the
  current upstream one.

---

## Renamed upstream, not retired

- **`templates/reading-rooms/` → `templates/board-index/`**
  (`ReadingRooms.dc.html` → `BoardIndex.dc.html`), 2026-08-03. `HomeController`
  documents route `/` as "the category/board index (pane 1 + 2 of the three-pane
  shell)", which is what this template always was. In the same pass **"rooms"
  was retired for "boards"** throughout it — rail label, headings, preview
  label, the ⌘B/⌘J tooltips, "Open this board", and the handler names. There is
  no room entity, no `/rooms` route, and no board→room mapping in the
  vocabulary card; "rooms" was a design-side invention over the product's own
  noun.

  The mirror still holds the pre-rename `templates/reading-rooms/`. It is
  reference-only under the same rule as everything above.

---

## Upstream templates NOT mirrored here

These exist in the live project and are **absent from this mirror**. They are
**out of scope for the admin / account migration** and were not fetched:

- `templates/forum-inbox/` (`ForumInbox.dc.html`) — route `/inbox`, the
  cross-board queue.
- `templates/board-page/` (`BoardPage.dc.html`) — route `/c/{slug}`, the board
  masthead over a compact ruled topic list.

Four **admin** templates were absent from this mirror when this file was
written and are *not* out of scope — they are the missing half of the ten-area
admin IA: `templates/admin-members/`, `templates/admin-features/`,
`templates/admin-integrations/`, `templates/admin-packages/`. They landed during
the same 2026-08-03 sync pass. `AdminNav` renders relative hrefs to all ten
areas, so the mirror only navigates end-to-end once all ten folders exist.
