repo: henryperkins/community-forums
branch: main

## Last sync

date: 2026-09-12
commit: 966a5b1c

### Updated in this project

- **Presence handoff synced per hunk** (`_archive/design_handoff_presence/README.md`): `templates/users-online/` is the roll in the shared shell; `templates/user-profile/` taken whole (the `role="img"` dot with its away state, and the 2026-08-03 cover this mirror had never received); new `components/presence/` (PresenceList, PresenceRow, legend card), `components/forum/ForumNav`, `BoardRail`, `chrome.card.html`; `tokens/colors.css` carries `--presence-away` / `--presence-offline` in both registers; `tokens/typography.css` the `--measure-*` family. Seven upstream hunks held back and the bundle's deletion of the production-transfer section refused — `LOCAL_RECONCILIATION.md`, 2026-09-12.
- **The member chrome is adopted verbatim** (ADR 0032): `partials/topbar.php` and `partials/sidebar.php` render ForumNav and BoardRail in the design's own vocabulary and the runtime layer styles them; the production-transfer section here and in `app.css` loses its "Shared shell" and presence-widget copies. `/users-online` ported verbatim from the template, all three optional deltas taken.
- **The `[hidden]` guard lands without `!important`**: `ImladrisAssetBuilder` refuses the flag in a runtime source, and inside the layer the attribute selector already outranks the block rule.

## Screen map

| Screen / artifact | Built from |
|---|---|
| `templates/living-brief/LivingBrief.dc.html` | `templates/partials/living_brief.php`, `templates/partials/thread_memory_tools.php`, `public/assets/app.css` (`.living-brief*`, `.reference-card*`) |
| `templates/thread-view/ThreadView.dc.html` (Council topic) | `templates/thread.php`, `templates/partials/{thread_tools,thread_status_history,thread_restructure,post,post_toolbar,composer_shell,living_brief}.php` |
| `templates/engineering-handoff/EngineeringHandoff.dc.html` | `README.md`, `SCHEMA.md`, `src/Core/App.php` (routes), `src/Core/FeatureFlags.php`, `src/Security/AuthorityGate.php`, `src/Service/ReactionService.php` |
| `ui_kits/retroboards/` | `templates/{inbox,thread,board,leaderboard}.php`, `templates/profile/show.php`, `templates/partials/{topbar,sidebar,thread_row,post,monogram}.php` |
| `templates/board-index/BoardIndex.dc.html` | `templates/{home,feed,search,notifications,compose}.php`, `templates/tags/{index,show}.php`, `templates/profile/connections.php`, `src/Controller/HomeController.php` (route `/` = the category/board index) |
| `templates/forum-inbox/ForumInbox.dc.html` | `templates/inbox.php`, `templates/partials/{sidebar,thread_row}.php` |
| `templates/board-page/BoardPage.dc.html` | `templates/board.php`, `templates/partials/{sidebar,thread_row,new_thread_form}.php`, `public/assets/app.css` (`.board-view` block), specs `2026-08-02-imladris-forum-inbox-board-identity-design.md` + `2026-08-03-board-topic-density-remediation-design.md` |
| `templates/account-settings/AccountSettings.dc.html` | `templates/account/*.php`, `templates/partials/settings_nav.php`, `src/Support/PreferenceSchema.php` |
| `templates/user-profile/UserProfile.dc.html` | `templates/profile/{show,gated,connections}.php`, `docs/evidence/imladris-profile-production/README.md` |
| `templates/users-online/UsersOnline.dc.html` | `templates/users_online.php` (ported verbatim, 2026-09-12), `templates/partials/{sidebar,presence_person,topbar}.php`, `templates/profile/show.php`, `src/Controller/PresenceController.php`, `src/Service/PresenceService.php`, `templates/account/privacy.php` (`show_presence`) |
| `components/forum/ForumNav.jsx`, `components/forum/BoardRail.jsx` | `templates/partials/{topbar,sidebar}.php` — rendered in the design's vocabulary and styled by the runtime layer (ADR 0032); `public/assets/app.css` "Member chrome" block carries only what the layer cannot express |
| `components/presence/PresenceList.jsx` | `templates/partials/{sidebar,presence_person}.php`, `public/assets/app.js` (the poller builds the same row and diffs `data-presence-sig`) |
| `templates/admin-overview/AdminOverview.dc.html` | `templates/admin/{dashboard,audit}.php` |
| `templates/admin-content/AdminContent.dc.html` | `templates/admin/{structure,tags,tag_merge_confirm}.php` |
| `templates/admin-people/AdminPeople.dc.html` | `templates/admin/{roles,role_edit,role_simulator}.php` |
| `templates/admin-appearance/AdminAppearance.dc.html` | `templates/admin/{branding,themes,theme_safe_mode}.php` |
| `templates/admin-notifications/AdminNotifications.dc.html` | `templates/admin/{email,announcements}.php` |
| `templates/admin-settings/AdminSettings.dc.html` | `templates/admin/{settings,thread_intelligence}.php` |
| `templates/admin-members/AdminMembers.dc.html` | `templates/admin/{users,user_record,users_bulk_confirm,invitations}.php` |
| `templates/admin-features/AdminFeatures.dc.html` | `templates/admin/{features,badge_rules,badge_rule_preview,custom_emoji}.php`, `src/Core/FeatureFlags.php` |
| `templates/admin-integrations/AdminIntegrations.dc.html` | `templates/admin/{api_tokens,webhooks,webhook_detail,providers,provider_disable}.php` |
| `templates/admin-packages/AdminPackages.dc.html` | `templates/admin/{packages,package_detail,package_plan,package_consent,package_security,package_publisher,registries,extensions}.php` |
| `ui_kits/auth/` | `templates/auth/*.php`, `public/assets/passkeys.js` |
| `ui_kits/mod/` | `templates/mod/{reports,approvals,appeals,user}.php`, `templates/appeals/index.php` |
| `ui_kits/dm/` | `templates/dm/{index,new,show}.php`, `templates/partials/{dm_list,dm_rail,dm_compose_fields}.php` |
| `ui_kits/system/` | `templates/setup/wizard.php`, `templates/errors/error.php`, `templates/{privacy,unsubscribe}.php`, `templates/profile/gated.php` |
| `styles.css`, `tokens/*.css`, `components.css` | `public/assets/app.css` |
| `feature-ui/` | `src/Core/FeatureFlags.php` — the rail flags only (`board_folders` `saved_feeds` `expanded_feeds` `bookmark_folders`); `polls`, `tags`, `topic_workflow` and `split_merge` are owned by `templates/thread-view/` |

## Open drift

- **Held from the 2026-09-12 presence handoff** (reasons in `LOCAL_RECONCILIATION.md`): the twilight `--surface-staff` / `--on-staff` re-tune and the deletion of the twilight `--artifact-link` remap; `.badge-staff`'s `color-mix` border; the `.field-hint` selector widening; the AdminNav operator-cluster rewrite (fourth sync); the tier chips back on numbered ramps; the thread-row FIDELITY-AUDIT §1/§2 rules (`.is-ruled`, the `[data-density="compact"]` binding, `.thread-star` on `--star`, `.thread-board .hash`); the removal of `.hash` from shared bits; the `.link-preview-action` restructure. Each is either a local correction upstream regressed or a production-visible change owed its own evidence run.
- **Resolved 2026-09-12.** The shell no longer drifts by transcription: production renders `ForumNav`, `BoardRail` and `PresenceList` in the design's vocabulary and the runtime layer styles them (ADR 0032). The deliberate deviations are listed in that ADR's decision 3 (the `--maxw` centring, the phone drawer, the persisted rail state, the account menu, the operator's logo, Sign up, the compose glyph on a phone).
- `templates/users-online/.thumbnail` still previews the pre-handoff directory design; the handoff shipped no replacement.
- **Raised upstream 2026-09-12.** `tokens/typography.css`'s base `a:hover { text-decoration: underline }` outranks nothing in `.forum-bar-surface`, `.board-rail-item` or `a.presence-person` (none sets `text-decoration` on hover), so ForumNav's pills and BoardRail's rows underline in gold on hover in the system's own specimens. Production suppresses it (ADR 0032, decision 3).
- `ui_kits/retroboards/` renders the profile cover as an older **survey** treatment — a display-font regard at 2.4rem labelled "Commends earned", `--surface-inverse` as the cover ground (which does flip to parchment on twilight), and stat labels at `.72rem`/`.06em`. It diverges from `templates/user-profile/` on purpose as a one-page survey; only the `--gold-800` tier fix was applied. `templates/user-profile/UserProfile.dc.html` is the owning artifact for `/u/{username}`.
- `templates/profile/gated.php` did not change in this range, but `app.css` still ships `.profile-gated-actions`. The 2026-08-03 review removed Send a message / Request access from this system's gated state as flows that do not exist; that call stands unless the partial proves otherwise on the next read.
- **Resolved 2026-08-03.** The earlier note claimed board rows "deliberately stay structurally unlike inbox rows (no board label, snippet, star, or inclusion cue)". Reading `partials/thread_row.php` disproved half of it: upstream has **one** partial with a `presentation` axis (`default` | `board`) and a `show_board` flag, and it renders the star, the unread dot, `assigned to @`, and `snoozed until` in **both** presentations. Only the board label is genuinely board-suppressed. `templates/board-page` was missing the star, the unread dot and the moderator cues; they are now in, at board weight. The snippet is ours either way — upstream has none on either surface — and is kept on `/inbox` as a documented triage aid. Decision recorded in `guidelines/thread-row.card.html`.
- `templates/leaderboard.php`, `templates/home.php`, and `templates/partials/{badges,icon}.php` moved in an earlier range but their design deltas were not reviewed; `ui_kits/retroboards/` and `templates/board-index/` may carry drift.

## Sync history

### 2026-09-12 — commit 966a5b1c

Presence handoff (`design_handoff_presence`) synced per hunk; the member chrome (ForumNav, BoardRail, PresenceList) adopted verbatim in production and the production-transfer section's shell copies retired in both files (ADR 0032). `/users-online` ported verbatim. Design digest `db7e73d6` → `548996ef`.

### 2026-08-03 — commit 3d317c770be4

- **New token `--gold-800: #6B5120`** — upstream added a darkest step to the mallorn ramp, and the profile layer of `app.css` now sets every small gold-on-`--gold-100` label in it (tier chip, regard plinth label, Commends eyebrow) where `--gold-700` — a fill colour — used to read thin. Added to `tokens/colors.css` as a register-independent ramp step and to `guidelines/gold.card.html`.
- **Profile cover rebuilt to the shipped treatment.** The regard plinth is a solid `--gold-100` card with a `--gold-200` hairline and `--ink-900` numerals, not a translucent gold wash; the tier chip gains its hairline; the header inherits `--parchment-50` and its gilt border is `color-mix(--gold-500 16%)`; watermark `.11`, `--shadow-lg`, member-since as a `.76rem` tracked label, website link gold. Upstream now carries an explicit comment that the cover **stays twilight in both registers** — the exception the last sync recorded is codified in source, so it is no longer drift.
- **Hardcoded rgba retired for register-aware tokens.** Follow's on-state is `--brand-subtle` / `--on-brand-subtle` / `--green-200`; the error rule and block action read `--danger` (which lightens to `#DB8C73` on twilight) rather than `--rust` (which does not).
- **Copy and affordances corrected against source.** The moderator strip is a `Moderator context` label plus a sanction sentence with "Open member record"; the `···` menu reads Copy link / Block; connections rows read `@handle · N regard` with **Remove follower** as the only row action, shown on your own seat in followers mode — the previous follow-back button and invented tenure strings did not exist upstream. Topic/post rows dropped the replies stat; commend rows are count + title; the regard note is one sentence.
- **`ui_kits/retroboards/` took the `--gold-800` tier fix only** — the rest of its cover is an older survey treatment, logged below as drift rather than corrected.

### 2026-08-03 (earlier) — commit 92fd94a1f7ed

Board page rebuilt to the evergreen identity band and its topic rows remediated to compact ruled list rows (ADR `2026-08-03-board-topic-density-remediation`); `PreferenceSchema` v3 retired `thread_sort`, dropping Account settings → Reading's Default sort; the profile cover was corrected to twilight in both registers, and profile-level Report plus the gated profile's Send a message / Request access were removed as flows that do not exist.

### 2026-08-02 — commit 3fa5704e2e42

Closed the admin gap: the twelve destinations that had no template became four — `templates/admin-members/`, `templates/admin-features/`, `templates/admin-integrations/`, `templates/admin-packages/`. Built from the attached local `community-forums` checkout (`templates/admin/*.php`), not a new upstream fetch; no commit advance claimed. Each template carries its drill-ins and validation. `ui_kits/admin/` labelled **superseded** — it survives only as the one-page survey.

### 2026-08-02 (earlier) — commit 3fa5704e2e42

Compared `3fa5704e2e42...main` — no upstream changes. Added `templates/account-settings/`, `templates/board-index/`, `templates/reading-rooms/`; retired all seven `@startingPoint` tags; folded `feature-ui/account` and `feature-ui/conversation` into their templates; merged `templates/council-topic/` into `templates/thread-view/`.

### 2026-08-02 — commit 3fa5704e2e42

Added `templates/living-brief/`; re-inspected upstream from `4efe4e33` to `3fa5704e2e42`. Flag drift: `group_dms` graduated to default-on (ADR 0022), leaving `link_previews`, `expanded_files`, `custom_css` dark. Admin console remediation rounds 1 & 2 (ADR 0021 / 0023) logged as open drift against `ui_kits/admin/` and `ui_kits/mod/`.

### 2026-07-14 — commit 4efe4e33db6475ce9c59190ba82c72cbd7d4b868

Modernization pass: composer brought to the shared-shell contract, fonts self-hosted, `--text-body` collision repaired, app snapshots archived, parity/runtime contracts added.
