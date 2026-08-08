# F4 — shared design-artifact mirror sync

Date: 2026-08-03
Design project: `c3e02753-607c-40b6-994c-9ba1a65bb367` (READ-ONLY this pass)
Mirror root: `C:/Users/htper/community-forums/docs/design-system/imladris/`

No write method was called against the design project. Only `list_files` and
`get_file` were used.

---

## 1. Files fetched and written

| Upstream path | Mirror path | State |
|---|---|---|
| `components/admin/AdminNav.jsx` | `components/admin/AdminNav.jsx` | **new** |
| `components/admin/AdminNav.d.ts` | `components/admin/AdminNav.d.ts` | **new** |
| `components/admin/admin.card.html` | `components/admin/admin.card.html` | **new** |
| `github.md` | `github.md` | **new** |
| `manifest.json` | `manifest.json` | overwritten |
| `CHANGELOG.md` | `CHANGELOG.md` | overwritten |
| `README.md` | `README.md` | overwritten |
| `production-contract.json` | `production-contract.json` | overwritten |
| `PRODUCTION.md` | `PRODUCTION.md` | **new** |
| `REDUNDANCY-AUDIT.md` | `REDUNDANCY-AUDIT.md` | **new** |
| — (authored) | `RETIRED.md` | **new** |

All overwritten files were already git-tracked, so the prior mirror content is
recoverable with `git show HEAD:docs/design-system/imladris/<file>`.

### Byte fidelity

Content was transcribed verbatim. Two mechanical notes:

- The **previous** mirror copies of `CHANGELOG.md` / `README.md` /
  `manifest.json` / `production-contract.json` were 100% CRLF; the upstream
  store holds them mostly LF. The new copies are written with **upstream's own
  byte sequence**, so `git diff` on those four files will show a whole-file
  line-ending change on top of the real content change.
- `CHANGELOG.md` upstream is genuinely **mixed**: LF throughout, except (a) the
  blank line immediately before `## 2026-08-02 — Settings and reading kits
  folded into their templates`, and (b) every line from
  `## 2026-08-02 — Thread-view template reconciled to production` to EOF, which
  are CRLF. Reproduced exactly — verified 37,192 bytes, 159 CR, 555 LF, CR-runs
  at line index 345 and 397→554.

---

## 2. Admin information architecture (`components/admin/AdminNav.jsx`)

`ADMIN_AREAS` is a **flat list of ten areas — no sections, no sub-tab list, no
flag or disabled handling.** The component is chrome only; each template owns
its own second-rank sub-tabs.

Labels verbatim, in console order:

| # | `key` | `label` | `dir` | `file` |
|---|---|---|---|---|
| 1 | `overview` | `Overview` | `admin-overview` | `AdminOverview.dc.html` |
| 2 | `content` | `Content` | `admin-content` | `AdminContent.dc.html` |
| 3 | `people` | `People` | `admin-people` | `AdminPeople.dc.html` |
| 4 | `members` | `Members` | `admin-members` | `AdminMembers.dc.html` |
| 5 | `appearance` | `Appearance` | `admin-appearance` | `AdminAppearance.dc.html` |
| 6 | `notifications` | `Notifications` | `admin-notifications` | `AdminNotifications.dc.html` |
| 7 | `integrations` | `Integrations` | `admin-integrations` | `AdminIntegrations.dc.html` |
| 8 | `packages` | `Packages` | `admin-packages` | `AdminPackages.dc.html` |
| 9 | `features` | `Features` | `admin-features` | `AdminFeatures.dc.html` |
| 10 | `settings` | `Settings` | `admin-settings` | `AdminSettings.dc.html` |

### Anatomy

Root `div.admin-bar`, two rows in one sticky block:

1. `div.admin-bar-id` — `span.admin-bar-brand` containing the system's own
   `EightPointStar` at `size={24}` plus `span.admin-bar-wordmark` reading
   `Imladris`; then `a.admin-bar-exit` (inline chevron SVG, 13×13, `M15 18l-6-6
   6-6`) with default label **`Back to the council`** and default href `#`; then
   `span.admin-bar-mode` with default label **`Admin mode`**.
2. `nav.admin-tier` with `aria-label="Admin areas"` — one `admin-tier-item` per
   area, `is-active` on the match, `aria-current="page"` on the active one.

### Props (from `AdminNav.d.ts`)

- `area: string` — "the only prop a template must set".
- `areas?: AdminArea[]` — defaults to `ADMIN_AREAS`.
- `backHref?: string` (default `'#'`), `backLabel?: string` (default
  `'Back to the council'`).
- `modeLabel?: string | null` (default `'Admin mode'`); pass `''` or `null` to
  hide the pill.
- `onNavigate?: (key: string) => void` — supplied, tabs render as `<button>` and
  the caller routes. **Omitted, the tier renders real relative anchors**
  `../${dir}/${file}`, and the active item's `href` is `undefined` (not a link).

### Register / rationale (from the JSDoc and `admin.card.html`)

- The house mark is **never redrawn**; `Mark()` resolves `EightPointStar` off
  `window.ImladrisDesignSystem_c3e027` **at render time**, because the bundle
  assigns exports only after every module has evaluated — module-scope capture
  comes up empty.
- The area tier uses the **PILL register** — the same idiom the forum topbar
  uses for primary nav — "so it never reads as a second copy of a page's own
  underline sub-tabs".
- Three signals separate the two ranks: pill row (tier) → page heading →
  underline tabs (the page's own sections).
- The card records the measurement: this chrome is **10px shorter** than the
  pages it replaces — the redundant "Operator desk · Area" kicker is gone, the
  mode pill moved into the identity row, and the page heading drops from
  **2.4rem to 2.1rem**.

### No flag/disabled handling

`AdminNav` has none. Flag posture is handled elsewhere:
`PRODUCTION.md` Part 1 → Flags states that reserved-dark features
(`server_extensions`, `governance`, `service_principals`, `verified_links`)
receive **no invented UI — only the disabled admin-nav entry that exists in
production**, and the parity matrix routes `admin/extensions` to "disabled nav
entry only — by rule". So the disabled entry lives in the owning template
(`admin-packages` carries `extensions.php`), not in the shared bar.

---

## 3. Already-adjudicated admin / account decisions (do not re-litigate)

From `CHANGELOG.md`:

1. **Ten admin templates, one shared bar.** `ui_kits/admin/` is retired (9
   files); all ten destinations are `templates/admin-*`, unified by
   `components/admin/AdminNav`. `PRODUCTION.md` rows for OAuth / invitations /
   providers and for Admin were repointed accordingly.
2. **Ownership split for the surfaces the old kit claimed.** Sign-in providers
   and the disable path → `templates/admin-integrations`. Invitations →
   `templates/admin-members`. Themes + safe mode and custom CSS →
   `templates/admin-appearance`. Packages / plan / consent / registry trust →
   `templates/admin-packages`. Badge rules → `templates/admin-features`.
   Thread-Intelligence operator controls → `templates/admin-settings`.
   Announcements → `templates/admin-notifications`.
3. **The ADR 0021/0023 admin remediation is re-filed per template**, not against
   the kit (see `manifest.json → unresolved_gaps[0]`). Still open work, with
   named owners.
4. **`manifest.json` no longer asserts provenance** — `"provenance":
   "github.md"`. Do not reintroduce `inspected_commit` / `inspected_branch` /
   `inspected_at` there.
5. **Account settings: `thread_sort` is retired.** `PreferenceSchema` reached
   **v3**; **Default sort** — and with it **Most replies** — is removed from
   Reading, which is now a two-column grid. Board order is fixed (pinned first,
   then last post) and is **never** a toolbar. Legacy blobs keep `thread_sort`
   as inert unknown data.
6. **Account settings absorbed the settings kit.** `ui_kits/settings/` was
   deleted after **Boards** (favourite / mute, grouped by category, under
   Reading & writing) and **Blocks** (blocked members, unblock, under Council)
   were added to `templates/account-settings`. The kit's Composing pane was
   already folded into Reading.
7. **Profile / account flows that do not exist were removed** and must not come
   back: profile-level *Report to the wardens*; the gated profile's *Send a
   message* and *Request access*. *Warden* → **moderator** in the moderator
   strip.
8. **Connections copy is settled**: rows read `@handle · N regard`; the only row
   action is **Remove follower**, and only on your own seat in followers mode.
   No follow-back button, no tenure strings.
9. **The profile cover stays twilight in both registers** — it is the only dark
   slab in the day register, and upstream now carries that as an explicit source
   comment.
10. **One object, one owner** (`guidelines/surface-map.card.html`) — the rule the
    system keeps relearning. Also: `templates/reading-rooms/` →
    `templates/board-index/`, and **"rooms" is retired for "boards"**.

From `README.md` (Index → Components → `admin/`), verbatim:

> `admin/` — `AdminNav`, `ADMIN_AREAS`: the admin chrome every `Admin —`
> template mounts. Pass `area` and nothing else; it renders real hrefs to its
> sibling templates unless you pass `onNavigate`.

And the operator-surface pairing, verbatim:

> `admin-overview` (dashboard & audit) · `admin-content` (boards & tags) ·
> `admin-members` (members & invitations) · `admin-people` (roles &
> capabilities) · `admin-features` (features & badges) · `admin-settings`
> (settings & Thread Intelligence) · `admin-appearance` (branding & themes) ·
> `admin-notifications` (email & announcements) · `admin-packages` (packages &
> registries) · `admin-integrations` (tokens, webhooks & sign-in).

Account settings, verbatim: *"grouped rail, engraved forms, live two-factor,
sessions, connections, guarded delete."*

---

## 4. Retired artifacts kept on disk

`RETIRED.md` was written at the mirror root. Nothing was deleted. It records:

- `ui_kits/admin/` — retired upstream 2026-08-03; superseded by the ten
  `templates/admin-*` + `AdminNav`; reference-only.
- `feature-ui/polls/`, `feature-ui/tags/`, `feature-ui/moderation/` — retired
  upstream 2026-08-03; absorbed by `templates/thread-view`; reference-only.
- Also retired upstream and still present locally: `feature-ui/account/`,
  `feature-ui/conversation/`, `ui_kits/settings/`, `ui_kits/reading/`,
  `templates/council-topic/`, and the local `templates/board-index/` (the *old*
  merged screen, not the current upstream artifact of that name).
- `templates/reading-rooms/` → renamed upstream to `templates/board-index/`.
- `templates/forum-inbox/` and `templates/board-page/` exist upstream and are
  **not** mirrored — out of scope for the admin/account migration.

---

## 5. Findings the next phase needs

**A. All ten admin templates are now present — four landed mid-pass.**
At the start of this pass the mirror had six (`admin-appearance`,
`admin-content`, `admin-notifications`, `admin-overview`, `admin-people`,
`admin-settings`). `admin-members`, `admin-features`, `admin-integrations` and
`admin-packages` were written by a sibling stage-1 agent while this sync ran
(file mtimes 20:20–20:23) and are untracked-new in git. `AdminNav` renders
relative hrefs to all ten, so the tier only navigates end-to-end with all ten
folders present — worth a link check once the sibling pass reports done.
`templates/forum-inbox/` and `templates/board-page/` remain unmirrored by
design.

**B. Two documents disagree about the rename target.** The task brief said
`templates/reading-rooms/` → `templates/board-page/`. The authoritative
`CHANGELOG.md` says → **`templates/board-index/`** (`ReadingRooms.dc.html` →
`BoardIndex.dc.html`). `board-page` is a *separate* upstream template for
`/c/{slug}`. `RETIRED.md` records the CHANGELOG version.

**C. `production-contract.json` regressed a flag.** The upstream file lists
`group_dms` under `implemented_dark`. The mirror copy it replaced had `group_dms`
in `default_on` and carried local reconciliation keys
(`reconciled_through_commit: 6d81da59…`, `surface_specs`, `surfaces_doc`,
`contract_doc`). Repo `CLAUDE.md` and ADR 0022 confirm **`group_dms` graduated to
default-ON on 2026-07-18**, and upstream's own `github.md` sync history says so
too — so the upstream JSON contradicts the upstream Markdown. Written verbatim as
instructed; the local variant is recoverable via
`git show HEAD:docs/design-system/imladris/production-contract.json`.

**D. `README.md` is stale against the same-day redundancy pass.** Its Index
still lists `feature-ui/polls/`, `feature-ui/tags/`, `feature-ui/moderation/` and
describes `ui_kits/admin/` as the live operator console — all four retired
upstream on 2026-08-03 per `REDUNDANCY-AUDIT.md` and `CHANGELOG.md`. Its Sources
section also still pins commit `3fa5704e` while `github.md` records
`3d317c770be4`. Prefer `CHANGELOG.md` + `REDUNDANCY-AUDIT.md` + `github.md` over
`README.md` on any conflict.

**E. Two consolidated docs are now superseded in the mirror.**
`PRODUCTION.md` replaces `RUNTIME_CONTRACT.md` + `PRODUCTION_PARITY.md`, both of
which still sit at the mirror root and are still git-tracked. They were not
deleted (nothing was), but they should not be cited.

**F. Nothing in the fetched files read as instructions to the agent.** All
content was ordinary design/spec prose. No prompt-injection attempt observed.
