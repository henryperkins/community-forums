# Living Brief redesign — design

**Date:** 2026-08-26
**Branch:** `worktree-living-brief-redesign`
**Source:** `CommunityForumDesignSystem.zip` → `design_handoff_living_brief/README.md`
**Baseline:** `b04f4726`, 2624 tests / 19055 assertions / 1 skipped, green.

## 1. What this is

The handoff proposes five changes to the living brief as it renders on a topic page. A
verification pass against the working tree found that three of the five rest on premises that
do not hold in this codebase. This spec ships the four changes that are true and correct here,
and records the rest as deferrals with the evidence needed to do them properly.

### Shipping

| § | Change | Layer |
|---|---|---|
| §1 | Delete the brief's own `<h2>`; the topic title becomes the region's visual head | template + CSS |
| §2a | One member-visible status line: automatic refresh paused | template + CSS + payload |
| §3 | Curator tools move to the foot of `.living-brief`, one primary action | template + CSS + **new route** |
| §5 | Curator-gate audit; empty-state copy that explains eligibility with real numbers | template + service accessor |

### Deferred to a follow-on slice (ADR 0026)

| § | Change | Why it is not in this slice |
|---|---|---|
| §2b | "Last verified brief" status | No such state exists anywhere in the member view model. New server work. |
| §4 | Footnote markers resolving to source rows | Blocked on an unresolved defect in source-union semantics (§7). |

## 2. Corrections to the handoff

These are recorded because the handoff will be read again by someone else.

1. **There are no two stacked status bands (§2).** The only two status paragraphs on the whole
   surface are `thread_memory_tools.php:6` (paused) and `:17-22` (ineligible), and they sit on
   opposite sides of an `if`/`else` at `:5`/`:11` — they are mutually exclusive and can never
   stack. `last_good` / `last_verified` does not exist in `templates/`, `src/`, or
   `public/assets/`; it appears only as a Playwright fixture key
   (`tests/browser/thread-intelligence.spec.ts:13`) and a screenshot name. "Collapse two bands
   into one, last-good wins" is therefore unimplementable as written. What is real and worth
   doing is the stated *goal*: members should see that the brief is frozen. That ships as §2a.

2. **"Pause automatic refresh" has no backend (§3).** `src/Core/App.php:2132-2137` registers
   exactly six memory routes; pause is not among them. `CommunityMemoryService` has
   `resumeAutomation()` (`:189`) and no counterpart. Today pausing happens only as a side
   effect of retire (`CommunityMemoryService.php:151`). **Decision: build the route.**

3. **The eligibility threshold is 8, not six (§5).** `ThreadIntelligenceEligibility::INITIAL_POST_THRESHOLD = 8`.
   The handoff's copy ("once a topic carries six eligible public counsels") states the wrong
   number. The counts are also unreachable: `eligiblePostCounts()` is `private` and the
   threshold is a `private const` with no getter.

4. **"Counsels" is design-system flavour, not this app's noun.** `templates/thread.php:53`
   renders `{n} repl{y|ies}`. Copy uses **replies**.

5. **The confirm step has no CSP-safe precedent (§3).** Zero occurrences of `data-confirm` or
   `confirm(` in `templates/` or `public/assets/`; `SecurityHeaders` sets `script-src 'self'`
   with no nonce, so `onclick="return confirm(…)"` is impossible. Design below uses a
   server-rendered disclosure instead.

6. **Two defects in the bundle itself.** `screenshots/08-citation-selected.png` and
   `09-curator-more-open.png` are byte-identical (md5 `347d1469…`), so **there is no screenshot
   of the curator footer** — `design/LivingBrief.dc.html` is its only visual source.
   `06-empty-restore.png` renders a **"RETIRE BRIEF"** button in the empty state because the
   prototype binds `{{ retireLabel }}` there; the README's prose (Restore) is authoritative.

## 3. §1 — Promote the topic question

**`templates/partials/living_brief.php`**

- `:2` — `aria-labelledby="living-brief-heading"` → `aria-label="Living brief"`.
- `:12` — delete `<h2 id="living-brief-heading">Where the discussion stands</h2>`.
- These two must change together. Deleting the `h2` alone leaves `aria-labelledby` pointing at
  nothing, which drops the region's accessible name entirely — strictly worse than today.
- The wrapper `<div>` at `:4`/`:13` exists only to stack the label over the `h2`. With the `h2`
  gone it is vestigial: unwrap it so `.living-brief-head` is a flex row of eyebrow + meta.
- The eyebrow keeps its `/privacy#thread-intelligence` link unchanged and now carries the
  accessible-name duty in the visual hierarchy.

**`public/assets/app.css`**

- `:10156-10157` — `.living-brief-head h2, .related-topic-fallback h2 { margin: 0; }`. Drop the
  `.living-brief-head h2` half only. `.related-topic-fallback h2` is still live
  (`templates/thread.php:191`).

**Verification:** confirm the thread title out-sizes the card after the `h2` is gone. If the
card still competes, reduce `.living-brief` `padding-top` by one step to `var(--space-3)`.

## 4. §2a — One status line

A single `.living-brief-status` element inside `.living-brief`, directly under the head row and
above the body. Structured with a modifier class from day one so §2b's last-good variant can be
added later without re-layout.

- Ships: the **paused** variant only.
- Copy, exactly: *Automatic refresh is paused for this topic. The brief stands as published.*
- Style per the handoff's Paused column: no background, no border, padding 0,
  `color: var(--text-muted)`, `font-size: .87rem`, `line-height: 1.5`, with a 13px pause-bars
  icon at `stroke-width: 1.8` inheriting `currentColor`.
- Source: `$memory_automation_paused`, which must be added to the partial's payload (§6).
- `thread_memory_tools.php:6`'s bare `<p class="muted">` is **moved, not duplicated** — delete it
  there.
- The **ineligibility** message stays curator-only, beneath Refresh, per the handoff's own §3
  ("Keep the existing `disabled` state and the ineligibility message under it").

## 5. §3 — Curator tools at the foot of the brief

Rendered only when `$can_curate_memory`. Separated from the body by `1px solid var(--border-hair)`
with `var(--space-4)` above the rule and `var(--space-3)` below.

```
[ Refresh ]  Amend ▾                                  More ▾
──────────────── (disclosure, closed by default) ─────────────
Earlier versions
v3   Amended by @galadriel     5 days ago         Restore
v2   Drafted by the archive    8 days ago         Restore
…
Add related topic  [ id ] [ reason ]              Add
──────────────────────────────────────────────────────────────
Pause automatic refresh                       Retire brief ▾
```

**Refresh** — the one filled button. `.btn` at `app.css:214-228` is *already*
`background: var(--accent); color: var(--accent-contrast)`, so this is simply dropping
`btn-small` from the existing form. Keeps its `disabled` state and the ineligibility message
beneath it.

**Amend** — `<details class="lb-amend"><summary class="linkbtn">Amend</summary>` wrapping the
existing summary composer form. A `<details>` rather than a button because the handoff wants it
to "open the composer", and that must work with JS off; `<summary class="linkbtn">` has
precedent at `thread_memory_tools.php:3`.

**More** — `<details><summary>`, closed by default, working natively without JS. Label swaps
More/Less via two spans toggled by `details[open]` in CSS (no JS, no inline style).

**Version list** — replaces the `<select id="summary-restore">` at `thread_memory_tools.php:46`.
One row per `$memory_history` entry, each its own `<form method="post" action=".../summary/restore">`
carrying `summary_id` and CSRF, so it works without JS. `$memory_history` rows carry a **raw
enum** `status` (`draft|published|retired`) — map to human labels. `published_at` is a raw DB
string with no `_utc` twin, unlike `living_brief`; render through `human_datetime()` and emit a
proper `<time datetime="…Z">` (the browser suite asserts that pattern).

*Known cost, unchanged from today:* `history()` runs `lineage()` per row and has no limit or
pagination. Rendering rows instead of `<option>`s adds no queries. Left uncapped to avoid a
behaviour change; noted in ADR 0026 as a follow-up.

**Add related topic** — the handoff is silent on this form (`thread_memory_tools.php:55-60`).
Judgment call: keep it, inside the More disclosure below the version list. It is a low-frequency
curator tool and has nowhere else to go.

**Pause** — new. `POST /t/{id}/summary/automation/pause`:
- `CommunityMemoryService::pauseAutomation()` mirroring `resumeAutomation()` (`:189`)
- the same curator capability check as resume
- a `moderation_log` audit row
- CSRF via `csrfField()`
- renders only when not already paused; when paused, the existing Resume form renders instead

**Retire** — destructive, and currently one click. Server-rendered two-step, no JS, no new route:

```html
<details class="lb-confirm">
  <summary class="linkbtn danger">Retire brief</summary>
  <p>Retiring hides the brief from the topic and pauses automatic refresh. Curators can restore it.</p>
  <form method="post" action="/t/{id}/summary/retire">…<button class="btn danger">Retire brief</button></form>
</details>
```

Use `.btn.danger` / `.linkbtn.danger` (`app.css:232-234`, `:273`) — **not** imladris's
`.btn-danger` (`imladris.css:396`). `app.css` is unlayered and outranks imladris regardless of
order, so the app.css names are the live ones.

**Call sites**

- Delete `living_brief.php:19` (the `hidden` `.living-brief-curate` button). The generic
  `data-topic-tools-open` JS branch stays — `templates/thread.php:76` also uses it.
- `thread_tools.php:96-103` keeps its `<details>` section and `$showMemory` gate, but its body
  becomes a link to the new footer rather than the duplicated controls. This preserves
  `$hasTools` (`:8`), so boards where memory was a curator's only tool section do not lose the
  whole `<aside>`.

## 6. Payload and scoping

`View::renderTemplate()` (`src/Core/View.php:70-99`) extracts only shared globals plus the
explicit payload — parent locals are invisible. `templates/thread.php:183-188` currently passes
four keys to `partials/living_brief`. Four more are required:

| Var | Needed for | Shape note |
|---|---|---|
| `$thread` | every form `action` | already in `thread.php` scope |
| `$memory_automation_paused` | §2a | `thread.php:106` |
| `$memory_refresh` | §3 ineligibility, §5 copy | **initialised to `[]`** when `community_memory` is off (`ThreadController.php:346`) — every read stays `?? `-guarded |
| `$memory_history` | §3 version list | 9 keys/row; raw enum `status`; no `_utc` twin |

Miss one and PHP emits an undefined-variable warning, which under PHPUnit's `failOnWarning`
turns the suite red. `thread_tools.php:100`'s `compact(...)` must be kept in sync or reduced
alongside.

New element ids are scoped by thread id. The existing `summary-body` / `summary-sources` ids are
not thread-scoped; the `summary-restore` id disappears with the `<select>`.

## 7. §5 — Correctness fixes

**Curator gate.** `living_brief.php:19` is already `!empty($can_curate_memory)`-guarded, so the
handoff's first fix is already satisfied *there*. The real exposure is that
`thread_memory_tools.php` contains **no `$can_curate_memory` check at all** — its entire
protection is the caller's `$showMemory`. Six CSRF-protected POST forms render unconditionally
once that partial is included. Moving the tools into the brief makes this load-bearing: the new
footer gates on `$can_curate_memory` **inside the partial**, not only at the call site.

`$can_curate_memory` is `!empty($can_write) && !empty($can_curate_memory)` (`thread.php:187`),
so a suspended admin is already denied by "state beats role" — but that must be a test, not an
assumption.

**Empty state.** Rendered **for curators only.** Two reasons: it preserves
`ThreadIntelligenceSurfaceTest:137-140`, which asserts a *guest* on a brief-less thread sees
neither `thread-memory-slot` nor `living-brief`; and it matches the state's stated purpose —
after Retire, the curator loses the only route back. Members are not shown an explainer on every
young topic.

Copy, with real numbers and this app's noun:

> The archive draws a brief once a topic carries eight eligible public replies. {N} of the {M}
> here are eligible; the rest are private, hidden, or held.

This needs a public accessor on `ThreadIntelligenceEligibility` exposing eligible count, total
count and threshold, merged into `$memory_refresh` in `ViewService::emptyModel()` (`:118`).
Counting must **not** be duplicated in `ThreadController` — that would re-implement the
`is_deleted = 0 AND is_pending = 0` predicate outside the policy object.

Note `forExplicitRefresh` passes `explicit: true`, so `post_delta_threshold` can never surface on
a thread page; `initial_post_threshold` is the only threshold a member can ever see. The copy
must also stay true under a rolled-back `automated_context` flag, where an already-published
brief still renders but new generation is dark.

The empty state carries a **Restore brief** affordance: the same version-row component as §3's
More panel, listing retired versions with one `POST .../summary/restore` form each, the first
styled with the filled-accent treatment used by Refresh. It renders only when `$memory_history`
holds at least one restorable version; a topic that has never had a brief shows the eligibility
copy alone.

## 8. Deferred: the source-union defect blocking §4

Recorded here because it is a live finding, not a design preference, and because it is what makes
§4's ordinal scheme sound today.

`thread_intelligence_generations.source_post_ids` is written with the **evidence-pack** union —
every eligible post in the window, plus baseline (`ThreadIntelligenceEvidenceBuilder.php:86-91`;
written at `ThreadIntelligencePublisher.php:180` and `ThreadIntelligenceWorker.php:362`).
`thread_summary_sources` is written with the **citation** union — only what the model actually
cited (`ThreadIntelligencePublisher.php:310`). `ThreadIntelligenceViewService::aiSourcesAreCurrent()`
(`:226-243`) compares the two for **set equality**, and `forThread()` (`:82-91`) suppresses the
entire brief when they differ.

But `ThreadIntelligencePromptBuilder.php:26` rule 6 only requires *"cite at least one for every
statement group."* So on this reading an AI brief renders **only when the model happens to cite
every post in the evidence window**. No test covers worker→view with a subset citation:
`ThreadIntelligenceWorkerTest.php:174` scripts a strict subset but asserts DB rows, not the view;
`tests/browser/thread-intelligence-fixture.php:252` sets the overview's `source_post_ids` to
*every* post, which is what keeps the fixture brief renderable.

**This is code-reading, not an executed repro.** The first task of the §4 slice is an integration
test that publishes a subset-citation brief through the worker and asserts what `forThread()`
returns. That test decides whether this is a defect or intended.

It matters for §4 because `aiSourcesAreCurrent()` is precisely what guarantees the handoff's
ordinal claim: the union is `sort()`ed ascending (`OutputValidator.php:149-150`) and
`sources()` is `ORDER BY p.id ASC` (`ViewService.php:205`), but `sources()` drops rows after the
query (`:207-213`), and one dropped row shifts every later ordinal. The equality gate is what
prevents that today. **Fixing the union semantics would break the ordinal guarantee**, so the two
must be designed together.

Also recorded for that slice: `[^n]` survives the sanitizer as inert text (no footnote extension
is loaded), but appending it with no leading space to an item ending in `!` produces `![^n]`,
which trips `UNSAFE_PATTERNS`' image-opener rule (`OutputValidator.php:34`) and fails the whole
generation. Use a leading space.

## 9. Tokens and CSS

All new rules go in `public/assets/app.css`. `imladris.css` wraps everything in
`@layer imladris.tokens, imladris.components`; `app.css` is entirely unlayered, so every app.css
rule outranks every imladris rule regardless of specificity or order.

Three traps to avoid:

- **`--text-body` is an ink colour**, not a size (`imladris.css:249-251`). The size token is
  `--text-size-body`. `font-size: var(--text-body)` is invalid and silently inherits.
- **`--surface-cool` has no dark override** — defined once at `imladris.css:117` as
  `var(--mist-100)`. Its only consumer is `.living-brief-related-card` (`app.css:10188`), where it
  paints `#EEF1ED` under `--text-muted` in dark mode. Do not reuse it for the ledger or the More
  panel; use `--surface-sunken`. (The existing defect is out of scope here; note it in ADR 0026.)
- **A literal hex anywhere in this feature is a bug** — it will not flip with the theme.

`.living-brief-label`'s `.78rem` (`app.css:10162`) maps exactly to `--text-meta`; swap it.
`.living-brief-meta`'s `.88rem` (`:10173`) has no matching step — keep raw rather than invent one.

Animation: brief fades in over 240ms with `var(--ease-calm)` from `opacity: 0; translateY(5px)`;
the disclosure panel uses 180ms, same easing. Both gated on `prefers-reduced-motion`.

## 10. Testing and evidence

DESIGN §13: *"anything marked `Live` must be accompanied by the tests, smoke checks, or
Playwright/browser verification that prove the claim. UI-visible work needs browser verification
in addition to server-side tests."* All four shipping changes are UI-visible.

**Tests that will need updating**

| File | Why |
|---|---|
| `tests/Unit/Core/FormattedContentContractTest.php:24-26` | pins the literal `class="post-body formatted-content"` in `living_brief.php` — must be preserved |
| `tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php` | asserts raw DOM order and `substr_count` of the section class; §1/§2a/§3 all perturb it |
| same, `test_curator_refresh_feedback_and_retirement_resume_are_gated_and_non_bypassing` | button markup and feedback copy change |
| `tests/Integration/Core/AppPhase4GateATest.php` (3 methods) | `assertSeeText` on strings §3 changes; `testSummarySourceMasksAnonymousAuthor` is the masking gate — **extend, never weaken** |
| `tests/Integration/ThreadIntelligence/ThreadIntelligenceOperationsServiceTest.php:437-441` | renders the partial **without** a `can_curate_memory` key — new curator markup must stay `!empty()`-guarded |

**New PHPUnit tests**

1. Curator gate: a non-curator member and a guest see zero `action="/t/{id}/summary` substrings.
2. Suspended admin: state beats role — no curator affordance renders.
3. Status line renders exactly once (`substr_count == 1`) in paused, ineligible, and eligible states.
4. Restore-by-row posts the same `summary_id` the `<select>` did; one form per version; CSRF present.
5. Pause route exists, is curator-gated, writes a `moderation_log` row, is CSRF-protected, and is
   rejected for a non-curator.
6. Empty state: curator sees eligibility copy with numbers matching `ThreadIntelligenceEligibility`'s
   own predicate; guest sees nothing (preserving `SurfaceTest:137-140`).
7. Empty state does not lie under a rolled-back `automated_context` flag.

Harness: reuse `ThreadIntelligenceSurfaceTest.php:213-333` — `seedThread(8, …)` (8 is the
eligibility floor), `insertAiBrief()` (**must** insert both the `thread_summary_sources` rows and a
matching `thread_intelligence_generations` row, or `aiSourcesAreCurrent()` fails the brief closed).
`community_memory` and `automated_context` are both default-ON, and
`SettingRepository::set('features', …)` **replaces the whole JSON override** — list every flag needed.

**Browser evidence**

- Update `tests/browser/thread-intelligence.spec.ts` and re-capture
  `docs/evidence/browser/{desktop,mobile}/75-79-*.png`.
- Extend the no-JS test: `<details>` More opens natively, the Retire confirm works without JS, and
  Refresh / Amend / Restore / Pause forms all still submit.
- axe: §1 removes the section's only heading, so the `aria-label` swap must be verified by axe, not
  assumed. `npm run a11y` sets `RB_BROWSER_DARK_SURFACES=1`.
- Full-suite double run (fresh + reused schema), identical counts.

**No migration.** Nothing in this slice adds schema, so `verify:upgrade`, a `SCHEMA.md` bump, and
the backup rehearsal are not in play. If that changes, the next migration number is `0082`.

## 11. Governance

ADR **0026** records: the two deferrals (§2b, §4) with the reasons above; the decision to build
the Pause route rather than drop the control; the curator-only empty state; the source-union
finding in §8; and the two out-of-scope defects noted along the way (`--surface-cool` dark
override, unbounded version history).
