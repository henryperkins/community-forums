# Shared-chrome handoff — 2026-10-06

Evidence for syncing upstream's shared-chrome handoff (`CommunityForumsDesignSystem.zip`,
SHA-256 `b818035…2d0c`) into the Imladris mirror. The work is on the working tree based on
`34f13b00`; no commit, push or deployment is implied. The sync itself, and every hunk taken
or held, is recorded in
[`LOCAL_RECONCILIATION.md`](../../design-system/imladris/LOCAL_RECONCILIATION.md)
(2026-10-06, shared chrome). The [working-tree fingerprint](working-tree.json) records the
asset version and both digests.

## What changed in production

One computed property. The design layer no longer gives the current Boards pill and the
current rail board `cursor: default`, so they show the link cursor. Both keep their href
under ADR 0032 decision 1. No template, application stylesheet or script changed. The only
application-surface file added is the regenerated bundle,
`public/assets/dist/imladris-style-DeGci5u_.css`. The previous bundle stays as a retained
release.

## How that was established

[`layer-diff.cjs`](layer-diff.cjs) loads each page of a seeded copy of the application. It
swaps only the Imladris stylesheet, in place: old, new, old, new. Webfonts are held in a
separate permanent sheet so they never reload. On each pass it records the computed value
of every property for every element and for its `::before`, `::after`, `::marker` and
`::placeholder` pseudo-elements. It also records the chrome controls' hover and focus
states. A difference counts only if both old passes agree and both new passes agree, which
rules out churn from polls, timers and transitions.

[The matrix](layer-matrix.json) covers:

- Personas: guest, a member who moderates one board, a private-board member, and an admin.
- 44 routes: the board index, a board, a private board, topics, Inbox, Compose, Search,
  Messages, Notifications, Tags, Drafts, Following, Users online, Leaderboard, profiles,
  four settings panes, `/mod/reports`, eight console pages, login, register and a 404.
- 121 route and width cases: the core routes at 1280, 1000, 880, 820 and 390px; the rest at
  1280 and 390px.
- Three registers each: parchment, explicit twilight and system-dark.

| Comparison | Result |
|---|---|
| Null control: the old bundle against itself, same matrix | 0 differences. |
| Old against new fingerprinted bundle, Chromium 149 | 447 differences, all `cursor: default → pointer` on `.forum-bar-surface.is-active` and `.board-rail-item.is-active`, plus the count, name and visibility spans that inherit it ([layer-chromium.txt](layer-chromium.txt)). |
| The same comparison, WebKit 26.5 | The same 447 differences. WebKit reports a link's cursor as `auto` ([layer-webkit.txt](layer-webkit.txt)). |
| Contrast: the bundle's whole `components.css` | 5,512 differences: the console search and username, the ≤900px identity row, the bell's hover wash, the composer send, `.star-toggle` and more ([upstream-whole-file.txt](upstream-whole-file.txt)). Hence the hunk-by-hunk sync. |

The rules taken from upstream without a visible effect produced no difference anywhere in
the matrix: `.btn:hover` on `--accent`, the `--field-rule` edge and token, the four
`--stage-*` tokens, the hover-underline rule, and comments. `app.css` already declares the
same values.

## The handoff's contract, checked against production

Read from `templates/partials/{topbar,sidebar,board_rail_item,presence_person}.php`,
`templates/admin/{_console,_area_links}.php`, `templates/layout.php` and
`src/Service/PresenceService.php`, and exercised by the browser runs below.

| Contract item | Production | Result |
|---|---|---|
| Bar order: brand, pills, search, pane toggles, divider, New topic, bell, seat | Same, plus the phone drawer's opener | Matches (ADR 0032 adaptation) |
| Guest: Log in, with no toggles, compose, bell, seat or counts | Same, plus Sign up | Matches |
| Boards, Inbox, Messages; the active pill keeps its href and `aria-current` | Same. Inbox and Messages need a viewer and their flags | Matches |
| Inbox count: signed in only, 99+, counted label | Same | Matches |
| Messages pill without a count | Carries an unread-conversations count, hidden at 0 | Differs; raised upstream |
| Search: a link to `/search`, absent on `/search` | Same | Matches |
| Rail toggle everywhere, reading-pane toggle on `/inbox`, as persisted forms | Same | Matches |
| New topic absent on `/compose` | Same | Matches |
| Bell before the seat, with its count in the accessible name | Same, since `88d51dfe` (2026-09-20) | Matches (delta 1 verified) |
| Bell 30×30 with a sunken hover wash; count at −3/−5px | 40×40 target, `--brand` hover ink, `.bell-count` at the corner | Differs; owner call |
| Seat: 30px monogram, name clips, leaf only when online or away | Same, as the summary of the account menu | Matches (ADR 0032 adaptation) |
| Current pill and rail board behave as links | Layer gave them `cursor: default` | Fixed by this change |
| Rail rows: category heading, active row keeps its href, 99+ unread pill, none for a guest | Same (guest pages carry no unread pills) | Matches |
| Visibility chip on every rail except the compose picker | Same | Matches |
| Compose picker: GET buttons, `aria-pressed`, "posting here", locked rows shown with a reason | Same | Matches |
| Roster: "Online" with the here-now count, one live summary, five rows, "+N more", empty copy, a foot link that is never hidden | Same; the title is also a link | Matches |
| Roster rows: profile link, 24px monogram with dot, bare dot when avatars are off, "you", Staff, "@handle · Here now/Away" | Same, without "where" (deferred, ADR 0031) | Matches |
| A member with presence off leaves the roll and their seat shows no leaf | Same (`show_presence` gate, `self_state`) | Matches |
| Console: brand and exit to `/`; search, bell, seat (seeded by username), Log out and mode pill on every area | Same | Matches |
| Exit label "Back to the council" | "Back to the forum" (ADMIN.md §9.2, ADR 0024) | Production stands (delta 2) |
| Console cluster classes `.admin-bar-bell`, `-signout` | `topbar-link bell`, `bell-count`, `linkbtn`, `admin-bar-action-label` | Differs; raised upstream |
| Tier: eleven areas, Moderation second, current area not a link, a moderator sees Moderation only | Same; flag-off areas render disabled with a note | Matches |
| ≤860px tier wraps with 44px targets | A native disclosure names the current area (`34f13b00`) | Production adaptation (ADMIN.md §9.2) |
| Active pill on `/`, `/c/*` and tag pages | `/`, `/c/*`, `/tags`, `/tags/*` | Matches (the handoff writes `/tag/`) |
| No pill on `/t/{id}` | Boards for an authorized topic, its board current in the rail | Production stands (ADR 0032 follow-up) |
| No pill on `/search`, `/compose`, `/u/*`, `/users-online`, `/settings/*`, `/leaderboard` | Same | Matches |
| `/leaderboard` rail | The standard rail with the roster | Open owner decision |

## Validation

- `composer verify:imladris`: **24 passed, 305 assertions** ([imladris.txt](imladris.txt)).
  `check:imladris` is green after both digests were refreshed in this change.
- Full PHPUnit suite in four shards: **3,125 tests, 23,728 assertions, no failures or
  errors, one skip** ([phpunit.txt](phpunit.txt)). The skip is the dedicated-database
  migration 0077 rehearsal. A new assertion in
  `AppImladrisFidelityTest::test_member_chrome_wears_the_design_systems_vocabulary_and_app_css_restates_none_of_it`
  pins the cursor; it fails against the previous layer.
- `npm run check:assets` current; `npm run test:assets` **23 passed** ([assets.txt](assets.txt)).
- Chromium ([chromium.txt](chromium.txt)): `chrome-consistency`, `unified-chrome`,
  `startup-geometry` and `admin-dashboard`, **51 passed, 23 intentional viewport skips**.
  WebKit ([webkit.txt](webkit.txt)): `chrome-consistency` and `admin-dashboard`, **19
  passed, 11 skips**. Each spec group ran on a freshly prepared database. Both counts match
  `34f13b00`'s. `chrome-consistency.spec.ts` now also asserts that the current pill and
  rail rows do not compute `cursor: default`.

## Scope and environment

Everything ran on localhost against real PHP rendering and MariaDB. The databases were
dedicated and disposable: `retroboards_e2e_chromesync` for the browser runs and the
comparison, and `retroboards_test_chromesync_s0`…`s3` for PHPUnit. Seeded credentials are
fake. All five databases were dropped afterwards, the temporary server on port 8041 was
stopped, and its rate-limit and package stores were removed. Browser captures went to a
scratch directory, so no committed evidence image changed.
These are Chromium and WebKit runs on Linux, not physical-device or assistive-technology
certification. The comparison covers the listed matrix, not every application page.
