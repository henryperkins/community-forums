# Activated RetroBoards Features

Date: 2026-07-01

This imported Imladris design-system bundle is the reference for the RetroBoards
surfaces that graduated to default-on runtime availability on 2026-07-01. The
application still owns behavior through `src/Core/FeatureFlags.php`; this file
maps the active flags back to the design-system source surfaces.

| Feature flag | Runtime surface | Imladris reference |
|---|---|---|
| `tags` | Public tag directory (`/tags`), tag detail (`/tags/{slug}`), thread tag editing, and admin tag catalogue (`/admin/tags`) | `ui_kits/reading/` Tags and Tag, `ui_kits/admin/` Tags, `feature-ui/tags/`, `components/core/Tag.jsx` |
| `expanded_feeds` | Following feed expanded to people, boards, and tags; Latest feed tab; board follow and tag follow controls | `ui_kits/reading/` Feed and Tag surfaces, `ui_kits/reading/ReadingSurfaces.jsx`, shared `inbox-tab` and feed-list primitives |
| `reputation_ledger` | Top contributors week/month/all-time windows and board-scoped leaderboard filtering backed by `reputation_events` | `ui_kits/retroboards/` Top contributors, `ui_kits/retroboards/Leaderboard.jsx`, `components/brand/CommendStar.jsx`, `components/identity/Monogram.jsx` |
| `board_folders` | Private board folders on `/settings/boards`, including readable-board filtering and per-user storage | `feature-ui/organize/`, `feature-ui/rail/`, `ui_kits/settings/` Boards organization |
| `bookmark_folders` | Private folders for starred threads on `/settings/boards`, with unreadable/unstarred thread filtering | `feature-ui/organize/`, `feature-ui/rail/`, `ui_kits/settings/` Bookmark organization |
| `saved_feeds` | Private saved board-filter feeds on `/settings/boards`, including digest preference groundwork | `feature-ui/organize/`, `feature-ui/rail/`, `ui_kits/settings/` Saved feed controls |

Operational notes:

- All listed flags default on after graduation and remain reversible via the
  `features` setting override.
- `tags` remains the prerequisite for tag pages and tag writes; hiding or
  disabling an individual tag is still controlled by the tag catalogue.
- `expanded_feeds` controls discovery expansion only. The base member feed and
  member follows remain part of the broader `community` surface.
- `reputation_ledger` controls ledger-backed windows and board scoping. The
  all-time leaderboard remains under the broader `community` surface.
- `board_folders`, `bookmark_folders`, and `saved_feeds` share the account
  board-organization page. Disabling one flag hides only its card and returns
  its write routes to 404 while the base board-preference page remains live.

## Pending Activation (Not Default-on)

Thread Intelligence uses the Imladris thread header/summary, source-link,
related-topic card, history, curator-form, and admin-status patterns, but it is
**not** part of the activated table above. Its implementation and Task 12 live
evaluation are complete pre-flip while both owning flags still default off:

| Pending surface | Flags | Imladris reference | Graduation state |
|---|---|---|---|
| AI/curator Living Brief, sources, related explanations, history/controls, and redacted admin operations | `community_memory`, `automated_context` | Thread reading surfaces and admin status/queue patterns in `ui_kits/reading/`, `feature-ui/`, and `ui_kits/admin/` | Pending final evidence and separate default-on change; selected live contract `low`/`16000`, 46/46 runs, 149/149 supported, zero incomplete/private-sentinel/fabricated-decision outcomes |

The canonical runtime source remains `src/Core/FeatureFlags.php`, where both
flags are currently `false`. See `docs/runbooks/thread_intelligence.md` and ADR
0019; do not treat this pending map as activation evidence.
