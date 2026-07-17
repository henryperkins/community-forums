# Activated RetroBoards Features

Date: 2026-07-12

This imported Imladris design-system bundle is the reference for the RetroBoards
surfaces that graduated to default-on runtime availability. The
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
| `community_memory`, `automated_context` | AI/curator Living Brief, readable sources, deterministic return context, related explanations, history/controls, and redacted admin operations | Thread reading surfaces and admin status/queue patterns in `ui_kits/reading/`, `feature-ui/`, and `ui_kits/admin/` |

Operational notes:

- Production consumes only the allowlisted token, font, and reusable-component
  sources through generated `/assets/imladris.css`. Preview React/JavaScript,
  documentation styles, UI kits, uploads, and archived application snapshots
  never enter the runtime asset graph.
- Imladris rules live in low-priority cascade layers. The unlayered application
  stylesheet retains shell layout, feature states, and compatibility behavior;
  WYSIWYG, theme-package, and operator-branding styles continue to load after it.
- `config/imladris-runtime-baseline.json` pins the reviewed production
  presentation surface. A later member/admin/community/composer spec, template,
  client asset, or feature-flag change makes `composer verify:imladris` fail
  until design parity is reviewed and the baseline is deliberately refreshed.
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
- Thread Intelligence graduated both owning flags on 2026-07-12 after the
  selected `low`/`16000` live contract completed 46/46 runs with 149/149
  supported claims and zero incomplete/private-sentinel/fabricated-decision
  outcomes. Either flag may be rolled back independently with an explicit
  `false` override; missing provider credentials leave manual memory and
  deterministic context available without provider calls.
