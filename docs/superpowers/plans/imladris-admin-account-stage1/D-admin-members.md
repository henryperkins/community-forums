# Stage 1 diff — `admin-members` (Admin — members & invitations)

**Design source:** `C:/Users/htper/community-forums/docs/design-system/imladris/templates/admin-members/AdminMembers.dc.html`
(861 lines; markup lines 9–515, `<script type="text/x-dc">` lines 516–859)

**Production targets read in full:**

| Path | Lines | Role |
| --- | --- | --- |
| `C:/Users/htper/community-forums/templates/admin/users.php` | 175 | Directory tab |
| `C:/Users/htper/community-forums/templates/admin/user_record.php` | 337 | User record drill-in |
| `C:/Users/htper/community-forums/templates/admin/users_bulk_confirm.php` | 67 | Bulk confirmation drill-in |
| `C:/Users/htper/community-forums/templates/admin/invitations.php` | 102 | Invitations tab |
| `C:/Users/htper/community-forums/src/Controller/AdminUserController.php` | 469 | Renders the first three |
| `C:/Users/htper/community-forums/src/Controller/AdminInvitationController.php` | 115 | Renders invitations |
| `C:/Users/htper/community-forums/templates/admin/moderation.php` | 56 | Anti-abuse — **not represented** on this screen |
| `C:/Users/htper/community-forums/src/Service/UserModerationService.php` | (read §§ 360–706, 795–820) | Behaviour of record + bulk |
| `C:/Users/htper/community-forums/src/Service/InvitationService.php` | (read §§ 38–125) | Invitation validation |
| `C:/Users/htper/community-forums/templates/admin/_nav.php` | 93 | Current grouped nav rail |
| `C:/Users/htper/community-forums/src/Core/App.php` | 2208–2379 | Route verification |

Routes verified against `App::buildRouter()`:
`/admin/users` (2362), `/admin/users/bulk` (2363), `/admin/users/bulk/apply` (2364),
`/admin/users/{id}` (2365), `…/pii` (2366), `…/title` (2367), `…/avatar/remove` (2368),
`…/signature/remove` (2369), `…/badges/grant` (2370), `…/badges/revoke` (2371), `…/warn` (2372),
`…/note` (2373), `…/suspend` (2374), `…/ban` (2375), `…/lift` (2376), `…/role` (2379),
`/admin/invitations` (2216–2218), `/admin/audit` (2210), `/admin/moderation` (2208–2209).
Public profile link `/u/{username}` used at `user_record.php:46`.

---

## 1. Section-order comparison

### Design order (verbatim headings / eyebrows / comment banners)

| # | Design node | Verbatim string |
| --- | --- | --- |
| D1 | `x-import … AdminNav area="members"` | (shared chrome, `hint-size="100%,101px"`) |
| D2 | `h1` | `Members &amp; invitations` |
| D3 | `nav aria-label="Member sections"` | tab strip: `Directory` · `Invitations` |
| D4 | flash `role="status"` (`sc-if flash`) | `{{ flashText }}` |
| D5 | `<!-- ═══ Directory ═══ -->` (`sc-if showDirectory`) | — |
| D5a | filter `section` | `Search` · `Role` · `State` · `Last seen` · `Min posts` · `Max posts`; `Apply filters` / `Reset`; `{{ resultLabel }}` |
| D5b | `p role="alert"` (`sc-if bulkError`) | `{{ bulkErrorText }}` |
| D5c | directory `table` | `Member` · `Role` · `State` · `Regard` · `Posts` · `Last seen` · `Joined` (+ select col) |
| D5d | empty state (`sc-if noRows`) | `h3` `No members match these filters` / `p` `Reset the filters to see the whole directory.` |
| D5e | `fieldset` (`sc-if bulkOn`) | `legend` `Bulk actions`; `{{ selectedLabel }}`; `You confirm the shared reason on the next screen; every member is actioned and audited individually.` |
| D5f | `nav aria-label="Pagination"` | `Previous` (disabled) · `Next` |
| D6 | `<!-- ═══ Bulk confirmation ═══ -->` (`sc-if showBulk`) | — |
| D6a | back link | `All members` |
| D6b | `h2` | `{{ bulkTitle }}` → `Suspend N members` / `Warn N members` |
| D6c | `p` | `{{ bulkBlurb }}` |
| D6d | subject `ul` | Monogram · `{{ s.at }}` · role pill · status · `skipped — administrator` |
| D6e | `p role="alert"` | `{{ bulkFormErrorText }}` |
| D6f | form | `Reason (shared; shown to each member)` · `Until (UTC, optional — blank is indefinite)` · `{{ bulkConfirmLabel }}` · `Cancel` |
| D7 | `<!-- ═══ User record ═══ -->` (`sc-if showRecord`) | — |
| D7a | back link | `All members` |
| D7b | identity row | Monogram `size="xl" gilt` + `h2 {{ rec.display }}` + `{{ rec.at }}` |
| D7c | `h3` | `Status` (+ `Suspended until {{ … }}` strip, `View public profile`) |
| D7d | `h3` | `Contact &amp; signals` |
| D7e | `h3` | `Account restrictions` → `h4 Suspend`, `h4 Permanent ban` |
| D7f | `h3` | `Role` |
| D7g | `h3` | `Staff actions` |
| D7h | `h3` | `Cosmetic title` |
| D7i | `h3` | `Badges` |
| D7j | `h3` | `History` → `h4 Warnings`, `h4 Bans &amp; suspensions`, `h4 Private staff notes`, `h4 Audit trail` |
| D8 | `<!-- ═══ Invitations ═══ -->` (`sc-if showInvites`) | — |
| D8a | `div role="status"` (`sc-if inviteLink`) | `Copy this invitation link now — it will not be shown again:` |
| D8b | `h2` | `Issue an invitation` |
| D8c | (unheaded) `section` | invitations `table`: `Created` · `Binding` · `Uses` · `Expires` · `Status` · (sr-only `Actions`) |

### Production order

| # | Node | Path:line | Verbatim string |
| --- | --- | --- | --- |
| P1 | `header.admin-head` + `h1` + `.pill-admin` | `users.php:25-28` | `Users` / `Admin mode` |
| P2 | `admin/_nav` grouped rail | `users.php:29` | 8 groups; `Users` and `Invitations` are siblings in the `People` group (`_nav.php:23`, `_nav.php:25`) |
| P3 | filter `form` (GET) | `users.php:33-92` | `Search` · `Role` · `State` · `Last seen` · **`Joined from`** · **`Joined to`** · `Min posts` · `Max posts` |
| P4 | `p.field-error[role=alert]` | `users.php:94-96` | `<?= $bulk_error ?>` |
| P5 | `form[POST /admin/users/bulk]` → `.table-scroll` → `table.audit` | `users.php:98-148` | `User` · `Role` · `State` · `Reputation` · `Posts` · `Last seen` · `Joined` |
| P6 | empty row | `users.php:143-145` | `No users match these filters.` |
| P7 | `fieldset.bulk-bar` | `users.php:150-162` | `Bulk actions` … `Review and apply…` |
| P8 | `nav.pager` | `users.php:165-172` | `Previous` / `Next` (conditionally rendered) |
| P9 | bulk confirm `h1` | `users_bulk_confirm.php:13` | `Suspend N members` / `Warn N members` |
| P10 | bulk confirm `h2` | `users_bulk_confirm.php:20` | `Review before applying` |
| P11 | bulk subject `ul.link-list` | `users_bulk_confirm.php:29-37` | `@username` link + role pill + state pill |
| P12 | bulk form | `users_bulk_confirm.php:39-64` | `Reason (shared; shown to each member)` · `Until (UTC, optional — leave blank for indefinite)` · submit · `Cancel` |
| P13 | record `h1` | `user_record.php:28` | `{display} @{username}` |
| P14 | `h2` | `user_record.php:35` | `Status` |
| P15 | `h2` | `user_record.php:51` | `Contact &amp; signals <span class="muted">(PII — access is audited)</span>` |
| P16 | `h2` | `user_record.php:69` | `Account restrictions` → `h3 Suspend`, `h3 Permanent ban` |
| P17 | `h2` | `user_record.php:123` | `Role` |
| P18 | `h2` | `user_record.php:151` | `Staff actions` → `h3 Issue a warning`, `h3 Private staff note` |
| P19 | `h2` | `user_record.php:178` | `Cosmetic title` |
| P20 | `h2` (flag `profile_media`) | `user_record.php:198` | `Profile media` |
| P21 | `h2` | `user_record.php:223` | `Badges` → `h3 Grant a manual badge`, `h3 Held manual badges` |
| P22 | `h2` | `user_record.php:264` | `History` → `h3 Warnings`, `h3 Bans &amp; suspensions`, `h3 Private staff notes`, `h3 Audit trail` |
| P23 | invitations `h1` | `invitations.php:8` | `Invitations` |
| P24 | `.flash[role=status]` | `invitations.php:15-18` | `Copy this invitation link now — it will not be shown again:` |
| P25 | `h2` | `invitations.php:22` | `Issue an invitation` |
| P26 | `h2` | `invitations.php:64` | `Issued invitations` → table `Created` · **`By`** · `Binding` · `Uses` · `Expires` · `Status` · `Actions` |

**Order verdict.** The record's card order (`Status → Contact & signals → Account restrictions → Role → Staff actions → Cosmetic title → Badges → History`) is an **exact match**; production inserts one extra flag-gated card (`Profile media`) between Cosmetic title and Badges. The bulk-confirm and invitation orders match too. The structural gaps are (a) no `Members & invitations` shell with a `Directory / Invitations` sub-tab strip, (b) four different `h1`s where the design has one, (c) three of the design's four sections are laid out as multi-column grids where production stacks full-width cards.

---

## 2. Difference table

| # | Section | Classification | Design | Production (path:line) | Action | Risk |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | Shell / chrome | copy | `<x-import … AdminNav area="members" hint-size="100%,101px">`; page `max-width: 1160px; padding: 22px 28px 110px` | Per-template `<header class="admin-head">` + `.pill-admin`: `users.php:25-28`, `user_record.php:27-30`, `users_bulk_confirm.php:12-15`, `invitations.php:7-10`; grouped rail `_nav.php:56-91` | Replace all four headers with the shared AdminNav partial (owned by the admin-overview slice); adopt the page frame | medium |
| 2 | H1 | copy | One `h1` `Members &amp; invitations` across all four states | Four different `h1`s (`users.php:26`, `user_record.php:28`, `users_bulk_confirm.php:13`, `invitations.php:8`) | Single `h1`; drill-in titles demote to `h2` | low |
| 3 | Tab strip | copy | `nav aria-label="Member sections"`, underline tabs `Directory` / `Invitations`, `aria-current="page"` on the active one | None; the two are separate rail links `_nav.php:23,25` | Add the strip to all four templates | medium |
| 4 | Tab controls | constraint | `<button onClick="{{ goDirectory }}">` | n/a | Must be `<a href="/admin/users">` / `<a href="/admin/invitations">` — PE, no client router | low |
| 5 | Invitations tab availability | constraint | Tab always present | `AdminInvitationController::gate()` 404s when `invitations` is off (`AdminInvitationController.php:76-81`); rail disables it (`_nav.php:80-84`) | Render the Invitations tab `aria-disabled` with the `Disabled until the feature flag is enabled` note when the flag is off | low |
| 6 | "Admin mode" pill | copy | Lives in AdminNav's identity row, not the page | Repeated on every template (`users.php:27` etc.) | Remove from page bodies once AdminNav lands | low |
| 7 | Flash | copy | Local `role="status"` banner under the tab strip: `--surface-done` / `--green-200` / 3px `--success` left rule + check SVG + `amRise` 200 ms | Global `partials/flash` from `layout.php:61,72,78` | Restyle the shared flash partial in the design idiom, or render locally on this screen | low |
| 8 | Directory filters — `Joined from` / `Joined to` | feature-added | Not modelled (6 filters) | `users.php:69-76`; normalised at `AdminUserController.php:128-129` | Keep; style as the design's `.field` label/input | low |
| 9 | Directory — result count | copy | `{{ resultLabel }}` = `N members of M`, right-aligned in the filter action row | `total` computed at `UserModerationService.php:644` but never rendered | Render it | low |
| 10 | Directory — Apply/Reset row | copy | `Apply filters` (Button sm) + `Reset` (ghost) | `users.php:88-91` — same strings, `btn-small` / `btn-ghost` | Adopt spacing + the trailing count span | low |
| 11 | Directory — member column header | copy | `Member{{ arrowUser }}` | `$sortHeader('username', 'User')` `users.php:111` | Rename to `Member` | low |
| 12 | Directory — sortable-header a11y | feature-added | Sort arrow inside a bare `<button>`, no `aria-sort` | `aria-sort="ascending\|descending\|none"` + `&#9650;/&#9660;` with `aria-hidden` (`users.php:18-21`) | Keep production's; adopt only the design's typography | low |
| 13 | Directory — `Last seen` sortable | feature-added | Static `<th>` (`AdminMembers.dc.html:99`) | `$sortHeader('last_seen','Last seen')` `users.php:116`; `SORTS` includes `last_seen` (`AdminUserController.php:31`) | Keep the extra sort | low |
| 14 | Directory — member cell monogram | copy | `<x-import … Monogram size="sm">` before the username (line 108) | No avatar (`users.php:126-129`) | Add; `monogram_initials()` / `monogram_class()` exist at `src/Support/helpers.php:21,29` | low |
| 15 | Directory — display name | copy | Faint `<span>` after the username, **no parentheses** (line 110) | `<span class="muted">(display_name)</span>` `users.php:128` | Drop the parentheses | low |
| 16 | Directory — role pills | copy | admin `--brand-subtle`/`--green-800`; moderator `--gold-100`/`--gold-700`; user `--surface-sunken`/`--text-muted`; board-mod chip `--surface-info`/`--on-info` (lines 114-117) | `.role-pill.role-{role}` + `.tag` `users.php:131-134` | Adopt the palette | low |
| 17 | Directory — board-mod tooltip | feature-added | Plain chip | `title="Moderates N board(s)"` `users.php:133` | Keep | low |
| 18 | Directory — board-mod chip condition | feature-changed | Rendered whenever `u.boardMod` (any role) | Only when `role === 'user'` (`users.php:132`) | Design wins on presentation: render whenever `moderated_boards > 0` | low |
| 19 | Directory — state cell | copy | 6 px dot + lowercase label, colour per state (lines 120-123) | `<span class="state state-{status}">` `users.php:136` | Adopt the dot + colour mapping | low |
| 20 | Directory — empty state | copy | Block below the table: `h3` `No members match these filters` + `p` `Reset the filters to see the whole directory.`, `padding: 44px 20px; text-align:center` | `<tr><td colspan="8" class="muted">No users match these filters.</td></tr>` `users.php:143-145` | Replace with the design's block + verbatim strings | low |
| 21 | Directory — scroll region | feature-added | Bare `overflow-x: auto` on the section | `<div class="table-scroll" tabindex="0" role="region" aria-label="User directory">` `users.php:106` (ADR 0021 "a11y/label/scroll-region sweep") | Keep — do not regress | low |
| 22 | Directory — bulk fieldset | copy | `legend Bulk actions`; options `Choose an action…` / `Warn selected` / `Suspend selected`; `Review and apply…`; blurb verbatim | `users.php:150-162` — identical strings | Adopt only the layout/tokens | low |
| 23 | Directory — selected count | constraint | `{{ selectedLabel }}` = `None selected` / `N members selected`, live on every tick | None | Live count needs JS; ship in `public/assets/app.js` beside the existing `[data-bulk-toggle]` IIFE (`app.js:1178-1189`) and server-render the checked count on a 422 re-render so no-JS still shows something | low |
| 24 | Directory — bulk error microcopy | copy | `Select at least one member before choosing a bulk action.` / `Choose an action to apply to the selected members.` | `Select at least one member first.` (`AdminUserController.php:62`) / `Choose a bulk action to apply.` (`:59`) | Adopt the design strings | low |
| 25 | Directory — bulk error styling | copy | `role="alert"` panel, `color-mix(in srgb, var(--rust) 9%, var(--surface-raised))` + 3px `--rust` left rule, **between** the filter card and the table | `<p class="field-error" role="alert">` **inside** the filter card `users.php:94-96` | Move + restyle | low |
| 26 | Directory — stale-selection guard | feature-added | Not modelled | `The bulk selection is no longer valid — start again.` `AdminUserController.php:84` | Keep | low |
| 27 | Directory — selection preserved on 422 | feature-added | No server round-trip exists | `bulk_selected` re-ticks rows (`users.php:124`; `AdminUserController.php:59,364`) — ADR 0023 item 4 | Keep — do not regress | low |
| 28 | Directory — pagination | copy | Both buttons always rendered; `Previous` `disabled="{{ true }}"` (lines 153-156) | Rendered only when reachable (`users.php:166,169`) | Always render both; disable rather than omit | low |
| 29 | Bulk confirm — back link | copy | `‹ All members` above the card (line 163) | None (only the bottom `Cancel` link `users_bulk_confirm.php:62`) | Add | low |
| 30 | Bulk confirm — heading | copy | The card `h2` **is** `{{ bulkTitle }}` | `h1` title (`:13`) **and** a card `h2` `Review before applying` (`:20`) | Drop `Review before applying`; card `h2` carries the title | low |
| 31 | Bulk confirm — blurb | copy | `…until the expiry (blank is indefinite)…` | `…(blank = indefinite)…` `users_bulk_confirm.php:23` | Use `blank is indefinite` | low |
| 32 | Bulk confirm — subject rows | copy | Ruled `<li>`: Monogram + mono `@user` + role pill + status label (lines 168-176) | `ul.link-list`, no monogram (`users_bulk_confirm.php:29-37`) | Add monogram + ruled rows | low |
| 33 | Bulk confirm — subject links to record | feature-added | Plain text `@user` | `<a href="/admin/users/{id}">` `users_bulk_confirm.php:32` | Keep | low |
| 34 | Bulk confirm — pre-flight skip marker | copy | `skipped — administrator` on admin rows (line 174) and `{{ bulkConfirmLabel }}` counts only non-admins (script line 673) | Skips are only discovered at apply time (`UserModerationService.php:700-703`) | Mark admin/self rows and label the button with the actionable count — `bulkPlan()` already returns `role` and `id` (`UserModerationService.php:656-670`), no new query. Presentation only: the service stays the authority on what is refused | medium |
| 35 | Bulk confirm — post-hoc skip report | feature-added | Flash counts only `N administrators skipped.` | Flash names each: `Skipped: username (reason)` `AdminUserController.php:101-103` | Keep the named list | low |
| 36 | Bulk confirm — shared-reason error | copy | `A shared reason is required — it is shown to every member and written to each audit entry.` | Generic `A reason is required.` from `requireReason()` `UserModerationService.php:799` | Give the bulk path its own message | low |
| 37 | Bulk confirm — until label | copy | `Until (UTC, optional — blank is indefinite)` | `Until (UTC, optional — leave blank for indefinite)` `users_bulk_confirm.php:54` | Adopt the design string | low |
| 38 | Bulk confirm — confirm button | copy | `{{ bulkConfirmLabel }}` uses the **actionable** count | `{Suspend\|Warn} {count} member(s)` uses the **selected** count `users_bulk_confirm.php:61` | Switch to the actionable count (pairs with #34) | low |
| 39 | Record — back link | copy | `‹ All members` (line 205) | None | Add | low |
| 40 | Record — identity row | copy | Monogram `size="xl" gilt` 64 px + `h2 {{ rec.display }}` + mono `@user` beneath (lines 207-213) | `h1` `{display} <span class="muted">@user</span>` `user_record.php:28`, no monogram | Rebuild as the design's identity row | low |
| 41 | Record — Status/Contact layout | copy | Two-column `repeat(auto-fit, minmax(330px,1fr))` grid (line 215) | Two stacked full-width `.card`s | Adopt the grid | low |
| 42 | Record — Status dl order | copy | `Role · State · Regard · Posts · Joined · Last seen` in `repeat(auto-fit, minmax(120px,1fr))` | `Role · State · [Suspended until] · Joined · Last seen · Reputation · Posts · Profile` `user_record.php:37-46` | Reorder | low |
| 43 | Record — suspended-until | copy | `--surface-review` strip **below** the dl: `Suspended until {{ rec.suspendedUntilText }}` (fallback `Indefinite`) (lines 227-229) | An extra `<dt>Suspended until</dt>` **inside** the dl `user_record.php:39-41` | Move out of the dl into the strip | low |
| 44 | Record — public-profile link | copy | `<p><a>View public profile</a></p>` below the dl (line 230) | `<dt>Profile</dt><dd><a href="/u/{username}">View public profile</a></dd>` `user_record.php:46` | Move out of the dl; keep `/u/{username}` | low |
| 45 | Record — Contact heading suffix | copy | Bare `Contact &amp; signals`; the caveat lives in the blurb | `Contact &amp; signals <span class="muted">(PII — access is audited)</span>` `user_record.php:51` | Drop the suffix (the blurb already says it) | low |
| 46 | Record — PII gate copy | copy | Identical blurb + `Reveal email &amp; IPs (audited)`; button `variant="secondary"` | `user_record.php:60-63`, button `class="btn btn-small"` | Strings already match; change the variant only | low |
| 47 | Record — PII values typography | copy | All three `<dd>`s in `--font-mono`; the email is not wrapped in `<code>` | Email wrapped in `<code>` `user_record.php:54`; IPs plain | Apply mono to all three, drop the `<code>` | low |
| 48 | Record — restriction banner | copy | `--surface-review` strip with `rec.restrictionText` — `This account is banned. Lifting restores it to active.` / `This account is suspended (read-only).` + inline `Lift restriction` (lines 252-257) | Bare form + `<span class="muted">Restore this account to active.</span>` `user_record.php:78-84` | Adopt the strip + the two design strings | low |
| 49 | Record — self / admin-target guards | feature-added | Not modelled | `You cannot suspend or ban your own account.` `user_record.php:74`; `Administrators cannot be suspended or banned here.` `:76`; mirrors `requireGovernable()` via `can_govern` (`AdminUserController.php:432-433`) | Keep; render as a muted note inside the restrictions card | low |
| 50 | Record — Suspend/Ban layout | copy | Side-by-side `repeat(auto-fit, minmax(300px,1fr))` grid inside one card, `h4` each (lines 258-293) | Stacked `h3`s in one card `user_record.php:86-118` | Adopt the two-column grid; demote to `h4` | low |
| 51 | Record — suspend until label | copy | `Until (UTC, optional)` (line 269) | `Until (UTC, optional — leave blank for indefinite)` `user_record.php:96` | Adopt the shorter design string | low |
| 52 | Record — ban blurb apostrophe | copy | `…the record’s most consequential action.` (curly) | `…the record's most consequential action.` `user_record.php:104` (straight) | Use the curly apostrophe | low |
| 53 | Record — typed ban confirm label | copy | `Type {{ rec.username }} to confirm` (script line 745) | `Type <code>@{username}</code>'s username to confirm` `user_record.php:113` | Adopt the design label | low |
| 54 | Record — typed ban confirm mechanism | constraint | Client-side gate behind a `banRequiresUsername` prop (`default: true`) that can be switched off | **Server-enforced, unconditional**: `AdminUserController.php:292-296` compares `confirm_username` before any write (ADR 0021 "typed-confirm ban") | Keep the server check exactly as-is. The design's toggle is an editor affordance, not a product option — never expose it | low |
| 55 | Record — ban mismatch error | copy | `The username does not match — type {username} exactly to confirm.` (script line 751) | `Type the member's username exactly to confirm the ban.` `AdminUserController.php:294` | Adopt the design string (keep the field key `confirm_username`) | low |
| 56 | Record — required attributes | constraint | Design has no `required` | `required` on suspend reason (`:92`), ban reason (`:109`), confirm username (`:114`), warn reason (`:159`), badge slug (`:229`) | Keep — client hints layered over the server check, never instead of it | low |
| 57 | Record — Role card | copy | `h3 Role`; select `user`/`moderator`/`admin` (lowercase); `Your current password`; danger `Change role` | `user_record.php:122-148` — structurally identical | Only the heading level + grid placement change | low |
| 58 | Record — role change flag coupling | constraint (verified clean) | `reauthOn: true` unconditionally; no roles/capabilities notion anywhere in this screen | Route `App.php:2379` is registered outside any flag guard; `AdminUserController::changeRole()` docblock states "UNGATED … flag-independent of `capabilities`" (`:320-328`) | **No change.** Do not let the tab strip or any card gate the Role form on `capabilities` — it must keep working when that flag is rolled back | low |
| 59 | Record — role error microcopy | copy | `Changing a role is a reauthenticated action — confirm your password.` / `@{user} already holds that role.` | `The member already has this role.` `UserModerationService.php:404`; reauth failure comes from `ReauthService` | Adopt the design strings for the two cases production owns | low |
| 60 | Record — Staff actions sub-headings | copy | No sub-headings; one card, two stacked controls | `h3 Issue a warning` (`:152`) and `h3 Private staff note` (`:165`) | Drop both `h3`s | low |
| 61 | Record — warn field label | copy | `Warning reason (shown to the member)` | `Reason (shown to the member)` `user_record.php:158` | Adopt `Warning reason (…)` | low |
| 62 | Record — note field label | copy | `Private staff note` | `Note (visible to staff only)` `user_record.php:169` | Adopt the design label (the card is already staff-only) | low |
| 63 | Record — Add note button variant | copy | `variant="secondary"` (line 330) | `class="btn"` `user_record.php:173` | Make it secondary | low |
| 64 | Record — warn idempotency key | feature-added | Not modelled | Hidden `idempotency_key` (`user_record.php:155-156`) + `DuplicateSubmissionException` replay (`AdminUserController.php:246-248`) — ADR 0023 item 4 | Keep — do not regress | low |
| 65 | Record — Cosmetic title layout | copy | Both buttons in one row: `Save title` + ghost `Clear (revert to derived)` (lines 342-345); input `placeholder="(none)"` | Two stacked `<form>`s (`user_record.php:180-193`), no placeholder | Add the placeholder; lay the buttons side by side | low |
| 66 | Record — Save/Clear split into two forms | constraint | One handler pair on one control group | Two `<form>`s so a no-JS submit is unambiguous (`user_record.php:180`, `:189` with hidden `title=""`) | Keep the two-form mechanism; only the visual row changes | low |
| 67 | Record — Profile media card | feature-added | Not modelled | `user_record.php:196-220`, gated on `profile_media` (`AdminUserController.php:449`); routes `App.php:2368-2369` | Keep between Cosmetic title and Badges; restyle in the idiom | low |
| 68 | Record — Badges grant label | copy | Select label is `Grant a manual badge` (line 353) | `h3 Grant a manual badge` + label `Badge` (`user_record.php:224,228`) | Fold the `h3` into the field label | low |
| 69 | Record — "Held manual badges" heading | copy | No heading; the list follows the grant control (lines 359-368) | `h3 Held manual badges` `user_record.php:243` | Drop the heading | low |
| 70 | Record — badge grant reason | feature-added | Not modelled | `Reason (optional)` `user_record.php:236-239` → `BadgeService::grantManual()` third arg (`AdminUserController.php:183`) | Keep | low |
| 71 | Record — badge glyph | feature-added | Hard-coded `✦` in `--star` | Real per-badge icon `$b['icon'] ?? '*'` `user_record.php:250` | Keep the real icon; adopt `--star` colouring and make `✦` the fallback instead of `*` | low |
| 72 | Record — badge revoke control | copy | Right-aligned bare danger link-button (line 364) | `<button class="linkbtn danger">` `user_record.php:255` | Adopt the right-aligned bare treatment | low |
| 73 | Record — badge revoke aria-label | feature-added | None | `aria-label="Revoke the {name} badge"` `user_record.php:255` | Keep | low |
| 74 | Record — History layout | copy | Four columns, `repeat(auto-fit, minmax(280px,1fr))` (line 375) | Four stacked lists `user_record.php:266-334` | Adopt the grid | low |
| 75 | Record — History heading levels | copy | `h3 History` + four `h4` legs | `h2 History` + four `h3` legs | Demote one level (pairs with #2) | low |
| 76 | Record — ban-history "lifted" pill | feature-added | Never modelled | `<span class="pill">lifted {date}</span>` `user_record.php:291-292` | Keep | low |
| 77 | Record — until/indefinite marker | copy | Inline text `· until 9 Aug 2026` / `· indefinite` (fixture line 531) | `<span class="pill">` `user_record.php:293-297` | Render as inline mono text in the design's `--text-faint` register | low |
| 78 | Record — audit-trail empty state | feature-added | No `noLog` state | `No audit entries.` `user_record.php:322` | Keep | low |
| 79 | Record — full-trail link visibility | copy | Always rendered (line 426) | Only when the log is non-empty (inside the `else`, `user_record.php:333`) | Always render; `/admin/audit?target_type=user&target_id={id}` is valid regardless (route `App.php:2210`) | low |
| 80 | Record — audit reason suffix | feature-added | Action only | `{action} — {reason}` `user_record.php:328` | Keep | low |
| 81 | Invitations — one-time link banner | copy | `--gold-050` / `--gold-200` / 3px `--gold-500` left rule, `role="status"`, `<code>` chip, `amRise` (lines 437-441); strong text verbatim match | Generic `.flash` `invitations.php:15-18` | Restyle to the gold register | low |
| 82 | Invitations — layout | copy | Two-column grid `minmax(320px,400px) 1fr` (line 443) | Two stacked full-width cards | Adopt the grid | low |
| 83 | Invitations — form field markup | copy | `.field`-style `<label>` + uppercase `<span>` caption for every control | Bare `<label>Bind to email (optional) <input>` text nodes (`invitations.php:28,33,39,44,49`) — inconsistent with the rest of the console | Convert to the label/span pattern | low |
| 84 | Invitations — ceilings in labels | feature-changed | Hard-coded `Max uses (1–25)`, `Expires in days (1–90)` | `Max uses (1–100, default 1)` / `Expires in days (1–365, default 14)` from `InvitationService::MAX_USES_CEILING` / `MAX_EXPIRY_DAYS` / `DEFAULT_EXPIRY_DAYS` (`InvitationService.php:38-40`; `invitations.php:38-46`) | Production wins on behaviour: keep the dynamic numbers, adopt the design's label shape | low |
| 85 | Invitations — binding-conflict error | copy | `Bind to an email address or a domain — not both.` | `Bind to an email address or a domain, not both.` `InvitationService.php:99` | Adopt the em dash | low |
| 86 | Invitations — extra validators | feature-added | Only three checks modelled | `Enter a valid email address to bind, or leave blank.` (`:101`), `Enter a bare domain like example.com, or leave blank.` (`:107`), `That board does not exist.` (`:119`) | Keep | low |
| 87 | Invitations — 429 state | feature-added | Not modelled | `Too many invitations created just now. Please wait before issuing more.` at HTTP 429 with `old` preserved (`AdminInvitationController.php:44-50`) | Keep; style as an alert in the idiom | low |
| 88 | Invitations — table heading | copy | Section is unheaded (line 477) | `h2 Issued invitations` `invitations.php:64` | Drop the `h2`; give the `<section>` an `aria-label="Issued invitations"` so it keeps an accessible name | low |
| 89 | Invitations — `By` column | feature-added | 5 data columns | `creator_username` column `invitations.php:71,77` | Keep | low |
| 90 | Invitations — status cell | copy | Pills: active `--surface-done`/`--on-done`; redeemed `--surface-sunken`/`--text-muted`; revoked rust-tinted/`--danger` (lines 495-497) | Bare `ucfirst($row['status'])` `invitations.php:85` | Adopt the pills | low |
| 91 | Invitations — revoke control | copy | Bare danger link-button, right-aligned; non-active rows render `—` (lines 500-501) | `<button class="btn btn-small danger">`, empty cell otherwise (`invitations.php:87-92`) | Adopt the link-button + the em-dash placeholder | low |
| 92 | Invitations — empty state | feature-added | Not modelled | `No invitations have been issued yet.` `invitations.php:66` | Keep | low |
| 93 | Invitations — `noindex` | feature-added | n/a | `$this->noindex(…)` on every response (`AdminInvitationController.php:71,102`) | Keep | low |
| 94 | Relative / terminal timestamps | feature-removed | `2 hours ago`, `yesterday`, `4 months ago`, `in 12 days`, `redeemed 11 Jul`, `revoked 24 Jun` | Only `human_date()` (`M j, Y`) and `human_datetime()` (`M j, Y \a\t H:i UTC`) exist — `src/Support/helpers.php:65-88`; used at `users.php:139-140`, `invitations.php:76,84` | **Do not build** a relative-time formatter for this adoption. Record as a gap: the design's relative strings are fixture data, and a `human_relative()` helper is net-new behaviour with its own tests | low |
| 95 | Anti-abuse console | feature-added | The design screen contains **zero** anti-abuse content | `templates/admin/moderation.php` (`h1 Anti-abuse`, eyebrow `Moderation`, mode select + blocked words), backed by `AdminSettingsController::moderation` / `updateAntiAbuse` (`App.php:2208-2209`), rail entry `_nav.php:16` (flag `anti_abuse`) | **Not represented on admin-members** and out of scope here. Do not delete it. Per the settled ownership list its natural home is `admin-settings`; if no design screen claims it, raise it upstream as an ownership gap rather than inventing a home | medium |

### ADR-recorded remediations — audit against the design

| ADR item | Design position | Verdict |
| --- | --- | --- |
| **Typed ban confirmation** (ADR 0021) | Present but behind a switchable `banRequiresUsername` prop and enforced client-side only | Production's server check (`AdminUserController.php:292-296`) is stronger and **stays**. Adopt only the label + error wording (#53, #55). Row #54 |
| **Bulk-action confirm step** (ADR 0021) | Fully modelled: `<!-- ═══ Bulk confirmation ═══ -->` with subjects, shared reason, shared expiry | Match. Design adds a pre-flight `skipped — administrator` marker production lacks (#34) and a back link (#29) |
| **User-record PII handling** (ADR 0021) | Modelled: hidden by default, `view_pii` named in the blurb, one-view disclosure | Match. Design's `revealPii` appends a `view_pii` log line; production POSTs `/admin/users/{id}/pii` → `UserModerationService::revealPii()` writes exactly one `view_pii` row (`:470-476`) and returns values for a single render. **No behaviour change** |
| **422-with-draft-preserved** (ADR 0021/0023) | Modelled per-form via `suspendErr` / `banErr` / `roleErr` / `bulkFormError` / `inviteErr`, but the design keeps state client-side | Production is stronger: `$error_context` + `$errs` + `$old` scoped per originating form (`user_record.php:13-24`), `bulk_selected` re-tick, invitation `old` on both 422 and 429. **Every restructured form must keep `$ferr`/`$fattr`/`$oldv` wired** — this is the highest-risk regression in the whole slice |
| **ADR 0021 deferral #9 — alt-account / device signals** | Design's Contact & signals card shows only email + session IPs + post IPs | Design does **not** reintroduce the deferred heuristic. No conflict |
| **ADR 0021 deferral #4 — ban types / durations / board scope** | Design shows only indefinite full ban + suspension-with-expiry | No conflict |
| **`users.role` flag independence** | Role card is unconditional; no `capabilities` notion on the screen | Verified clean (#58) |

---

## 3. Fiction strings

| # | Design string (verbatim) | Where | Proposed production string |
| --- | --- | --- | --- |
| 1 | `Regard` | directory column header (line 97), record `<dt>` (line 222) | `Reputation` — production's term at `users.php:114`, `user_record.php:44` |
| 2 | `Imladris` (wordmark in AdminNav's identity row) | `AdminNav` mounted at line 22 | The operator's site name from settings/branding |
| 3 | `Back to the council` | AdminNav `backLabel` default | `Back to the forum` (link to `/`) |
| 4 | `https://imladris.example/join/{token}` | script line 840 | `{app.url}/invite/{token}` — production's real shape (`AdminInvitationController.php:58-61`) |
| 5 | `evaluations`, `audit-trails`, `interpretability` | board-grant `<option>`s (line 470) | Real board names from `BoardRepository::allOrdered()` (`AdminInvitationController.php:104`) |
| 6 | `Loremaster of Evals` | badge catalogue (script line 517) | Real slugs/names from `BadgeRepository::manualCatalogue()` (`AdminUserController.php:441`) |
| 7 | `Welcome`, `First Thread`, `Trusted Answerer`, `Problem Solver`, `Anniversary` | badge catalogue (script line 517) | Same — data-driven, never hard-coded |
| 8 | `Master of the House` | `title` fixture (script line 520) | Operator-set `users.title` override |
| 9 | `Legend`, `Loremaster`, `Counsellor`, `Companion`, `Newcomer` | `derived` fixtures (script lines 520-537) | Configured ladder labels resolved by `TitleService::derive()` (thresholds are config-driven; floor label is `New`) |
| 10 | `elrond`, `galadriel`, `erestor`, `glorfindel`, `arwen`, `lindir`, `saruman`, `mellon`, `haldir` | user fixtures | Real usernames |
| 11 | `@imladris.council`, `@orthanc.test`, `@lorien.test` email domains | user fixtures | Real emails behind the PII gate |
| 12 | `by @elrond`, `by @galadriel`, `by @system` | history/log fixtures | `by @{actor_username}` — production's `$w['issued_by_username'] ?? 'system'` etc. (`user_record.php:275,299,314,329`) |
| 13 | `Reposting the same appeal across four boards.`, `Misrepresenting the council record.`, `Fabricated audit entries`, `Do not reinstate without a full review of the audit trail.`, `Volunteered to keep the evaluations board through the summer.` | warning/ban/note fixtures | Operator-authored free text — never shipped |
| 14 | `Third Age` | not present in this file | n/a (checked) |

No fiction string appears in a **chrome** position on this screen other than #1–#4; #5–#13 are fixture payloads that a data-driven render replaces automatically.

---

## 4. State inventory

### Success / flash

| Design state string (verbatim) | Production equivalent |
| --- | --- |
| `Suspended N members — each action was audited individually.` (+ ` N administrators skipped.`) | `Suspended N members.` (+ ` Skipped: user (reason)`) — `AdminUserController.php:99-104`. **Gap:** the "each action was audited individually" clause. Production's named skip list is richer |
| `Warned N members — each action was audited individually.` | `Warned N members.` — same site |
| `@{user} is suspended. The action was audited.` | `User suspended.` — `AdminUserController.php:280`. **Gap** |
| `Restriction lifted — @{user} is active again.` | `Account restriction lifted.` — `:317`. **Gap** |
| `@{user} is banned. The action was audited.` | `User banned.` — `:303`. **Gap** |
| `@{user} is now {role}.` | `Role updated.` — `:348`. **Gap** |
| `Warning recorded on @{user}’s record.` | `Warning recorded.` — `:249`. **Gap** |
| `Private staff note added.` | `Note added.` — `:264`. **Gap** |
| `Cosmetic title set to “{T}”.` / `Cosmetic title cleared.` | `Title updated.` (both cases) — `:171`. **Gap** |
| `Cosmetic title reverted to the derived ladder.` | `Title updated.` — same. **Gap** |
| `{Badge} granted to @{user}.` | `Badge granted.` — `:187`. **Gap** |
| `@{user} already holds {Badge}.` (flash, not an error) | `BadgeService` throws `ValidationException` → 422 with `badge_grant` context (`:185`). **feature-changed** — production's 422 is stricter and correct; keep it, adopt the wording |
| `{Badge} revoked from @{user}.` | `Badge revoked.` — `:203`. **Gap** |
| `Invitation revoked — the link no longer redeems.` | `Invitation revoked.` — `AdminInvitationController.php:71`. **Gap** |
| — | `Avatar removed.` / `Signature removed.` (`:213`, `:223`) — production-only, no design equivalent (feature-added, row #67) |

### Error / validation

| Design state string | Production equivalent |
| --- | --- |
| `Select at least one member before choosing a bulk action.` | `Select at least one member first.` — `AdminUserController.php:62` |
| `Choose an action to apply to the selected members.` | `Choose a bulk action to apply.` — `:59` |
| `A shared reason is required — it is shown to every member and written to each audit entry.` | `A reason is required.` — `UserModerationService.php:799` (shared with the single-member paths) |
| `A reason is required.` (suspend, ban) | Exact match — `UserModerationService.php:799` |
| `The username does not match — type {username} exactly to confirm.` | `Type the member's username exactly to confirm the ban.` — `AdminUserController.php:294` |
| `Changing a role is a reauthenticated action — confirm your password.` | From `ReauthService::requirePassword()` (`UserModerationService.php:397`) |
| `@{user} already holds that role.` | `The member already has this role.` — `UserModerationService.php:404` |
| `Bind to an email address or a domain — not both.` | `Bind to an email address or a domain, not both.` — `InvitationService.php:99` |
| `Max uses must be between 1 and 25.` | `Max uses must be between 1 and 100.` — `InvitationService.php:112` (ceiling is a constant) |
| `Expiry must be between 1 and 90 days.` | `Expiry must be between 1 and 365 days.` — `:116` |
| — | `Reason must be 255 characters or fewer.` (`:805`), `Title must be 64 characters or fewer.` (`:501`), `Unknown role.` / `No such member.` (`:399,:402`), `You cannot warn your own account.` (`:75`), `Enter a valid email address to bind, or leave blank.`, `Enter a bare domain like example.com, or leave blank.`, `That board does not exist.`, `The bulk selection is no longer valid — start again.`, `Too many invitations created just now. Please wait before issuing more.` — **all production-only; keep** |

### Empty

| Design | Production |
| --- | --- |
| `No members match these filters` + `Reset the filters to see the whole directory.` | `No users match these filters.` (single muted row) — `users.php:144` |
| `No manual badges granted.` | Exact match — `user_record.php:245` |
| `No warnings.` | Exact match — `:268` |
| `No ban history.` | Exact match — `:283` |
| `No staff notes.` | Exact match — `:307` |
| (none — audit trail has no empty state) | `No audit entries.` — `:322` (feature-added) |
| (none — invitations table has no empty state) | `No invitations have been issued yet.` — `invitations.php:66` (feature-added) |

### Counts / labels

| Design | Production |
| --- | --- |
| `{{ resultLabel }}` = `N members of M` | Not rendered (`total` available at `UserModerationService.php:644`). **Gap** |
| `{{ selectedLabel }}` = `None selected` / `N members selected` | Not rendered. **Gap** — needs JS (row #23) |
| `{{ bulkTitle }}` = `Suspend N members` / `Warn N members` | `users_bulk_confirm.php:13` — match |
| `{{ bulkConfirmLabel }}` = actionable count | Selected count — `users_bulk_confirm.php:61`. **Gap** |
| `skipped — administrator` | Not rendered pre-flight. **Gap** |
| `Moderates N board(s)` | Production-only (`title` attr, `users.php:133`) |

### Disabled / pending / gated

| Design | Production |
| --- | --- |
| Pagination `Previous` `disabled="{{ true }}"` | Link omitted entirely — `users.php:166` |
| `piiHidden` / `piiShown` two-phase card | `$pii === null` vs populated — `user_record.php:52`, `AdminUserController.php:151-158` — match |
| `banGate` prop toggling the confirm field | Unconditional server enforcement — `AdminUserController.php:292` — production wins |
| `bulkOn` prop toggling the whole bulk bar | Always rendered — `users.php:150` |
| — | `is_self` / `!can_govern` branches hide all restriction controls (`user_record.php:73-77`) — production-only |
| — | `profile_media` flag gates the Profile media card (`AdminUserController.php:449`) — production-only |
| — | `invitations` flag 404s the whole tab (`AdminInvitationController.php:76-81`) — production-only |

### Loading

The design models **no** loading state anywhere on this screen — all transitions are synchronous `setState`. Production is server-rendered with full page loads, so there is nothing to map. No gap.

### Observation (not a diff row)

`user_record.php:73-84` places the `Lift restriction` form inside the `else` branch of the `is_self` / `can_govern` guard, so the Lift control is also hidden for an admin subject or for yourself. Reachable only if a member was suspended and *then* promoted to admin. Out of scope for this adoption; worth a separate look.

---

## 5. Slice proposal

Each slice is independently shippable, independently testable, and leaves production green on its own.

1. **S1 — Members shell + sub-tab strip.**
   Add the `Members & invitations` `h1` and the `nav aria-label="Member sections"` underline strip (`Directory` → `/admin/users`, `Invitations` → `/admin/invitations`) to all four templates as `<a href>` links, with the Invitations tab rendered `aria-disabled` + `Disabled until the feature flag is enabled` when `invitations` is off. Demote every drill-in `h1` to `h2`. Depends on the AdminNav slice for the outer chrome but ships before it (the per-template `admin-head` stays until then).
   *Tests:* integration — the strip renders on all four routes; `aria-current="page"` is correct per route; the Invitations tab is non-navigable with `invitations` rolled back; Playwright evidence of both tabs. Rows 2–5.

2. **S2 — Directory table + empty state + count.**
   Monogram in the member cell, un-parenthesised display name, design role/state pills, board-mod chip whenever `moderated_boards > 0`, the design's centred empty-state block with the two verbatim strings, `N members of M` in the filter action row, and both pagination buttons always rendered (disabled when unreachable). Keeps `.table-scroll`, `aria-sort` and the `last_seen` sort untouched.
   *Tests:* integration — empty-filter render asserts the `h3` string; count string; disabled Previous on page 0. Rows 9–20, 28.

3. **S3 — Bulk path: microcopy, pre-flight skip marker, actionable count.**
   Adopt the four design error/blurb strings, add the `‹ All members` back link, drop `Review before applying`, mark admin/self subjects `skipped — administrator` from the rows `bulkPlan()` already returns, and label the confirm button with the actionable count. Add a bulk-specific shared-reason message. Must not touch `UserModerationService::bulkApply()` refusal logic or the `bulk_selected` re-tick.
   *Tests:* integration — select an admin + a user, assert the marker renders and the button reads `Warn 1 member`; assert the apply-time skip flash still names the admin; assert a 422 on an empty reason still re-renders with the reason field and the selection intact. Rows 24–26, 29–38.

4. **S4 — Selected-count enhancement (JS).**
   Extend the existing `[data-bulk-toggle]` IIFE in `public/assets/app.js` to maintain `None selected` / `N members selected` from `input[name="selected[]"]` state, with the server rendering the checked count on the 422 re-render so the no-JS path is not blank. External file only — no inline script.
   *Tests:* Playwright — tick two rows, assert the label; disable JS, assert the server-rendered count on a 422. Row 23.

5. **S5 — User record: layout + Status/Contact cards.**
   Identity row (xl gilt monogram + `h2` + mono handle), the two-column Status/Contact grid, Status dl reordered to `Role · Reputation · Posts · Joined · Last seen` with `Suspended until` and `View public profile` pulled out of the dl, the bare `Contact & signals` heading, and mono PII values. No behaviour change; the PII POST and its `view_pii` audit row are untouched.
   *Tests:* integration — the dl term order; the suspended-until strip appears only for `status = suspended`; the `view_pii` reveal still renders values once and writes one audit row. Rows 39–47.

6. **S6 — User record: restrictions + role + staff actions.**
   Restriction banner with the two design strings and an inline `Lift restriction`; Suspend/Ban side by side as `h4`s; the design's ban label, ban blurb (curly apostrophe) and mismatch error; Staff actions loses its two `h3`s and takes the design's field labels with a secondary `Add note`. The server-side `confirm_username` check, `required` attributes, the warn idempotency key and every `$ferr`/`$fattr`/`$oldv` binding stay exactly as they are.
   *Tests:* integration — a wrong `confirm_username` still 422s with the (new) message and the reason field preserved; a suspend 422 preserves both `reason` and `until` under the `suspend` context and does **not** echo under `ban`; role change still works with `capabilities` rolled back. Rows 48–64.

7. **S7 — User record: title, badges, history grid.**
   `(none)` placeholder + side-by-side Save/Clear (two forms retained); badge grant label folded into the field, `Held manual badges` heading dropped, `✦` fallback + `--star` colouring, right-aligned bare revoke; History as a four-column grid at `h4`, until/indefinite as inline text, full-trail link always rendered. Profile media card restyled in place.
   *Tests:* integration — Clear still posts `title=""` and produces the derived ladder; revoke still carries its `aria-label`; the full-trail link renders on an empty log. Rows 65–80.

8. **S8 — Invitations tab.**
   Two-column grid, gold one-time-link banner, `.field` label pattern for all five controls, design label shape with the *dynamic* ceilings, the em-dash binding error, status pills, bare right-aligned revoke with an `—` placeholder, `h2` dropped for an `aria-label`ed section. `noindex`, the 429 path, the `By` column, the extra validators and the empty state all stay.
   *Tests:* integration — 422 on `email`+`domain` both set preserves all five old values; 429 path still preserves them; revoked row renders the pill and the em dash. Rows 81–93.

9. **S9 (record-only, no code) — anti-abuse ownership.**
   `/admin/moderation` has no home on this design screen. File it against `admin-settings` (or wherever the settled ownership list lands it) rather than deleting or duplicating it. Row #95.
