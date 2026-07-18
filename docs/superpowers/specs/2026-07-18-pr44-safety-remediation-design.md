# PR #44 Safety, Integrity, and CI Remediation

**Date:** 2026-07-18

**Status:** Approved in session; awaiting written-spec review
**Owner:** RetroBoards core

## Context

PR #44 (`admin-console-remediation`) is open, unmerged, non-mergeable against
`main`, and failing the required Browser evidence workflow. The remote PR head is
`80efe39`; the inspected local branch head is `47554dd`, four commits ahead of
the remote PR head and already merged with current `origin/main` (`084ed0c`). The
local checkout also contains pre-existing modified browser-evidence images that
must be preserved and excluded from remediation commits.

The review findings were verified against the local source, the live PR review
threads, and the failing Actions job. The release-blocking findings are:

1. a validation-failure re-render can disclose a private or pending thread
   because `ModerationController` bypasses the normal thread-read gate;
2. any moderator assigned to one board can open any user's `/mod/u/{id}` panel,
   read global moderation history, and submit warnings or notes without an
   enforced board scope;
3. admin dashboard validation payloads are overwritten by array-union defaults,
   dropping the error and rejected input;
4. board deletion previews count only visible content while deletion considers
   all thread rows, and its validation occurs outside the mutation transaction;
5. exact-multiple pagination, tag-merge impact counts, audit dates, recent-IP
   ordering, duplicate submission handling, and several controller/repository
   boundaries are incorrect;
6. API-token creation can mint another live credential when a successful POST is
   refreshed; and
7. the required verification gates are red: the PR adds twelve browser failures
   beyond the two Composer-shell failures already present on current `main`, and
   the PHPUnit suite has two reproducible Thread Intelligence concurrency
   failures.

Authority remains `DECISIONS.md` > `DESIGN.md` > `SCHEMA.md` > `ADMIN.md` and
the other surface specifications. In particular, ADMIN §1.4/§2.3 requires board
scope to be explicit, ADMIN §5.1 limits moderators to participants in their
boards, and DESIGN §13 requires both automated behavior coverage and browser
evidence for visible work.

## Scope

### In scope

- every still-valid security, authorization, functional-correctness, data-
  integrity, and named controller/repository-boundary finding on PR #44;
- focused regression coverage for each repaired behavior;
- strict board-scoped moderator panel access and writes;
- an authoritative, transaction-safe board-delete-with-move workflow;
- replay-safe API-token minting backed by additive migration `0078` and matching
  `SCHEMA.md` shape/changelog/version updates;
- the twelve PR-added browser failures, the two existing Composer-shell browser
  failures, and the two Thread Intelligence concurrency failures;
- refreshed browser evidence generated only after the complete workflow is
  green; and
- a finding-by-finding disposition update in the existing remediation history
  document, including explicit reasons for any purely stylistic comment that is
  not changed.

### Out of scope

- new admin or moderation features unrelated to the verified findings;
- widening moderator authority, adding site-wide moderator grants, or granting
  board moderators access to PII, global notes, site bans, or global audit data;
- changing feature-flag defaults;
- redesigning the visual language of the admin console;
- suppressing failures by raising Playwright timeouts, weakening assertions,
  skipping tests, or documenting red tests as "pre-existing"; and
- pushing, force-pushing, merging PR #44, resolving review threads, or posting
  review comments during implementation.

Pure consistency comments with no behavior, security, data-integrity, boundary,
or test-coverage consequence are not release blockers. They are recorded with a
brief disposition rather than expanded into unrelated refactors.

## Approaches considered

### 1. Minimal defect patches

Patch the disclosed lines, add narrow tests, and leave the affected controller
and repository orchestration in place. This is smaller but does not satisfy the
repository's required Controller -> Service -> Repository boundary and would
leave duplicated authorization/read-model logic near the repaired paths.

### 2. Make `/mod/u/{id}` admin-only

This closes the cross-board disclosure quickly, but removes the intended reduced
board-moderator workflow specified by ADMIN §5.1 and already exposed by the PR.
It also avoids rather than implements the required participant scope.

### 3. Scoped, service-first remediation — selected

Preserve the board-moderator workflow with explicit participant and board
scoping, centralize shared read gates, move affected orchestration into focused
services, keep repositories single-table, and repair the verification gates.
This is the only option that closes the findings without deleting intended
behavior or retaining the confirmed boundary violations.

## Design

### 1. Thread read authorization on moderation failures

A shared thread-read service becomes the single loader for normal thread pages
and moderation re-renders. It loads the thread with board context, rejects
missing/deleted rows, applies `BoardPolicy::canRead`, resolves private-board
membership, and enforces the existing pending-thread author/in-scope-moderator
rule. It returns the authorized thread array to the rendering boundary.

`ThreadController` and the moderation failure path both use this service. The
moderation controller no longer loads a thread repository row and passes it
directly to another controller. A failed split, merge, or move still re-renders
at 422 with typed input when the actor can read the thread; an actor who cannot
read it receives the normal non-disclosing 404.

`ModerationService::moveThread()` checks authority on the source board before
validating the destination. A legitimate destination is then checked for
existence, scope, and archived state. This ordering prevents validation details
from being computed for an actor who has no authority over the source while the
shared read gate protects every 422 rendering path.

### 2. Board-scoped user moderation

`UserModerationService` owns an actor-aware panel model and all panel action
authorization. The controller marshals the route/request values, calls one
service operation, and renders or redirects from the returned result.

For an administrator:

- the full existing account moderation history remains available;
- site warnings may omit `board_id`, and board-specific warnings may name a real
  board; and
- private account notes remain available.

For a board moderator:

- the subject must have authored a thread or post in at least one board assigned
  to the actor; historical, deleted, and pending content counts as participation
  because moderators need accountability for moderated content;
- an unrelated subject is returned as 404 so member existence and staff-surface
  availability are not disclosed;
- the panel exposes only the reduced identity summary, the actor/subject overlap
  boards, and warnings whose `board_id` is in that overlap;
- global warnings, bans, suspensions, private account notes, global audit rows,
  PII, and role controls are not queried or rendered;
- warning submission requires a `board_id` from the overlap set; the service
  revalidates both moderator assignment and subject participation before insert;
  and
- private account notes are admin-only because `user_notes` has no board scope.
  The board-moderator note form is absent and direct POSTs are forbidden.

Warnings keep their existing `warnings.board_id` field. The audit entry records
the selected board in its structured `after` payload. No new note-scope column
is introduced because a superficially scoped UI over globally scoped rows would
be misleading and would require a broader historical migration policy.

The controller catches both `ValidationException` and
`DuplicateSubmissionException`, preserves the originating form's input, and
returns a 422. Administrators continue to see the complete admin record through
the separate admin route.

### 3. Board-delete preview and mutation

The preview and mutation use one definition: every row in `threads` whose
`board_id` is the source, including pending and soft-deleted threads. The preview
service returns the authoritative count, destination options, blocked state,
labels, and selected destination. It does not use the visible denormalized
`boards.thread_count` value.

Deletion runs in one database transaction:

1. lock the source and requested destination board rows in ascending id order;
2. re-read and validate source existence, destination existence, non-self
   destination, and destination non-archived state;
3. count source threads again while the board locks prevent competing board
   lifecycle changes and foreign-key inserts from passing the locked parent;
4. require a destination exactly when the authoritative count is non-zero;
5. move every source thread row;
6. recompute the destination's visible thread/post counters and last-post cache
   from authoritative rows through a service/shared repair component;
7. delete the source board; and
8. return the actual moved-thread count for the success message.

`BoardRepository` retains only direct `boards` SQL. Cross-table recount logic
moves out of `BoardRepository::recountContent()` into a service that uses the
same predicates as `RepairService`.

### 4. Read models, pagination, and repository boundaries

The named orchestration violations move behind focused service methods rather
than one new catch-all service:

- an audit query service parses and validates filters, fetches the page and
  total, enriches actors, and returns the complete `/admin/audit` model;
- `AdminDashboardService` returns the complete dashboard model, including
  settings, feature-gated custom emoji, and preserved validation overlays;
- an email dashboard query method returns the complete `/admin/email` model;
- admin user bulk confirmation/application and directory view models move into
  the existing user-management service boundary;
- appeal failure rendering state is returned by the appeal service;
- moderation mutations and failed-render state use one service boundary per
  route;
- authorized move destinations come from the moderation service;
- tag-merge confirmation and execution go through a tag service; and
- webhook detail/deletion view-model assembly goes through the webhook service.

The audit repository reads and writes only `moderation_log`; actor enrichment is
performed in the audit query service using user-repository rows. Invalid non-
empty dates must exactly round-trip `Y-m-d`; invalid dates return an inline 422
filter error and execute no misleading date comparison.

Every paginated surface uses the already-computed total:

```text
has_next = page_end_offset < total
```

The formula is adapted to each route's existing zero- or one-based page value.
It replaces `count(rows) === per_page` on audit, email deliveries, and reports,
so an exact-multiple final page has no empty Next link.

The tag merge impact query counts every `thread_tags` association carrying the
source tag, matching the source set consumed by `mergeInto()` instead of the
public/visible tag listing predicates.

### 5. Dashboard draft preservation

Dashboard defaults are merged so caller-supplied validation context wins. The
dashboard service first creates its base model and then applies the error/old
overlay with replacement semantics. Invalid site-name and moderation-settings
POSTs return 422 with both the validation message and rejected values present in
the rendered form on desktop and mobile.

### 6. Recent IP samples

PII reveal remains admin-only and audited. Session IP rows group by packed IP
and order by `MAX(last_seen_at) DESC`; post IP rows group by packed IP and order
by `MAX(created_at) DESC`. Each list remains limited to five distinct addresses.
Tests use observations whose address byte order differs from their activity
order to prove the API returns recent observations, not numerically low IPs.

### 7. Replay-safe API-token minting

Migration `0078_api_token_idempotency.php` adds a nullable
`idempotency_key CHAR(64)` to `api_tokens` and a unique key over
`(created_by, idempotency_key)`. Existing rows remain valid because the column is
nullable. `SCHEMA.md` advances from v1.38 to v1.39, updates the `api_tokens`
shape, and adds a §9 changelog entry.

The GET form renders a cryptographically random idempotency key. Validation
failures preserve the same key so a corrected submission remains valid. The
service validates the key and inserts it in the same transaction as the token
hash and audit row. A database uniqueness conflict on the named API-token
idempotency constraint becomes `DuplicateSubmissionException`; unrelated
database errors continue to propagate normally. The controller returns HTTP 409
with a safe conflict response, no plaintext token, and no new credential. The first successful response
continues to render the plaintext directly, never through the cookie-backed
Flash mechanism. Refreshing that POST cannot mint another token.

The template removes the instruction that refreshing mints another credential
and states only that the displayed token is shown once and must be copied now.

### 8. Browser and concurrency failures

The isolated worktree first reproduces failures before changing implementation:

- run the two current-main Composer-shell cases to retain the baseline;
- run the twelve additional PR failures in their exact seeded order and inspect
  the first state divergence rather than treating later timeouts independently;
- run the new admin-remediation spec after its dedicated `prepare.sh` reset;
- run both Thread Intelligence concurrency tests individually and in the full
  suite to capture transaction/connection state at the failure boundary.

Fixes target the proven shared cause. Playwright timeouts are not raised and
assertions are not weakened. Thread Intelligence tests must preserve the second-
connection concurrency behavior rather than becoming single-connection unit
tests. If a runtime defect is exposed, the runtime is fixed; if the test harness
mis-models MariaDB isolation, the harness is corrected with evidence that the
production invariant is still exercised.

## Failure handling

- Read and scope failures use the existing 404/403 conventions without leaking
  private titles, bodies, user history, or cross-board state.
- Validation failures re-render the originating surface at 422 with errors and
  typed values preserved.
- Duplicate API-token submissions return HTTP 409 with a safe conflict, no
  plaintext, and no additional row.
- A board-delete race fails inside the transaction and leaves source,
  destination, threads, counters, and audit state unchanged.
- Audit filters fail closed on malformed dates and remain visible for correction.
- Email and report pagination never invents an extra page at exact multiples.

## Testing and completion evidence

Implementation is test-first. Every functional change starts with the smallest
automated regression that fails for the verified reason.

### Focused PHPUnit coverage

- private and pending thread move validation cannot reveal title, body, or
  rendered posts to an unrelated logged-in user;
- readable validation failures still render 422 with typed input;
- unrelated board moderators cannot open or write to a user panel;
- an in-scope board moderator sees only overlap-scoped warnings, cannot see
  notes/bans/global audit, cannot post notes, and cannot warn outside the overlap;
- administrators retain the full panel/history/actions;
- dashboard 422 responses retain site/settings errors and rejected values;
- board-delete preview counts pending/deleted threads and offers or blocks the
  correct destination state;
- concurrent board lifecycle changes cannot invalidate transactional deletion;
- post-delete counters and last-post caches match `RepairService` predicates;
- exact-multiple audit/email/report pages have no Next link;
- malformed audit dates return 422, valid inclusive bounds still work;
- tag merge preview count matches the actual association set;
- IP samples are ordered by latest observation;
- duplicate moderation actions re-render safely;
- API-token refresh/replay mints exactly one credential and one mint audit row;
- the non-failed delivery test uses a real queued/delivered row and proves no
  mutation; and
- warning draft-preservation assertions cover both the PHP and browser surfaces.

### Browser evidence

- desktop/mobile dashboard invalid settings preserve the error and typed value;
- board-moderator panel access, scoped warning selection, hidden global history,
  and rejected out-of-scope navigation are exercised in real Chromium;
- board-delete impact and destination behavior use authoritative counts;
- API-token refresh does not mint a second token;
- all repaired PR journeys remain responsive and axe-clean at the existing
  desktop/mobile projects; and
- the two Composer-shell regressions and all twelve PR-added failures pass in the
  complete serial evidence workflow.

### Final gates

1. focused PHPUnit files/methods during each red-green cycle;
2. syntax checks for every changed PHP file;
3. migration parser/status checks and schema documentation review;
4. `composer test` with zero failures, errors, warnings, or risky tests;
5. focused Playwright desktop/mobile checks;
6. complete `cd tests/browser && npm run evidence` with zero failures;
7. inspect refreshed screenshots and ensure no serious/critical axe findings or
   browser-console errors;
8. `php bin/console repair` against the documented throwaway browser database,
   followed by direct counter parity checks;
9. `git diff --check` and an audit of changed/untracked files; and
10. verify the original checkout's pre-existing evidence-image modifications
    remain unchanged.

Only after every gate is green is the branch eligible to be pushed for review.
Push, PR updates, review-thread resolution, and merge still require an explicit
user instruction at that point.

## Isolation and delivery

After the written spec and implementation plan are approved, implementation
runs in a linked worktree on a dedicated remediation branch created from the
current local PR head plus the approved planning commits. The original dirty
checkout is not used for test evidence or source edits. Each behavior family is
implemented and verified independently before the complete gates run.

The existing PR history/disposition document is updated with the final outcome
for every open review finding. Pure redirect-helper consistency comments may be
recorded as no-behavior deferrals; the redundant panel staff check is naturally
removed by the actor-aware panel-service refactor.
