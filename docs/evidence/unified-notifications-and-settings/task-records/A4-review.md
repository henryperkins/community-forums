# A4 review

Reviewed frozen change `435987d0..d6b11924`, A4 brief/report, and the combined design's shared visibility, preferences, digest, and saved-feed contracts. Applied the parent ruling that original empty filters preserve Latest discovery while explicit IDs use canonical current read access. No source, index, or HEAD changes; no browser runs or subagents.

## Verdict

**Compliance: changes required. Quality: changes required.** The main implementation satisfies the owner-scoped route, canonical selected-feed read, rail, controlled opt-out, source intersection, and aggregate delivery design. Two unhandled cases prevent accepting complete malformed-filter safety and complete no-JS filter management.

## Actionable findings

1. **[P2] Preserve JSON object/array types before validating stored filters** — `src/Support/SavedFeedFilter.php:11–12`.

   `json_decode($json, true)` converts the invalid stored shape `{"board_ids":{},"sort":"latest"}` into `['board_ids' => [], 'sort' => 'latest']`. `valid()` then accepts it as intentional all-boards discovery. The saved-feed page renders unrelated public activity, and `savedSources()` stores that expanded empty selection in the digest snapshot and delivers its activity. This violates the explicit requirement that corrupt filter shapes remain neutral/unavailable and never become unrestricted. Preserve JSON types during parsing and require `board_ids` to be a JSON array before converting the validated structure. Cover the malformed empty object in both page and digest source selection; retain acceptance of the legitimate empty JSON array.

2. **[P2] Make clearing a legacy multi-board filter an explicit submitted operation** — `templates/account/boards.php:169–177`, `src/Service/PersonalOrganizationService.php:153–154`.

   A legacy feed with multiple selected IDs renders a `multiple` select named `board_ids[]`, without an All boards option or an empty-selection marker. Deselecting every option causes an ordinary browser form submission to omit the board field entirely. The service interprets omission as preserving the existing filter, redirects successfully, and retains the previous selected IDs and digest scope. Thus a normal exposed filter edit silently fails. Give the full edit form a distinct filter-present marker or an explicit All boards operation and handle an empty selection deliberately; keep omitted filter fields on partial/opt-out requests from broadening scope. Add coverage for clearing all selections and its 422 round-trip, as well as preserving the separate digest-disable form's restricted-state exception.

## Verified evidence

- Inspected the frozen runtime/template/test diff. Named unchanged collaborator inspection covered canonical board eligibility, scope construction, Latest's remaining exclusions/query, and the shared delivery worker's final eligibility handling. No broad unchanged-code audit.
- Ran `flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --do-not-cache-result /tmp/A4ReviewRegressionTest.php` against configured `retroboards_unified_test`: **2 tests, 4 assertions, 2 expected regression failures**, 0.061 seconds. The temporary reproduction uses the transaction-rollback test harness and does not send mail.
- `/tmp/a4-review-malformed-result.json` records HTTP 200, `page_contains_unrelated: true`, a digest snapshot containing `board_ids: []` despite stored `{}`, and `digest_contains_unrelated: true`.
- The second reproduction verifies the rendered multi-select, submits the successful controls a fully cleared no-JS form would send, sees the success redirect, and finds the two original IDs still stored.
- Existing new tests inspect ownership, duplicate conflicts, form selection preservation, all four restricted states, revoked access, anonymous/pending/deleted/block exclusions, legacy multi-ID filters, saved-feed flag darkening, overlap/effective preferences, retry source deletion/disable/replacement/corruption, independent remaining sources, and terminal suppression. Those tests miss the two cases above. The implementer's passing suite and query-profile numbers are supplied evidence, not independently re-run results in this review.
- Parent-reported desktop/mobile no-JS management, restricted opt-out, populated Axe, and composer evidence was acknowledged but not rerun; parent retains the combined browser and full-suite gate.

## Remaining contract assessment

- Source IDs and filters remain owner scoped. Flags precede saved page queries and mutations, and shell lookups are lazy/guarded. Ordinary Latest keeps its existing discovery query. Selected feed results apply canonical board read access and post/thread publication, anonymity, and both block directions in the query before limits.
- Digest original/current saved filters are intersected independently. Enabled current source lookup cannot revive deleted/disabled sources. Effective thread preferences outrank board preferences; uncontrolled saved-feed targets are eligible, Instant/Off/email-disabled targets are excluded. One outer aggregate query avoids per-source duplicate posts/topics. Global schedule/pause/suppression and terminal suppressed state stay with the previously established shared worker.
- CSRF remains the global POST gate. New saved-feed/folder mutations use WriteGate except the exact owner-scoped `digest_enabled=0` reduction. The reduction neither needs source access nor permits mixed edits. Owned stale folder associations can be removed without authorizing their board. Folder/feed deletion does not delete source boards/topics.
- Repository extraction keeps single-table mutation concerns narrow; folder deletion relies on existing FK cascades for associations. Query selection uses batched membership/assignment scopes and a bounded feed SQL query; no per-result membership queries were introduced. The supplied 20-board/200-topic profile is a local sample, not a load benchmark.
- Rail custom groups precede categories, omit additional unread counters, and are skipped in composer destination mode. Existing category navigation, drawer, and footer remain intact. Form-specific error identifiers and 422 rendering generally preserve normal submitted values.
- ADR/phase/evidence publication and the final combined gate remain parent-owned per the task handoff. No finding is raised merely because those parent-owned artifacts were absent from the source commit.

## Superseding scoped re-review: `9809aba3..2c8a59cd`

**Final A4 task verdict: compliance PASS; quality PASS. Both original P2 findings are resolved.** This verdict supersedes the changes-required verdict above for A4; the parent still owns final combined verification and release evidence.

Reviewed the frozen fix diff and `A4-fix-report.md`, limited to the two findings and their direct consequences. No new actionable defect was found in this scope.

- The saved filter parser now retains JSON object/list types. Both `{}` and numeric-key objects in `board_ids` fail validation, while actual `[]` remains intentional discovery. Page reads, scheduling snapshots, and current retry-source selection share this parser. The committed page and digest regressions exercise each boundary and preserve legitimate all-board behavior.
- The added raw digest parser retains the same distinction through nested durable payloads. Both worker delivery and repository/admin replay admission use it; object-shaped source lists and filter board lists cannot first lose their JSON types through associative decoding. Invalid jobs receive the existing permanent failure classification, with no sent timestamp/message ID, and later valid jobs continue. Normal empty-list v1 payloads remain deliverable and replayable after transport failure. No payload version or query semantics changed.
- The full edit form now carries a validated `board_filter_present=1` marker. Clearing the multi-select explicitly becomes all boards; the label states that meaning. A 422 retains the cleared selection and digest checkbox. Partial requests that omit both marker and filter still preserve the previous scope. The separate digest-disable form stays marker-free, and restricted-state requests adding the marker remain mixed changes rejected by WriteGate.
- Reviewed the adapted external reproduction: collecting the full form's rendered hidden successful controls correctly models the new browser request. Retaining the old hardcoded marker-free POST as a partial update is intentional and covered by a separate committed assertion; this is not weakening the clearing requirement. The original reproduction remains preserved separately.

Evidence inspected: `/tmp/a4-fix-green.log` reports **5 tests / 59 assertions PASS**, and `/tmp/a4-fix-review-green.log` reports **2 tests / 4 assertions PASS**. The parent reports untouched no-JS desktop/mobile regressions **12/12 PASS**; the implementer reports the expanded focused selection **146 tests / 1,331 assertions PASS**. These are inspected/supplied results, not independent reruns by this reviewer. No additional unresolved doubt justified repeating suites. No browsers, source/index/HEAD changes, or subagents were used in the re-review.
