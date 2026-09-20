# Unified notifications and account-settings repair design

**Date:** 2026-09-20

**Status:** Approved repair scope; implementation and verification in progress. The [combined evidence index](../../evidence/unified-notifications-and-settings/README.md) records completion separately from the design contract.

**Source baseline:** `7257ca42`

**Scope approved by the user:** Unify existing notification features, fix every notification audit finding, and incorporate all nine supplied account-settings findings.

## Purpose and scope

Members need one reliable answer to “what needs my attention?” across the header, notification page, Notices pane, subscriptions, and email. They also need settings operations that preserve their work and respect their account state. Operators need delivery records that distinguish successful sends from suppressed, unavailable, and failed work.

This design fixes existing behavior and completes already exposed saved-feed/folder controls. It does not add the deferred event-by-channel preference matrix, quiet hours, digest preview, member test-send, suppression recovery, email editing, or a new staff alert inbox. ADR 0014 and ADR 0021 remain the accepted carryover authorities. [Proposed ADR 0035](../../adr/0035-member-settings-completion-carryovers.md) records the newly identified security-activity and email-discoverability gaps and cross-references the username-editing and notification-dropdown gaps; none is silently included as a new feature implementation or described as shipped.

Authority remains `DECISIONS.md` → `PRODUCT_DESIGN.md` → `SCHEMA.md` → `USER.md` / `ADMIN.md` / `COMMUNITY.md`. Relevant decisions include ADR 0006 (lifecycle), ADR 0008 (email-domain blocking), ADR 0014 (notification carryover), ADR 0028 (Notices), and ADR 0032 (shared chrome).

## Evidence and audit coverage

Both audits inspected baseline `7257ca42`. Their temporary reports are `/tmp/retroboards-notification-audit-20260920/report.md` and `/tmp/retroboards-settings-audit-20260920/review.md`. They are supporting evidence, not prerequisites for executing this design: the defects and expected outcomes are reproduced below. In the table, N refers to the notification audit and A to the account-settings audit.

| Finding | Required outcome | Implementation owner |
|---|---|---|
| N1, critical: digest leaks revoked private content and blocked actors | Recheck recipient access and blocks at selection and every send/retry | N1, N3 |
| N2 / A2, major: lists, bell, and subscriptions leak private titles; unsubscribe fails | Shared visibility rules; owner-scoped removal without content access | N1, N2 |
| N3, major: DM clicks always fail | Resolve the owned conversation notification using the conversation read contract | N2 |
| N4, major: “Not set (UTC)” prevents digests | Explicit UTC normalization plus legacy NULL fallback | N2, N3 |
| N5, major: failed digests cannot be recovered and can be falsely marked sent | Replayable digest jobs, ordinary retries, honest terminal states | N3 |
| N6, major: mobile timestamps crush text | Shared responsive rows with secondary relative time | N4 |
| N7, major: unread history beyond 30 rows is stranded | All/Unread filters, keyset history, direct owned-ID resolution | N1, N2, N4 |
| N8, minor: displaced badge, no initial/no-JS count | Visible server-rendered bell and shared count updates | N5 |
| N9, minor: missing accessible unread state, inconsistent bulk action | Same semantic row and action states in both locations | N4 |
| N10, minor: prose link distinguishable only by color | Persistent non-color link treatment in both themes | N4 |
| A1, critical: deactivate/reactivate clears restrictions and bypasses deletion | Locked, explicit lifecycle transitions; no moderation/deletion downgrade | A1 |
| A3, major: invalid TOTP removes verification form | Pending enrollment remains confirmable after errors and reload | A2 |
| A4, major: saved feeds/folders and digest setting are inert | Openable feeds, usable rail groups, working digest inclusion and management | A4 |
| A5, major: avatar operations lose unsaved profile text | Preserve all profile fields on upload/remove success and failure | A3 |
| A6, minor: organization errors reset selections | Field-specific 422 responses retaining each form's draft | A4 |
| A7, minor: impossible password prompts for passwordless members | State-aware forms and a working first-password path | A2 |
| A8, minor: mobile settings navigation displaces forms | Collapsed native section chooser with the current section visible | A5 |
| A9, polish: raw user agents are primary session labels | Recognizable browser/OS labels with raw details secondary | A5 |

The notification audit's full PHPUnit run passed 2,780 tests / 20,348 assertions, with six deprecations and one skip. The supplied account review reports the same full-suite result and 13 passing / 11 skipped account browser tests. These are baseline results, not evidence that the proposed fixes work. New regression cases and browser captures are required before closing any finding.

## Chosen approach

Three approaches were considered:

1. Consolidate templates only. Small change, but it leaves privacy, account-state, and delivery failures intact.
2. **Share existing read policy, presentation, and delivery services.** Retain notification rows, subscriptions, the email outbox, PHP forms, and polling; extract the small boundaries that currently disagree. This is the selected approach.
3. Introduce an event bus and replace the notification/preferences platform. This adds migration and operational work without being necessary for the approved scope.

Two bounded implementation workstreams share this design:

- [Notification implementation and overall delivery order](../plans/2026-09-20-unified-notifications.md).
- [Account-settings repair implementation](../plans/2026-09-20-account-settings-repairs.md).

## Shared contracts

### Recipient visibility and ownership

- A recipient owns the notification/subscription row; ownership alone does not authorize its content.
- Revalidate thread/board access, deletion, publication state, and blocks before rendering labels, counting visible unread events, resolving a target, or sending email. Apply both directions of a block to actor-driven member activity: `reply`, `new_thread`, `new_post`, `mention`, `reaction`, `follow`, `dm`, and `solved`. The explicit actor-block exemptions are `mod`, `announcement`, and `badge`; their target authorization still applies. A blocked broadcasting admin cannot make a site announcement disappear from the list, unread count, or bell. Email pause/suppression still applies to announcement email.
- Reuse `ThreadReadService` as the thread read authority. Its assigned-board-moderator exception matters: `BoardPolicy::canRead()` with membership alone is not equivalent. Notification activity remains withheld while a post or thread is pending, including for recipients who can preview held content.
- Apply eligibility before list limits. The list and unread count use the same repository predicate built from the same recipient scope. Do not filter a fixed 30-row result in a template or perform one membership lookup per notification.
- Resolve DM access using the existing `ConversationController` contract: historical `membership()` can permit retained history; `isParticipant()` only describes active membership and must not replace it. With `dms=false`, DM notices and targets are unavailable. With `dms=true` and `group_dms=false`, existing group conversations remain readable and their notifications remain visible/openable; the group flag gates creation/management, not retained history. Preserve membership visibility bounds.
- Targetless `mod` rows are produced today by `AppealService::resolve()`. Link them to the member's `/appeals` view when `appeals` is enabled; do not invent an appeal ID. Only when that route is unavailable should the row offer a generic acknowledgment with no inaccessible detail.
- Mask anonymous actors before a view model or JSON result leaves the service. Do not put inaccessible titles or actor details into hidden HTML, accessibility labels, logs, or queued email snapshots.
- Content that becomes inaccessible disappears from visible lists/counts. Its owned subscription can still be turned off using a neutral “Unavailable subscription” row. Ownership checks must reject another member's ID without disclosing its existence.
- GET remains read-only. All state changes use authenticated, CSRF-protected POSTs with appropriate write-state checks. Lifecycle recovery operations have their explicit state machine rather than an indiscriminate `WriteGate` call that would prevent reactivation/cancellation. Notification opt-out is another explicit exception: an authenticated owner may reduce delivery in any account state; enabling or otherwise reconfiguring delivery still requires `WriteGate`.

### One notification presentation

Keep `/notifications` as the dedicated center and `/?pane=notices` as a compatible embedded entry. Both consume the same page model and partial. The visible section name is **Notifications**; the existing `pane=notices` URL remains valid.

Both locations have All / Unread GET filters, reachable older history, a preferences link, Mark all read, and Clear all. Mark all read is disabled when the visible unread count is zero. Clear all explicitly clears the member's notification history; it does not remove subscriptions or source content. Return paths preserve the originating surface/filter and accept only the two known local notification entry points.

Use the stronger existing Notices treatment: one type/icon/copy map, readable actor and topic text, a visible marker plus screen-reader “Unread” text, and a relative `<time datetime="…">` with the full timestamp available. At narrow widths time moves below the message instead of reserving most of the row width. Empty All and empty Unread states have different copy. The design uses existing Imladris tokens and components.

The header has a persistent notification link with an initial server-rendered count, also usable without JavaScript. The shell receives a lazy `notification_unread(): int` closure, evaluated only by surfaces displaying the count. Memoize the recipient scope and count within a request, so the dedicated bell endpoint and page do not pay twice. JSON/plain/health routes that do not display a badge incur no shell notification query. The account-menu shortcut may remain; all badges update together from the existing short-poll endpoint. Preserve the tour's `data-bell` hook on the visible primary link. Polling updates counts, not the focused list. The product-spec last-20 dropdown is not newly implemented in this repair; its remaining surface gap is recorded in ADR 0035.

### Preferences and subscriptions

Use the existing digest hour, timezone, global email pause, and per-subscription channel/frequency fields. Render accessible editable controls for existing subscription settings. A saved hour with an empty timezone means UTC; invalid non-empty zones or malformed hours produce a 422 and preserve values rather than silently changing them.

Thread overrides board, including an explicit Off row. Turning off an inaccessible owned subscription must preserve that override; deleting it could reactivate an inherited board subscription. Settings-originating changes return to settings. Global pause and automatic bounce suppression remain separate states.

Opt-out means setting an owned subscription to Off, disabling an existing channel without enabling another, turning daily digest Off, or setting global email pause to true. These reductions succeed for suspended, banned, deactivated, and deletion-pending members with a valid session; no content-read gate is needed to disable an owned subscription. Mixed requests that also enable/change delivery require `WriteGate` and fail atomically if restricted. Keep signed email-unsubscribe behavior available without login. A purged account has no authenticated settings identity, and its queued jobs must be suppressed independently. Controllers catch subscription/settings `ValidationException` and re-render the originating form at 422; the kernel does not supply this behavior.

### Durable email

Retain `email_deliveries`, its JSON payload, unique idempotency key, advisory drain lock, and retry schedule. No new queue service or transport is required.

`DailyDigestWorker` schedules a versioned job in the same transaction that advances its consumed activity window. An insertion failure cannot advance the window. The scheduler locks the recipient, derives their local calendar day, and uses `digest:{user_id}:{local_date}` as the unique job key. Remove `timezone IS NOT NULL` from the recipient-selection predicate; NULL/empty zones normalize to UTC before due-time evaluation, including legacy users who never re-save settings. Invalid non-empty stored zones are reported and skipped without aborting other recipients or advancing that recipient's window.

Scheduling runs **before** transport-configuration and verified-domain checks, while honoring operator feature availability and member eligibility/preferences. An unconfigured From or blocked domain therefore leaves a durable queued job and advances its watermark only alongside that insert; it sends nothing and consumes no retry attempt. The shared drainer owns transport guards and reports a global `blocked_reason` (`sender_unconfigured` or `domain_unverified`) in its result/CLI and the admin banner. It does not call `markQueuedBlocked()` or overwrite per-job `error` fields. SMTP never runs inside the scheduling transaction. The watermark records durable scheduling/consumption, not proof of delivery.

A daily job becomes due at the configured local hour and remains due for that local day if cron runs late. A nonexistent spring-forward hour sends at the first valid local time afterward; a repeated fall-back hour still produces one job. A timezone change cannot recreate an already scheduled local date or produce a reversed UTC activity window. Paused/suppressed windows retain the accepted no-catch-up policy.

The job stores UTC window bounds, an upper post-ID bound, and the source selection at scheduling time: daily subscriptions and enabled saved-feed definitions. It stores identifiers/filter values, not private titles, actor labels, bodies, or rendered mail. Every send/retry reloads the current recipient inside the per-message exception boundary, then checks account state, current access, blocks, preferences, suppression, and source deletion/disablement. A changed source cannot expand the queued selection. Do not truncate a large digest silently: retain the existing thread aggregation behavior and test a large window.

For instant/digest/announcement jobs, missing or NULL `user_id`, a missing/purged recipient, or `status=deleted` suppresses the job without calling the transport. `status=banned` also suppresses every attempt, including a ban imposed after an earlier transport failure. Other existing states (`active`, `suspended`, `deactivated`, `pending_deletion`) may retain mail subscriptions; a write restriction alone is not an opt-out, which is why reductions must remain available in every state. Unknown status fails closed. This keeps the existing digest state policy while making it explicit and consistent for queued notifications. Operator test-email jobs retain their separate authorized diagnostic contract; they are not rendered as instant notification mail.

`DigestService::render(?User, payload)` accepts a missing recipient defensively and returns no content. The worker identifies the missing/banned state before calling it, so the recorded reason is accurate. Wrap recipient lookup, suppression checks, payload decoding, rendering, transport, and status recording in the per-job failure boundary. An invalid job cannot abort processing a subsequent valid job. Database unavailability that prevents recording an outcome is a reported batch infrastructure failure, not a fabricated successful continuation.

`NotificationEmailWorker` dispatches explicitly by kind. `worker:email` drains supported queued kinds; `worker:digest` schedules due digests and drains due digest jobs, so existing cron usage continues to deliver and retry. Both use the existing drain lock.

Only a successful transport call produces `sent`, `sent_at`, and a transport message ID. Content or recipients no longer eligible produce existing status `suppressed`, with a machine-readable reason in `error` (`content_unavailable`, `recipient_missing`, `recipient_deleted`, `recipient_banned`, `recipient_paused`, `address_suppressed`, or `delivery_disabled`), no message ID, and no sent timestamp. Suppressed is terminal for that job: neither admin requeue nor later unsuppression replays it; new eligible activity may create new jobs. Malformed/legacy digest jobs without a reconstructable payload become `failed` with `invalid_digest_payload` or `unreplayable_legacy_digest`; admin retry rejects those permanent failures. Valid failed transport jobs remain explicitly requeueable. The implementation does not invent an original window or label a skipped job sent. This deliberately avoids an enum migration just to represent “not sent.”

Transport success followed by a process crash can still produce a duplicate retry with a non-idempotent external mailer. Document this boundary; the database prevents duplicate scheduling, not guaranteed exactly-once SMTP delivery. An unconfigured sender or domain policy block continues to fail closed without affecting in-app delivery.

### Saved feeds and folders

Complete the current advertised feature rather than merely hiding its Digest control. Provide an owner-only feed route, accessible rail shortcuts/groups, and rename/delete/remove controls for the existing saved feeds and board folders. Reuse the current latest-feed rendering and filtering conventions; no new filter language or sorting modes.

An explicitly selected board that is deleted or becomes unreadable yields an empty/unavailable feed, never “all boards.” Distinguish an originally empty board filter (intentional all eligible boards) from a filter whose selected IDs become unavailable. Lists, rail, and digest must obey that distinction.

An originally empty selection uses the existing Latest discovery rules. An
explicit selection uses current canonical thread-read permission within those
selected IDs, including readable hidden and private boards; a readable board
offered by settings must not produce an inert feed merely because general
discovery excludes it. Queued digests intersect the original and current
filter scopes separately, preserving this distinction on retries.

An enabled saved feed contributes eligible activity to the member's one daily digest at the configured hour. Global digest Off, global email pause, suppression, private access, blocks, and explicit subscription Off still apply. Effective thread-over-board email preferences outrank a saved feed: email-disabled/Off excludes activity, Instant remains instant-only, and Daily can contribute to the digest. A feed supplies daily activity when no explicit subscription controls that target. Deduplicate posts/threads that match several feeds or a subscription; aggregate the thread once. Saved-feed creation does not silently enable a user's global digest. When digest is Off, show a link to configure it. Disabling/deleting a saved feed removes it from subsequent delivery, including retries.

### Account-state safety and recoverable forms

Lifecycle changes lock and reread authoritative rows within the mutation transaction. Only an effectively active account without a pending deletion may deactivate. Only a self-deactivated account without an effective moderation restriction or pending deletion may reactivate. Deletion requests may start from active or self-deactivated state; restricted states receive an explicit refusal instead of being overwritten. Cancelling a deletion cancels the request but must retain a concurrent ban/suspension. Lifting moderation must retain any pending deletion. Expired suspensions keep their existing automatic-expiry semantics. Final-admin/owner, session invalidation, and audit requirements remain intact.

Use live site-scope restriction records when checking lifecycle recovery, as well as the locked user row; an existing status inconsistency must not permit recovery to erase an unlifted ban. Keep a documented diagnostic for pre-existing inconsistent records. No retrospective blanket reactivation is permitted.

TOTP confirmation depends on pending enrollment state, not on having a freshly returned QR/secret array. An invalid code or a reload leaves the confirmation form available for the same encrypted secret. Only an explicit authenticated restart creates a new secret. Do not automatically reveal the provisioning secret on ordinary GETs or replay passwords/OTP values after errors.

Avatar actions submit the same profile draft as the profile form. They update only the avatar and re-render the draft (200 on success, 422 on validation error); profile text remains unsaved until Save profile. Clear copy states this. All fields, including custom profile fields, must survive; an invalid file must be chosen again. Avatar paths always come from trusted persisted data, never posted values.

Passwordless accounts see Set a password in Security, backed by the existing `AccountService::setInitialPassword()` policy, and a direct link from password-required lifecycle/TOTP actions. Keep the Connections endpoint compatible. No current-password field is demanded until a password exists; no new OAuth or passkey reauthentication ceremony is introduced.

### Responsive settings and session labels

At mobile widths use a closed native `<details>` section chooser with “Settings: {current section}” as its summary. All real navigation links remain usable without JavaScript. Desktop retains the existing grouped rail. Use one link model and avoid duplicate IDs in the two responsive renderings. At 390×844, the first primary action or editable control must be in the initial viewport on Security, Profile, and Notifications.

Session rows display a concise browser/OS label with “This device,” timestamps, and revoke controls intact. Escape raw user-agent text in secondary details, with a readable unknown-device fallback. Do not infer a physical device model or claim the user agent is trustworthy security evidence.

## Implementation constraints and release proof

- PHP 8.2+, existing MySQL/MariaDB schema and prepared statements; no new application framework or runtime dependency.
- Server-rendered forms and GET navigation work without JavaScript; progressive enhancement and short polling only.
- Strict CSP; no inline scripts/styles. Escape all new member-controlled names, labels, and drafts.
- Services own policy, repositories own queries, and new services are hand-bound in `App::buildContainer()` and explicit worker construction in `bin/console`.
- Shell lookups tolerate missing tables/unreachable DB and preserve pre-setup/health behavior.
- Existing flags keep their defaults; disabling a feature hides its controls and gates its routes/worker source.
- No schema migration is required by this design. If measured query plans require a new index, use an additive numbered migration (0082 is the current latest), update `SCHEMA.md`, and add upgrade evidence before release.
- Synchronize deliberate Imladris adaptations, generated CSS, mirror source, and runtime digests through the documented build/reconciliation workflow.
- Reproduce every audit failure before fixing it; verify HTTP behavior as well as data. Tests must not rely on nested transaction rollback undoing rows in the integration harness.
- Capture fresh desktop/mobile, light/dark, keyboard, no-JS, and relevant persona evidence. Assert security and state behavior, not only screenshots. Isolate test databases and clean up fixtures, credentials, files, and generated test state.
- Release proof includes the full local PHPUnit suite, focused browser suites, Imladris checks, and a saved artifact-to-finding matrix. Record skips and limitations explicitly. External OAuth and actual mail delivery remain unproven unless separately exercised.
