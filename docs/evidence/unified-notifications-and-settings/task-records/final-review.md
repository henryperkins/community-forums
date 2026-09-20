# Final independent branch review

Review date: 2026-09-20. Application review range: `7257ca423e01a488bc21220542d9515fa41deda2..265f9f73`. Contract: combined design, both implementation plans, and the seven recorded implementation rulings. The task reports and their final correction reviews were used as evidence and context, not as substitutes for reviewing the combined implementation.

## Scope and method

Reviewed the supplied frozen substantive package in bounded passes across runtime, templates/assets, tests/fixtures, evidence tooling, and specification/runbook changes. Mirrored/generated CSS was treated as repeated implementation, rather than three independent policy sources. Cross-task review concentrated on visibility before counts/pagination, action authorization, restricted-state reductions, source snapshots and retry eligibility, lifecycle/credential state, shared navigation/presentation, and integration of regression fixtures. The full raw-evidence package remains separate; this is not a claim to have independently replayed or visually inspected every historical artifact.

No application, index, HEAD, database, browser server, or generated assets were changed by this reviewer. No subagents or test suites were launched. Only this requested review document was written. Existing independent task reproductions and final parent-owned verification were used where they resolved the concrete concern; no additional unresolved runtime doubt justified a new reproduction.

## Strengths

- Notification lists, direct owned-ID opening, SQL unread counts and delivery share current eligibility. Filtering precedes pagination; final target resolution retains canonical read checks. Anonymous actor identifiers are removed, actor blocks cover both directions, and retained DM history respects membership bounds and feature availability. Explicit instant-event provenance survives history clearing; conservative legacy inference cannot replace a blocked original author with a later actor.
- Controller/service boundaries consistently preserve ownership, CSRF, durable current state and 422 draft rendering. Reductions have narrow forms and service predicates; mixed or escalating changes still pass the write/read gates. Thread Off overrides survive, and inaccessible subscriptions retain neutral management rows without revealing labels or links.
- Digest enqueue records recipient/day, UTC window, post bound and source definitions transactionally before transport readiness. Retry validates raw JSON structure and rechecks current recipient, content, source and preference eligibility. Original/current selected scopes intersect; malformed filters do not become All boards. Suppressed/invalid outcomes and operator replay distinguish transport failure from permanent ineligibility.
- Account repair uses locked current credentials and lifecycle facts rather than stale request entities. Pending deletion survives moderation transitions and timed expiry; recovery refuses ambiguous legacy rows. First-password, pending TOTP, avatar drafts and native profile submission retain their respective authentication, secret-disclosure and draft-ownership constraints.
- Shared notification rows/presenter and lazy unread counts reduce inconsistent surface behavior. Polling updates all count targets, primary navigation keeps the tour bell, and the responsive settings chooser shares the existing navigation model. Session labels are bounded display-only parsing; raw agent data remains escaped. The account no-JavaScript rail adaptation is explicitly recorded rather than presented as literal prototype fidelity.
- Regression coverage exercises real kernel/repository behavior, queue/retry mutation, malformed shapes, ownership, restricted states, stale state, native forms and responsive geometry. Historical red/fix/review evidence is retained with superseding verdicts, and documentation explicitly separates this slice from deferred workflows.

## Issues

### Critical

No confirmed unresolved critical application finding.

### Important

No confirmed unresolved application correctness, privacy, state, or required-flow finding in the frozen range. The parent identified a final evidence-runner environment serialization issue during closeout; the scoped correction reviewed below resolves the identified publication path, subject to its focused harness check and cleanup of existing reports.

### Minor

No new actionable minor finding. Existing bounded compatibility seams and documented rendering adaptations do not justify unrelated refactoring in this branch.

## Verification evidence and remaining acceptance work

Inspected the supplied `php-gates.json` and `phpunit.log`: build, currency check, Imladris verification and full PHPUnit commands exited 0; PHPUnit reports **2,926 tests, 22,080 assertions, six deprecations and one skip**. The deprecation locations are unchanged Database TLS/ThemeAssetScanner code; the skipped test is the fixture-free thread-intelligence migration rehearsal. These results were produced by the parent, not rerun by this reviewer. The recorded gate launch HEAD is `eeb72a9c`; the parent verified that its difference to reviewed `265f9f73` is only twelve ADR0032/LOCAL_RECONCILIATION lines, with identical runtime/test/build files. The later `54c01c93` promotion changes only reports/screenshots/logs. This explains the evidence association without claiming commands ran at a later commit.

Final combined browser execution, selected capture inspection, report sanitization, disposable-state cleanup and final canonical evidence/status promotion remain parent-owned acceptance gates. Partial suite completion is not a complete browser pass. Task-level N5/A5 final correction approvals do not replace those combined gates. The superseded mobile glyph caveat is not an established runtime defect; the parent reports the corrected capture shows both glyphs and will inspect the final combined capture.

## Declined to judge

- A new Security history product workflow: explicitly deferred; this review checks the implemented account controls and truthful deferral only.
- Email-discovery privacy and username editing: tracked carryovers outside the approved repair slice, with no claim that this branch ships them.
- A notification dropdown and full per-type preference matrix: explicitly deferred in favor of the primary route and current preference controls.
- Exactly-once SMTP delivery and real provider delivery guarantees: excluded by the transport contract; local evidence uses captured mail and preserves the documented crash/transport limitation.
- Automatic reconstruction of ambiguous historical account-state combinations: deliberately conservative/manual reconciliation remains the contract; production data diagnostics are an operator deployment gate, not a local fixture claim.
- Real external OAuth/provider ceremonies, deployment configuration and production-data/load behavior: no such environment was exercised or authorized as part of this read-only branch review. Local query-count evidence is not a production load benchmark.
- Native browser zoom equivalence: the evidence explicitly uses CSS reflow emulation; it does not claim browser UI zoom was manipulated.

## Scoped final evidence-runner correction

Reviewed the parent's frozen `/tmp/unified-report-redaction.diff`, affecting only `tests/browser/run-notifications-settings.cjs`, after the primary application review. Playwright can serialize inherited host environment values in `config.webServer.env`; publishing the original reports would therefore create an evidence disclosure risk. The correction pins deterministic browser application/provider credentials, writes raw JSON only under ephemeral scratch, removes `env` from either the single-object or array webServer configuration, and publishes only the transformed report. Publication runs in `finally` after a failed browser process too. A malformed report throws before publication while the outer `finally` removes raw scratch and records incomplete execution.

No defect was found in that scoped correction. Inspected `report-redaction-check.json`: **3/3 mocked-child orchestration cases pass** (success: 40 child calls/21 reports; failed test: two calls/two reports; malformed report: two calls/only the aggregate result), with temporary files removed. The parent additionally reports every child asserted deterministic keys/ArrayMailer and published output excluded credential sentinels. This is explicitly a harness check, not additional browser execution. The parent still owns sanitation of reports produced by the already-running old process and the final evidence integrity check. Rerunning twenty browser suite/project combinations solely for this output-path correction is not necessary. No application change is requested by this review.

## Assessment

**Spec-compliance verdict: PASS. Code-quality verdict: PASS. Ready to merge: conditional on the remaining parent-owned verification and closeout gates.** No new unresolved application acceptance blocker was found in the reviewed range or the scoped runner correction. A final merge-ready claim requires the combined browser run and selected capture inspection, final report integrity, disposable-state cleanup, and canonical evidence/status promotion to complete successfully. This review does not mark those still-running gates complete.
