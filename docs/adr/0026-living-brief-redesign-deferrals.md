# ADR 0026: Living Brief redesign — decisions, deferrals, and findings

**Date:** 2026-08-27
**Status:** Accepted and implemented. The design handoff proposed five changes.
Four shipped on branch `worktree-living-brief-redesign` (baseline `b04f4726`) —
§1, §3, §5, and the half of §2 that is buildable here (§2a). Two are deferred
with the evidence a follow-on slice needs: §2's other half (§2b) and §4. The
count does not divide evenly because §2 splits into a shipped half and a
deferred one: four shipped rows in the table below, two deferrals after them.
**Relates to:** ADR 0019 (Thread Intelligence auto-publication — the subsystem
this surface renders), `docs/superpowers/specs/2026-08-26-living-brief-redesign-design.md`
(the design this branch implements, and the authority for what was deferred and
why), `docs/runbooks/thread_intelligence.md` (updated with the new pause route
and the curator-tools relocation), and CLAUDE.md's rule that deferrals are
recorded in an ADR and never silently dropped.

## Context

`design_handoff_living_brief` (from `CommunityForumDesignSystem.zip`) proposed
five changes to the living brief as it renders on a topic page. A verification
pass against the working tree found three of the five resting on premises that do
not hold in this codebase: there are no two stacked status bands to collapse,
"Pause automatic refresh" had no backend, and the confirm step the handoff drew
has no CSP-safe precedent here (`script-src 'self'`, no nonce, zero occurrences
of `data-confirm` or `confirm(` in `templates/` or `public/assets/`).

What shipped is the half of each proposal that is real:

| § | Change |
|---|---|
| §1 | The brief's own `<h2>` is gone; the topic title is the region's visual head, and the region takes an `aria-label` so it keeps an accessible name. |
| §2a | One member-visible status line — *Automatic refresh is paused for this topic. The brief stands as published.* — moved out of the curator-only partial. |
| §3 | Curator tools moved from the topic-tools drawer to the foot of `.living-brief`, one primary action, plus a new Pause route. |
| §5 | The curator gate is enforced inside `partials/thread_memory_tools.php` rather than only at the call site, and the empty state explains eligibility with the real threshold and the real count. |

This ADR records what was **not** built, the two judgement calls the specs were
silent on, and three findings this work surfaced but did not fix.

## Decisions

**D1 — Build the Pause route rather than drop the control.**
The handoff's §3 assumed a pause action. `src/Core/App.php` registered exactly six
memory routes and pause was not among them; `CommunityMemoryService` had
`resumeAutomation()` and no counterpart, and pausing happened only as a side
effect of Retire. Dropping the control would have left Resume with nothing to
undo on a topic a curator had never retired. It now exists:
`POST /t/{id}/summary/automation/pause` → `CommunityMemoryController::pauseAutomation()`
→ `CommunityMemoryService::pauseAutomation()`, an exact mirror of
`resumeAutomation()`: one `db->transaction`, `threads->findForUpdate()`,
`assertCuratorForLockedThread()`, then the already-existing
`ThreadIntelligenceQueue::setAutomationPaused($threadId, true, $actor->id())`.
CSRF is automatic — the kernel enforces it on every POST.

**D2 — The Pause route writes no `moderation_log` row.**
Its audit trail is `thread_intelligence_jobs.paused_by` / `paused_at`, which
`setAutomationPaused()` already persists (columns exist since migration `0077`).
`CommunityMemoryService` contains no `moderation_log` write at all — not for
publish, retire, restore, refresh, resume, or related-topic curation, nor for the
three wiki actions. Adding one only for Pause would make it the single audited
action on a surface where Retire and Resume flip the same flag unaudited.

Recorded honestly, because it is a real asymmetry and not a tidy one: the
**administrator** path to the same operation *is* audited.
`ThreadIntelligenceAdminService::setThreadPaused()` logs
`thread_intelligence_thread_pause` / `_resume` against `target_type = 'thread'`.
So the same underlying flag change is a `moderation_log` row from
`/admin/thread-intelligence` and no row from the topic page. The choice here was
consistency with the curator surface's own siblings over consistency with the
admin console; closing the gap means auditing all seven curator actions
together, which is its own slice and its own evidence pass.

**D3 — The curator empty state is curator-only.**
`partials/living_brief_empty.php` returns early on `empty($can_curate_memory)`,
and `templates/thread.php` gates the call site on the same value. Two reasons.
It preserves an existing guest-facing contract —
`ThreadIntelligenceSurfaceTest` asserts that a guest on a brief-less thread sees
neither `thread-memory-slot` nor `living-brief` — and it matches the state's
stated purpose: after Retire, the curator loses the only route back, so this
panel is where Restore lives.

The consequence, stated plainly: a curator now sees a "No brief yet" panel on
**every** brief-less topic where they hold curator authority, which is more
prominent than the collapsed topic-tools drawer section it replaced. That is a
deliberate trade — the drawer hid first-summary authoring behind two disclosures
— but it is a real increase in what a moderator sees on young topics, and if it
proves noisy the fix is a collapse affordance, not re-hiding Restore.

## Deferrals (owned, not dropped)

**1 — §2b, the "last verified brief" status line.**
The handoff asked to collapse two stacked status bands into one, with last-good
winning over paused. No last-good state exists anywhere in the member view model.
`ThreadIntelligenceViewService::forThread()` returns exactly
`living_brief`, `sources`, `related`, `fallback_related`, `history`, `refresh`,
`automation_paused` — no `last_good`, no `last_verified`, no failed-generation
state. Grepping `src/`, `templates/`, and `public/assets/` for either name
returns nothing; the only occurrences in the tree are a Playwright fixture's
thread-name key (`tests/browser/thread-intelligence-fixture.php`), the spec that
consumes it, and one PHPUnit test *name*
(`ThreadIntelligenceWorkerTest::test_failure_preserves_last_good_…`) — where
"last good" means only that the previously published brief is left alone, not
that anything renders saying so.

Nor were the two bands ever stacked: the paused and ineligible paragraphs sat on
opposite sides of one `if`/`else` and were mutually exclusive. So the handoff's
§2b is unimplementable as written; building it is new server work — surfacing
generation-failure state into the member model, deciding what a member may be
told about a provider failure, and re-deriving a "verified at" timestamp that
does not today exist.

What shipped instead is the half that is real, and the handoff's actual stated
goal: members can now see the brief is frozen. The paused line moved out of the
curator-only partial into `partials/living_brief.php`, styled with a
`.living-brief-status.is-paused` modifier so §2b's variant can be added later
without re-layout.

**2 — §4, footnote citations resolving claims to their source posts.**
The handoff called this "the reason to do this work". It is blocked on Finding F1
below, which is what currently makes §4's ordinal numbering sound. Two
implementation constraints were established while scoping it, both by **executed
check**, so the next slice does not have to rediscover them:

- `[^n]` survives the pipeline as inert literal text. No footnote extension is
  loaded (`src/Support/Markdown.php` registers CommonMark core, Strikethrough,
  Table, TaskList, Autolink, and the app's Spoiler extension, and nothing else),
  so `A claim about the thing [^1].` renders as
  `<p>A claim about the thing [^1].</p>`.
- **But it must carry a leading space.** Appended with no separator to an item
  ending in `!`, `Ship it![^3]` contains `![`, which matches
  `ThreadIntelligenceOutputValidator::UNSAFE_PATTERNS`' Markdown-image-opener
  rule and rejects the **entire generation**, not the one item.
  `Ship it! [^3]` passes every pattern.
- Markup is not a route. Raw `<sup>` and `<a>` are each stopped three ways:
  `UNSAFE_PATTERNS`' `/<[a-z!\/]/i` rejects the generation before rendering;
  the CommonMark environment is `html_input => 'escape'`, so a tag that got
  through would be escaped to text; and after that `HtmlSanitizer::ALLOWED` has
  no `sup` at all, while any rendered `<a …>` is caught by the validator's
  `FORBIDDEN_RENDERED_MARKUP`. Markdown link syntax is separately blocked by
  `/\]\(/`.

**The only viable route is plain text through the generation pipeline, upgraded
to an anchor in the view layer.**

## Findings — surfaced here, not fixed here

**F1 — The source-union mismatch that blocks §4. Code-reading, not an executed repro.**

`thread_intelligence_generations.source_post_ids` is written with the
**evidence-pack** union: every eligible post in the window, plus baseline
(`ThreadIntelligenceEvidenceBuilder` builds it; `ThreadIntelligencePublisher` and
`ThreadIntelligenceWorker` persist `$evidence->sourcePostIds()` /
`$pack->sourcePostIds()`).

`thread_summary_sources` is written with the **citation** union: only what the
model actually cited. `ThreadIntelligencePublisher` inserts one row per
`$output->sourcePostIds()`, documented on `ValidatedThreadIntelligenceOutput` as
*"ascending unique union of every citation"*.

`ThreadIntelligenceViewService::aiSourcesAreCurrent()` compares the two for **set
equality** (`sort()` both, then `$expected === $current`), and `forThread()`
suppresses the entire brief when they differ. But
`ThreadIntelligencePromptBuilder`'s rule 6 only requires *"cite only supplied
post IDs, and cite at least one for every statement group."*

On this reading an AI brief renders only when the model happens to cite every
post in the evidence window. Nothing in the test suite would catch it:
`ThreadIntelligenceWorkerTest::providerResult()` scripts a strict subset
(`[$first, $last]`) but asserts DB rows, not the view; the browser fixture sets
the overview's `source_post_ids` to *every* post, which is what keeps the fixture
brief renderable; and `ThreadIntelligenceSurfaceTest::insertAiBrief()` writes the
*same* id list into both tables by hand, so the mismatch cannot arise there.

**This was established by reading the code. It has not been reproduced by
running anything.** The first task of any §4 slice is therefore an integration
test that publishes a subset-citation brief through the real worker and asserts
what `forThread()` returns. That test decides whether this is a defect or the
intended fail-closed posture.

It cannot be fixed independently of §4. The equality gate is precisely what
guarantees the handoff's ordinal claim: the generation union is sorted ascending
and `sources()` is `ORDER BY p.id ASC`, but `sources()` drops rows *after* the
query (deleted, pending, or unreadable-board posts), and one dropped row shifts
every later ordinal. Equality is what prevents that today. **Relaxing the union
semantics would break the ordinal guarantee, so the two must be designed
together.**

**F2 — An enumeration oracle on `/summary/restore`. Code-reading, not an executed repro.**

`CommunityMemoryService::republishSummary()` resolves the summary row *before*
the curator check, and no board read gate runs on that path at all:

- summary id does not exist → `NotFoundException` → **404**
- summary exists but belongs to another thread → `NotFoundException` → **404**
- summary belongs to *this* thread → falls through to
  `assertCuratorForLockedThread()` → `ForbiddenException` → **403**

So any authenticated account can post a candidate `summary_id` to
`/t/{id}/summary/restore` and learn from 403-vs-404 whether that id belongs to
that thread — including a thread on a private or hidden board it cannot read.

**Severity: low.** The leak is an opaque integer mapping only: no summary body,
no title, no author, no board name, no write, and no state change. The two miss
cases are byte-identical 404s.

**Deferred deliberately.** Hoisting the curator assert above the lookup changes
observable statuses on a route that already has committed browser and PHPUnit
evidence, so it needs its own change and its own evidence pass rather than a
drive-by edit inside a UI redesign. It is owned here.

**F3 — An entire browser evidence group has been dead, and is dead in CI. Executed repro.**

`tests/browser/role-assignments.spec.ts` cannot be parsed:

```
SyntaxError: tests/browser/role-assignments.spec.ts:
  Identifier 'openTopicComposer' has already been declared. (93:15)
Error: No tests found.
```

`openTopicComposer` is declared twice, at lines 54 and 93 — a module-scope
duplicate lexical binding. `npx playwright test role-assignments.spec.ts --list`
exits **1**, listing zero tests in zero files.

This is unrelated to the living brief. `git diff main -- tests/browser/role-assignments.spec.ts`
is empty and `git log -S` puts the second copy in `3f5f0472` (2026-08-08, the
board-page polish pass), so it predates this branch by weeks.

It matters operationally. `npm run evidence` chains its four groups with `&&`:

1. `thread-view-study` + `rich-content` + `thread-content-presentation`
2. `gate-a` … `thread-intelligence` … `link-previews` (12 specs)
3. `role-assignments` under `CAPABILITIES_MODE=enforce` ← **cannot start**
4. `admin-remediation` + `admin-dashboard`

Group 3's non-zero exit means group 4 never runs. `.github/workflows/browser-evidence.yml`
— the repository's only GitHub workflow — runs exactly `npm run evidence`, on
`workflow_dispatch` and on any push touching `src/`, `templates/`, `public/`,
`database/migrations/`, `tests/browser/`, or the workflow file itself. So this
is a CI failure, not merely a local one.

**Caveat for whoever picks this up: group 1 may stop you before group 3 does,
and for an unrelated reason.** On Windows, `thread-view-study.spec.ts:349`
("Study layout matches desktop and mobile geometry") fails on a 15px delta
where it allows 2 — the width of a classic
Windows scrollbar gutter (`html { scrollbar-gutter: stable }`, `app.css:11`).
This branch demonstrated it is not ours by checking `templates/` out at `main`,
leaving the branch's CSS and PHP in place, and re-running the single test: it
failed identically. Headless Chromium on Linux — where the workflow runs — uses
overlay scrollbars and reserves nothing, so group 1 is expected to pass in CI
and group 3 is expected to be the first real stop there. That expectation has
**not** been confirmed against an actual CI run; a maintainer reproducing this
locally on Windows should run the groups individually rather than conclude the
chain dies at group 1.

**Not fixed here.** Choosing which of the two `openTopicComposer` implementations
survives (they differ only in parenthesisation of the same ternary) is a decision
for whoever owns that spec, and the fix must be evidenced by a green group-3 run
under `CAPABILITIES_MODE=enforce`, which is outside this branch's surface. It is
recorded so it is not lost again.

## Known follow-ups

1. **Version history is unbounded and runs a lineage query per row.**
   `ThreadIntelligenceViewService::history()` selects every `thread_summaries`
   row for the thread with no `LIMIT` and no pagination, then calls `lineage()`
   per row — and `lineage()` walks the `parent_summary_id` chain one query per
   ancestor. Cost is `O(versions × chain depth)` queries per topic-page render.

   **Every viewer pays this, not only curators.** `history()` is called
   unconditionally inside `forThread()` on every path that gets past the read
   gate, and `ThreadController::show()` calls `forThread()` for every viewer
   whenever `community_memory` is on — the flag is the only condition, and
   `$user` may be `null`. Only the *rendering* of the version rows is
   curator-gated. So a guest reading a heavily-amended public topic runs the
   same query fan-out and is shown none of it. Size the fix against total topic
   traffic, not curator traffic.

   This is **unchanged from before the redesign** (the old
   `<select id="summary-restore">` iterated the same rows, and `forThread()`
   called `history()` the same way); rendering forms instead of `<option>`s adds
   no queries. Left uncapped deliberately, to avoid smuggling a behaviour change
   into a visual slice. A cap, a single recursive CTE, or deferring the
   `history()` call to viewers who can act on it are all fixes — the last is the
   cheapest and closes the guest cost outright.

2. **`--surface-cool` still has no dark override.** It is defined once, at
   `public/assets/imladris.css:117` as `var(--mist-100)` (`#EEF1ED`), and neither
   it nor `--mist-100` is redefined in the `[data-theme="dark"]` block — so it
   paints a light surface in dark mode. Its only consumer was
   `.living-brief-related-card`, which this branch repointed to
   `--surface-sunken`; the card is fixed and `--surface-cool` now has **zero**
   consumers in `app.css`. The token itself is still a trap for the next
   consumer. Fixing it belongs to the Imladris token layer, not to app CSS.

## Evidence

- **PHPUnit** — `ThreadIntelligenceSurfaceTest` (curator gate across role, state,
  guest, and per-board-authority axes; the paused status line rendering exactly
  once; restore-by-row posting the same `summary_id` the `<select>` did, one
  CSRF-carrying form per version; the pause route curator-gated and rejected for
  guests and non-curators; the curator empty state's numbers matching
  `ThreadIntelligenceEligibility`'s own predicate, and the guest seeing nothing);
  `AppPhase4GateATest` (anonymous-author masking on brief sources — extended, not
  weakened); `ThreadIntelligenceOperationsServiceTest` (the partial rendered
  without a `can_curate_memory` key stays inert); `FormattedContentContractTest`
  (the pinned `class="post-body formatted-content"` contract preserved).
- **Browser** — `tests/browser/thread-intelligence.spec.ts`, desktop + mobile,
  **18 passed**, including *"no-JS: Living Brief navigation, every disclosure,
  and all five curator forms remain native"* (the `<details>` More panel and the
  Retire confirm opening without script, and every curator form still submitting
  with JavaScript disabled) and the reduced-motion assertions. axe on the
  re-labelled region is clean of serious/critical violations — required, because
  §1 removes the section's only heading. Captures refreshed at
  `docs/evidence/browser/{desktop,mobile}/75-79-*.png`.
- **Runbook** — `docs/runbooks/thread_intelligence.md` §11 (the seven curator
  routes, the new pause action, and where the controls now render).
- **Schema** — none. This slice adds no migration; the next number remains `0082`.
