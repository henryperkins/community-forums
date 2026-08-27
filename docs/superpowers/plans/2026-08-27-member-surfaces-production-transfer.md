# Member Surfaces Production Transfer Implementation Plan

> **Execution:** Use `superpowers:executing-plans` task-by-task. This session executes inline because the user approved implementation and production delivery. Tests must be observed failing for the intended reason before each production change.

**Goal:** Transfer the approved Board Index, Forum Inbox, Search, Compose, and shared member shell from `CommunityForumDesignSystem.zip` into RetroBoards, prove visual and behavioral fidelity, and deploy the exact merged SHA to production.

**Architecture:** Keep server-rendered PHP, production authorization, and canonical routes authoritative. Extend the existing navigation, preference, repository, and replaceable-search seams; use query strings for surface state; render all real fallbacks first; then add strict-CSP vanilla JavaScript and route-scoped semantic-token CSS. Mirror the reviewed handoff for provenance but never execute its prototype runtime.

**Tech stack:** PHP 8.2+, MySQL/MariaDB, PHPUnit 11, server-rendered PHP templates, vanilla JavaScript/CSS, Imladris asset builder, Playwright, Cloudflare Workers Builds.

## Global constraints

- Approved design: `docs/superpowers/specs/2026-08-27-member-surfaces-production-transfer-design.md`.
- Source archive SHA-256: `8683122937E85111F76E8A29579D284A314845DD4E33956DE0E9BA10054090EC`.
- Preserve the unrelated dirty Thread Study work in `C:\Users\htper\community-forums`; all implementation stays in this worktree.
- Preserve dynamic operator branding, taxonomy, membership, capability, feature-flag, privacy, CSRF, idempotency, and anti-draft-loss behavior.
- Keep `/feed` as a separate personalized Following surface in secondary navigation.
- Add no migration and make no production data write.
- Write production CSS with semantic tokens only. No inline script/style, raw color value, handcrafted icon/brand asset, CDN, framework, or prototype runtime.
- Reconcile Imladris source before generating runtime assets. Do not hand-edit generated `public/assets/imladris.css`.
- The starting commit `d2517fec` already has an application-digest mismatch. Refresh the digest only after this work's visual comparison passes, and report the pre-existing source separately.
- Every visible surface must pass JavaScript/no-JavaScript, desktop/mobile, light/twilight, keyboard, accessibility, console, overflow, and combined source-vs-production comparison checks.
- Commit only explicitly staged task files. Publish and merge only after immutable final verification.

---

### Task 1: Preserve the approved handoff and settle product ownership

**Files:**

- Create: `docs/design-system/imladris/templates/member-surfaces/README.md`
- Create: `docs/design-system/imladris/templates/forum-inbox/ForumInbox.dc.html`
- Create: `docs/design-system/imladris/templates/search/Search.dc.html`
- Create: `docs/design-system/imladris/templates/compose/Compose.dc.html`
- Replace after review: `docs/design-system/imladris/templates/board-index/BoardIndex.dc.html`
- Create: `docs/design-system/imladris/components/forum/app-shell.card.html`
- Create: `docs/design-system/imladris/components/forum/thread-row.card.html`
- Create: `docs/design-system/imladris/templates/member-surfaces/screenshots/*.png`
- Modify: `docs/design-system/imladris/README.md`
- Modify: `PRODUCT_DESIGN.md`
- Modify: `COMMUNITY.md`
- Test: `tests/Integration/Core/ImladrisRuntimeAssetTest.php`

**Interfaces:**

- Produces a stable, reviewed source mirror and authoritative route/rail ownership text.
- Preserves newer Imladris tokens/components by comparing bundle snapshots rather than overwriting them.

- [x] Add a failing runtime-asset/provenance test for the member-surface source map and source archive digest.
- [x] Copy the reviewed `.dc.html`, decision cards, and PNG references into their owning design-system directories; adapt the handoff README to identify the source archive and production reconciliation.
- [x] Compare every bundled token file with current source. Apply only missing member-surface selectors/tokens that are compatible with newer source; record intentional differences.
- [x] Update `PRODUCT_DESIGN.md` and `COMMUNITY.md` with the approved topbar/board-rail ownership and retained standalone `/feed` decision.
- [x] Run the focused provenance and design-surface digest tests green and refresh only `config/imladris-design-baseline.json` with `--print-design-digest`.
- [x] Stage only the mirror/spec/product-contract files and commit `docs: adopt the member surfaces handoff`.

---

### Task 2: Make the shared shell server-owned and first-paint correct

**Files:**

- Modify: `src/Support/PreferenceSchema.php`
- Modify: `src/Service/PreferenceService.php`
- Modify: `src/Service/NavigationService.php`
- Create: `src/Service/PresenceService.php`
- Modify: `src/Controller/PresenceController.php`
- Modify: `src/Controller/SettingsController.php`
- Modify: `src/Repository/ThreadUserRepository.php`
- Modify: `src/Core/App.php`
- Modify: `templates/layout.php`
- Modify: `templates/partials/topbar.php`
- Modify: `templates/partials/sidebar.php`
- Modify: `templates/account/preferences.php`
- Test: `tests/Unit/Preferences/PreferenceSchemaTest.php`
- Create: `tests/Integration/Core/AppMemberShellTest.php`
- Modify: `tests/Integration/Core/AppPresenceTest.php`

**Interfaces:**

- Adds managed reading preferences `rail_open`, `inbox_reading_open`, `directory_sort`, and `directory_peek`; increments the schema version without deleting unknown keys.
- Adds `ThreadUserRepository::unreadCountsByBoard(...)`, one read-gated aggregate matching Inbox unread/mute semantics.
- Adds `PresenceService::roster(?User $viewer): array`, shared by server shell and JSON polling.
- Adds `POST /settings/member-surfaces` for validated preference changes and a safe local return path.

- [x] Add failing schema tests for defaults, coercion, version upgrade, section updates, unknown-key preservation, and export.
- [ ] Add failing integration tests proving one board-only rail across `/`, `/inbox`, `/search`, and `/compose`; muted boards remain; counts sum to the topbar Inbox pill; private/hidden access does not leak; the shell survives missing tables.
- [x] Add failing presence tests proving guest/server rendering, signed-in self/block exclusion, privacy exclusion, feature-dark behavior, and JSON parity.
- [ ] Add failing navigation/persistence tests for Boards/Inbox/Messages active state, identity-menu destinations, New topic suppression on Compose, POST toggle CSRF, safe return validation, and server-first pane classes.
- [x] Implement the preference schema/service, bulk unread aggregation, presence service, routes/bindings, and defensive global sharing.
- [x] Rebuild the topbar and sidebar partials with real asset/icon partials and semantic landmarks.
- [ ] Re-run the focused unit/integration group green and commit `feat: establish the member surface shell`.

---

### Task 3: Implement the place-oriented Board Index

**Files:**

- Modify: `src/Controller/HomeController.php`
- Modify: `src/Service/NavigationService.php`
- Modify: `src/Repository/BoardRepository.php`
- Modify: `src/Repository/ThreadRepository.php`
- Modify/read existing: `src/Repository/TagRepository.php`
- Modify/read existing notification/follow repositories used by Home panes
- Modify: `templates/home.php`
- Create: `templates/partials/directory_board_row.php`
- Modify: `tests/Integration/Core/AppForumIndexDesignTest.php`
- Create: `tests/Integration/Core/AppForumIndexViewingTest.php`

**Interfaces:**

- `GET /?pane=boards|tags|notices|connections&sort=category|active|newest|unanswered|top|solved&peek=0|3|5`.
- Adds one read-gated board-directory query returning board facts, ranked signal, and at most five topic peeks without per-board queries.
- Uses stored sort/peek only when the query omits them; authenticated submissions persist through Task 2's POST route.

- [x] Add failing tests for the hero/tabs/totals, allowed query normalization, member preference fallback, guest URL state, category-vs-ranked grouping, all six sort orders, all three peek sizes, and absence of personal row signals.
- [x] Add failing visibility tests proving totals and topic peeks reveal only policy-listed/readable boards and content.
- [x] Add failing pane tests for read-gated tag catalog, notification actions, follower/following lists, feature gates, and guest states.
- [x] Implement one bounded repository query for directory facts/peeks and the controller's validated view model.
- [x] Render the approved Board Index anatomy with canonical links and existing notification/follow actions.
- [x] Re-run the focused index tests green and commit `feat: transfer the board index surface`.

---

### Task 4: Separate Inbox scope from order and bound the reading preview

**Files:**

- Modify: `src/Repository/ThreadUserRepository.php`
- Modify: `src/Controller/InboxController.php`
- Modify: `src/Core/App.php`
- Create: `src/Controller/InboxPreviewController.php`
- Create: `src/Controller/InboxBulkController.php`
- Modify: `templates/inbox.php`
- Create: `templates/partials/inbox_thread_row.php`
- Create: `templates/partials/inbox_preview.php`
- Modify: `tests/Integration/Core/AppThreadStateTest.php`
- Create: `tests/Integration/Core/AppInboxMemberSurfaceTest.php`

**Interfaces:**

- `ThreadUserRepository::inbox(userId, scope, order, ...)` and `countInbox(...)` use independent validated axes; pinned leads for activity/newest/commended.
- `GET /inbox/preview/{id}` returns only a read-gated HTML fragment for the selected topic.
- `POST /inbox/bulk` accepts current-view thread IDs plus one allow-listed action and redirects to the current scope/order.
- Existing single-topic POST routes remain canonical; bulk operations call their owning services in a transaction-safe, authorization-checked loop.

- [x] Add failing repository tests for the 12 scopes crossed with all three orders, pinned-first behavior, deterministic ties, commend counts, pagination, feature-disabled scopes, and mute/snooze parity with unread totals.
- [x] Add failing route/template tests for `scope`, `order`, `page`, legacy `filter` normalization, count labels, canonical links, row/menu/sweep forms, no duplicate IDs, and precise empty states.
- [x] Add failing preview tests for public/private/hidden access, deleted/pending content, anonymity, bounded replies, canonical link, locked/reply permission, and absence of full topic tools.
- [x] Add failing bulk tests for CSRF, invalid/missing IDs, cross-view/cross-board access, suspended accounts, partial authorization, read/unread/star/snooze actions, and view-scoped redirects.
- [x] Implement the query split, preview controller/template, and service-backed bulk endpoint.
- [x] Re-run the focused Inbox group green and commit `feat: transfer the forum inbox surface`.

---

### Task 5: Extend the replaceable Search seam

**Files:**

- Create: `src/Search/SearchQuery.php`
- Modify: `src/Search/SearchService.php`
- Modify: `src/Search/MysqlSearchService.php`
- Modify: `src/Controller/SearchController.php`
- Modify any SearchService call sites to construct the options object
- Modify: `templates/search.php`
- Modify: `tests/Integration/Core/AppSearchTest.php`
- Modify: `tests/Unit/Search/MysqlSearchServiceTest.php` if present, otherwise add focused integration coverage

**Interfaces:**

- `SearchQuery` validates query, scope (`everything|topics|replies|mine`), order (`relevance|newest`), and limit.
- `SearchService::search(SearchQuery $query, ?User $viewer): array` remains replaceable and read-gated.
- Results add `created_at` and `author_id` only as internal presentation fields; URLs/snippets remain safe and stable.

- [x] Add failing tests for every scope/order combination, guest `mine`, read gating, limit-before/after union correctness, deterministic newest/relevance ties, short queries, escaped snippets, and URL fragments.
- [x] Add failing page tests for query retention, active controls, count line, result kinds, engraved-well validation, and empty/initial states.
- [x] Implement the immutable query object, update the MySQL union/ranking path without reused placeholders or bound limits, and update call sites.
- [x] Render the approved Search surface and re-run focused tests green.
- [x] Commit `feat: transfer the search surface`.

---

### Task 6: Make Compose a real top-level destination

**Files:**

- Modify: `src/Controller/PostController.php`
- Modify: `src/Core/App.php`
- Modify: `templates/compose.php`
- Modify: `templates/partials/sidebar.php`
- Modify: `tests/Integration/Core/AppComposerShellTest.php`
- Add authorization cases to the nearest write-gate/board-policy integration test

**Interfaces:**

- Adds `GET /compose?board=<slug-or-id>`.
- Adds a controller view model containing every policy-listed board plus `can_post`, selected board, anonymity support, and resolved reading preferences.
- POST validation reuses the same view model and preserves title/body/board/anonymity.

- [x] Replace the old intentional-404 assertion with a failing GET contract for guest redirect, active member render, selected-board normalization, readable-but-disabled boards, private membership, archived board, authority mode parity, and suspended/banned/deactivated accounts.
- [x] Add failing 422 tests for a title shorter than three characters, invalid board, preserved draft text, board selection, error focus, CSRF, idempotency, anonymity, and exactly one shared composer instance.
- [x] Add failing markup tests for rail/select synchronization hooks, no New topic topbar action, Cancel, draft status copy, and no prototype toast replacing canonical navigation.
- [x] Implement the GET route and one shared `composeBoards()` view-model builder used by GET and 422.
- [x] Rebuild the Compose template around the production composer shell and re-run focused tests green.
- [x] Commit `feat: transfer the compose surface`.

---

### Task 7: Add strict-CSP interactions and source-owned presentation

**Files:**

- Modify: `docs/design-system/imladris/components.css`
- Modify: `public/assets/app.css`
- Modify: `public/assets/app.js`
- Generated by `composer build:imladris`: `resources/imladris/*`, `public/assets/imladris.css`, manifests
- Modify: `tests/Integration/Core/AppImladrisFidelityTest.php`
- Modify: `tests/Integration/Core/AppImladrisFidelityHighImpactTest.php`
- Create: `tests/browser/member-surfaces.spec.ts`

**Interfaces:**

- Enhances panel preference forms, `Ctrl/Cmd+B`, `Ctrl/Cmd+J`, search shortcut, mobile viewing sheet, Inbox scope/menu/cursor/sweep/row actions, preview swaps, and Compose rail/select synchronization.
- Keeps every canonical link/form usable with JavaScript disabled.

- [ ] Add failing source/runtime tests for semantic-token-only selectors, real asset references, no inline code, the 62px topbar, 272px rail, 1280px reading-pane floor, focus/reduced-motion contracts, and generated-source parity.
- [ ] Add failing browser interactions for keyboard suppression, menu Escape/focus return, shift selection, scroll dismissal, panel persistence, preview/canonical fallback, Compose picker synchronization, mobile sheet location, and no-JavaScript flows.
- [ ] Implement source-owned shared/member-surface CSS; build generated Imladris assets.
- [ ] Implement progressive enhancement in `app.js` with no authorization decisions or duplicated Markdown rendering.
- [ ] Run focused PHP and browser checks green and commit `feat: finish member surface interactions`.

---

### Task 8: Perform same-viewport design QA and refresh reviewed evidence

**Files:**

- Modify: `tests/browser/member-surfaces.spec.ts`
- Create/refresh: `docs/evidence/member-surfaces-production/{reference,desktop,mobile,comparisons}/*`
- Create: `docs/evidence/member-surfaces-production.md`
- Create at project root: `design-qa.md`
- Modify only after pass: `config/imladris-runtime-baseline.json`

**Interfaces:**

- Captures `/`, `/inbox`, `/search?q=rollback`, and `/compose` at each reference PNG's exact dimensions plus 390×844 mobile.
- Produces combined two-column source/production comparison images used for visual judgment.

- [ ] Read the Product Design QA rubric and record exact source-image dimensions/states.
- [ ] Prepare a throwaway browser database and deterministic dynamic fixtures that exercise all visible anatomy without copying fixture identities into production code.
- [ ] Capture light/twilight, desktop/mobile, JavaScript/no-JavaScript screenshots; assert Axe serious/critical, console error/warning, overflow, landmarks, target sizes, and focus behavior.
- [ ] Build one combined comparison input per surface with the source and production screenshot at the same viewport. Inspect typography, spacing, crop, alignment, borders, radii, density, states, and responsive changes.
- [ ] Fix every visible defect through a new failing assertion where practical; re-capture and compare until the QA result is `passed`.
- [ ] Write `design-qa.md` and the evidence report with exact commands, SHAs, measured results, comparisons, and intentional dynamic-data differences.
- [ ] Print and review the application digest, update only the reviewed baseline fields, then run `composer verify:imladris` and `npm run check:wysiwyg` green.
- [ ] Commit `test: verify the member surface transfer`.

---

### Task 9: Immutable verification and production release

**Files:**

- No new implementation files unless a verification failure requires a tested fix.
- Deployment evidence may update the existing member-surface evidence report after live checks.

- [ ] Lint every changed PHP file; syntax-check changed JavaScript/TypeScript; run `git diff --check`.
- [ ] Run all focused member-surface PHPUnit classes, then `php vendor/bin/phpunit`.
- [ ] Run `composer build:imladris`, `composer verify:imladris`, and `npm run check:wysiwyg`.
- [ ] Run the focused member-surface Playwright suite twice, plus existing shell/composer/inbox/thread regressions, with no shared-fixture delta.
- [ ] Confirm `git diff --name-only origin/main...HEAD -- database/migrations/ wrangler.jsonc worker Dockerfile` is empty.
- [ ] Commit any evidence-only finalization, then rerun the full immutable gate against a clean tree and record the exact SHA.
- [ ] Push `codex/member-surfaces-production-transfer` and use the repository's GitHub merge path to merge the verified head to `main` without touching the user's dirty primary checkout.
- [ ] Confirm the merge commit's Cloudflare Workers deployment succeeds and serves the exact SHA.
- [ ] Verify `https://forum.candidary.online/healthz`, `/`, `/inbox`, `/search`, `/compose`, asset hashes, HTML shell markers, authenticated behavior where available, and responsive visual state in the chosen browser.
- [ ] Append remote merge/deploy/live evidence to the report; separately identify any gate that could not be independently verified.

## Completion condition

The four approved member surfaces and shared shell are dynamic, policy-correct, progressively enhanced, visually matched, merged, deployed, and verified live at one immutable SHA. Local tests, merge status, deployment status, and live behavior are reported as separate evidence layers.
