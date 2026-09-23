# Image Upload Reliability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` or, when selected by the user, `superpowers:subagent-driven-development` to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Status:** Implemented locally on `fix/image-upload-reliability` from `12db9821`, in the isolated `image-upload-reliability` worktree. Tasks 1–6 and all local Task 7 checks are verified; evidence and test-resource cleanup are complete. Actual iPhone validation remains open. The user authorized committing, pushing to main, and production deployment on 2026-09-23; Task 8 release execution is underway. See the [implementation evidence](../../evidence/image-upload-reliability/README.md).

**Goal:** Let members attach supported images up to the documented 5 MiB limit, understand failures, recover without losing their text, and publish a reply only after its selected images are ready or explicitly removed.

**Architecture:** Repair the existing PHP upload boundary, shared composer, Milkdown adapter, and posting services. Keep canonical Markdown, temporary attachment storage, transactional finalization, authorization-gated delivery, ordinary form submission, and existing routes. Add a small server-side guard against unfinished upload references and a real HTTP test lane using the production Dockerfile.

**Tech Stack:** PHP 8.2+, Apache, MySQL/MariaDB, GD, vanilla JavaScript, the installed Milkdown/ProseMirror packages, PHPUnit, and Playwright. Production runs through Cloudflare Workers/Containers with R2-backed media storage.

**Spec:** [DECISIONS §6 #6](../../../DECISIONS.md), [PRODUCT_DESIGN §13](../../../PRODUCT_DESIGN.md), [COMPOSER §§7, 9, 12, 14–15](../../../COMPOSER.md), [PHASE_3_PLAN §§8–9](../../../PHASE_3_PLAN.md), and [ADR 0020](../../adr/0020-composer-shell-follow-ups.md). The repair contract in this document makes the investigation findings actionable without changing those locked decisions.

## Global constraints

- The application default remains **5,242,880 bytes per image**, with JPEG, PNG, GIF, and WebP accepted. Existing 4096×4096 dimension limits, 24,000,000-pixel guard, output-size cap, content sniffing, and GD re-encoding remain enforced.
- This is a repair to an existing available feature; do not add a new feature flag, database migration, application framework, storage service, or image editor.
- Preserve server-rendered forms, source mode, the plain-textarea fallback, strict CSP, the shared shell, and canonical Markdown. No inline scripts/styles or client Markdown rendering engine.
- Preserve authentication, account-state restrictions, CSRF, upload rate limits, ownership, private-board/DM read gates, and finalize-on-publish transactions. An oversized-request diagnostic may reject a request before CSRF; it must never allow a write around CSRF.
- Preserve typed text, title, board, recipients, anonymous choice, and the current idempotency key on a blocked send. Do not queue a send for automatic execution after an upload finishes.
- The user may deliberately send without a failed image by removing it. A failed or interrupted image must not disappear silently from the member's intended submission.
- Apply shared composer behavior to reply, new-topic, existing edit, and DM mounts. Do not invent an absent DM-edit endpoint to satisfy the surface matrix.
- Keep avatar, branding, and optional non-image uploads compatible with any shared request/runtime change; their unrelated UX is outside this repair.
- Tests use dedicated disposable databases and storage. PHPUnit bootstrap and browser preparation reset databases; never point them at production, a personal development database, or a shared active browser database.
- Publish only sanitized evidence. Never include cookies, CSRF/session values, credentials, request bodies containing member content, inherited environment dumps, IP addresses, or geolocation in reports.
- A green local result is not production or Safari evidence. Record each unexercised lane explicitly.

## Findings and evidence boundaries

Investigation baseline: repository commit `12db9821`, 2026-09-23. Recheck the checkout and deployed version before implementation; these observations are dated evidence.

| ID | Finding | Evidence and confidence | Owner |
|---|---|---|---|
| F1 | PHP's upload ceiling is below the application's 5 MiB allowance. | An existing runtime image built with an identical Dockerfile reported PHP 8.2.33, `upload_max_filesize=2M`, and `post_max_size=8M`. The Dockerfile SHA-256 matched `2f4aba31861302a343c66ecadd75afcf8d3d4ee96c0476b20e15cb6f3628acf5`. This confirms the image configuration; the running production PHP configuration was not directly inspected. | Task 1 |
| F2 | PHP upload failures lose their reason. | `Request::file()` returns `null` for every nonzero PHP upload error. `MediaController::upload()` then returns 422 with “No image was uploaded.” An oversized whole POST can also lose `_token` before dispatch and be misreported as a CSRF failure. The latter is a code-path finding to reproduce through HTTP. | Task 2 |
| F3 | A reply can be submitted before its images are ready, or after an image failed. | Current compiled assets in an isolated Chromium browser allowed a reply with text to submit during an upload and after mocked 422 rejection. Failure removed the image; a pending submission contained an `rbup-…` image destination. | Tasks 3–5 |
| F4 | Temporary image destinations are fetched as real URLs. | Browser and production observations included `GET /t/rbup-…` returning 404. Rendering an unresolved image through the server renderer produced no usable image. Temporary transport state must not become published content. | Tasks 3, 5 |
| F5 | Existing evidence does not exercise the failing infrastructure boundary. | PHPUnit constructs successful `$_FILES` entries directly; the principal browser upload journey uses a tiny PNG; `tests/prodlike/php.ini` separately raises the upload limit to 8M. The ordinary browser server and the production Apache image also differ. | Tasks 1, 6–7 |
| F6 | Cancellation and completion callbacks lack a reliable success contract. | Source inspection: Remove does not cancel its individual XHR, completion does not check the result of replacing the pending marker, and the rich adapter can report success before deferred replacement has happened. These are concrete lifecycle gaps requiring controlled race tests, not claims that every one caused this incident. | Tasks 3–4 |
| F7 | A successfully accepted upload subsequently losing its image remains unconfirmed. | Mocked successful responses retained `/media/{id}` in the editor and loaded the image. Real Safari, actual production media storage, and full upload→publish→reload were not exercised. A direct production asset hash comparison was blocked by HTTP 403. | Tasks 6–8 |

Sanitized production sequence, UTC on 2026-09-23:

| Time | Request | Request bytes | Response |
|---|---|---:|---:|
| 07:43:05.186 | `POST /upload` | 2,673,378 | 422 |
| 07:43:15.293 | `POST /upload` | 2,673,378 | 422 |
| 07:43:19.243 | `POST /t/4/reply` | 143 | 303 |

The requests came from an iPhone Safari client; the observed Worker version was `b7e77b5d-1e63-4eb3-a8e6-9f8a6e22d43d`. Request sizes include multipart overhead. The two upload requests were approximately 2.55 MiB, consistent with F1; no successful upload or `/media/…` retrieval appeared in that traced sequence. Logs do not contain the upload response body, so they alone do not identify the exact PHP error.

Temporary investigation screenshots exist at `/tmp/retroboards-readonly-upload-rejected.png` and `/tmp/retroboards-readonly-upload-rejected-with-text.png`. They show an isolated fixture with intercepted responses, not production. Do not use their existence as durable release evidence; reproduce and capture the corrected behavior under Task 7.

## Chosen repair contract

Changing only PHP configuration leaves the lost-image submission path open. Changing only JavaScript leaves valid files rejected before the application receives them. Deliver the server configuration, request diagnostics, client state handling, and server guard together.

### Limits and failure responses

- Install one shared `deploy/php-uploads.ini` in the production and production-like images: `upload_max_filesize=8M`, `post_max_size=10M`. This provides transport headroom while application validation remains authoritative at 5 MiB.
- A runtime verification gate must compare PHP's effective limits with `uploads.max_bytes`. If an operator raises the application limit, the PHP/web-server limits must be raised together; do not silently advertise a limit the transport cannot accept.
- Stamp the configured application maximum on the composer as an escaped integer. Use it for a visible “Up to 5 MiB per image” hint and immediate size checks; keep server enforcement authoritative.
- Preserve the existing `{ok, error}` response shape and add stable `code` and `max_bytes` fields for transport failures. Do not infer codes by matching English exception messages.
- Keep ordinary application validation at 422. Its size-related messages must name the configured limit, including the post-processing size cap.

| Condition | Status / code | Member-facing behavior |
|---|---|---|
| No selected file / `UPLOAD_ERR_NO_FILE` | 422 / `upload_missing` | “Choose an image to upload.” |
| `UPLOAD_ERR_INI_SIZE` or `UPLOAD_ERR_FORM_SIZE` | 413 / `upload_too_large` | State the supported limit; preserve the composer and require a smaller image or removal. |
| Whole request exceeds PHP's configured POST ceiling | 413 / `upload_request_too_large` | Return a JSON size error for `/upload` and `/upload/file`, even when PHP discarded the form fields. |
| `UPLOAD_ERR_PARTIAL` | 422 / `upload_incomplete` | “The image upload was interrupted. Retry or remove it.” |
| Missing temporary directory, write failure, or extension-stopped upload | 503 / `upload_unavailable` | State that uploads are temporarily unavailable; do not expose filesystem paths. |
| Malformed file field / unknown error | 422 / `upload_invalid` | A controlled validation response, never a warning, exception leak, or false success. |
| Application MIME, dimension, pixel, or output-size rejection | 422 / `upload_rejected` | Preserve the service's specific safe message; size messages include the limit. |
| Expired authentication/CSRF, rate limiting, non-JSON response, timeout, or network failure | Existing server status | Show an actionable failed card and retain text. Honor `Retry-After`; never retry automatically. |

Use a 120-second client upload timeout. A transfer reaching its byte count changes the label to “Processing image…”; it does not mark the image ready. A successful upload response must have `ok: true`, a positive integer ID, a matching same-origin `/media/{id}` URL, and positive dimensions. Mark ready only after the canonical image reference is committed and its preview loads. A preview failure retains the stored media ID and offers a preview retry instead of uploading a duplicate.

### Composer state and submission

Each form owns its own upload records. Do not derive readiness only from CSS classes or a global counter.

```text
uploading -> verifying -> inserting -> ready
     \           \            \
      -> failed   -> failed     -> failed

failed -> uploading           (explicit retry or replacement-file selection)
failed -> verifying/inserting (reuse an already stored media ID)
any live state -> removing -> removed
                             (abort first; confirm canonical removal before unblocking)
removing -> failed            (cleanup failed; retain a removal-retry action)
```

Each record includes a local ID, state, original `File` while available, XHR, attempt generation and `AbortController`, pending marker, committed Markdown/media ID, failure stage, recovery action, error text, and optional retry deadline. Ready and removed are the only nonblocking states. An intentional source/rich edit deleting a completed image also removes its associated record/card; it must not recreate the image later.

**Review correction:** Preview verification precedes replacing the pending marker. This keeps incomplete image intent in saved drafts during failed or pending preview loads, so reload still blocks publication and offers recovery. Ready continues to require both a loaded preview and committed canonical Markdown.

- Disable Send while any image is uploading, inserting, verifying, removing, or failed. Show the reason visibly and announce state changes through a polite live region.
- Repeat the same check in the submit handler before `_rbSubmitting` is set, and in keyboard/fallback submit helpers. Block click, Enter, Ctrl/Cmd+Enter, `requestSubmit()`, and native submit-event paths consistently.
- Never consume the idempotency key or clear drafts for a blocked attempt. Finishing an upload does not send the reply automatically.
- Failed images stay visible with a recovery action and Remove. Use Retry for transient failures while the original file is available; use “Choose image” for a restored draft with no `File` or a file that fails size/type policy. Choosing a replacement updates the same record and intended position. Removing a failed image explicitly acknowledges sending without it. Retry is unavailable until `Retry-After` expires, if supplied.
- Capture a generation for every asynchronous operation; removed, retried, destroyed, or replaced forms ignore stale callbacks. Removing a pending image aborts that image's request only.
- Track multiple images independently. Out-of-order completion cannot change their intended order, alt text, or neighboring text.
- Switching source/rich mode preserves state. Restoring a draft with an unfinished `rbup-…` image creates an interrupted-upload error requiring reselection or removal; browser `File` objects cannot survive a reload and must not be serialized.
- Do not clear unfinished references silently during draft save, rendering, or migration. Preserve the draft, expose the interruption, and reject publication server-side until resolved.

### Temporary markers and server enforcement

Retain the existing `rbup-<time>-<random>` protocol for compatibility with current drafts, but render pending rich-editor images as a non-fetching placeholder element. Ordinary media images retain their normal rendering. Source mode remains editable Markdown.

On final writes, parse Markdown image destinations with the installed CommonMark parser and reject reserved pending destinations. Plain prose, inline code, and fenced examples containing `rbup-…` remain valid. This validation runs before a posting transaction, counter update, notification, or attachment finalization. Controllers use the existing 422 anti-draft-loss response.

The guard prevents unresolved references from new topics, replies, post edits, and existing DM creation/reply paths. Draft save continues to preserve incomplete work. Previously published rows are not rewritten or deleted; any historical repair requires a separate, reviewed inventory.

## Review focus

1. **A phone photo between 2 and 5 MiB:** the production Apache image must accept it and the published image must load after reload. Tests: Tasks 1 and 6.
2. **Remove/retry/navigation while callbacks are pending:** late responses cannot insert an unwanted image, affect another form, or keep Send permanently blocked. Tests: Tasks 3–4.
3. **Reloaded drafts, source-mode edits, and code examples:** genuine unfinished uploads remain recoverable and blocked; literal examples remain publishable. Tests: Tasks 3 and 5.
4. **Expired sessions, 429, HTML errors, partial uploads, and storage failures:** specific recovery guidance preserves text and does not misrepresent failure as success. Tests: Tasks 2, 4, and 6.
5. **An accepted image after publication, restart, or access changes:** bytes persist and delivery continues to enforce current parent permissions. Tests: Tasks 6–8.

## File and responsibility map

| Area | Existing files to modify or verify | New files |
|---|---|---|
| Runtime limits | `Dockerfile`, `deploy/entrypoint.sh`, `tests/prodlike/Dockerfile`, `tests/prodlike/php.ini`, `tests/browser/playwright.config.ts`, `docs/runbooks/deployment-cloudflare.md` | `deploy/php-uploads.ini` |
| Request diagnostics | `src/Core/Request.php`, `src/Core/App.php`, `src/Core/ValidationException.php`, `src/Controller/MediaController.php`, `src/Service/AttachmentService.php` | `src/Support/UploadLimits.php`, `tests/Unit/Core/RequestUploadTest.php`, `tests/Unit/Support/UploadLimitsTest.php`, `tests/Integration/Core/AppUploadErrorsTest.php` |
| Editor marker contract | `public/assets/composer.js`, `src/client/wysiwyg/milkdown-adapter.ts`, `src/client/wysiwyg/styles.css` | `tests/browser/composer-upload-reliability.spec.ts` |
| Form state and feedback | `public/assets/composer.js`, `templates/partials/composer_shell.php`, `public/assets/app.css`, `src/Core/App.php` | No new application subsystem |
| Server publication guard | `src/Service/PostingService.php`, `src/Service/DirectMessageService.php`, `src/Service/CommunityMemoryService.php`, existing form controllers and wiki edit templates | `src/Support/PendingUploadGuard.php`, `tests/Unit/Composer/PendingUploadGuardTest.php`, `tests/Integration/Core/AppPendingUploadTest.php` |
| Existing regression suites | `tests/Integration/Core/AppImageUploadTest.php`, `AppPrivateMediaAccessTest.php`, `AppPostingTest.php`, `AppDirectMessageTest.php`, `AppComposerShellTest.php`, `AppExpandedFilesTest.php`, `AppProfileMediaTest.php`, `AppBrandingThemeTest.php`; browser composer, draft, Messages, and group-DM specs | Extend only the cases affected by this repair |
| Production-image HTTP lane | `tests/browser/package.json`, `.github/workflows/browser-evidence.yml` | `tests/uploads/compose.yml`, `tests/uploads/run.sh`, `tests/browser/upload-fixture.php`, `tests/browser/playwright.uploads.config.ts`, `tests/browser/upload-http.spec.ts` |
| Built artifacts and contract | `public/assets/dist/`, `config/assets.json`, `config/imladris-runtime-baseline.json`, `resources/imladris/manifest.json`, `COMPOSER.md` | `docs/evidence/image-upload-reliability/README.md` and sanitized evidence |

Execute Tasks 1–5 before the complete HTTP/browser gate. Task 3 defines the adapter interface consumed by Task 4. Tasks 6–7 are required completion gates. Task 8 describes the later production release; this plan does not itself authorize deployment.

## Task 1: Align runtime upload limits and test-environment configuration

**Files:** Runtime-limit row above; `tests/Unit/Core/CloudflareDeploymentContractTest.php` for existing deployment assertions. Runtime behavior is ultimately verified by Task 6, not by string assertions alone.

**Interfaces:** Produces shared `deploy/php-uploads.ini`; application `uploads.max_bytes` retains its existing meaning. No environment or configuration key is renamed.

- [x] **1. Record and retain a failing baseline.** Before changing the Dockerfile, build its image as `retroboards:upload-baseline`. Read effective `ini_get('upload_max_filesize')` and `ini_get('post_max_size')` with the entrypoint overridden. Record the image ID, source SHA, PHP version, and numeric limits, using PHP 8.2's `ini_parse_quantity()` for this probe. The pre-fix 2 MiB ceiling must fail the requirement `PHP file limit >= uploads.max_bytes`. Retain this local image for Task 6's HTTP baseline; do not publish it.
- [x] **2. Create the shared upload-only configuration.** Keep the production-like file's unrelated memory/OPcache settings, removing its duplicate upload directives.

```ini
; deploy/php-uploads.ini
; Application policy is 5 MiB; transport has room to return useful validation.
upload_max_filesize=8M
post_max_size=10M
```

```dockerfile
# In the production phpbase stage and the production-like PHP image:
COPY deploy/php-uploads.ini /usr/local/etc/php/conf.d/retroboards-uploads.ini
```

- [x] **3. Align the ordinary browser server.** Its PHP command must load the same upload-only INI in addition to normal extension configuration; do not replace the entire `PHP_INI_SCAN_DIR` or lose GD/PDO. Passing the two parsed settings as `php -d` arguments is acceptable. Tests must assert their values match the shared file, so another hidden test-only override cannot recur.
- [x] **4. Verify application overrides explicitly.** Require the effective PHP file limit to be at least the configured application limit and the POST limit to exceed the file limit, leaving multipart headroom. Use `ini_parse_quantity()` for this task's probe; Task 6's configuration gate can use the tested `UploadLimits::phpBytes()` helper added in Task 2. Document coordinated operator changes for `UPLOADS_MAX_BYTES`; do not assume a Worker variable reaches PHP unless it is actually forwarded in `worker/index.js`. The default repair needs no new Worker variable.
- [x] **5. Rebuild and verify.** Run `docker build -t retroboards:upload-reliability .`, then a read-only `php -r` probe with the entrypoint overridden so it does not mount production storage or run migrations. Expected: effective 8M/10M and the 5 MiB application default. Retain the later actual Apache HTTP proof as a required gate.
- [x] **6. Review and checkpoint the runtime change.** Confirm no credentials, storage mounts, feature defaults, or production service state were changed by the local checks.

## Task 2: Preserve upload errors and identify rejected request bodies

**Files:** Request-diagnostics row; extend `AppImageUploadTest.php` and `AppExpandedFilesTest.php`. Keep avatar/branding `Request::file()` callers compatible.

**Interfaces:**

```php
// Additive Request methods; file() keeps its current successful-file/null contract.
public const UPLOAD_ERR_INVALID = -1; // Internal sentinel, distinct from PHP errors.
public function fileError(string $key): ?int;
public function contentLength(): ?int;

// Pure helper; K/M/G are binary quantities; 0, -1, and unset mean no finite cap.
App\Support\UploadLimits::phpBytes(string|false $value): ?int;
```

`fileError()` returns `null` for an absent field, the integer PHP error for a well-formed field, and `Request::UPLOAD_ERR_INVALID` for malformed structures or an apparently successful entry with no temporary path. Map this sentinel and unknown integer errors to `upload_invalid`; preserve `UPLOAD_ERR_EXTENSION` as an infrastructure failure. Share the shape validation with `file()` so malformed fields return `null` without casting arrays or emitting warnings. `contentLength()` accepts only a nonnegative decimal server value within integer range; missing, negative, malformed, or overflowing values return `null`.

- [x] **1. Add request-level failure tests before modifying code.** Include all PHP error constants, a missing field, empty successful `tmp_name`, nested arrays, missing error, and malformed lengths. Example of the error-information regression:

```php
$request = new \App\Core\Request('POST', '/upload', files: [
    'image' => ['name' => 'photo.png', 'type' => 'image/png',
        'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0],
]);
self::assertNull($request->file('image'));
self::assertSame(UPLOAD_ERR_INI_SIZE, $request->fileError('image'));
self::assertSame(2_097_152, \App\Support\UploadLimits::phpBytes('2M'));
self::assertNull(\App\Support\UploadLimits::phpBytes('0'));
```

- [x] **2. Add kernel/controller tests for the response table.** Exercise `postFile()` with a synthetic errored entry, then assert the HTTP status, stable code, limit, and safe copy. Also construct a POST with `CONTENT_LENGTH` beyond the finite POST cap and empty POST/files; assert 413 rather than the current misleading CSRF response. Test an ordinary invalid CSRF token still yields 403 and reaches no upload write.
- [x] **3. Implement the additive request methods and pure INI parser.** Use scalar/shape validation, reject malformed numeric values safely, and cover K/M/G case-insensitively without overflow. Do not change the global meaning of `file()` or make it throw through unrelated controllers.
- [x] **4. Add a narrowly scoped oversized-body rejection immediately before CSRF validation in `App::process()`.** Only POST `/upload` and `/upload/file` qualify. A reliable length beyond a finite PHP POST limit returns the JSON 413 diagnostic and stops dispatch. Unknown/chunked length must not be invented; web-server rejections are handled by the client's non-JSON error path. This branch is a rejection, not a new CSRF exemption.

```php
$postCap = \App\Support\UploadLimits::phpBytes(ini_get('post_max_size'));
$length = $request->contentLength();
if ($request->isPost()
    && in_array($request->path(), ['/upload', '/upload/file'], true)
    && $postCap !== null && $length !== null && $length > $postCap) {
    return Response::json([
        'ok' => false,
        'code' => 'upload_request_too_large',
        'error' => 'The upload request is too large. Choose a smaller file.',
        'max_bytes' => (int) $this->config->get('uploads.max_bytes'),
    ], 413);
}
```

`App::process()` already has access to its constructor-injected `$this->config`; keep the diagnostic's `max_bytes` sourced from that application configuration. Do not add a public phpinfo/config endpoint.

- [x] **5. Map file errors in `MediaController` before invoking `AttachmentService`.** Share the mapping between the image and optional file endpoint. Preserve existing JSON keys and service validation errors; add the fields specified above. Include the configured size in input/output size messages inside `AttachmentService` without weakening sniffing/re-encoding.
- [x] **6. Add sanitized operational error classification.** Use the existing server error-log channel for upload infrastructure failures, with route, code, status, and configured numeric limits only. Do not log the file name, bytes, temp path, full request, or user credentials. Avoid a new telemetry service or schema.
- [x] **7. Run the focused PHP suites and collateral avatar/branding/file tests.** Expected: correct diagnostics, no warnings under strict PHPUnit, intact CSRF/auth behavior, and no attachment created for an intake failure. A pass here does not replace Task 6's real parser test.

## Task 3: Make editor upload insertion and removal reliable

**Files:** `public/assets/composer.js`, `src/client/wysiwyg/milkdown-adapter.ts`, `src/client/wysiwyg/styles.css`, and the new browser regression spec.

**Interfaces:** Change `replacePendingUpload(token, markdown, options?)` to return `Promise<boolean>` consistently for the textarea and rich adapters, including the TypeScript `FallbackAdapter` type. The optional third argument is `{ signal?: AbortSignal }`. Resolve true only after that replacement exists in canonical Markdown; resolve false for an aborted or destroyed target. Check the signal immediately before any deferred editor transaction, not just when scheduling it. Update every caller, including completion, failure, Remove, and alt-text editing. Keep `insertMarkdown`, normal toolbar operations, and existing mode-switch APIs compatible.

- [x] **1. Add controlled browser regressions using intercepted upload responses.** Cover delayed editor readiness, immediate successful response, source/rich switching during transfer, deletion of the marker before completion, concurrent images finishing in reverse order, and alt-text updates while an earlier update is pending. Assert canonical Markdown and rendered media, not only the chip label.
- [x] **2. Replace optimistic boolean acknowledgement with an awaited result.** The textarea implementation wraps the actual replacement result. The rich implementation waits for editor readiness, finds the current marker by parsed image destination or source range, applies the transaction, synchronizes Markdown, and verifies the replacement. Missing/destroyed targets resolve false.

```javascript
TextareaComposerAdapter.prototype.replacePendingUpload = function (token, markdown, options) {
    if (options && options.signal && options.signal.aborted) return Promise.resolve(false);
    return Promise.resolve(replaceOnce(this.ta, token, markdown));
};
```

Update existing completion callbacks to await the result and use their existing failure UI when it is false; do not infer success from scheduling. Adapter regressions in this task assert canonical insertion/removal and the returned boolean. Task 4 then connects this contract to form-owned records, keeps an accepted server media ID for insertion retry, and suppresses error messages for intentionally cancelled attempts.

- [x] **3. Stop fetching pending destinations.** Extend the installed commonmark image schema's `toDOM` for the exact reserved destination pattern and keep its original image rendering for every other destination. Replace the image schema in the commonmark plugin list; do not register two nodes named `image`.

```typescript
const pendingImageSchema = imageSchema.extendSchema((previous) => (ctx) => {
  const base = previous(ctx);
  return {
    ...base,
    toDOM(node) {
      const src = String(node.attrs.src || '');
      if (/^rbup-[a-z0-9]+-[a-z0-9]+$/.test(src)) {
        return ['span', { class: 'composer-pending-image', role: 'status' }, 'Uploading image…'];
      }
      return base.toDOM!(node);
    },
  };
});

// Import imageSchema from @milkdown/preset-commonmark. Its two plugins are
// flattened into commonmark; remove both before installing the replacement.
const commonmarkWithPendingImages = commonmark.filter(
  plugin => plugin !== imageSchema[0] && plugin !== imageSchema[1],
);
// Replace .use(commonmark) in the existing editor construction with:
// .use(commonmarkWithPendingImages).use(pendingImageSchema)
```

Keep the underlying node destination available for marker lookup/serialization. The human-facing placeholder contains no internal token. A browser request listener must assert that selecting an image produces no `/t/rbup-…` network request.

- [x] **4. Make source/rich replacement match the same reserved marker despite Markdown normalization.** Preserve adjacent text, existing media, alt escaping, selection, undo behavior, and no-op legacy edits. Use generation checks around asynchronous alt/remove operations as well as initial completion.
- [x] **5. Verify both adapters with a single shared assertion contract.** A successful replacement resolves true and produces exactly one canonical `/media/{id}` image; removal leaves neither the pending marker nor the committed reference. Missing or cancelled targets resolve false and cannot produce an “Uploaded image” success card. Assert the ready-record transition when Task 4 adds that state.
- [x] **6. Build assets for the browser run and record failing/passing evidence for the adapter cases.** Do not hand-edit hashed output files. Review this interface before implementing Task 4 against it.

## Task 4: Add per-form upload state, recovery actions, and a submission gate

**Files:** `public/assets/composer.js`, `templates/partials/composer_shell.php`, `public/assets/app.css`, `src/Core/App.php`, browser regression spec, and `AppComposerShellTest.php`.

**Interfaces:** Each enhanced form owns `_rbUploadController` with `blocksSubmit(): boolean`, `message(): string`, `remove(id): Promise<void>`, `retry(id): Promise<void>`, and `destroy(): void`. Internal `failRecord(record, message)` transitions a live record to failed and calls one `notifyState()` function. `notifyState()` dispatches `retroboards:uploads-change`; the submit controller subscribes and recomputes readiness. Each retry aborts the old attempt controller, increments `record.generation`, and creates a fresh controller for adapter operations as well as XHR callbacks.

- [x] **1. Write the failing gate tests.** Use a held route response, fill reply text, choose an image, and exercise every submission entry point. The request counter must remain zero while blocked. One representative test:

```typescript
const held: Array<import('@playwright/test').Route> = [];
await page.route('**/upload', route => { held.push(route); });
let replyPosts = 0;
page.on('request', request => {
  if (request.method() === 'POST' && /\/t\/\d+\/reply$/.test(new URL(request.url()).pathname)) replyPosts++;
});
await form.locator('[data-composer-upload-input]').setInputFiles({
  name: 'photo.png', mimeType: 'image/png', buffer: pngBytes,
});
await expect(form.locator('.composer-upload-card')).toHaveCount(1);
await expect.poll(() => held.length).toBe(1);
await expect(form.locator('.composer-send')).toBeDisabled();
const submitWasBlocked = await form.evaluate((el: HTMLFormElement) => {
  const event = new Event('submit', { bubbles: true, cancelable: true });
  el.dispatchEvent(event);
  return event.defaultPrevented;
});
expect(submitWasBlocked).toBe(true);
await held[0].fulfill({ status: 422, contentType: 'application/json',
  body: JSON.stringify({ ok: false, code: 'upload_rejected', error: 'Choose a smaller image.' }) });
await expect(form.locator('.composer-send')).toBeDisabled();
await form.getByRole('button', { name: 'Remove photo.png' }).click();
await expect(form.locator('.composer-send')).toBeEnabled();
expect(replyPosts).toBe(0);
```

Obtain `form` from the rendered authenticated reply fixture, fill its body with text, and create `pngBytes` with the existing tiny-PNG fixture. The fixture setup seeds its own user/thread rather than depending on another test's post. The example checks synchronous cancellation of the submit event and the request count after the upload/removal transitions settle. Add separate native `requestSubmit()` and keyboard tests that assert the guard's visible blocked status and no publication through the end of the journey; do not treat a single immediate zero count or a fixed sleep as proof.

- [x] **2. Implement the form-owned state machine and input checks.** Add the record before inserting its marker or starting XHR. Reject files above the server-stamped maximum locally with a visible failed card. Restrict the picker to the existing supported extensions, but tolerate an empty browser MIME type when the extension is supported and let the server sniff the content. Explain unsupported types such as HEIC/AVIF; do not silently ignore selected files.
- [x] **3. Gate all send paths and preserve the draft.** Make the following predicate common to button updates and submit guards. Register the actual submit guard early enough to stop enhancement handlers, and remove the raw `form.submit()` fallback from `requestComposerSubmit()` unless it has passed this same predicate and ordinary native validity checks.

```javascript
function uploadBlocksSubmit(form) {
    return !!(form._rbUploadController && form._rbUploadController.blocksSubmit());
}

send.disabled = !!form._rbSubmitting || markdown().trim() === '' || uploadBlocksSubmit(form);

// Before setting _rbSubmitting, disabling fields, clearing drafts, or navigating:
if (uploadBlocksSubmit(form)) {
    event.preventDefault();
    event.stopImmediatePropagation();
    showUploadStatus(form._rbUploadController.message());
    return;
}
```

`showUploadStatus(message)` writes plain text to the form's visible upload summary and existing submit live region. It does not move focus on every progress event. If a user explicitly attempts a blocked keyboard/programmatic send, reveal the summary and offer focus to the first actionable failed card.

- [x] **4. Implement Retry, Remove, timeout, and stale-response handling.** Bind every callback to the record's ID and generation; pass its attempt signal into deferred adapter edits. Remove enters blocking `removing` state, aborts the attempt and its XHR, cancels preview timers, then removes its marker/reference with a separate live cleanup operation. Enter `removed` only after canonical Markdown contains neither reference; an already absent reference is an idempotent success. If cleanup fails, retain a failed card with a removal-retry action. Retry or replacement-file selection resets only that record. `destroyWithin()` invokes upload-controller cleanup before removing the adapter; no late event may mutate a remounted Inbox or Messages form. Aborted server work may leave an unbound temporary attachment; use the existing orphan cleaner, not an unsafe new delete endpoint.
- [x] **5. Verify success before unblocking.** Validate the JSON schema, load the preview with listeners installed before setting `src`, then await the adapter with the attempt signal. Retain the pending marker until preview success so interrupted drafts stay recoverable. Keep a 30-second preview timeout. A broken preview shows “The image uploaded, but its preview could not be loaded. Retry or remove it.” Retrying preview/insertion reuses the stored ID. If its original marker is missing, only an explicit Retry may create a replacement marker at the preserved insertion position, falling back to the current caret; late callbacks may never recreate it. Mark ready and show success only after these stages finish.
- [x] **6. Implement the failure response contract.** Handle JSON and non-JSON 413, 401/403 or redirected login HTML, 429 with `Retry-After`, 5xx, offline/network error, timeout, and malformed success payloads. Keep each image's error visible. Never turn an HTTP completion event or 95% progress into ready state. Do not auto-retry authentication failures or replay publication.
- [x] **7. Restore interrupted drafts safely.** Detect the reserved generated image syntax after local/server draft restoration and source changes. Use a narrow marker scanner that skips fenced and inline code; cover its exact accepted syntax in tests and keep the server AST guard authoritative. Reconstruct failed records for unfinished references, preserve text/other images, and require reselection or removal. Do not store binary `File` data in localStorage or server drafts. Client blocking must not be applied to literal code examples.
- [x] **8. Add accessible, compact feedback.** Stamp `data-upload-max-bytes` and the size hint from configuration without another database lookup. Use escaped names, unique status IDs, a visible summary associated with Send, polite terminal-state announcements, and distinguishable non-color status text. Label controls “Retry photo.png” and “Remove photo.png”; disable reordering/alt controls until ready. Preserve 390px layout, both themes, keyboard reachability, reduced motion, and the existing composer height behavior.
- [x] **9. Run the controlled browser matrix.** Include two independent forms, multiple images with mixed success/failure, identical file names, removal while insertion/preview is pending, send attempted while removal is pending, response after abort, two retries with reversed callbacks, replacement-file selection, source/rich switching, and leaving/reopening an Inbox fragment. Verify no text, alt text, order, key, or focus is lost. Finish with a real success-path smoke in Task 6.

## Task 5: Reject unfinished image references at the server write boundary

**Files:** `src/Support/PendingUploadGuard.php`, `src/Service/PostingService.php`, `src/Service/DirectMessageService.php`, new unit/integration tests; verify `ThreadController`, `PostController`, and `ConversationController` anti-draft-loss rendering.

**Interfaces:** `PendingUploadGuard::containsPendingImage(string $body): bool` is a pure helper using the installed CommonMark AST. It recognizes only image destinations matching the reserved `rbup-<time>-<random>` pattern. Services add the body error “An image has not finished uploading. Reattach it or remove it before sending.”

- [x] **1. Add AST-level tests, including false-positive protection.** Normal media and ordinary text pass. Generated unfinished images fail even in mixed text, reference-style image syntax, or normalized Markdown. Inline/fenced code displaying the same bytes passes.

```php
self::assertTrue(PendingUploadGuard::containsPendingImage('Text ![uploading…](rbup-abc123-def456)'));
self::assertTrue(PendingUploadGuard::containsPendingImage("![photo][pending]\n\n[pending]: rbup-abc123-def456"));
self::assertFalse(PendingUploadGuard::containsPendingImage('![photo](/media/42)'));
self::assertFalse(PendingUploadGuard::containsPendingImage('The identifier rbup-abc123-def456 is an example.'));
self::assertFalse(PendingUploadGuard::containsPendingImage('`![uploading…](rbup-abc123-def456)`'));
self::assertFalse(PendingUploadGuard::containsPendingImage("```markdown\n![uploading…](rbup-abc123-def456)\n```"));
```

- [x] **2. Add HTTP behavior tests for each existing write path.** Submit a pending image from a new topic, reply, post edit, DM start/group start, and DM reply. Assert 422 and preserved originating fields. Assert no observable new post/message or changed existing body, notifications, or counters. Respect the test transaction model: do not base rollback claims solely on row counts after an inner rollback.
- [x] **3. Implement the parser guard before mutations.** Build a CommonMark environment with `CommonMarkCoreExtension`, parse with `MarkdownParser`, iterate its nodes, and inspect `League\CommonMark\Extension\CommonMark\Node\Inline\Image::getUrl()`. Do not regex the entire raw body or modify the sanitizer to permit temporary URLs.

```php
$environment = new \League\CommonMark\Environment\Environment();
$environment->addExtension(new \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
$document = (new \League\CommonMark\Parser\MarkdownParser($environment))->parse($body);
foreach ($document->iterator() as $node) {
    if ($node instanceof \League\CommonMark\Extension\CommonMark\Node\Inline\Image
        && preg_match('/^rbup-[a-z0-9]+-[a-z0-9]+$/D', $node->getUrl()) === 1) {
        return true;
    }
}
return false;
```

The installed CommonMark node API provides `iterator()`; unit-test the helper before service integration. It produces a boolean only; `PostingService::validate()` and `DirectMessageService::validateBody()` own `ValidationException` construction and context-specific preservation.

- [x] **4. Preserve existing good paths.** Completed `/media/{id}` images continue through normal ownership/finalization and permission checks. Source/no-JS users can post legitimate Markdown and code examples. Draft saves retain incomplete references for recovery, but a resumed draft cannot publish them.
- [x] **5. Run focused PHP tests plus attachment privacy/finalization, idempotency, and anti-draft-loss suites.** Exercise both valid-token and invalid-CSRF cases; the new guard must not become an authorization bypass. No migration or historical content rewrite belongs in this task.

## Task 6: Exercise actual multipart parsing in the production image

**Files:** New `tests/uploads/compose.yml`, `tests/uploads/run.sh`, `tests/browser/upload-fixture.php`, `tests/browser/upload-http.spec.ts`, and `tests/browser/playwright.uploads.config.ts`; update `tests/browser/package.json` and CI integration.

**Interfaces:** `bash tests/uploads/run.sh` builds the root Dockerfile, creates its own database/storage, seeds only that database, runs real HTTP upload/publication tests, writes sanitized evidence, and cleans its own resources on exit. `bash tests/uploads/run.sh --baseline-image retroboards:upload-baseline` instead uses the retained image and runs only the explicitly labeled pre-fix reproduction. Baseline mode records the expected failure without claiming the repaired suite passes. `playwright.uploads.config.ts` selects only the two upload specs and adds `webkit-mobile` without changing all existing browser projects.

- [x] **1. Create an isolated harness using the actual production Dockerfile.** Use Compose project `retroboards-upload-check`, MariaDB 11.4, database `retroboards_upload_http`, and local-only ports 3334 (DB) and 8024 (Apache). Set `APP_ENV=test`, `APP_URL=http://127.0.0.1:8024`, `SESSION_SECURE=false`, `MAIL_DRIVER=array`, `RUN_MIGRATIONS=true`, and explicitly nonproduction DB credentials/app key. Leave all R2 secrets and `R2_BUCKET` unset; use a dedicated named `/data` volume. Do not pass production environment wholesale.
- [x] **2. Seed without changing the production build context.** `.dockerignore` excludes tests. Mount `tests/browser/` read-only at `/var/www/html/tests/browser` in the test container and execute its seed there, with the harness database selected. Add `tests/browser/upload-fixture.php` to enable the required composer flags and create public/private topics and DM participants. The HTTP harness refuses to reset any database other than `retroboards_upload_http`; its fixture helper also permits the separate disposable `retroboards_upload_browser` database for the controlled local lane. Cleanup may remove only this Compose project's containers/volumes and separately identified task-owned fixtures.
- [x] **3. Capture the failing real-upload boundary using Task 1's retained baseline image, then run the corrected image.** Use the runner's baseline option without reverting any source changes. Generate a valid 1024×1024 PNG with uncompressed GD output and require its byte size to be greater than 2 MiB and less than 5 MiB. Use a multipart browser/API request with a real authenticated cookie jar and CSRF token. Do not construct `Request` or `$_FILES` directly in this lane.

```php
$image = imagecreatetruecolor(1024, 1024);
ob_start();
imagepng($image, null, 0);
$bytes = (string) ob_get_clean();
imagedestroy($image);
if (strlen($bytes) <= 2 * 1024 * 1024 || strlen($bytes) >= 5 * 1024 * 1024) {
    throw new \RuntimeException('The synthetic fixture does not cross the required size boundary.');
}
echo $bytes;
```

Supply bytes to Playwright as an in-memory file. Record fixture byte count, dimensions, and SHA-256, not image content from a real member.

- [x] **4. Verify the complete success transaction.** Upload → ready preview → send reply → canonical redirect → inspect rendered post → reload → GET `/media/{id}`. Assert exact ownership, finalized parent binding, correct MIME, nonempty decodable bytes, no pending token in stored Markdown, and correct public/private cache behavior. A successful `/upload` alone is insufficient.
- [x] **5. Cover size and transport boundaries.** Include a tiny image, 2.55 MiB-class image, input exactly at the 5 MiB policy boundary, one byte over it, a file above PHP's 8 MiB ceiling but below the 10 MiB POST ceiling, and a request above 10 MiB. Padding valid PNG ancillary data is suitable for precise input-size fixtures; assert dimensions and validity before sending. Cover JPEG, PNG, GIF, WebP, wrong extension/content, HEIC/AVIF rejection, empty browser MIME, malformed image data, dimension/pixel limits, and re-encoding expansion beyond the cap.
- [x] **6. Inject recoverable failures in isolated lanes.** Exercise a low PHP limit via a test-only override to prove the specific 413 diagnostic, unavailable/writable media roots through dedicated harness overrides, simulated partial-file errors at the request unit boundary, 429, expired login/CSRF, dropped network, and invalid/HTML responses. Do not claim unit-created `UPLOAD_ERR_PARTIAL` reproduces an actual interrupted Apache transfer; label the boundary tested.
- [x] **7. Verify persistence and permissions.** Restart the local app container and retrieve the already published media from its persistent test volume. For private boards and DMs, verify owner/authorized reader success and guest/nonmember denial before and after parent permission changes. Keep the production R2 restart check in Task 8 separate from this local-volume proof.
- [x] **8. Wire a repeatable command and cleanup.** Add `"evidence:uploads": "bash ../../tests/uploads/run.sh"` to the browser package. The runner sets `E2E_SKIP_WEBSERVER=1`, `E2E_BASE_URL`, and its test DB settings; installs no production dependencies; uses traps to clean only its resources; and returns nonzero on any missing lane or failure. Provide a clearly labeled diagnostic mode to retain its own failed fixture for inspection.
- [x] **9. Connect the gate to CI.** Add root `Dockerfile`, `deploy/**`, `tests/uploads/**`, and relevant PHP configuration paths to workflow triggers. Run the production-image upload lane as an isolated job, install the required browsers, and upload sanitized results. A skipped or unavailable CI run is an explicit gap, not a green check.

## Task 7: Complete browser, compatibility, and repository verification

**Files:** Upload browser specs/config, affected regression suites, built assets, Imladris baseline, `COMPOSER.md`, and `docs/evidence/image-upload-reliability/README.md`.

- [x] **1. Run controlled race coverage and real HTTP coverage as separate lanes.** The former deterministically tests ordering/failures; the latter proves transport/storage/publication. Clearly label which responses were intercepted.
- [x] **2. Run Chromium desktop 1280×800, Chromium mobile 390×844, and WebKit mobile 390×844.** Cover rich/source modes, paste/drop/file-picker, success, failure/retry/removal, and pending-send blocking. Each project passed all 47 upload cases.
- [ ] **2b. External device validation.** Run a real iPhone Safari check for photo-library selection, large photo upload, interruption, recovery, send, and reload. WebKit emulation does not replace device/browser evidence; this remains explicitly unverified.
- [x] **3. Cover shared surfaces and accessibility.** Run new-topic, inline reply, post edit, DM new/reply, and group-DM journeys. Check keyboard submission, focus after Remove/Retry, long names, multiple chips, light/dark themes, and axe serious/critical findings. With JavaScript disabled or a failed WYSIWYG load, ordinary text/Markdown submission and existing media references must remain usable. Do not claim no-JS file-picker support that the current shell does not implement.
- [x] **4. Rebuild and verify generated contracts.** Run:

```bash
npm run build
npm run check:assets
npm run test:assets
composer check:imladris
composer verify:imladris
```

If the reviewed composer/spec changes alter the Imladris application digest, compute it with `php bin/build-imladris-assets.php --print-application-digest`, update `config/imladris-runtime-baseline.json` with the reviewed change, rebuild with `composer build:imladris`, and rerun the relevant check. The original plan named the design baseline in error; this repair updates the application digest and generated manifest while preserving design and audit metadata. A future merge must recompute the digest if its application surface differs. Do not update a digest merely to conceal unrelated drift.

- [x] **5. Run focused and full PHPUnit against an explicitly verified local test database.** The repeatable verification commands are:

```bash
DB_TEST_DATABASE=retroboards_upload_test vendor/bin/phpunit tests/Unit/Core/RequestUploadTest.php
DB_TEST_DATABASE=retroboards_upload_test vendor/bin/phpunit tests/Unit/Support/UploadLimitsTest.php
DB_TEST_DATABASE=retroboards_upload_test vendor/bin/phpunit tests/Unit/Composer/PendingUploadGuardTest.php
DB_TEST_DATABASE=retroboards_upload_test vendor/bin/phpunit tests/Integration/Core/AppImageUploadTest.php
DB_TEST_DATABASE=retroboards_upload_test vendor/bin/phpunit tests/Integration/Core/AppUploadErrorsTest.php
DB_TEST_DATABASE=retroboards_upload_test vendor/bin/phpunit tests/Integration/Core/AppPendingUploadTest.php
DB_TEST_DATABASE=retroboards_upload_test composer test
bash tests/uploads/run.sh
```

Set local DB host/port/credentials deliberately before these commands. Serialize resets; never run PHPUnit and browser preparation against the same database. For existing browser suites, run `tests/browser/prepare.sh` with their dedicated database and storage paths before each independently seeded slice.

- [x] **6. Update the composer and deployment documentation with shipped behavior.** Specify upload blocking, processing/ready states, Retry/Remove semantics, interrupted-draft recovery, size units, and server-vs-application limits. Record the corrected error taxonomy and the diagnostic command. ADR 0020's unrelated optimistic-send, global-key, edit-last, and quote-chip deferrals remain unchanged.
- [x] **7. Write durable evidence.** Include source SHA, image/build identifiers, effective numeric limits, deterministic fixture dimensions/sizes, sanitized failing/passing HTTP results, browser/viewport/engine matrix, representative screenshots, canonical Markdown/HTML assertions using synthetic content, privacy checks, cleanup outcome, and explicit external gaps. Exclude full Playwright configuration/environment serialization.
- [x] **8. Review the integrated diff and finish the coverage ledger.** Run `git diff --check`; inspect generated assets and the root Dockerfile; verify each finding F1–F7 maps to passing evidence or a stated external validation requirement. Do not report the entire incident fixed solely from mocked browser tests.

## Task 8: Production release and incident closeout

**Authorization boundary:** Perform this task only after implementation verification and a separate release instruction. Writing this plan authorizes none of the production mutations below.

- [x] **1. Prepare the release record.** Identify the reviewed source SHA, all PHP/JS/image changes, local evidence, rollback image/version, and the deployment runbook. The rebuilt Apache image and frontend must be released together; a static-asset-only release cannot repair the PHP ceiling.
- [ ] **2. Deploy through the repository's Cloudflare Workers Builds workflow.** Follow `docs/runbooks/deployment-cloudflare.md`; verify the actual production deployment/version rather than treating a feature-branch build as deployed. Keep secrets and R2 mount credentials out of evidence.
- [ ] **3. Verify the running configuration and health.** Obtain the numeric PHP upload/POST settings through an authorized operational channel, without publishing phpinfo. Check final HTTP 200 responses, `/healthz`, and asset hashes from `config/assets.json`. If a hash check is blocked by 403, use the authenticated operational route and record the result; do not call it verified.
- [ ] **4. Reproduce the member journey with a designated test account.** Use a synthetic 2.55–3 MiB supported image in a designated test topic. Confirm ready state, published image after reload, actual `/media/{id}` delivery, and successful retrieval by the expected reader. Exercise one rejected oversized image and verify visible recovery plus blocked Send until Retry/Remove. Repeat photo-library selection on iPhone Safari.
- [ ] **5. Verify R2-backed durability.** Confirm the stored object and mount path correspond to the accepted test attachment. If a production restart is part of the approved release, retrieve the same image afterward; otherwise use the deployment's actual container replacement as the persistence boundary or record restart durability as unexercised. Never restart production merely because a plan contains this checkbox.
- [ ] **6. Review logs and clean up through supported operations.** Confirm successful `/upload` and `/media/{id}` responses, useful classified failures, and absence of new `/t/rbup-…` fetches. Remove only designated test content/uploads using normal supported lifecycle operations; let the existing retention/orphan cleaner handle eligible temporary objects. Verify original member content and attachments were not changed.
- [ ] **7. Close the incident with evidence or retain a precise gap.** Report which large-image, failure-recovery, source/rich, Safari, privacy, and persistence checks actually ran. If a true successful upload still loses its image, preserve its sanitized request/media ID and investigate insertion, finalization, storage reads, and access gating independently; do not attribute it to the size limit without evidence.

**Rollback:** Use the last known-good reviewed Worker/container release if the deployment fails health or corrupts the compose flow. Preserve database rows and R2 objects; there is no schema rollback in this plan. If upload availability must be disabled temporarily, report that limitation explicitly and retain read access to existing media. Disabling the rich editor alone does not fix the shared JavaScript submission or PHP-limit issue.

## Acceptance checklist

- [x] A supported image larger than 2 MiB and no larger than 5 MiB passes through the production Dockerfile's real Apache/PHP parser, is published, and remains visible after reload.
- [x] Every PHP upload error has an accurate controlled response; oversized whole requests do not masquerade as expired forms where their size can be reliably identified.
- [x] Send and every enhanced submission path remain blocked until selected images are ready or explicitly removed; successful completion never sends automatically.
- [x] Retry, Remove, mode changes, concurrent uploads, and form destruction cannot insert stale images, lose text, or leave permanent blocked state.
- [x] Success requires canonical insertion and a loaded media preview; broken insertion/preview is recoverable and never falsely labeled ready.
- [x] Temporary image markers do not trigger network image fetches or reach newly published posts/messages. Existing incomplete drafts have a recoverable path; literal code examples still work.
- [x] Backend, real HTTP, controlled browser, privacy, and shared-surface regressions pass; actual iPhone Safari and production-only checks are separately evidenced or explicitly outstanding.
- [x] Built assets, Imladris contract, documentation, CI triggers, evidence, and test-resource cleanup are complete.

## Implementation review and scope adjustments

The local implementation supplies server and client defenses, recovery behavior, compatibility checks, infrastructure parity, and a repeatable production-image HTTP gate. It introduces no data migration or historical-content repair. The [evidence](../../evidence/image-upload-reliability/README.md) maps F1–F7 to verification and records exact counts, image identifiers, and remaining external checks.

- The actual container harness found two related runtime failures: empty local volumes were mistaken for R2 mounts and left unwritable, and PHP displayed oversized-POST warnings inside JSON. The entrypoint now initializes local volume ownership; shared PHP configuration logs errors without displaying them.
- Shared wiki editing needed the same pending-image guard, image-count check, attachment finalization, preserving 422 response, and later-page redirect handling as other composers. Those paths are included and tested.
- A fresh final reviewer found loss of pending-preview intent on reload and false interrupted-image recovery inside quoted code. Both have regressions that failed before repair and pass afterward. Preview verification now comes before canonical insertion; the client scanner excludes quoted/list code containers.
- WebKit retry requires clearing a failed preview's `src` before reusing the same URL. Native XHR timeout is exercised in Chromium; the intercepted WebKit case exercises its timeout handler using an injected event, not a native network timeout.
- Identical-filename coverage exposed CSS overriding the hidden Retry button on completed cards. An explicit hidden rule fixes the display and accessibility state; both adapters are checked across all three engines.
- Minor deferred finding: hand-authored reference-style unfinished images do not receive a client recovery card. The server rejects them with input preserved, and source mode supports manual correction/removal. Uploader-generated inline markers have the full recovery flow.

Actual iPhone Safari, CI execution, production configuration/assets, and R2 durability remain unverified. Task 8 is now authorized and underway. Its [release preflight](../../evidence/image-upload-reliability/release-preflight.json) records the rollback version/image and exact local evidence; local results alone do not close the production incident.
