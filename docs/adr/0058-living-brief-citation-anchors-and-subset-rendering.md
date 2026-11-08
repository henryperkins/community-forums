# ADR 0058: Living Brief citation anchors and subset-citation rendering

**Date:** 2026-11-08
**Status:** Accepted and implemented. Resolves ADR 0026 Finding F1 and the
deferred §4 (footnote citations resolving claims to their source posts), which
ADR 0026 required to be designed together.

## Context

ADR 0026 recorded, from code reading only, that a brief whose model cited a
strict subset of the evidence window would publish but never render:
`thread_intelligence_generations.source_post_ids` holds the evidence-pack
union, `thread_summary_sources` holds the citation union, and
`ThreadIntelligenceViewService::aiSourcesAreCurrent()` required the two to be
set-equal. A REVIEW pass on 2026-11-08 reproduced this at `eeceed78` through
the real worker: a generation citing 2 of 8 evidence posts published
successfully and `forThread()` then returned `living_brief = NULL`.

The same equality was also the load-bearing guarantee for §4's ordinal
citations: with sources listed `ORDER BY p.id ASC`, ordinal `n` names the
nth listed source only while no listed source can silently drop out.

## Decision

1. **Cited sources gate the brief; uncited evidence does not.**
   `aiSourcesAreCurrent()` now compares the summary's stored citation list
   (`thread_summary_sources`) against the currently safe cited sources, and
   additionally requires every citation to trace back to the generation's
   evidence pack. A cited source that becomes deleted, pending, or
   unreadable still suppresses the whole brief — so surviving ordinals never
   shift — while an uncited evidence post changing state has no effect.
2. **Claim citations are server-composed plain text.** The validator appends
   ` [^n]` markers to the overview's first paragraph and to each key point /
   open question, where `n` is the 1-based ordinal of the cited post in the
   ascending citation union. The model never writes markers; it only supplies
   the structured `source_post_ids` it already supplied. Each marker carries
   a leading space so an item ending in `!` cannot form the `![` image
   opener the unsafe-pattern guard rejects.
3. **Markers become anchors only in the view layer.** Stored `body_html`
   keeps the literal markers. `ThreadController::show()`, which already
   builds each source's paginated post URL, upgrades `[^n]` to
   `<sup class="living-brief-cite"><a href="…#p{id}">n</a></sup>` for
   AI-lineage briefs. Manual curator briefs are not rewritten.

## Consequences

- Subset-citation generations — the normal case under the validator's
  "at least one citation per statement group" rule — now render with exactly
  their cited posts listed as sources.
- The provenance list and the in-text anchors share one ordering (ascending
  post id), so anchor `n` always resolves to the nth listed source.
- No schema, prompt, provider, or flag changes. Existing briefs published
  before this change carry no markers in their stored text and render as
  before; markers appear from the next generation onward.
- ADR 0026 §2b (member-visible last-good status) and Finding F2 remain open
  and are not affected by this record.

## Evidence

- Unit: `ThreadIntelligenceOutputValidatorTest` pins the composed canonical
  markers, sorted-union ordinals over out-of-order citations, and the
  `Ship it! [^1]` unsafe-pattern interaction.
- Integration: `ThreadIntelligenceSurfaceTest` pins subset rendering,
  uncited-source non-suppression, cited-source suppression, and the HTML
  anchor upgrade; `ThreadIntelligenceWorkerTest` pins the end-to-end
  worker-publishes → view-renders path that F1 broke.
- The pre-fix reproduction and suite counts are recorded in the 2026-11-08
  REVIEW/IMPLEMENT session report for this change.
