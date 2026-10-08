---
target: Independent review of both Henry's Forums mockups
total_score: 14
max_score: 24
na_heuristics: 3,5,7,9
p0_count: 0
p1_count: 2
target_identity: "file:/home/ubuntu/community-forums/.impeccable/critique/evidence/mockup-review-2026-10-07/other-controls.png"
target_fingerprint: "sha256:7d18418ce1dc475ee437c2f7fdd5f315fa2554392b9048a6a5fe34e0c8dd1c70"
target_path: /home/ubuntu/community-forums/.impeccable/critique/evidence/mockup-review-2026-10-07/other-controls.png
timestamp: 2026-10-07T23-33-21Z
slug: ckup-review-2026-10-07-other-controls-png-dd9bd2e9
---
Method: independent design assessment A (`/root/independent_mockup_design`), source/detector/browser assessment B (`/root/independent_mockup_evidence`), and mobile assessment C (`/root/independent_mockup_mobile`). Each reviewed the original artwork without the other assessments' findings. A completed before B's detector findings entered synthesis.

Three independent reviewers found five issues to resolve before implementation. The parchment, evergreen and serif identity is preserved; the single-row context and calmer controls are a sound direction. The mockups still need a focused revision.

Targets: `inbox-snooze.png` (three-panel proposal) and `other-controls.png` (six-panel proposal), archived beside this report under `evidence/mockup-review-2026-10-07/`. These are raster proposals, not an implemented interface.

1. **[P1] Complete the hide/restore model.** The first mockup shows visible and indefinitely hidden topics but omits a topic hidden **Til tomorrow**. The second mockup sends users to **Snoozed** while leaving that view below the visible scope-menu area. Keep the existing Snoozed destination within the first visible choices. Draw timed and indefinite rows: both have **Show in Inbox** OFF; turning it ON clears either hide condition. Timed rows state their return condition; indefinite rows retain **Until you turn it back on**. Explain that normal Inbox filters still apply after restoration. Suggested commands: clarify + harden.

2. **[P1] Prove the one-row toolbar with complete content.** The artwork demonstrates short **For You / Activity** values, but not **Needs Answer / Commended**, a separate unread summary, narrow 320px layouts or enlarged text. The six-panel sheet omits the Inbox unread summary even while its scope menu shows two unread topics. Keep the user's one-row constraint; define content allocation and compact labels without shrinking type or sacrificing the creation/overflow controls. Produce those constrained variants before approval. This is an unresolved fitting risk and omitted information, not a measured responsive failure. Suggested commands: adapt + layout.

3. **[P2] Reconcile selection and sample counts.** In the first mockup, the only topic is selected and the footer says **1 of 1**, yet **Select all on this page** is unchecked. It must be checked in that depicted state; partial selection needs a mixed state. In the second mockup's Scope panel, the trigger says **2 topics** while three topic rows are visible behind the menu. Make sample data internally consistent. Current `public/assets/app.js:1315` synchronizes the master checkbox; the artwork contradicts that behavior rather than proving a runtime bug. Suggested command: harden.

4. **[P2] Stop shrinking the bulk command to fit.** The six-panel bulk menu's **Hide until I turn it back on** uses visibly smaller text than neighboring commands and singular wording for two selected topics. Use **Hide from Inbox** at the shared menu type size, with **Until you restore them** as supporting text. In Snoozed, offer the explicit bulk action **Show in Inbox**. A bulk verb is appropriate for mixed selections; it does not need to copy the row's binary switch. Suggested commands: clarify + typeset.

5. **[P2] Finish the shared control vocabulary.** Inbox's bare plus differs from Boards/Messages' plus-with-chevron despite opening the same **New topic / New message** menu. The first image's long pointed popovers differ from the second image's rectangular menus. Watched badges reuse a star while Watching and Starred are separate views, and several drawn stars have five points. Use one creation affordance, one restrained menu geometry, a text-only Watched badge, and the established four-point Star/Starred glyph. Suggested command: polish.

The independent design assessor's static quality score is **14/24 across six assessable heuristics** (acceptable; significant focused refinement needed). Higher scores are better. Four behavioral heuristics are unscored rather than inferred from artwork.

| Nielsen heuristic | Quality /4 | Evidence |
| --- | ---: | --- |
| System status | 2 | Selection/count examples conflict. |
| Real-world language | 2 | Timed and indefinite hiding need clearer state copy. |
| Control and freedom | n/a | Reversal, dismissal and undo cannot be exercised. |
| Consistency | 2 | Creation/menu/glyph treatments diverge. |
| Error prevention | n/a | Mutation behavior is unavailable. |
| Recognition | 2 | Snoozed recovery is below the visible menu range. |
| Efficiency | n/a | Shortcut/bulk execution is unavailable. |
| Aesthetic restraint | 3 | Calm hierarchy; pointed popovers and fitted labels remain. |
| Error recovery | n/a | No error states are depicted. |
| Help | 3 | Concise; recovery terminology needs refinement. |
| **Total** | **14/24** | **Static assessment only.** |

What works: the literary identity is preserved, page context sits beside creation on one row, and the ON/OFF examples communicate indefinite hiding more clearly than an unlabeled icon. Cognitive load remains moderate around scope selection and restoration: the visible Your queue group has five choices, seven choices are visible overall, and the recovery destination requires scrolling. That does not justify removing valid views. Prioritize the recovery view and keep clear grouping. The initial queue feels calm; a buried return path weakens confidence after hiding a topic.

Persona risks: a mobile member has no demonstrated narrow/large-text toolbar; an occasional member must find Snoozed below the visible menu; a frequent bulk user sees a master checkbox that contradicts the selected count. Help also refers to **Show** and **Sort** while those labels are absent from the compact toolbar; align Help with the visible controls.

Source-contract checks: shared creation choices match `templates/partials/subheader.php:16`; sort values match `src/Support/InboxView.php:48`; page-limited bulk IDs match `src/Controller/InboxBulkController.php:58`; a named phone plus is specified in `docs/adr/0043-create-menu-subheader.md:49`; the unread summary remains in `PRODUCT_DESIGN.md:126` and `templates/inbox.php:18`.

Implementation requirements, not proved mockup bugs: individual Tomorrow currently means 24 hours from now (`src/Controller/ThreadWorkflowController.php:119`). Bulk snooze and the # shortcut currently use Monday (`src/Service/InboxBulkService.php:41`, `public/assets/app.js:1295`). A NULL snooze deadline currently means normally visible, and the Snoozed query selects future deadlines (`src/Repository/ThreadUserRepository.php:184`, `:597`). Indefinite hide therefore needs an explicit persistent state and coordinated query/count/restore behavior. Clearing hiding restores eligibility; normal scope predicates still apply (`:538`). Decide the timed return contract before implementation. Preserve no-JavaScript forms, authorization, feature gates, and CSRF.

Evidence limits: reviewers inspected both original PNGs, the authoritative docs and current PHP/JS. Assessment B opened each PNG in an isolated Chromium page: image/png, 1536x1024 and 1024x1536, zero DOM controls. It closed its browser and did not touch app servers, database state or existing sessions. The raster-target detector attempt exited 1 with ENOENT despite independently confirmed files; a PNG-only directory scan returned [] with exit 0. This is zero scannable implementation evidence, not an automated design/accessibility pass. No detector overlay was established. Contrast compliance, CSS hit targets, responsive layout, keyboard/focus, no-JS execution, menu scrolling and restore behavior remain unverified.

Questions skipped: the user already supplied the scope, one-row constraint and two snooze intentions; this request is an independent review. No application implementation or release was performed.
