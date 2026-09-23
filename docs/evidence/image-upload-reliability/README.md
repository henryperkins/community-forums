# Image upload reliability — local implementation evidence

Date: 2026-09-23. Source base: `12db9821`; implementation branch: `fix/image-upload-reliability`. At the local verification timestamp, changes were uncommitted in the isolated worktree. The later release authorization and rollback baseline are recorded in [release-preflight.json](release-preflight.json). The [source inventory](source-inventory.json) identifies the implemented files by SHA-256; the base SHA alone does not identify the repairs.

This evidence uses synthetic accounts, generated images, and disposable databases. No production content or configuration was changed. Actual iPhone Safari, production deployment/assets/PHP settings, and R2 persistence remain external checks under Task 8 of the [plan](../../superpowers/plans/2026-09-23-image-upload-reliability.md).

| Final local gate | Result |
|---|---|
| Full PHPUnit | 3,024 tests / 22,928 assertions; exit 0; 6 baseline deprecations and 2 skips |
| Upload matrix against the built root image | 141 passed: 47 each on desktop Chromium, mobile Chromium, and mobile WebKit |
| Existing composer/draft/group-message browsers | 118 passed; 40 declared viewport skips |
| Generated asset checks and Worker asset tests | Current; 15 passed |
| Imladris generated contract | 24 tests / 296 assertions passed |
| Cleanup | Owned test containers/volumes removed; 34 synthetic host files removed; legacy screenshot churn restored |

| Finding | Local resolution and evidence | Remaining boundary |
|---|---|---|
| F1: PHP ceiling below application policy | Shared 8M/10M INI, effective runtime probe, real multipart boundaries | Running production configuration |
| F2: upload reasons lost | Request/error taxonomy tests, real low-limit and storage failures, JSON whole-POST 413 | Partial-transfer classification is synthetic |
| F3: send before readiness or after failure | Per-form blocking and recovery in both adapters; server AST guard on all final writes | Hand-authored reference-style markers need manual source correction |
| F4: temporary image URL fetches | Non-network pending image rendering; browser request assertions; server rejection | No historical content rewrite |
| F5: missing infrastructure proof | Root Apache image, large real images, exact boundaries, publication, restart and access tests | CI configured but not invoked |
| F6: stale callbacks and optimistic replacement | Awaited adapter contract, abort/generation checks, mixed/duplicate names, queued insertion/alt/removal tests | Native WebKit timeout and actual iPhone interruption remain untested |
| F7: accepted production upload later missing | Local real upload → publish → reload → restart, plus current permission checks | Accepted production-loss claim remains unconfirmed; iPhone and R2 checks pending |

## Runtime boundary and incident finding

The retained image built from the original root Dockerfile rejects a valid **1024×1024 PNG, 3,151,914 bytes**, with HTTP **422**, `No image was uploaded.` PHP reports `upload_max_filesize=2M`, below the application's 5 MiB policy. This reproduces the infrastructure failure through Apache multipart parsing rather than a synthetic `Request` object. The baseline's unrelated local-volume permissions were prepared by the harness so login could reach the upload boundary.

The repaired root image receives **no ownership shim**. Its entrypoint initializes its empty local volume, and its shared INI reports:

| Layer | Original | Repaired |
|---|---:|---:|
| PHP version | 8.2.33 | 8.2.33 |
| PHP individual file | 2,097,152 bytes | 8,388,608 bytes |
| PHP whole POST | 8,388,608 bytes | 10,485,760 bytes |
| Application image policy | 5,242,880 bytes | 5,242,880 bytes |

The generated PNG input SHA-256 is `9804c31d113d1ee41892fdd321296054f78c6450cfe2fce6d203942e7c27ec50`. The original and repaired image IDs and effective limits are in [baseline](baseline/runtime-limits.json), [runtime limits](runtime-limits.json), and [image ID](image-id.txt). These are local images, not production versions.

| Real multipart input | Expected HTTP result |
|---|---|
| 3,151,914-byte PNG | 200; canonical insertion, loaded preview, publication, reload |
| Valid PNG padded to 2,673,378 bytes | 200 |
| Valid PNG padded to exactly 5,242,880 bytes | 200 |
| Valid PNG padded to 5,242,881 bytes | 422 / `upload_rejected` |
| 9 MiB file, below the whole-body ceiling | 413 / `upload_too_large` |
| 11 MiB file, exceeding whole-body ceiling | 413 / `upload_request_too_large`, valid JSON |
| Temporary test-only PHP ceiling of 2 MiB | 413 / `upload_too_large` for the 3 MiB image |
| Attachment directories made non-writable | 503 / `upload_unavailable`; upload succeeds after restoration |

See the per-engine `*-multipart.json` files for measured statuses and byte counts. JPEG, PNG, GIF, and WebP delivery is content-sniffed despite renamed filenames and generic MIME metadata. A real multipart file part without a Content-Type header is accepted. Malformed images, HEIC/AVIF brands, 4097-pixel width, and a 25-million-pixel PNG header are rejected. A separate kernel regression caps output after JPEG re-encoding expands a small input; it is not mislabeled an Apache-parser test.

## Browser and publication coverage

`composer-upload-reliability.spec.ts` controls upload/media responses to test races deterministically. `upload-http.spec.ts` uses the built production image and real HTTP for multipart parsing, storage, publication, permissions, restart, and shared composer journeys.

| Project | Engine / viewport | Verification |
|---|---|---|
| desktop | Chromium, 1280×800 | See `desktop-results.txt` |
| mobile | Chromium, 390×844, touch | See `mobile-results.txt` |
| webkit-mobile | Playwright WebKit, 390×844, touch | See `webkit-mobile-results.txt`; not an actual iPhone |

The controlled cases cover both adapters: native/programmatic/keyboard submit blocking, retained text/key, strict success payloads, no temporary URL fetches, reverse completion with identical filenames, deletion before insertion, explicit retry using the accepted ID, queued alt updates, source/rich switching, abort/stale callbacks, native XHR timeout, network loss, preview timeout, removal during insertion/preview, replacement selection, interrupted draft reload, paste/drop/empty MIME, Retry-After, independent forms, and fragment destruction. Failed previews exposed a WebKit-specific retry issue; clearing the image element's source before reassigning the same URL makes retry load again. Identical-filename coverage also caught CSS making a completed card's hidden Retry button visible; the corrected rule keeps recovery controls exclusive to failed cards.

The final review found and prompted two additional repairs: pending/failed preview state now survives reload because the draft retains its marker until preview verification succeeds; client recovery also excludes literal fenced/indented code inside quote/list containers. Six regressions failed before the fixes. Chromium exercises a native shortened XHR timeout; Playwright's WebKit interception pauses that native clock, so its test dispatches the timeout event at the handler boundary. This is explicitly different from a native WebKit network-timeout test. Theme contrast checks wait for finite CSS transitions to finish rather than measuring an intermediate crossfade.

One minor review limitation remains: hand-authored reference-style unfinished images receive the server's preserving 422 response, but the narrow client recovery scanner only builds cards for the inline syntax generated by the uploader. A member must correct or remove such a reference in source mode. No unfinished reference can be published; ordinary reference-style completed images remain supported.

Real publication checks cover new topics, replies, post edits, wiki edits, DM starts/replies, and group-DM starts/replies. They assert rendered images after reload, canonical Markdown without a pending token, finalized parent binding, and expected ownership. A published 3 MiB image remains byte-identical after a container restart. Private-board media denies guests/nonmembers, grants a current member access, and denies access after membership revocation; DM media remains restricted to its conversation. This proves the dedicated Docker volume, not production R2.

Long filenames, light/dark themes, 390px containment, focus after removal, and axe serious/critical checks are covered. Representative `*-failed.png` and `*-published.png` captures show synthetic content. The older composer suites additionally exercise failed rich-editor entry/chunk loading, lazy Inbox mounting, Markdown/source submission, server drafts, group-DM workflows, and existing shell/toolbar contracts. No-JS proof covers ordinary Markdown and existing media references; there is no claim of a no-JS file picker.

## Backend and generated-contract verification

Focused tests cover all PHP upload error constants, malformed file shapes and lengths, INI quantity parsing, CSRF preservation, image/file/avatar/branding compatibility, partial-file classification, unavailable storage, and post-processing output caps. A synthetic `UPLOAD_ERR_PARTIAL` unit/kernel case is not a claim of an interrupted Apache multipart transfer.

CommonMark AST tests distinguish unfinished inline/reference images from literal prose, escaped images, indented/fenced/inline code, and complete media. Actual topic/reply/edit/DM/group-DM/wiki writes reject unfinished images with 422 and preserve input. Wiki edits on later pages retain the body/reason on that page and redirect back to the edited post after success.

Full PHPUnit, Imladris, asset checks, final review, and cleanup results are recorded in [verification.json](verification.json). The PHP baseline had six deprecations and two skipped tests; they are carried explicitly, not reported as warning-free. The generated Imladris application digest and manifest reflect this reviewed composer change; design-source and audit metadata remain unchanged.

## Repeatability and cleanup

Run `bash tests/uploads/run.sh` after installing the browser dependencies. It builds the root Dockerfile, seeds only `retroboards_upload_http`, verifies limits, runs each engine against a freshly seeded database, and traps exit to remove only its own Compose project and volumes. `--baseline-image retroboards:upload-baseline` runs only the expected original failure. `--keep` is an explicit diagnostic mode; it prints the retained fixture location.

The runtime/prodlike/browser INI is shared. The CI workflow includes a separate upload job and triggers for the root Dockerfile, deployment files, and upload harness. CI itself was not invoked from this local implementation session.

No production deployment, actual-device test, production asset-hash verification, R2 restart test, or historical-content rewrite is claimed. The original observation that an already accepted production upload later disappeared remains unconfirmed; this repair addresses demonstrated rejection, submission, and lifecycle defects and supplies the release checks needed to close the incident.

## Implementation rulings and deferred review finding

1. Task 8 remains a separate release step, as the approved plan specifies. Local success therefore leaves the production incident open until deployment and operational checks.
2. Preserve the isolated branch and worktree with uncommitted changes; the plan has no required commit step. The scratch ledger is retained because history does not yet contain these changes. Integration and a reviewed commit remain future work.
3. Execute the independent server guard (Task 5) before the client tasks while the server context is loaded. Scope and interfaces are unchanged; an unforeseen client dependency would have required reordering/rework.
4. Prepare ownership only in the retained baseline fixture so the upload-ceiling regression is reachable. This intentionally does not prove the old image can initialize a fresh volume; the repaired image must do so without a shim.
5. Refresh the application digest in the runtime baseline, correcting the plan's design-baseline filename. This is an independent repair, not an ADR 0024 adoption slice; design/audit metadata is preserved. A different merged application surface requires recomputing that digest.
6. Verify preview before committing the canonical image reference, preserving recovery after reload. The inline editor image consequently appears after preview verification; the attachment thumbnail is visible during that stage.

The fresh reviewer reported no critical findings, two important findings, one minor finding, and no declined judgments. Both important findings have failing-then-passing regression evidence. The deferred minor is the missing client recovery card for manually authored reference-style pending images; the server rejects those images with text preserved, and source mode supports correction/removal.
