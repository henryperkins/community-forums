# Review: WYSIWYG Composer and Slack-Style References

**Reviews:** `2026-07-02-wysiwyg-composer-design.md`
**Date:** 2026-07-02
**Method:** Cross-checked the design's claims against the authoritative docs
(`DECISIONS.md`, `COMPOSER.md`, ADR 0001) and the actual code/schema it references.

---

## Verdict

Solid, implementable direction that correctly preserves the canonical-Markdown
contract and kill switches, and whose factual claims about the current system
check out. Before an implementation plan, it needs to resolve **one critical
omission (strict CSP)** and **one over-stated acceptance criterion (byte-stable
round-trip)**, plus several medium interaction gaps.

---

## Claims verified as accurate

- Board `/c/{slug}`, topic `/t/{id}-{slug}`, user `/u/{username}`, tag
  `/tags/{slug}`, and `POST /composer/preview` all exist; `/composer/suggest`
  does not (`src/Core/App.php:1295-1317`).
- `content_references.target_type` is exactly `ENUM('board','thread','post')`
  (`database/migrations/0048_phase4_gate_a.php:374`).
- `ContentReferenceService::extract()` handles `/t/{id}`, `#p{id}`, `/c/{slug}`
  and nothing else — extending to `/tags/{slug}` is a clean additive change
  (`src/Service/ContentReferenceService.php:84-98`).
- The enum-migration + rollback pattern the design prescribes matches existing
  code exactly (e.g. `0068` `MODIFY target_type ENUM(...)`, `down()` deletes
  new-type rows then reverts the enum).
- `MentionParser` extracts raw `@username` for notifications and is unchanged by
  this work; mention **link-rendering does not exist today** (`src/Support/Markdown.php`
  does CommonMark + emoji only). The design is right that it is new work.
- Flags: `rich_composer`/`tags` on, `content_references`/`link_previews` off;
  `wysiwyg_composer` is genuinely new (`src/Core/FeatureFlags.php:40-62`).
- `SearchService` mandates a read gate; `MysqlSearchService` enforces it and has
  a 3-char FULLTEXT minimum (`src/Search/SearchService.php:11-12`,
  `src/Search/MysqlSearchService.php:29`).
- composer.js already provides toolbar/preview/drafts/uploads/GIPHY over a
  textarea, matching §0.

---

## High-priority issues

### 1. Strict CSP will fight the editor — and the design never mentions it

`src/Security/SecurityHeaders.php:41` emits `script-src 'self'; style-src 'self'`
with **no `'unsafe-inline'`**, and `CLAUDE.md` forbids inline styles/scripts
anywhere. Milkdown/ProseMirror routinely (a) inject runtime `<style>` elements
(Milkdown theme system, e.g. Crepe/Nord) and (b) set inline `style=""`
attributes — notably `prosemirror-tables` writes inline widths on `<col>`, and
the design lists **tables** as supported. Under `style-src 'self'` these are
blocked and the editor degrades with console violations.

**Action:** make this a first-class constraint in §3.4 — ship all editor CSS as
a committed stylesheet served from `'self'`, pick/patch a theme that does not
inject `<style>`, and audit table/gapcursor/decoration plugins for inline
styles. This is the biggest feasibility risk.

### 2. The "no-op load/save doesn't mutate canonical Markdown fixtures" criterion (§7.3) is unrealistic as written

Today storage is verbatim precisely because there is no editor
(`AppComposerTest`; `tests/Unit/Composer/MarkdownRoundTripTest.php` tests
render-fidelity, not editor serialization). A Markdown serializer normalizes
(`*`↔`_`, `-`↔`*` bullets, ATX vs setext, table whitespace, wrap width), so
byte-stability only holds for a corpus authored in Milkdown's own output form.
**Editing any pre-existing free-form post via WYSIWYG will rewrite its `body`
formatting**, inflating edit diffs and potentially flipping the "edited" marker
on an open-and-save no-op.

**Action:** reframe the guarantee as *semantic* (re-renders to identical
sanitized HTML) rather than byte-identical, and explicitly address editing
legacy Markdown in §5.1.

---

## Medium issues

### 3. `#` trigger collides with Markdown headings

`@` is unambiguous, but `#`/`## `/`### ` start headings. Slack has no headings,
so the analogy breaks. The design supports both headings *and* `#`-references
without specifying disambiguation (e.g. only trigger the picker when `#` is
immediately followed by a query char, not `# `). Call this out.

### 4. Heading clamping breaks WYSIWYG fidelity

The server clamps `#`→`<h2>` and `####`→`<h3>` at render
(`tests/Unit/Composer/MarkdownRoundTripTest.php:39-40`). A rich editor showing a
live H1 will post an H2 — a concrete "what you see isn't what you get." The
editor must clamp to the same H2/H3 set, or the design must accept the
discrepancy and lean on preview as the truth view (per §2.1).

### 5. The bridge interface can't express the upload placeholder swap

composer.js inserts `![uploading…](token)` then replaces it with the final URL
on completion (`public/assets/composer.js` ~811). In a rich surface that
placeholder is an image node, not a substring — `insertMarkdown` /
`replaceSelection` don't cover "find this pending token and replace it." The
bridge (§3.2) needs a replace-by-token/node method, or the upload flow needs
rethinking for the Milkdown adapter.

### 6. Two mention-detection code paths must stay in lockstep

Notifications use `MentionParser`'s grammar (`@` + 3–32 `[A-Za-z0-9_]`, not after
a word char/second `@`, code/pre stripped — `src/Support/MentionParser.php:24-49`).
The new render-time DOM linker (§4.4) must use the *same* grammar and exclusions,
or displayed links and notified users will diverge. Recommend sharing one grammar
definition. (Good news: the proposed DOM-walk mirrors the existing emoji walker
in `src/Support/Markdown.php:82-99`, so the pattern is proven.)

---

## Minor / clarifications

- **Default-off cards undercut the headline feature.** With `content_references`
  off (its default, per Non-Goals), `#`-inserted references render as **plain
  links, no cards** — the "inline chip" is editor-only. §6 hints at this; make it
  explicit that default rendered output is a link, cards require enabling the
  flag, and tag cards additionally require the new enum migration.
- **Typeahead responsiveness for topics/posts.** FULLTEXT `NATURAL LANGUAGE
  MODE` won't prefix-match and has the 3-char floor, so `#` topic/post results
  won't feel instant as-you-type. The prefix/LIKE plan for boards/tags/users
  (§4.2) is the right instinct; state that topic/post suggestions are
  whole-word-ish by design.
- **Suggestion endpoint:** specify a dedicated rate-limit policy in
  `config/config.php` (alongside `post`/`dm`/`upload`) plus client debounce.
  "CSRF-safe authenticated GET" is fine but redundant — a read-only GET needs no
  token; just ensure it never mutates.
- **Fallback-ladder divergence.** §1 lists Milkdown → Tiptap → **raw
  ProseMirror**, but `DECISIONS.md` §6 #1 and `COMPOSER.md` §14.2 lock Milkdown →
  Tiptap/ProseMirror → **CodeMirror/ink-mde**. Per the doc's own precedence note,
  DECISIONS wins — either keep the CodeMirror fallback or record the change.

---

## Process / doc gaps (this repo is spec-driven)

- This design reverses ADR 0001's *accepted* decision (it chose the textarea to
  avoid a build step, a client serializer, and round-trip drift — all three now
  reintroduced). ADR 0001 left a revisit trigger, so it is legitimate, but it
  should land as a **new ADR superseding/amending 0001**, not just a spec.
- Follow-on doc updates to enumerate in §9: **COMPOSER.md** (§6.3 currently
  frames references as P2 "nice-to-have"), **SCHEMA.md** after the enum migration
  (shape + §9 changelog + version bump, per `CLAUDE.md`), and a **runbook** for
  `wysiwyg_composer` like the existing flag runbooks.
- Heads-up for implementation: `CLAUDE.md` says "next migration 0049," but the
  tree is already at **0068** — the tag-enum migration is `0069`.

---

## Recommendation

The increment ordering in §9 is sound (backend contract before editor). Add two
explicit spikes ahead of the Milkdown adapter:

1. **CSP-compatibility spike** — can Milkdown run with zero inline styles/scripts
   under `style-src 'self'`?
2. **Serializer-fidelity spike** — run against the real stored-post corpus to
   define the round-trip guarantee as semantic-equality.

Those two answers most affect whether this ships as designed.

---
---

# Second-pass review (2026-07-02, after fold-in)

**Reviews:** the updated doc (Status: "review folded in").
**Method:** verified each first-round finding was folded faithfully, independently
re-verified the doc's factual claims against the code, then hunted for gaps the
first pass missed.

## Fold-in verification

All ten first-round findings are present and accurately stated in the doc (CSP as
locked decision + §3.4 + smoke test + acceptance criterion + spike; semantic
round-trip in §1/§5.1/§7.1/§7.3; `#`-trigger disambiguation §2.3; H2/H3 clamp
§2.1; `replacePendingUpload` §3.2/§5.2; mention-grammar lockstep §4.4; default-off
cards §2.3/§6; debounce + whole-word typeahead + dedicated rate policy §4.2;
fallback ladder now per DECISIONS §6 #1 / COMPOSER §14.2; ADR-first + doc
follow-ons §0/§9).

Spot-re-verified claims — all check out: flags (`src/Core/FeatureFlags.php:40-66`),
routes (`src/Core/App.php:1305-1317`), CSP string (`src/Security/SecurityHeaders.php:41`),
mention grammar + cap (`src/Support/MentionParser.php:17-24`; usernames are exactly
`[A-Za-z0-9_]` 3–32, `src/Service/AuthService.php:124`, so every valid handle is
mention-matchable), composer.js selectors/placeholder/conflict panel
(`public/assets/composer.js:438,811,311`), layout gating on `rich_composer`
(`templates/layout.php:75`), `composer_preview` rate-policy precedent
(`config/config.php:204`), tags `visibility ENUM('public','hidden')` + `is_enabled`
(`0048_phase4_gate_a.php:152-153`), and `/tags/` vs `/t/` regex non-collision in
`ContentReferenceService::extract()`.

Two notes for the implementation plan:

- Numbering as of today: the superseding ADR is **0013**; the tree is at
  migration **0070**, so the tag-enum migration is **0071+** (the first review's
  "0069" is already stale — §9's "follow the latest tree" wording is the right call).
- ADR 0001's "supported syntax" list omits tables/task lists, but the server
  renderer genuinely enables `TableExtension` + `TaskListExtension`
  (`src/Support/Markdown.php:39-40`). The doc's corpus is correct; the *ADR text*
  is stale — ADR 0013 should restate the real supported set.

## New high-priority issues

### A. Anonymous authorship can leak through the suggestion service

`posts.is_anonymous` exists (`database/migrations/0009_posts.php:18`) and masking
happens only at render time via `mask_author` (`src/Support/helpers.php:47`). The
design never mentions anonymity, and it has two leak paths:

- §2.2 ranks "participants in the current thread" first in the `@` picker. A user
  who participated only anonymously surfacing at the top of that thread's picker
  deanonymizes them. "Recent **visible** participants" (§2.2) does not obviously
  exclude anonymous posts — visible-but-masked is exactly the dangerous case.
- §4.2's post suggestions display "author". That metadata must pass through
  `mask_author` semantics, never the raw username.

**Action:** add to §4.3 — participant/recency ranking signals must exclude
anonymous posts, and post-suggestion author metadata must use masked authorship.
Add both to the §7.1 test list.

### B. `context`/`target_id` must be read-gated before shaping ranking, not just per result

§4.2's "read-gated per result" filters what is *returned*, but the *ranking
signal* is computed from `target_id`. A forged `target_id` naming a private
thread / hidden board / other people's DM turns result **order** into an oracle
for "who participates in {id}" — every ranked user is individually visible, so
per-result gating never fires.

**Action:** specify that the server verifies the requester can read the context
target (thread/board via `BoardPolicy` + membership, DM via participant check)
before applying contextual ranking, and silently falls back to global ranking
otherwise. Define `target_id` semantics per `context` value while at it. Test:
an inaccessible `target_id` returns results identical to no-context.

## New medium issues

### C. Pin down where the mention linker runs

§4.4's "displayed post/DM HTML" is ambiguous, and the natural implementation
over-applies: the cited emoji-walker precedent runs *inside* `Markdown::render()`
(`src/Support/Markdown.php:58-107`), whose consumers include not just
posts/DMs/preview but **bios** (`ProfileController`) and **community-memory
summaries**. A render()-level hook links mentions everywhere. Two more unstated
consequences: `body_html` is a write-time cache, so existing posts gain links
only when re-rendered (edit/approve) — there is no backfill mechanism (`repair`
recomputes counters, not `body_html`); and baked links go stale on username
rename.

**Action:** enumerate the surfaces (posts, DMs, and preview in — COMPOSER.md's
preview spec requires "mentions linked"; bios/summaries out, or explicitly in),
state the write-time/no-backfill consequence, and accept or address rename
staleness.

### D. Decide whether the pickers also serve the textarea adapter

The suggest endpoint is gated on `rich_composer`, not `wysiwyg_composer` — but
the doc only ever mounts pickers on the rich surface. COMPOSER.md §6.1/§17.1
promised mention **autocomplete** on the Phase 2/3 composer, and composer.js has
none today (verified: no mention/suggest code). The bridge's
`insertMarkdown`/`replaceSelection` makes a GitHub-style textarea picker cheap.

**Action:** state explicitly either (a) pickers are bridge-level and mount on
both adapters — discharges the outstanding COMPOSER.md promise, keeps UX parity
when `wysiwyg_composer` is off or killed, and lets increment 8 land independent
of the Milkdown adapter — or (b) pickers are WYSIWYG-only and the COMPOSER.md
autocomplete promise remains an open item. (a) is recommended.

### E. Make the CSP spike measure enforcement, not DOM shape

Under CSP, `style-src 'self'` blocks runtime `<style>` elements and
parser/`setAttribute` `style=""` writes — but **not** CSSOM property writes
(`el.style.width = …`) or constructable stylesheets, even though those serialize
as inline style attributes in the DOM. prosemirror-tables sets column widths via
CSSOM (`updateColumnsOnResize`), so tables may pass unpatched; conversely an
`adoptedStyleSheets` theme passes CSP while violating the repo's
committed-static-CSS rule. As folded, §1/§3.4's blanket ban on "inline `style=\"\"`
attributes" would wrongly condemn CSP-legal CSSOM styling.

**Action:** restate the constraint as (1) zero CSP violations under the real
header — which the §7.2 smoke test already asserts — and (2) no runtime-injected
CSS *rules* regardless of CSP legality. The spike should assert on violation
reports, not on grepping the DOM for style attributes.

## Minor

- **Pasted-URL chips (§2.4):** the chip displays `#general`/title while the
  stored link text stays the URL — with cards off, the rendered post shows a raw
  URL where the editor showed a chip. Either rewrite link text on conversion or
  render pasted chips with URL text; specify which.
- **Mention cap UX:** `MentionParser::MAX = 10` — an 11th mention chip looks
  identical but never notifies. Mark over-cap mentions in the editor, or state
  that the silent cap is accepted.
- **Flag-gating tests, named per repo convention:** `rich_composer=false` ⇒
  `GET /composer/suggest` 404s (AppFeatureFlagTest style); `wysiwyg_composer=false`
  ⇒ layout emits no editor bundle (server-side assertable; `templates/layout.php:75`
  pattern).
- **Committed-bundle provenance:** ADR 0013 should state how the committed
  `public/assets` bundle is reviewed and rebuilt (lockfile-pinned versions +
  documented build command; ideally a reproducible-rebuild check).
- **Bundle weight:** consider lazy-loading the editor (dynamic import on composer
  focus) so thread pages don't pay Milkdown's cost before the user writes.
- FYI, no change needed: `ContentReferenceService::capture()` already runs for
  `dm_message` sources (`src/Service/DirectMessageService.php:246`), so tag
  references will flow from DMs automatically once `extract()` learns `/tags/`.

## Second-pass verdict

The fold-in is faithful and the doc is implementable as sequenced. Fold **A and
B** in before writing the ADR/implementation plan — both are one-paragraph spec
changes but security-relevant. C–E are precision fixes that will keep the spikes
and the linker implementation from wrong turns.
