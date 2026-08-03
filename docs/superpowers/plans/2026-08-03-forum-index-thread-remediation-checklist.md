# Forum Index and Thread Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close every confirmed Forum Index, board, and canonical-thread review finding, turn the source-derived risks into reproducible tests, and preserve the presentation and security contracts that already pass.

**Architecture:** Keep the server-rendered PHP document, capability gates, POST/CSRF forms, and privacy rules authoritative. Fix each finding in a small red/green slice, use progressive-enhancement JavaScript only for interaction state, and use route-scoped application CSS over the generated Imladris foundation. A finding reaches Done only after focused PHP tests, browser behavior, accessibility scans, inspected evidence, and final-SHA verification agree.

**Tech Stack:** PHP 8.2+, MySQL/MariaDB, PHPUnit, server-rendered PHP templates, strict-CSP vanilla JavaScript, unlayered application CSS, Playwright 1.61.1, Axe 4.12.

## Scope and audit baseline

- Audit target: `main@3d317c770be49b5166f6d3663d3e6c5136216409`.
- Raw audit evidence: `output/playwright/forum-index-thread-audit-2026-08-03-3d317c7/`.
- Interaction metrics: `interaction-results.json`.
- Accessibility results: `a11y-results.json`.
- This tracker is a companion to, and does not overwrite, `docs/superpowers/plans/2026-08-03-thread-content-presentation-remediation.md`.
- This plan covers the Forum Index, individual-board surface, canonical thread, and the same thread markup inserted into the Community Inbox. It does not authorize deployment, migration, merge, or unrelated visual redesign.

## Tracker rules

Use these exact states in the dashboard:

- **Not started** — no implementation branch or owner has begun the slice.
- **Decision required** — an enumerated product choice must be recorded before behavior changes.
- **Validation required** — a source/coverage risk needs a failing reproduction before behavior changes.
- **In progress** — an owner and branch/PR are recorded and the failing test exists.
- **Blocked** — a named dependency or decision prevents the next step; record it in the finding section.
- **Ready for verification** — implementation and focused tests are green, but final evidence or review is incomplete.
- **Done** — every finding-level acceptance box and the global closeout gate are checked on the recorded final SHA.

Do not mark a row Done because code landed. Update Owner, PR/commit, and Evidence as the work progresses.

## Progress dashboard

| ID | Severity | Surface | Finding | State | Owner | PR/commit | Evidence |
|---|---|---|---|---|---|---|---|
| FT-01 | Blocker | Thread, no JS | Fixed-height layout collapses the reading pane and places Topic tools after the composer | Ready for verification | Unassigned | Working tree (uncommitted) | Measured no-JS at 1266×854: reading share **1.41% → natural flow** (`overflowY: visible`, `innerOverflow: false`); JS-on control unchanged at 477px/55.85%; `#p{id}` deep link still lands. Full PHPUnit 2437 green. **Step 5 refuted — see note below** |
| FT-16 | High | Test harness | Six a11y call sites clicked a `<summary>` that `.has-js` hides by design, timing out before the axe scan ran | Ready for verification | Unassigned | Working tree (uncommitted) | Root cause `6a06cb5` + `app.css:4883`; fixed in `a11y.spec.ts`; **28/28 axe tests now pass and the five previously-unscanned surfaces report zero serious violations**. 11 identical stale call sites remain in 4 other specs — see note |
| FT-02 | High | Thread controls | Topic tools lets Tab escape after the final closed summary | Not started | Unassigned | None | Audit `interaction-results.json` |
| FT-03 | High | Board/thread identity | Uploaded avatars and the avatar preference do not reach every identity render path | Not started | Unassigned | None | Audit captures 05, 12, and 20 |
| FT-04 | High | Thread accessibility | Staff badge contrast fails and overflowing code blocks are not keyboard-focusable | Ready for verification | Unassigned | Working tree (uncommitted) | RED proof: staff pair measured 3.55:1 with ground `244,235,207` in *both* registers; GREEN 6.25:1 light / 8.3:1 dark. `thread-view-study.spec.ts` + `rich-content.spec.ts` green desktop+mobile; full PHPUnit 2436 green. Nine-scan closeout still blocked — see note below |
| FT-05 | High | Board mobile | Follow board and New topic are 38px tall instead of at least 44px | Not started | Unassigned | None | Audit `08-board-general-member-mobile.png` |
| FT-06 | High | Board mobile | Fixed New topic action overlaps the final topic row and title | Not started | Unassigned | None | Audit `interaction-results.json` |
| FT-07 | Medium | Board navigation | Active board rail link lacks `aria-current="page"` | Not started | Unassigned | None | Audit `interaction-results.json` |
| FT-08 | High | Board authorization | Suspended members are offered a Follow control that the write service rejects | Not started | Unassigned | None | Source-confirmed; RED test required |
| FT-09 | Medium | Thread mobile | Final-post action menu opens below the scroll-pane boundary | Not started | Unassigned | None | Audit captures 28 and 29 |
| FT-10 | High | Thread stream | Grouping can cross a UTC day divider or a special-post boundary | Not started | Unassigned | None | Source-confirmed; RED test required |
| FT-11 | Medium | Thread pagination | “Opened by” disappears when the OP is not loaded on the current page | Not started | Unassigned | None | Source-confirmed; RED test required |
| FT-12 | High | Signatures | Current plain-text implementation misses the locked rich, height, age/post, and moderation contract | Decision required | Unassigned | None | `DECISIONS.md` and `USER.md` |
| FT-13 | Medium | Thread composer | Desktop dock consumes 29.8% of a 1266×854 viewport; no approved budget exists | Decision required | Unassigned | None | Audit `interaction-results.json` |
| FT-14 | Medium | Board resilience | Compact density, large text, and long/localized rows lack natural-growth proof | Validation required | Unassigned | None | Source risk; RED test required |
| FT-15 | Medium | Forum Index coverage | Empty and private-member policy-filtered totals lack direct regression coverage | Validation required | Unassigned | None | Coverage gap |

## Global constraints

- Authority order remains `DECISIONS.md` → `DESIGN.md` → `SCHEMA.md` and migrations → `USER.md` / `ADMIN.md` / `COMMUNITY.md` / `COMPOSER.md`.
- Use Blocker/High/Medium/Low for this tracker. Do not use `P0`–`P3` as phase labels; those are MoSCoW priority tiers in this repository.
- Preserve one authoritative server-rendered copy of every form. Every write remains POST + CSRF and keeps its current service authorization.
- Account state beats role. Anonymous rendering must suppress real name, title, reputation, avatar, and signature.
- Do not introduce inline script/style, `unsafe-inline`, client-owned write state, a GET mutation, a dependency, or prototype runtime code.
- Do not edit generated `public/assets/imladris.css` directly. Change `resources/imladris/` and rebuild when the generated foundation must change.
- Keep the Forum Index a quiet policy-filtered directory, the Inbox a personal queue, and the board list fixed to pinned → latest activity → id.
- Preserve unrelated dirty and untracked files. Stage with an explicit allowlist if commits are later authorized.
- UI-visible completion requires PHPUnit plus browser evidence under `DESIGN.md` §13.

## Dependencies and execution order

1. Land or deliberately reconcile the existing thread-content-presentation work before editing the same final CSS rules or browser evidence.
2. Complete FT-01 and FT-02 first; they restore the foundational no-JS and keyboard contracts.
3. Complete FT-03 and FT-04 next; identity and accessibility changes affect most later screenshots.
4. Complete FT-05 through FT-09 as the board/mobile interaction wave.
5. Complete FT-10 through FT-12 as the thread semantic-contract wave.
6. Resolve FT-13, then run FT-14 and FT-15 coverage hardening.
7. Run the global closeout only from one immutable final SHA.

## Decisions to record before implementation

### D-01: Avatar preference scope

- [ ] Adopt the recommended scope: `show_avatars` hides avatars in reading/list/composer content, including the participant stack, but the signed-in member’s own global topbar identity remains visible and uses its resolved image.
- [ ] If a different scope is selected, update `USER.md` §4.2 and the existing `AppReadingPreferencesTest` topbar expectation before FT-03 implementation.

### D-02: Signature presentation constants

- [ ] Adopt the locked unlock rule from `DECISIONS.md` §5 #5: signatures unlock after 10 posts **or** 3 account days.
- [ ] Approve a 140px rendered-height cap as the implementation constant, or record a different exact pixel value in `USER.md` before coding.
- [ ] Adopt the recommended grouping rule: show the signature on the first full post in a same-author run and suppress it on `post-grouped` continuations. If repetition is preferred, record that choice in `USER.md`.
- [ ] Confirm that the existing 500-character cap remains the canonical Markdown-source cap.

### D-03: Desktop composer budget

- [ ] Choose one disposition and record it in the FT-13 row: accept the measured 29.8% dock as intentional, or approve a compact idle dock capped at 220px at 1266×854 with expansion on editor focus.
- [ ] If compact idle is approved, require at least 520px of visible thread reading area at 1266×854 while the editor is idle.

---

### Task 1: Restore the canonical no-JavaScript document flow (FT-01)

> **Implementation note (2026-08-03) — Step 5 was not implemented as written.**
>
> Nesting `partials/thread_restructure` inside the Topic management section makes the
> split/merge dialog a descendant of `[data-topic-tools]`. `app.js:271` calls
> `setTopicTools(root, false)` before showing the dialog, and `app.js:251` sets
> `tools.hidden = true` — so opening split/merge hides its own ancestor. The dialog,
> its close button and its scrim all go `display:none` while `body.thread-restructure-open`
> keeps the page scroll-locked; `visible()` (`app.js:216`) then returns false, so both
> the Escape handler (`app.js:443`) and the Tab trap (`app.js:460`) skip it. The result
> is an invisible, undismissable modal. Five existing browser tests are genuine guards
> against this and were left unchanged.
>
> Step 5's premise was also inaccurate: there is no duplicate render to remove. Both
> partials had exactly one call site each (`thread.php:258` and `:268`).
>
> **What shipped instead:** both partials moved into `.thread-scroll` beneath
> `.thread-study-head` as *siblings*. The existing `[data-thread-restructure-open]`
> button stays in the management section; `app.js:350` resolves the dialog by
> root-scoped `querySelector`, so opener and dialog need only share the
> `[data-thread-study]` article, not be nested.
>
> **Two additions Step 6 did not specify, both proven necessary:**
> 1. The height rule has an `@media (max-width: 860px)` twin at `app.css:1889`. Gating
>    only the desktop rule raises its specificity above the twin, silently discarding
>    the mobile offset *and* leaving the collapse intact at 390×844. Verified: with only
>    the desktop rule gated, the desktop project passes and the mobile project fails on
>    `conversationClipped` (750px column over 2227px of content).
> 2. Gating the scroller behind a deferred attribute breaks `#p{id}` deep links —
>    `Controller::threadRedirect()` sends reply, edit, accepted-answer, moderation,
>    memory and notification click-through there. The browser resolves the fragment
>    against the document scroller before `app.js` runs, then `.thread-scroll` is born
>    at `scrollTop 0`. `app.js` now re-resolves the fragment once, after enhancement.
>
> The `.thread-scroll` rule deliberately carries **no** `body[data-route="thread"]`
> prefix: the Community Inbox injects the same markup under `data-route="inbox"`, where
> `.thread-scroll` is the only scroll surface (`.inbox-reading.is-open` is
> `overflow: hidden`). `app.css:1884-1886` and `:1895-1900` were left untouched.

**Files:**

- Modify: `templates/thread.php`
- Modify: `templates/partials/thread_tools.php`
- Modify: `public/assets/app.css`
- Test: `tests/Integration/Core/AppThreadViewStudyTest.php`
- Test: `tests/browser/imladris-forum-surfaces.spec.ts`

**Interfaces:**

- Produces: Topic tools immediately after the topic head in server document order.
- Produces: split/merge as the single native disclosure inside Topic management.
- Preserves: one copy of every authorized form and the enhanced desktop drawer/mobile sheet.

- [ ] **Step 1: Add a failing server-rendered order test.** Create `test_no_js_document_order_keeps_tools_beneath_head_and_each_form_once()`. Assert the positions are `thread-study-head < topic-tools < post-stream < thread-dock` and each authorized action appears exactly once.
- [ ] **Step 2: Strengthen the failing no-JS browser test.** In both projects, assert `.thread-scroll` has natural height, `scrollHeight <= clientHeight + 1`, Topic tools is reachable by normal page scrolling, native disclosures open, and a real reply POST succeeds.
- [ ] **Step 3: Run RED.**

```powershell
php vendor/bin/phpunit tests/Integration/Core/AppThreadViewStudyTest.php --filter no_js_document_order
Set-Location tests/browser
npx playwright test imladris-forum-surfaces.spec.ts --grep "no-JavaScript"
```

Expected: the order assertion reports Topic tools after the dock, and the browser reports the 12px reading pane / internal overflow.

- [ ] **Step 4: Move the single Topic tools render beneath `.thread-study-head` inside `.thread-scroll`.** Pass the existing data unchanged; remove the detached render after `.thread-dock`.
- [ ] **Step 5: Render `partials/thread_restructure` from the Topic management section and remove its detached copy.** Keep the same form actions, field names, CSRF fields, validation state, and data hooks.
- [ ] **Step 6: Scope the fixed-height conversation and internal scroller to enhanced instances.** Move `flex: 1 1 auto`, `min-height: 0`, and `overflow-y: auto` off the unenhanced base scroller; the winning selector must follow this shape:

```css
body[data-route="thread"] [data-thread-enhanced="1"].thread-conversation {
    height: calc(100dvh - var(--topbar-h) - 48px);
}
[data-thread-enhanced="1"] > .thread-scroll {
    min-height: 0;
    overflow-y: auto;
}
```

The unenhanced thread remains natural document flow.

- [ ] **Step 7: Run GREEN** for the focused PHP and Playwright tests, then rerun the existing enhanced drawer/sheet and Inbox-insertion cases.
- [ ] **Step 8: Capture and inspect** desktop and mobile no-JS full-page evidence. Record the new paths in FT-01.

**Acceptance:**

- [ ] Reading share is no longer 1.4%; `.thread-scroll` is not a 12px nested scroller without JavaScript.
- [ ] Topic tools and split/merge are reachable once in logical order.
- [ ] A real no-JS reply submits on desktop and mobile.
- [ ] Enhanced drawer/sheet, modal, Escape, scrim, focus return, and Inbox insertion remain green.

### Task 2: Make Topic tools focus containment deterministic (FT-02)

**Files:**

- Modify: `public/assets/app.js`
- Test: `tests/browser/thread-view-study.spec.ts`
- Test: `tests/browser/community-inbox-theme.spec.ts`

**Interfaces:**

- Consumes: the existing visible-focusable filter and `setTopicTools()` lifecycle.
- Produces: cyclic Tab/Shift+Tab movement within the topmost visible drawer or modal.

- [ ] **Step 1: Extend the existing desktop test to traverse more than one complete focus cycle.** Record every active element and assert `dialog.contains(document.activeElement)` after each Tab and Shift+Tab, including the closed Topic management summary.
- [ ] **Step 2: Repeat one full cycle in an Inbox-inserted topic.**
- [ ] **Step 3: Run RED.**

```powershell
Set-Location tests/browser
npx playwright test thread-view-study.spec.ts community-inbox-theme.spec.ts --grep "Topic tools|Inbox-inserted"
```

Expected: forward Tab leaves the panel after Topic management.

- [ ] **Step 4: Replace endpoint-only trapping with explicit cyclic movement.** After building the visible focusable list, calculate the current index, focus the next/previous item modulo the list length, and always prevent the native Tab when the modal/drawer owns focus:

```javascript
var current = focusable.indexOf(document.activeElement);
var next = event.shiftKey
    ? (current <= 0 ? focusable.length - 1 : current - 1)
    : (current < 0 || current === focusable.length - 1 ? 0 : current + 1);
focusable[next].focus();
event.preventDefault();
```

- [ ] **Step 5: Run GREEN** in canonical desktop/mobile and Inbox contexts.
- [ ] **Step 6: Recheck initial focus, Escape, close button, scrim dismissal, exclusive accordion state, and trigger-focus return.**

**Acceptance:**

- [ ] Repeated Tab and Shift+Tab never reach `BODY`, the skip link, or background controls while Topic tools is visible.
- [ ] The split/merge modal remains the outermost focus owner when open.

### Task 3: Propagate avatar sources and honor the reading preference (FT-03)

**Files:**

- Modify: `src/Domain/User.php`
- Modify: `src/Repository/PostRepository.php`
- Modify: `src/Repository/ThreadRepository.php`
- Modify: `templates/partials/post.php`
- Modify: `templates/thread.php`
- Modify: `templates/partials/thread_row.php`
- Modify: `templates/partials/composer_shell.php`
- Modify: `templates/partials/topbar.php`
- Test: `tests/Integration/Core/AppProfileMediaTest.php`
- Test: `tests/Integration/Core/AppReadingPreferencesTest.php`
- Test: `tests/Integration/Core/AppThreadViewStudyTest.php`
- Test: `tests/browser/forum-index-thread-remediation.spec.ts`

**Interfaces:**

- Produces: `User::avatarPath(): ?string`.
- Produces: `author_avatar_path` in post, participant, and board-row read models.
- Preserves: `partials/monogram.php` as the one image-or-monogram renderer.

- [ ] **Step 1: Write failing integration tests.** Seed an uploaded `/media/{id}` avatar and assert it renders in an attributable post, participant list, board row, reply composer identity, and signed-in topbar.
- [ ] **Step 2: Add the privacy/preference matrix.** With `show_avatars=false`, assert no post/list/composer/participant image or monogram is emitted; retain the own-account topbar identity per D-01. Assert an anonymous post never contains the real media path.
- [ ] **Step 3: Run RED.**

```powershell
php vendor/bin/phpunit tests/Integration/Core/AppProfileMediaTest.php tests/Integration/Core/AppReadingPreferencesTest.php tests/Integration/Core/AppThreadViewStudyTest.php
```

- [ ] **Step 4: Select `u.avatar_path AS author_avatar_path` in `PostRepository::listByThread()` and `participantsForThread()`; add the participant field to `GROUP BY` because native MySQL grouping is strict.
- [ ] **Step 5: Select the OP author avatar in `ThreadRepository` board rows.**
- [ ] **Step 6: Add `User::avatarPath()` and pass it to the topbar and current-user composer identity.**
- [ ] **Step 7: Pass `avatar_path` to `partials/monogram` only when the public author is attributable and the relevant reading preference is on.** Wrap the participant images in the same preference; retain a text-only participant count if the fact is still shown.
- [ ] **Step 8: Verify fallback.** A removed/missing upload must render the monogram. Record a separate disposition in FT-03 for OAuth/Gravatar: implement their existing stored source, or document the optional source as an owned contract deferral rather than claiming the full `USER.md` §5.2 chain.
- [ ] **Step 9: Run GREEN** and capture avatar-on, avatar-off, and anonymous states at desktop/mobile.

**Acceptance:**

- [ ] Uploaded image count is nonzero on every attributable in-scope identity path.
- [ ] Avatar-off removes every reading-surface/list/participant avatar.
- [ ] Anonymous markup contains no real image URL.
- [ ] Removed or unavailable media falls back to a monogram without a broken image.

### Task 4: Clear the two serious Axe failures (FT-04)

**Files:**

- Modify: `resources/imladris/components.css`
- Modify: `public/assets/app.css`
- Modify through the asset builder: `public/assets/imladris.css`
- Modify: `src/Support/HtmlSanitizer.php`
- Test: `tests/Unit/SanitizationTest.php`
- Test: `tests/Unit/Composer/MarkdownRoundTripTest.php`
- Test: `tests/browser/rich-content.spec.ts`
- Test: `tests/browser/thread-view-study.spec.ts`

- [ ] **Step 1: Add failing unit assertions** that sanitized fenced code emits `<pre tabindex="0" role="region" aria-label="Scrollable code block">`.
- [ ] **Step 2: Add failing browser assertions** that the Staff badge has contrast ≥4.5:1 in both themes and that keyboard focus plus ArrowRight changes `scrollLeft` on the narrow overflowing code block.
- [ ] **Step 3: Run RED.**

```powershell
php vendor/bin/phpunit tests/Unit/SanitizationTest.php tests/Unit/Composer/MarkdownRoundTripTest.php
Set-Location tests/browser
npx playwright test rich-content.spec.ts thread-view-study.spec.ts
```

- [ ] **Step 4: Change the Staff badge foreground from `var(--gold-700)` to `var(--gold-800)` in the source component and the final application override.** Rebuild the generated Imladris asset; do not hand-edit it.
- [ ] **Step 5: Teach `HtmlSanitizer` to add the fixed safe accessibility attributes to `pre` after stripping untrusted attributes.** Do not preserve author-supplied ARIA or tabindex values.
- [ ] **Step 6: Run GREEN**, rebuild/verify Imladris assets, and rerun all nine audit-equivalent scans.

**Acceptance:**

- [ ] Staff badge contrast is at least 4.5:1 in light/dark and desktop/mobile.
- [ ] The code block is keyboard-focusable and horizontally keyboard-scrollable.
- [ ] Every audit-equivalent scan reports zero serious or critical violations.

### Task 5: Repair mobile board controls and current-route semantics (FT-05, FT-06, FT-07)

**Files:**

- Modify: `public/assets/app.css`
- Modify: `templates/partials/sidebar.php`
- Test: `tests/Integration/Core/AppBoardIdentityDesignTest.php`
- Test: `tests/browser/imladris-forum-surfaces.spec.ts`

- [ ] **Step 1: Add failing browser measurements** for Follow board/New topic width and height at 390×844, FAB/final-row rectangle intersection, final-title focus visibility, and document overflow.
- [ ] **Step 2: Add a failing integration assertion** that only the active `/c/{slug}` rail link receives `aria-current="page"`.
- [ ] **Step 3: Run RED.**
- [ ] **Step 4: Remove the late 38px override.** Set `.board-view .board-identity-actions .btn` to `min-height: 44px` while preserving Follow → New topic order.
- [ ] **Step 5: Add board-scoped mobile bottom clearance** at least equal to the 56px FAB plus its 22px edge and one spacing unit, including `env(safe-area-inset-bottom)`, so the last topic can scroll fully clear of the fixed control.
- [ ] **Step 6: Emit `aria-current="page"` on the active board rail anchor.**
- [ ] **Step 7: Run GREEN** at 390, 430, 680, 681, 800, and 860px; recheck visible focus and no-JS native New topic access.

**Acceptance:**

- [ ] Both mobile header actions measure at least 44×44 CSS pixels.
- [ ] The FAB intersects neither the final row nor its title at the tested end position.
- [ ] The active board is programmatically current, with no false current link.
- [ ] No horizontal overflow or action-order regression.

### Task 6: Remove the suspended-member dead Follow control (FT-08)

**Files:**

- Modify: `src/Controller/BoardController.php`
- Test: `tests/Integration/Core/AppBoardIdentityDesignTest.php`
- Test: `tests/Integration/Core/AppWriteGateTest.php`

- [ ] **Step 1: Add a failing GET test.** A suspended member can read `/c/{slug}` but the response contains no board-follow POST form.
- [ ] **Step 2: Add/retain the direct POST test.** Posting to `/b/{id}/follow` as that member is rejected by `WriteGate` and does not mutate follow state.
- [ ] **Step 3: Run RED** and confirm only the GET affordance assertion fails.
- [ ] **Step 4: Include `WriteGate::canWrite($user)` in `$canFollowBoard` before the repository lookup.** Do not weaken `FollowService::toggleTarget()`.
- [ ] **Step 5: Run GREEN** for active, suspended, flag-off, follow, and unfollow cases.

**Acceptance:**

- [ ] Suspended members retain read access but receive no dead Follow control.
- [ ] Active members and feature gating behave exactly as before.

### Task 7: Keep the mobile post menu inside the reading pane (FT-09)

**Files:**

- Modify: `public/assets/app.js`
- Modify: `public/assets/app.css`
- Test: `tests/browser/thread-view-study.spec.ts`

- [ ] **Step 1: Add a failing last-post test.** At 390×844, scroll to the final post, open More post actions, and assert the menu rectangle is fully contained by `.thread-scroll` without a second scroll.
- [ ] **Step 2: Run RED.** Preserve the measured failure: menu bottom 830px versus pane bottom 753px.
- [ ] **Step 3: Add one placement class, `post-menu-up`.** On disclosure open, compare available space above/below inside the nearest scroll pane and toggle the class before paint. Recompute for an open menu on resize/scroll.
- [ ] **Step 4: Add the flipped rule:**

```css
.post-menu.post-menu-up .post-menu-pop {
    top: auto;
    bottom: calc(100% + 8px);
}
```

- [ ] **Step 5: Run GREEN** at the first and last post. Recheck exclusivity, Escape, outside click, keyboard activation, 44px mobile targets, and the unenhanced native `details` fallback.

**Acceptance:**

- [ ] Opening the final menu requires no extra scrolling.
- [ ] Every action remains visible, focusable, and touch-reachable.

### Task 8: Fix grouping boundaries and the paginated opener byline (FT-10, FT-11)

**Files:**

- Modify: `src/Repository/ThreadRepository.php`
- Modify: `templates/thread.php`
- Modify: `templates/partials/post.php`
- Test: `tests/Integration/Core/AppThreadViewStudyTest.php`
- Test: `tests/Integration/Core/AppImladrisFidelityTest.php`
- Test: `tests/browser/forum-index-thread-remediation.spec.ts`

- [ ] **Step 1: Add a failing grouping boundary matrix.** Assert a normal same-author reply within ten minutes groups, then assert grouping resets across UTC day, OP, accepted, wiki, deleted, anonymous, and staff boundaries.
- [ ] **Step 2: Add failing page-2 byline tests** for a named OP and an anonymous OP with enough replies to move the opener off the current page.
- [ ] **Step 3: Run RED.**
- [ ] **Step 4: Give grouping eligibility one owner in `thread.php`.** A row is eligible only when it is non-anonymous, non-OP, non-accepted, non-wiki, non-deleted, and has role `user`. Reset prior author/time state on a day change and after every ineligible row; only compare two eligible rows.
- [ ] **Step 5: Keep the partial’s exclusions as defensive presentation checks, but do not let it silently repair state computed by the parent.**
- [ ] **Step 6: Join the OP post in `ThreadRepository::findWithBoard()` and select `COALESCE(op.is_anonymous, 0) AS op_is_anonymous`.** Render the byline from that stable field instead of scanning the current post page.
- [ ] **Step 7: Run GREEN** and capture the combined grouping/special-state and page-2 states.

**Acceptance:**

- [ ] Grouping occurs only for two eligible same-author replies within ten minutes on the same UTC day.
- [ ] Every special state and day divider breaks the run.
- [ ] Every page renders exactly one correctly masked “Opened by” byline.

### Task 9: Align signatures with the locked account contract (FT-12)

**Files:**

- Modify: `src/Service/AccountService.php`
- Modify: `src/Controller/ThreadController.php`
- Modify: `templates/account/settings.php`
- Modify: `templates/partials/post.php`
- Modify: `public/assets/app.js`
- Modify: `public/assets/app.css`
- Modify: `config/config.php`
- Test: `tests/Integration/Core/AppProfileMediaTest.php`
- Test: `tests/Integration/Core/AppReadingPreferencesTest.php`
- Test: `tests/Unit/SanitizationTest.php`
- Test: `tests/browser/forum-index-thread-remediation.spec.ts`
- Verify existing moderation: `src/Service/UserModerationService.php` and its integration tests

**Interfaces:**

- Stores: canonical Markdown in the existing `users.signature` column; no migration is required.
- Produces: sanitized `author_signature_html` for attributable posts only.
- Consumes: the same `Markdown`/`HtmlSanitizer` pipeline as posts.
- Produces config keys: `limits.signature_max`, `limits.signature_rendered_height_px`, `limits.signature_unlock_posts`, and `limits.signature_unlock_days`, with values fixed by D-02.

- [ ] **Step 1: Complete D-02 and update the authoritative docs with the selected exact height/repetition values before changing behavior.**
- [ ] **Step 2: Add failing service tests** for 9 posts/younger than 3 days rejected; 10 posts accepted; 3-day-old account accepted; 501 characters rejected; a second image rejected; and unsafe Markdown sanitized.
- [ ] **Step 3: Add failing render tests** for nofollow links, one safe image, viewer preference off, anonymous suppression, grouped repetition disposition, and oversize collapse.
- [ ] **Step 4: Add a failing moderation regression** proving staff clear is audited and the signature disappears from later thread renders.
- [ ] **Step 5: Run RED.**
- [ ] **Step 6: Replace the three-plain-text-line rule with the locked unlock gate and Markdown-source validation.** Count at most one Markdown image and rely on the sanitizer for allowed source/attribute enforcement.
- [ ] **Step 7: Render signatures through `Markdown::render()` in the controller display model, never raw in the template.**
- [ ] **Step 8: Render an initially open native `details.post-signature`.** Progressive enhancement measures the configured height; it hides the summary for content within the cap and collapses oversized content behind “Show signature.” With JavaScript absent, the sanitized signature remains readable.
- [ ] **Step 9: Apply the D-02 grouped-repetition rule and retain `show_signatures` plus anonymous suppression.**
- [ ] **Step 10: Run GREEN** in PHP/browser tests and capture ordinary, oversized, image, grouped, preference-off, and anonymous states.

**Acceptance:**

- [ ] Threshold is exactly 10 posts or 3 account days.
- [ ] Signature HTML is sanitized, links are nofollow, and at most one safe image renders.
- [ ] Oversized content has an operable show/hide control; in-cap content has no dead disclosure.
- [ ] Preference, anonymity, grouping, and audited moderation behave as documented.

### Task 10: Resolve the desktop composer footprint (FT-13)

**Files if compact idle is selected:**

- Modify: `public/assets/app.css`
- Modify: `public/assets/app.js`
- Test: `tests/browser/thread-view-study.spec.ts`

- [ ] **Step 1: Complete D-03 and update FT-13 to Accepted or In progress.**
- [ ] **Step 2: Add geometry assertions** for dock height/share and reading-pane height at 1266×854 before changing CSS.
- [ ] **Step 3A: If accepted unchanged,** record the 255px / 29.8% baseline plus inspected screenshot and close FT-13 without a behavior change.
- [ ] **Step 3B: If compact idle is approved,** add a failing test for dock ≤220px and reading area ≥520px while idle, then assert the full editor controls become reachable on focus.
- [ ] **Step 4B: Implement compact/expanded state** with a CSS class toggled by editor focus, without removing fields, changing submit behavior, or hiding validation errors.
- [ ] **Step 5: Verify** typing, keyboard navigation, anti-draft-loss, preview, attachment controls, and mobile composer behavior.

**Acceptance:**

- [ ] The chosen disposition and numeric evidence are recorded.
- [ ] No composer field, error, or submission path regresses.

### Task 11: Add board-density and Forum Index policy matrices (FT-14, FT-15)

**Files:**

- Modify: `tests/Integration/Core/AppForumIndexDesignTest.php`
- Modify: `tests/browser/imladris-forum-surfaces.spec.ts`
- Create: `tests/browser/forum-index-thread-remediation-fixture.php`
- Create: `tests/browser/forum-index-thread-remediation.spec.ts`
- Modify if a RED test proves a defect: `public/assets/app.css` and/or `templates/partials/thread_row.php`

- [ ] **Step 1: Add the Forum Index policy tests.** Cover an empty index, guest totals with public/hidden/private boards, and a private-board member whose listed totals include only boards visible to that viewer.
- [ ] **Step 2: Promote the deterministic audit state into the maintained browser fixture.** Seed long/localized titles, multiple state chips, uploaded avatar, grouped/special posts, code/table overflow, signatures, page 2, and private-member access. Do not depend on `output/` at test runtime.
- [ ] **Step 3: Add board-row geometry/content assertions** in comfortable/compact density, default/large text, avatars on/off, and widths 1266, 860, 800, 681, 680, 430, and 390.
- [ ] **Step 4: Require every title, author, reply count, latest activity value, state chip, and focus target to remain visible and non-overlapping.** Desktop’s seeded row stays 60–72px only when content fits; mobile/large-text rows grow naturally.
- [ ] **Step 5: Add the missing special-state browser assertions.** Positively identify accepted, wiki, Staff, anonymous, deleted, grouped, and day-divider treatments; repeat avatar/signature preference states and the page-2 byline.
- [ ] **Step 6: Run a full-thread Axe scan in JavaScript-on and JavaScript-disabled contexts** in both themes and projects, including open Topic tools and the wide-code state.
- [ ] **Step 7: Run the new tests before CSS changes.** If they pass, close FT-14 as validated without speculative CSS. If they fail, make the smallest board-scoped reset to nowrap/ellipsis/fixed sizing and rerun the unchanged assertions.
- [ ] **Step 8: Verify Forum Index semantics** remain categories/lists with no topic-feed/composer leakage and no hidden-board count disclosure.

**Acceptance:**

- [ ] Long/localized/large-text rows neither clip nor overlap and cause no document overflow.
- [ ] Empty and private-member totals are policy-correct.
- [ ] Forum Index remains distinct from Inbox and Messages.

## Validation queue for source risks not yet promoted to findings

These do not authorize implementation until a failing test or accessibility-tree inspection confirms the issue.

- [ ] **V-01 Board composer semantics:** verify whether the enhanced scrim treatment is intended to be modal. If so, test name, dialog semantics, focus containment, background exclusion, Escape, and focus return.
- [ ] **V-02 Participant naming:** verify each avatar-only participant `li` has an accessible name without depending on the non-normative `title` attribute.
- [ ] **V-03 Staff meaning:** confirm whether moderators and admins should both receive the visible Staff badge; current `mask_author()` semantics must match the approved product meaning before changing it.

## Cross-surface regression matrix

Every applicable cell must have either an automated assertion or an inspected evidence path.

| Axis | Required states |
|---|---|
| Viewer | Guest; active member; topic owner; private-board member; board moderator; admin; suspended member |
| Route | Forum Index; board; canonical thread; Inbox-inserted thread; page 2 |
| Enhancement | JavaScript on; JavaScript off |
| Viewport | 1266×854; 860×800; 800×800; 681×800; 680×800; 430×844; 390×844 |
| Appearance | Light; dark; comfortable; compact; default font; large font |
| Preference | Avatars on/off; signatures on/off; reactions on/off |
| Post state | OP; ordinary; grouped; accepted; wiki; staff; anonymous; deleted; UTC day divider |
| Content stress | Long/localized title; multiple chips; wide code; wide table; oversized signature |

## Preserve these passing contracts

- [ ] Forum Index totals are computed only after `BoardPolicy::isListed()` filtering; hidden/private boards never leak to unauthorized viewers.
- [ ] Forum Index uses semantic categories and lists and contains no topic preview feed or composer.
- [ ] Board identity colors, Follow → New topic order, fixed topic ordering, and single-composer invariant remain.
- [ ] Thread OP, accepted, wiki/staff, deleted, anonymous, and day-divider states remain word-plus-color and readable.
- [ ] Mobile post action targets remain 44×44 and the document remains 390px wide with no horizontal overflow.
- [ ] Initial Topic tools focus, Escape, close/scrim behavior, and focus return continue to pass.
- [ ] All forms remain real POST/CSRF forms with capability and `WriteGate` enforcement.
- [ ] Anonymous rendering suppresses real identity, title, reputation, avatar, and signature.
- [ ] No-JS reading, posting, disclosures, and action forms remain functional.
- [ ] Light and dark Forum Index/board surfaces retain their current clean Axe result.

## Final verification and completion record

- [ ] Run PHP syntax checks for every changed PHP file.
- [ ] Run focused PHPUnit:

```powershell
php vendor/bin/phpunit tests/Integration/Core/AppForumIndexDesignTest.php tests/Integration/Core/AppBoardIdentityDesignTest.php tests/Integration/Core/AppThreadViewStudyTest.php tests/Integration/Core/AppReadingPreferencesTest.php tests/Integration/Core/AppProfileMediaTest.php tests/Integration/Core/AppImladrisFidelityTest.php tests/Unit/SanitizationTest.php tests/Unit/Composer/MarkdownRoundTripTest.php
```

- [ ] Run the full PHP suite with `composer test`.
- [ ] Run `composer verify:imladris`.
- [ ] Run `npm run check:wysiwyg` if composer source or generated assets changed.
- [ ] Add `imladris-forum-surfaces.spec.ts` and `forum-index-thread-remediation.spec.ts` to the maintained evidence command; add the remediation Axe cases to `npm run a11y`.
- [ ] Run focused desktop/mobile Playwright:

```powershell
Set-Location tests/browser
npx playwright test imladris-forum-surfaces.spec.ts thread-view-study.spec.ts rich-content.spec.ts forum-index-thread-remediation.spec.ts
```

- [ ] Run `npm run evidence` and `npm run a11y`.
- [ ] Inspect light/dark desktop/mobile/no-JS captures; do not treat screenshot creation alone as review.
- [ ] Run `git diff --check` and review the exact diff against this checklist.
- [ ] Obtain an independent source review and close every confirmed Critical/Important finding.
- [ ] Record the immutable final SHA, exact command results, evidence paths, reviewer, and completion date in the dashboard/history record.
- [ ] Create `docs/history/forum-index-thread-remediation-2026-08-03.md` only at closeout, mapping every FT ID to change, test, evidence, and final SHA.

## Self-review

- [x] Every dashboard ID maps to an implementation/test section or an explicit decision gate.
- [x] Confirmed findings are not diluted into “monitor” items.
- [x] Source-derived risks require a RED reproduction before behavior changes.
- [x] No task silently changes an authoritative product decision.
- [x] No unrelated dirty or untracked work is included.
