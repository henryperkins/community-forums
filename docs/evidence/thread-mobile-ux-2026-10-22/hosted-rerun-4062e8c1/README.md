# Hosted thread-view follow-up for 4062e8c1

The exact hosted rerun (parent `5600c61a-296a-4435-bf69-28576ee61d11`, child `6f5b007d-8503-4eae-b143-29b0f0008662`) reached the thread suites after the canonical asset check passed. Desktop finished with 22 passes and 2 skips. Mobile had 11 passes, 12 skips and one failure: the 820px touch test required `position: absolute`, while the approved compact grid uses relative positioning through 860px (ADR 0050 / DESIGN.md).

The old case reproduced locally before edits. Geometry and direct touch probes at 390, 820, 860, 861 and 900px confirmed the existing layout keeps one 44px menu target within the byline, without prose overlap or horizontal overflow, and opens Quote and Reactions. `geometry-before.json` retains the original measurements; its diagnostic avatar rectangle includes the whole author container. The updated regression deliberately uses only the monogram when bounding the byline, so a stretched desktop author rail cannot hide an extra toolbar row.

The test now checks the visible space and interaction contract at all five widths. Application PHP, JavaScript, CSS, the compact byline, wider prose and fingerprinted bundles are unchanged.

## Verification

- The old regression failed with the exact hosted expected/received values.
- The updated case passed all five widths with direct touchscreen activation.
- Full hosted-equivalent thread-view, rich-content and presentation suites: desktop 22 passed / 2 expected skips; mobile 12 passed / 12 expected skips. Separate fresh disposable databases and a supervised isolated PHP server were used.
- Isolated runtime and three original specs matched commit 4062e8c1 across 671 paths before the test-only edit. Text comparisons normalized LF; binary assets used exact bytes.
- Independent focused review found no issues. Its two excluded scopes remain separately tracked.
- A fresh snapshot protects 76 existing dirty/untracked paths in the primary checkout. Integration uses an explicit test/evidence allowlist and checks the primary spec against its unchanged Git baseline before replacement.

The original hosted run was still active in the upload suites when this snapshot was prepared. Its capture stage remains failed because of the stale assertion; this report does not replace that historical failure with a local pass. Final provider receipts are retained separately after publication. Physical-device checks remain pending and are not certified by desktop touch emulation. No production data was written.
