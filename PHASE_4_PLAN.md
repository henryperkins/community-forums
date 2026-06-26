# RetroBoards Phase 4 Plan — Advanced Community & Content

**Owner:** Henry  
**Plan type:** Delivery baseline, release train, and formal phase closeout  
**Plan status:** **Draft — execution is gated by formal Phase 3 closeout and Milestone 0 scope approval**  
**Prepared:** 2026-06-25  
**Source hierarchy:** `DECISIONS.md` is authoritative where documents conflict; `DESIGN.md` is the product source of truth; `SCHEMA.md` owns final database shape; `ADMIN.md`, `USER.md`, `COMPOSER.md`, and `COMMUNITY.md` own their respective surfaces. P0/P1/P2 in the source documents are priority tiers, not delivery-phase numbers.

## 1. Phase objective

Turn the polished Phase 3 product into an advanced community workspace where durable topics can be triaged, discussed in small groups, discovered through social signals, and refined into reusable community knowledge.

A member should be able to:

1. Triage the Community Inbox with explicit topic status, deterministic “For You” ranking, snooze, assignment, and focused filters without losing the durable topic model.
2. Hold private group conversations with clear participant history, membership controls, unread state, notification controls, reporting, and the same safe composer used elsewhere.
3. Create richer content with tables, task lists, references, restricted files, privacy-conscious link previews, polls, custom emoji, and insertion commands while keeping Markdown canonical.
4. Follow boards and tags for discovery without confusing “follow” with notification-bearing subscriptions.
5. Browse a richer Following feed and a global Latest feed that remain topic-anchored, permission-safe, block-aware, and understandable.
6. See time-windowed contributor recognition and operator-defined badges without turning reputation into authority or pressure.
7. Turn long discussions into reusable knowledge through summaries, related-topic links, wiki-style editing, canonical answers, and audited thread split/merge operations.
8. Complete the classic community profile layer with moderated avatar uploads, safe signatures, and personal board groups where those items were not accepted in earlier phases.

An operator should be able to:

1. Configure the fixed topic-status vocabulary, assignment mode, snooze behavior, tag catalogue, group-DM limits, expanded attachment allowlist, embed providers, poll limits, and community-memory controls.
2. Manage custom badges and limited declarative award rules with preview, audit, idempotent backfill, revocation, and no arbitrary code or SQL.
3. Moderate group DMs only through participant reports and the minimum necessary context; ordinary staff must never receive a general private-conversation browser.
4. Curate or review summaries, related topics, wiki revisions, canonical answers, and split/merge operations with immutable provenance and rollback paths.
5. Diagnose feed, group-message, preview-fetch, attachment, badge, leaderboard, summary, and migration behavior through documented metrics and runbooks.
6. Disable each Phase 4 subsystem independently without taking the core forum, Phase 2 Community Inbox, or Phase 3 composer offline.

Phase 4 is a **release train**, not a big-bang release. Gate A delivers the advanced community workflow and durable-knowledge foundation. Gate B delivers the richer expression, automated context, and profile-organization extensions. Phase 4 is not formally closed until both gates are accepted or every omitted Gate B item has an approved scope-change record.

## 2. Entry gate — Phase 3 must be closed first

This plan may be refined before Phase 3 closes, but Phase 4 implementation must not begin until all of the following are true:

- Phase 3 Gate A and Gate B have recorded product-owner acceptance, or every incomplete item has an explicit deferral with owner, rationale, risk, and destination phase.
- The Phase 3 evidence index covers migrations, route/permission matrices, rich-editor fallback, media authorization, anti-abuse, accessibility, SEO, cache isolation, plugin/webhook/API operation, backup, and rollback.
- No unresolved critical or high-severity Phase 3 security, privacy, accessibility, authorization, data-integrity, upload, cache, extension, or release-operability defect remains.
- The deployed database has been reconciled against `SCHEMA.md`, including the actual Phase 3 shape for attachments, drafts, TOTP, appeals, plugin metadata, webhook attempts, API tokens, bookmark folders, and profile fields.
- Phase 3 feature flags, kill switches, worker controls, custom-CSS safe mode, cache bypasses, attachment cleanup, webhook replay, token revocation, and backup/restore procedures have been exercised.
- Baselines exist for inbox/filter latency, unread counts, notification load, DM usage and report rate, composer fallback/error rate, attachment volume and scan failures, feed/follow adoption, badge and solved-answer usage, leaderboard visits/opt-outs, search quality, report resolution, queue lag, disk growth, cache behavior, and accessibility defects.
- Product evidence supports the Phase 4 scope. At minimum, the owner has reviewed actual demand for triage, group DMs, advanced formats, board/tag discovery, time-windowed recognition, and community-memory tools rather than treating every P2 idea as automatically urgent.
- The fixed Phase 4 decisions are approved: topic-status vocabulary, assignment permissions, group-DM participant limit/history rule, follow-versus-subscribe semantics, tag ownership, attachment and embed allowlists, leaderboard windows, custom-badge rule vocabulary, wiki permissions, and summary provenance/review policy.
- Every unfinished Phase 3 obligation is placed in a **carryover ledger**. A carryover may block Phase 4, be completed before Gate A, or be explicitly moved later; it must not be silently renamed as Phase 4 work.

**Ownership boundary:** Phase 3 remains responsible for the shared composer, image and approved file pipeline, server draft sync, core anti-abuse, accessibility baseline, branding/themes, TOTP, appeals, internal vetted extensions, webhooks, admin API, and their evidence. Phase 4 extends accepted foundations; it does not rebuild or retroactively accept them.

## 3. Definition of done

Phase 4 is accepted only when all of the following are true. **A criterion describing a Gate B feature applies only if and when that feature ships in this phase; a formally re-scoped Gate B item carries its criteria to its destination (§1, §4).** (Some bullets state this inline as "when Gate B ships"; the same conditionality covers every Gate B feature — link previews/embeds, expanded non-image attachments, polls, custom emoji, the slash-command menu and GIF provider, avatar uploads/signatures, and personal board groups — not only those three.)

- Every accepted Phase 1–3 journey remains functional, permission-safe, server-rendered, and usable without JavaScript wherever the underlying action has a no-JS path.
- Every Phase 4 schema change is additive, documented in `SCHEMA.md`, tested on clean and populated upgraded installations, and reversible at the application or feature-flag level.
- The topic-state model separates **canonical workflow state**, **existing structural flags**, **personal triage state**, and **moderation state**; the UI never presents those different concepts as one ambiguous status enum.
- Canonical workflow states use the approved fixed vocabulary and transition policy. Existing pin, lock, accepted-answer, pending-review, and deletion behavior remains authoritative and cannot drift from the displayed chips.
- Expanded inbox filters return only content the requester can currently read and produce counts from the same authorization-safe query boundary.
- “For You” is deterministic, inspectable, and based only on approved signals such as replies, mentions, participation, subscriptions, and follows; it never implies opaque profiling or leaks hidden activity.
- Snoozing a thread hides it only from the approved personal inbox surfaces, appears in the Snoozed view, and reliably returns at or after the selected time. Snooze does not silently unsubscribe the user or suppress direct mentions/replies.
- Assignment follows the approved board policy, is capability-checked on every request, records who assigned whom and when, supports unassign/reassign, and cannot expose private-board membership through suggestions or counts.
- Group DMs enforce participant eligibility, current account state, blocks, invite limits, group-size limits, membership history, current-access checks, unread state, notification preferences, and message-report privacy.
- A newly added group-DM participant cannot read messages from before the approved membership boundary; a removed or departed participant cannot read future messages; every join, leave, removal, rename, and ownership transfer is recorded as a participant-visible event.
- No moderator or administrator receives unrestricted DM browsing. A report exposes only the reported message and the minimum approved surrounding context to authorized staff.
- The advanced composer still stores canonical Markdown. Tables, task lists, horizontal rules, board/thread/post references, custom emoji tokens, and any structured content blocks have documented round-trip behavior and a usable plain-text representation.
- Advanced syntax, references, polls, emoji, attachments, previews, and slash-menu insertion behave consistently across every context in which they are approved. Unsupported contexts fail explicitly rather than silently dropping content.
- Link previews and embeds use an allowlisted, asynchronous, SSRF-controlled fetch path with DNS/redirect revalidation, strict time/size limits, no user cookies, sanitized stored metadata, privacy-conscious rendering, and an operator kill switch.
- No third-party script, tracking pixel, autoplay media, or active embed loads before the member takes the approved action; an author can remove a preview without removing the underlying link.
- Expanded attachments remain restricted by a positive allowlist, content sniffing, size and decompression limits, quarantine/scanning policy, safe download headers, parent-content authorization, retention rules, and disk-pressure controls. Executable or browser-active files are never served inline.
- Poll creation, voting, changing a vote where allowed, closing, result visibility, deletion, and moderation are idempotent and authorization-safe; aggregate results do not expose individual votes unless an explicitly approved policy says otherwise.
- Custom emoji are operator-managed, uniquely named, size/dimension limited, sanitized through the media pipeline, and usable without widening the XSS or reaction-abuse surface.
- The slash-command menu inserts approved content constructs only. It never executes user-supplied code, server commands, SQL, arbitrary HTTP requests, or plugin code from text input.
- Following a board or tag affects discovery feeds and does not automatically create a notification subscription. Watching/subscribing remains a separate explicit action with the Phase 2 channel/frequency model.
- Tags have an approved creation, application, rename, merge, visibility, moderation, and deletion policy. Tag and board follows never reveal inaccessible threads through counts, feed rows, notifications, suggestions, or URLs.
- The Following and Latest feeds remain reverse-chronological or deterministically ranked, paginated, topic-anchored, block-aware, privacy-safe, and query-time by default. A materialized fan-out feed is not introduced without the approved capacity trigger.
- Removing a follower prevents future follower-list and feed relationships as designed without notifying the removed person or weakening blocks.
- The reputation ledger supports idempotent event creation, reversal/correction, board attribution where required, rebuild, and reconciliation with `users.reputation`.
- Weekly, monthly, all-time, and approved board-scoped leaderboards use the defined windows and eligibility rules, exclude opted-out users, remain recognition-only, and never grant permissions, limits, prizes, or moderation authority.
- Custom badges use a limited declarative rule set, preview the affected population before activation/backfill, award at most once per logical achievement unless explicitly repeatable, retain award/revocation history, and never run arbitrary code.
- Manual topic summaries preserve canonical Markdown, author/editor attribution, revision history, source-post references, publication state, and rollback.
- Related-topic suggestions and links apply the canonical read gate on both source and target, omit deleted/private/inaccessible content, explain why a topic is related, and allow authorized curation.
- Wiki-style posts have complete revision history, attributed edits, board-configured eligibility, revert, report, moderation, and no ability to overwrite canonical content without an auditable revision.
- Thread split/merge operations run through a dry-run plan, lock affected data, preserve post IDs, repair counters and read state, maintain old links through canonical redirects, avoid duplicate notifications, and record complete before/after evidence.
- Automated “since you last read” context, when Gate B ships, is assembled only from posts the requester can currently read, links back to its source posts, and never silently changes canonical summaries, accepted answers, or topic status.
- Private-board or DM URLs are never sent to a preview provider or remote fetch path unless the operator explicitly enables that data class, documents the processor, and passes the approved privacy review; DM previews remain disabled by default.
- Avatar uploads and signatures, when Gate B ships, use the accepted media/sanitization pipeline, respect new-user gates and viewer preferences, remain reportable/moderatable, and have safe fallback behavior.
- Custom board groups/folders, when Gate B ships, are user-private, ordered deterministically, and cannot alter authorization or unread truth.
- Every new state-changing route uses CSRF protection, centralized authorization, account-state gates, validation, idempotency where retries are plausible, and route-specific rate limits.
- Every changed shared component passes the Phase 3 accessibility baseline plus keyboard, screen-reader-critical, zoom/reflow, reduced-motion, mobile, and no-JS checks appropriate to the feature.
- Public status, tag, summary, wiki, related-topic, poll, and leaderboard surfaces have correct canonical/indexing behavior; private, personalized, group-DM, moderation, preview-fetch, draft, and tokenized surfaces are excluded from public discovery.
- Phase 4 meets the numeric latency, query, queue, disk, scan, preview-fetch, feed, summary, and error budgets approved at Milestone 0 on production-like fixtures.
- The full automated suite, migration matrix, concurrency tests, browser evidence, no-JS smoke, security/privacy tests, accessibility evidence, SEO checks, worker/cron checks, and rollback rehearsals pass.
- No unresolved critical or high-severity security, privacy, accessibility, authorization, data-integrity, private-message, community-memory, attachment, moderation, or release-operability defect remains.

## 4. Scope and release gates

### Gate A — Advanced community workflow and durable knowledge

Gate A is the minimum Phase 4 release:

- Phase 3 closeout reconciliation, Phase 4 carryover ledger, route/permission inventory, representative fixtures, numeric budgets, feature flags, and requirement-to-evidence map.
- Reconciled Community Inbox model:
  - fixed canonical topic-status vocabulary and audited transitions;
  - status chips and history;
  - filters for For You, Mentions, Replies to You, Watching, Needs Answer, Assigned, Decisions, Solved, Drafts, Snoozed, and scoped Moderation;
  - personal snooze with automatic return;
  - board-configured self/staff assignment with reassign/unassign history;
  - deterministic ranking and “Why this?” explanation for For You.
- Group DMs:
  - direct-versus-group conversation type;
  - configurable participant cap;
  - creator/owner and member roles;
  - eligible member search, add/remove/leave, rename, ownership transfer, and conversation mute;
  - membership-boundary history, unread state, notifications, blocked-user and account-state handling;
  - participant-visible system events and narrow message reporting.
- Advanced canonical content:
  - task lists, tables, horizontal rules, `#board` references, and tidy board/thread/post reference cards;
  - round-trip fixtures, sanitization, mobile toolbar behavior, keyboard access, and textarea fallback;
  - no new editor-specific source format.
- Discovery and community graph expansion:
  - moderated global tag catalogue and thread tagging;
  - board and tag follows distinct from subscriptions;
  - expanded Following feed and global Latest feed;
  - follower removal, feed filters, privacy/block enforcement, and quiet follow-activity digest controls.
- Recognition expansion:
  - production-grade reputation-event ledger and reconciliation command;
  - weekly, monthly, all-time, and approved board-scoped leaderboards;
  - leaderboard opt-out and humane presentation;
  - custom manual badges and a limited declarative auto-award rule set with preview/backfill/revoke/audit.
- Community-memory foundation:
  - manual topic summaries with revisions and source references;
  - curated and deterministic related topics;
  - wiki-style posts with edit history and revert;
  - safe moderator split/merge with dry run, canonical redirects, counter/read-state repair, and audit;
  - canonical-answer curation that builds on the accepted-answer model without auto-publishing machine-written text.
- Full Gate A moderation integration, accessibility, SEO, performance, observability, runbooks, staged rollout, rollback evidence, and product-owner acceptance.

### Gate B — Rich expression, automated context, and profile organization

These items are committed to the broader Phase 4 window. They may ship after Gate A, but Phase 4 requires acceptance or an approved re-scope for each:

- Allowlisted link previews and embeds, beginning with the approved providers, with asynchronous fetch, stored sanitized metadata, click-to-load rendering, privacy disclosure, purge, and emergency disable.
- Expanded non-image attachment allowlist, quarantine/scanning, controlled document preview where safe, download disposition, retention, and scanner/disk-pressure operation.
- Polls with single/multiple-choice rules, optional close time, vote-change policy, aggregate results, moderation, no-JS voting, and idempotent concurrency behavior.
- Operator-managed custom emoji, shortcode picker/search, reaction compatibility where approved, asset moderation, rename/disable behavior, and fallback rendering.
- Slash-command insert menu backed only by approved first-party/vetted commands; optional GIF-provider integration with explicit operator configuration, privacy notice, licensing/storage policy, and a no-provider fallback.
- Automated “what changed since you last read” context and scheduled related-topic refresh built from canonical local posts/search results, with source links, deterministic rules, privacy-safe access checks, quality review, and instant disable. No external generative model is required for Phase 4 acceptance.
- Avatar uploads, safe signatures, and personal board groups/folders where they were not already accepted as Phase 3 carryovers.
- Advanced feed organization such as saved feed filters and digest composition, provided it remains topic-anchored and does not become an addictive infinite-scroll product.
- Full Gate B hardening, documentation, evidence index, and formal Phase 4 closeout.

### Conditional carryovers — not automatically Phase 4 scope

The following may enter Phase 4 only through the carryover ledger or a signed scope change:

- Any unaccepted Phase 3 composer, server-draft, attachment, TOTP, appeals, category-moderator, theme, plugin, webhook, API, account, accessibility, SEO, cache, or operational requirement.
- Restricted non-image attachments that Phase 3 already committed. Phase 4 may expand an accepted allowlist; it must not relabel unfinished Phase 3 security work.
- Avatar, signature, custom-board-group, or profile items that were already accepted earlier; Phase 4 should extend or polish them rather than build a second implementation.
- Feed fan-out storage, external search, object storage, or other infrastructure changes. They enter only through an approved capacity-trigger decision and normally belong to Phase 6.
- Public extension packages needed solely to deliver a Phase 4 feature. Phase 4 may use the accepted first-party/vetted hook system but cannot bypass the Phase 5 ecosystem trust gate.
- Any still-unaccepted masked-anonymous-posting, `post_min_role`, board archive/reorder, password/email recovery, announcement/broadcast, or report-outcome requirement. Preserve its original phase ownership; bring it into Phase 4 only through an explicit blocking carryover or signed scope change. _(IP-retention removed 2026-06-26: it is now a firmly-owned Phase-3 deliverable — PHASE_3_PLAN P3-05 — and any unaccepted Phase-3 operational requirement is already covered by the Phase-3 guard above.)_

### Explicitly deferred beyond Phase 4

The following must not delay Phase 4 acceptance unless formally pulled in:

- Public plugin/theme marketplace, arbitrary uploaded PHP extensions, third-party sandbox/review ecosystem, extension signing/distribution, and public theme packages.
- Granular custom roles, user-defined capabilities, delegated organization administration, and broader governance mechanics.
- Passkeys/WebAuthn, additional OAuth providers, SMS authentication, mandatory member 2FA, and invitation-only identity expansion beyond existing settings.
- SSE/WebSockets, real-time chat semantics, typing indicators, read receipts per message, voice/video, ephemeral messaging, and unrestricted private-message administration.
- Meilisearch/Elastic, Redis, object storage/CDN, read replicas, distributed workers, or materialized fan-out feeds without the documented Phase 6 capacity trigger.
- Arbitrary remote media fetching, arbitrary iframes/scripts, executable archives, browser-active HTML/SVG uploads, macros, or server-side execution of pasted/attached content.
- Native mobile applications, PWA/offline mode, forum import tooling, multi-community/multi-tenant operation, and full internationalization.
- Third-party generative summaries or canonical-answer services, autonomous moderation, auto-published machine-written content, or any generated content path without a separate privacy, quality, cost, retention, and human-approval decision.

## 5. Reconciled and locked implementation decisions

The following decisions are treated as fixed for Phase 4:

1. **Phase ownership stays intact.** Phase 4 extends accepted Phase 1–3 behavior; it does not hide incomplete earlier work.
2. **Phase 4 remains on the selected server-rendered PHP/MySQL architecture.** Progressive enhancement and no-JS core actions remain required.
3. **Topic status is not a catch-all.** Canonical workflow state, structural flags, personal triage, subscription state, assignment, and moderation state are modeled separately and combined only in presentation.
4. **The initial canonical status vocabulary is fixed and small:** `open`, `needs_answer`, `solved`, `decision_made`, and `archived`. Pinned, locked, staff notice, pending, escalated, watching, muted, snoozed, and assigned remain separate concepts.
5. **Accepted answers and status remain consistent but not identical.** Selecting an accepted answer may transition a topic to `solved`; authorized users can reopen it. Decision topics may close through an approved summary without pretending a reply was accepted.
6. **Snooze is personal presentation state.** It does not change the thread, authorization, follows, subscriptions, or direct reply/mention notifications.
7. **Assignment is opt-in per board.** Default is off; approved modes are `self` and `staff`. A normal user may assign only themselves where self-assignment is enabled; scoped staff may assign eligible members.
8. **For You is deterministic and explainable.** It uses fixed weighted signals and exposes a reason label; no opaque machine-learning ranking is introduced in this phase.
9. **Group DMs are private forum conversations, not real-time chat.** They use the existing request/short-poll model, bounded participants, durable messages, and normal moderation/report rules.
10. **Group-DM access follows membership intervals.** New participants do not receive prior history by default; departed or removed members do not receive future content. The UI never implies that adding someone is retroactively private-safe.
11. **Blocks prevent new direct/group invitations involving that pair.** An existing shared group is not silently rewritten; the blocked member can mute or leave, direct notifications are suppressed as approved, and reports remain available.
12. **No general DM browser exists.** Staff access is report-driven, minimal, capability-gated, and audited.
13. **Markdown remains canonical.** Advanced formats must have stable Markdown or an explicit, documented content-token representation that survives plain-text editing and round trips.
14. **Structured widgets are server-owned records.** Polls and similar blocks use stable IDs referenced from canonical text; editor JSON is never the source of truth.
15. **Link previews are snapshots, not live remote embeds.** Fetch happens asynchronously through an allowlist; stored metadata is sanitized; active third-party content is click-to-load and optional.
16. **Expanded attachments remain allowlist-only.** Executables, scripts, browser-active documents, and unscanned archives are rejected. Scanner unavailability fails closed for newly uploaded files while existing safe reads remain available.
17. **Follow and subscribe are distinct.** Follow controls discovery; subscribe/watch controls in-app/email delivery. Neither silently creates the other.
18. **Tags are curated metadata, not an unrestricted folksonomy at launch.** Admins/scoped moderators manage the catalogue; members select approved tags where a board permits.
19. **Feeds remain topic-anchored and query-time by default.** Infinite scroll is not required; bounded pagination and clear end states are preferred. Fan-out storage is a later capacity swap behind the feed interface.
20. **Recognition remains cosmetic.** Reputation, custom badges, titles, and leaderboards grant no permission, limit increase, queue priority, or prize.
21. **Custom badge automation is declarative only.** The first rule vocabulary is limited to approved counters/events; no arbitrary SQL, PHP, expressions, or outbound calls.
22. **Community memory is reviewable and source-linked.** Manual curation is the baseline. Automated context is deterministic, links to source posts, and cannot publish or change canonical summaries/status without an authorized human action.
23. **Private data classes are off for remote preview/fetch by default.** Enabling private-board URLs requires an explicit operator policy and privacy review; DM preview fetching remains excluded unless a later decision specifically permits it.
24. **Split/merge preserves durable identity.** Post IDs remain stable, old thread URLs redirect to the canonical destination, and the operation never refires historical notifications or reputation events.
25. **Profile media uses the accepted attachment lifecycle.** Avatar/signature media does not create a separate ungoverned storage path.
26. **Schema design precedes implementation.** Any table or column absent from `SCHEMA.md` must be reconciled and approved before dependent production code is merged.
27. **Every subsystem has an independent disable path.** Disabling previews, polls, tags, feeds, summaries, group-DM creation, badge rules, or split/merge must leave core reading/posting intact.

## 6. Delivery workstreams

| ID | Workstream | Deliverables | Acceptance evidence | Dependency | Gate |
|---|---|---|---|---|---|
| P4-00 | Entry gate, scope, and baselines | Phase 3 closeout review; carryover ledger; route/permission inventory; product-demand review; representative fixtures; numeric budgets; feature flags; evidence map | Signed Phase 3 acceptance/deferrals; baseline report; schema diff; requirement ledger; rollback map | Phase 3 | A |
| P4-01 | Topic status and history | Fixed canonical status model; transition policy; chips; status history; accepted-answer synchronization; admin settings/help text | Transition/authorization matrix; history/audit assertions; private-board count tests; browser/no-JS evidence | P4-00 | A |
| P4-02 | Inbox triage, snooze, and assignment | Expanded filters; deterministic For You; reason labels; snooze/unsnooze/auto-return; board assignment modes; assign/reassign/unassign; counts and saved views | Query/permission fixtures; due-time tests; concurrency tests; direct-request scope matrix; performance evidence | P4-01 | A |
| P4-03 | Group direct messages | Conversation kind/title/owner; membership intervals; add/remove/leave/transfer; participant cap; unread/mute; events; reporting; privacy/block/account-state gates | Membership-history and access tests; invite/concurrency tests; report-context privacy tests; browser/no-JS evidence | Accepted Phase 2 DMs, P4-00 | A |
| P4-04 | Advanced Markdown and references | Task lists; tables; horizontal rule; board/thread/post references; reference cards; paste/round-trip rules; toolbar/shortcuts/fallback | Golden round-trip corpus; sanitizer/XSS tests; context-parity matrix; keyboard/mobile/no-JS evidence | Accepted Phase 3 composer | A |
| P4-05 | Link previews and expanded attachments | URL normalization; allowlisted async fetch; sanitized metadata; click-to-load embeds; purge/refresh; expanded file policy; scanner/quarantine; safe download/preview | SSRF/DNS/redirect/size tests; tracking/privacy tests; MIME/polyglot/archive tests; private-parent access; scanner outage and disk-pressure tests | P4-00, accepted Phase 3 media | B |
| P4-06 | Tags, follows, and feeds | Tag catalogue; thread tagging; board/tag follows; follow-versus-subscribe UI; Following and Latest feeds; remove follower; feed filters/digest controls | Tag-scope and visibility tests; feed privacy/block tests; query plans; duplicate follow tests; browser/no-JS evidence | Accepted Phase 2 follows, P4-00 | A/B |
| P4-07 | Reputation ledger and leaderboards | Idempotent event ledger; board attribution; repair/backfill; week/month/all-time/board rankings; opt-out; anti-gaming signals | Backfill/reversal/reconciliation tests; deterministic ranking fixtures; privacy tests; load evidence | Accepted Phase 2 reputation | A |
| P4-08 | Custom badges | Badge definitions/assets; manual award; declarative rule vocabulary; preview; idempotent backfill; revoke/disable; notifications; audit | Rule parser/evaluator tests; preview-versus-actual reconciliation; duplicate/revoke tests; accessibility/browser evidence | P4-07, accepted Phase 2 badges | A |
| P4-09 | Summaries and related topics | Manual summaries; revisions; source-post references; publish/retire; deterministic related-topic candidates; curated relations; permission-safe display | Revision/provenance tests; private/deleted source tests; search/relevance fixtures; browser/no-JS evidence | Accepted search, P4-01 | A |
| P4-10 | Wiki posts and split/merge | Wiki eligibility; post revisions/revert; dry-run split/merge; locks; counter/read-state repair; redirect/canonical handling; audit/rollback tools | Revision authorization tests; migration/operation fixtures; permalink and notification tests; fault injection; browser evidence | P4-01, accepted moderation | A |
| P4-11 | Polls, custom emoji, slash menu, and GIF provider | Structured polls; vote rules; custom emoji catalogue/assets; safe reaction integration where approved; first-party insert commands; optional GIF provider | Vote concurrency/privacy tests; emoji sanitizer/rename tests; command registry tests; provider privacy/failure tests; no-JS poll path | P4-04, P4-05, accepted hook system | B |
| P4-12 | Automated community context | Since-last-read context; scheduled related-topic refresh; source links; deterministic rules; privacy/access checks; quality review; kill switch | Source/range fixtures; private-data isolation tests; stale/deleted-post handling; refresh retry; browser evidence | P4-09, P4-10, accepted search | B |
| P4-13 | Profile and personal organization | Avatar crop/variants extending the Phase-3 base upload (`users.avatar_path`, P3-12); signature editor/render/gates; board groups/folders; viewer preferences; moderation/removal | Media access/moderation tests; signature sanitizer/height tests; new-user threshold tests; private folder/order tests | Accepted Phase 3 media/preferences | B |
| P4-14 | Safety, accessibility, SEO, and performance | Abuse/threat-model review; group-DM/tag/feed/badge/poll protections; shared-component accessibility; canonical/indexing rules; query/cache/worker budgets | Security and route matrix; accessibility report; crawler fixtures; load/soak evidence; cache isolation tests | P4-01–P4-13 | A/B |
| P4-15 | Operations, release, and closeout | Metrics; dashboards/logs; runbooks; backup/restore; staged rollout; evidence index; release notes; source-doc/schema reconciliation | Full suite; migration/rollback rehearsals; no critical/high defects; product-owner Gate A and final acceptance | All applicable workstreams | A/B |

## 7. Recommended execution sequence

### Milestone 0 — Close Phase 3, lock Phase 4, and establish evidence

- Review Phase 3 Gate A/Gate B evidence against the Phase 3 plan rather than roadmap labels.
- Create the carryover ledger and decide whether each item blocks Phase 4, precedes Gate A, or moves to a later phase.
- Capture the deployed schema and reconcile it with `SCHEMA.md`.
- Inventory every public, member, private-board, DM, moderation, feed, tag, summary, preview, attachment, worker, and admin route that Phase 4 will add or change.
- Capture product baselines and demand evidence for triage, DMs, follows, tags, badges, leaderboards, composer formats, files, and knowledge reuse.
- Define production-like fixtures: many boards/tags, large inboxes, deep threads, private boards, high-follow users, large group conversations, many reputation events, badge rules, wiki revisions, link previews, attachments, and split/merge histories.
- Lock numeric budgets and evidence targets for every Gate A/B requirement.
- Define independent feature flags and emergency disables for status transitions, each triage feature, group-DM creation/invites, tags, feeds, leaderboards, badge rules, advanced syntax, previews, file types, polls, emoji, GIF provider, summaries, wiki editing, split/merge, avatar uploads, and signatures.

**Exit gate:** Phase 3 is formally closed or explicitly re-scoped; Phase 4 scope, decisions, budgets, schema gaps, evidence targets, and rollback controls are approved.

### Milestone 1 — Domain decisions and schema reconciliation

- Finalize the topic-state taxonomy and transition/capability matrix.
- Decide and document assignment cardinality, eligible assignees, self/staff modes, history, and notification behavior.
- Define group-DM membership intervals, history visibility, participant cap, ownership transfer, block interactions, report context, and deletion/retention behavior.
- Define tag lifecycle, board-level tag policy, rename/merge redirects, and follow cleanup.
- Replace the suggested reputation-event seam with a production-grade idempotent/reversible ledger design.
- Define badge rules as a closed declarative vocabulary and design preview/backfill/revocation history.
- Specify summary, related-topic, wiki-revision, split/merge, redirect, and automated-context provenance schema.
- Specify link-preview fetch isolation, attachment allowlists, scanner behavior, poll model, custom-emoji keys, and GIF-provider data handling.
- Add clean-install and populated-upgrade migration tests before feature code depends on the new shape.

**Exit gate:** Every Gate A domain has an approved ADR/schema and no production code depends on an undocumented table, status, token, event, or transition.

### Milestone 2 — Topic status and personal triage

- Implement the canonical status service and history.
- Add chips to inbox rows and topic headers without duplicating existing pin/lock/pending/accepted-answer indicators.
- Add status filters and authorization-safe counts.
- Implement snooze, Snoozed view, due-time return, and cleanup/reconciliation.
- Implement board assignment settings, eligible-user search, assign/reassign/unassign, notifications, and Assigned view.
- Implement deterministic For You ranking with fixed weights, bounded candidate sets, and reason labels.
- Verify direct requests, private-board changes, user removal, status transition races, and cache variation.

**Exit gate:** A member can understand and triage why a topic appears; status and personal state remain correct under retries, permission changes, and concurrent updates.

### Milestone 3 — Group conversations

- Migrate existing 1:1 conversations safely to the approved direct/group model.
- Implement group creation, eligible participant selection, participant cap, title, owner, membership events, and unread state.
- Add add/remove/leave/transfer/mute flows with membership-boundary reads.
- Reuse the shared composer, attachments, drafts, idempotency, and notification system.
- Apply blocks, DM preferences, account states, new-user throttles, invite limits, and abuse signals.
- Extend message reporting to group DMs while preserving minimum-context staff access.
- Add retention, participant export/delete behavior, and operator runbooks without creating a general DM browser.

**Exit gate:** Eligible members can use a bounded group conversation safely; no participant can read outside their membership interval and no staff role can browse unreported private content.

### Milestone 4 — Advanced canonical content

- Add task lists, tables, horizontal rules, and reference syntax to the canonical renderer, sanitizer, editor, preview, and textarea documentation.
- Add board/thread/post reference resolution with permission-safe fallback for inaccessible or deleted targets.
- Expand the Markdown round-trip corpus with real legacy posts and adversarial fixtures.
- Verify all approved contexts, edit reuse, copy/paste, mobile overflow, keyboard behavior, and no-JS submission.
- Add the stable structured-content token contract needed for later polls without storing editor-specific state.

**Exit gate:** Advanced content round-trips losslessly, degrades clearly in plain text, and cannot bypass sanitization or read gates.

### Milestone 5 — Discovery, recognition, and community graph

- Add the tag catalogue, board tag settings, thread-tag application, rename/merge, and moderation paths.
- Extend follows to board/tag targets with target validation and cleanup.
- Build Following and Latest feeds through the existing feed interface with bounded query-time reads and permission filtering.
- Add follower removal and quiet follow-activity digest controls.
- Introduce the production reputation-event ledger, backfill from canonical reactions/solved events, and reconcile `users.reputation`.
- Add week/month/all-time and board-scoped leaderboards with opt-out.
- Add custom manual badges and limited declarative rules, preview, backfill, revoke, and notifications.

**Exit gate:** Discovery and recognition are useful, quiet, privacy-safe, idempotent, and remain entirely cosmetic.

### Milestone 6 — Durable community memory and moderator operations

- Add manual topic summaries, source references, revisions, publish/retire, and canonical display.
- Add deterministic related-topic candidates using accepted search/tag signals and curated relation management.
- Add wiki-style post mode, eligibility, full revisions, edit reasons, attribution, and revert.
- Implement split/merge dry-run planning, affected-row locks, post moves, counter repair, thread/read/subscription reconciliation, canonical redirects, search/cache invalidation, and audit.
- Verify that old post links resolve correctly and historical notifications/reputation are not duplicated.
- Add canonical-answer curation linked to accepted answers or approved summaries.

**Exit gate:** Authorized community knowledge work is reversible, source-linked, permalink-safe, and fully audited.

### Milestone 7 — Gate A hardening, staged release, and acceptance

- Profile all Gate A routes under production-like data and concurrency.
- Remediate N+1 queries, unbounded joins, feed/status count drift, membership checks, and cache variation.
- Complete security/privacy review for group DMs, tags, feeds, badge rules, wiki editing, split/merge, and summaries.
- Complete shared-component accessibility and public-discovery/SEO checks.
- Rehearse migration, backup/restore, feature disablement, counter/ledger repair, snooze recovery, group-DM invite disable, tag/feed disable, badge-rule pause, wiki revert, and split/merge failure recovery.
- Release to staff/test boards, then selected cohorts/boards, then all eligible users.
- Record product and operating metrics plus Gate A product-owner acceptance.

**Exit gate:** Gate A is accepted in production with no critical/high defects and no unresolved private-message, status, feed, reputation, wiki, or permalink incident.

### Milestone 8 — Rich expression and automated context

- Implement the allowlisted preview-fetch worker, metadata store, click-to-load UI, purge/refresh, and provider controls.
- Expand the attachment allowlist only after scanning/quarantine and storage-pressure evidence passes.
- Add polls, custom emoji, slash-command insertion, and optional GIF provider.
- Add deterministic since-last-read context and scheduled related-topic refresh with source links, privacy-safe input selection, review metrics, and a kill switch.
- Add avatar uploads, signatures, and custom board groups/folders if still owned by Phase 4.
- Run each Gate B surface through independent privacy, safety, accessibility, performance, migration, and rollback acceptance.

**Exit gate:** Every Gate B feature is independently accepted or has an approved destination phase; no automated context or remote content path bypasses privacy policy, authorization, source provenance, or canonical human-controlled content.

### Milestone 9 — Phase 4 release candidate and formal closeout

- Run the complete Phase 1–4 regression suite and route/permission matrix.
- Rehearse clean install, supported historical upgrades, feature-disable paths, worker pause/replay, scanner outage, preview-fetch isolation, ledger rebuild, badge-rule rollback, summary/context-job outage, wiki revert, split/merge repair, and backup restore.
- Reconcile `README.md`, `DESIGN.md`, `SCHEMA.md`, surface documents, route inventory, runbooks, changelog, and completion evidence with the deployed product.
- Record all accepted Gate A/Gate B requirements and every explicit deferral.
- Capture post-release product baselines and capacity triggers for Phase 5 ecosystem work and Phase 6 infrastructure swaps.

**Exit gate:** The Phase 4 evidence index and product-owner closeout are recorded; no hidden Phase 4 obligation remains under an ambiguous “later” label.

## 8. Data and migration plan

### 8.1 Existing tables and behavior to verify before reuse

Phase 4 must verify actual deployment and accepted behavior for:

- `threads`, including `accepted_answer_post_id`, pin/lock/pending/deleted state, counters, and search indexes;
- `thread_user`, `subscriptions`, notifications, saved/star state, and server-side drafts;
- `conversations`, `conversation_participants`, `dm_messages`, DM reports, and message attachment access;
- `attachments`, processing/quarantine state, private delivery, ownership, moderation, retention, and orphan cleanup;
- `follows`, `badges`, `user_badges`, `users.reputation`, profile visibility, blocks, and notification preferences;
- `plugins`, webhook delivery, admin API tokens, and any provider interfaces reused for previews or GIFs;
- `user_preferences`, `user_board_prefs`, bookmark folders, avatar/profile columns, and signature fields;
- `moderation_log` before/after snapshots and target-type coverage for the new Phase 4 operations.

A table or column appearing in the consolidated schema is not evidence that its migration, data, indexes, service behavior, or acceptance tests shipped.

### 8.2 Schema gaps that must be resolved at Milestone 1

1. **Topic status and history.** Add the fixed canonical status to `threads` and an append-only status-history model with actor, prior/new state, reason, and timestamp. Do not encode pin, lock, pending, assignment, snooze, or subscription state in the same field.
2. **Personal triage.** Add `thread_user.snoozed_until` and any approved personal filter metadata. Define due-time indexing and cleanup. Do not add a duplicate mute flag if the accepted subscription model already represents mute.
3. **Assignment.** Use a normalized assignment/history model or a current-assignee column plus history. Record assigned user, assigner, board/scope validation, assigned/reassigned/unassigned timestamps, and one-active-assignee invariant.
4. **Tags.** Define `tags`, `thread_tags`, aliases/redirects for rename/merge, ordering/visibility, moderation fields, and indexes. If tags are global, document board-level applicability and permission checks.
5. **Group DMs.** Expand conversation metadata and replace or extend participant rows to represent direct/group kind, title, creator/owner, role, joined boundary, left/removed state, unread pointer, notification mode, and participant-visible events. Existing 1:1 rows need a deterministic backfill.
6. **DM report targets.** Ensure reports can reference a DM message without pretending it is a public post, and maintain the minimum-context access model.
7. **Advanced references.** Define any stable reference-token registry or cached reference metadata needed for round-trip and permission-safe fallback.
8. **Link previews.** Add normalized URL/hash, provider, status, sanitized metadata, fetch timestamps, expiry, safety decision, failure reason, redirect chain summary, and post/DM bindings. Secrets, full response bodies, and private request headers must not be stored.
9. **Attachment expansion.** Finalize purpose, storage key/hash, scan/quarantine state, preview derivative state, retention/deletion, download disposition, and owner/context fields for the expanded allowlist.
10. **Polls.** Define poll, option, vote, close/result policy, context binding, uniqueness, choice-count enforcement, moderation, and deletion behavior. Individual votes remain private unless policy explicitly says otherwise.
11. **Custom emoji.** Define stable emoji key/shortcode, media reference, enabled state, aliases/rename behavior, creator/updater, and whether reactions require a wider key or an emoji foreign key.
12. **Reputation ledger.** The suggested `reputation_events` DDL is insufficient by itself. Add a durable logical event/idempotency key, event time, board attribution where needed, correction/reversal semantics, source integrity, and indexes for time windows and rebuilds.
13. **Custom badges.** Expand badge definitions with enabled/visibility/order/icon state and add declarative rule/version data. Preserve award and revocation history rather than deleting evidence.
14. **Summaries.** Define versioned summary records, kind, canonical Markdown/render, draft/published/retired state, author/reviewer, and normalized source-post references.
15. **Related topics.** Define directional/symmetric relation type, source, score/reason, curator, status, and uniqueness. Merged/duplicate redirects must not be confused with ordinary recommendations.
16. **Wiki revisions.** Add `posts.is_wiki` or equivalent plus complete revision records containing editor, canonical body, reason, and timestamp before broad editing is enabled.
17. **Split/merge and redirects.** Define operation records, source/destination thread mapping, post movement plan, redirect/canonical metadata, failure/recovery state, and immutable audit links.
18. **Profile media and signatures.** The canonical current avatar is `users.avatar_path` (set in Phase 3 — P3-12 — through the attachment/media pipeline); Phase 4 only adds crop/variant derivatives and binds signature images to the attachment lifecycle, adding rendered signature cache/version/removal metadata as needed — it does **not** introduce a second avatar storage model. _(Clarified 2026-06-26: base avatar upload + `users.avatar_path` are owned by Phase 3, resolving the prior Phase-3/Phase-4 avatar ownership split.)_
19. **Personal board groups.** If Gate B owns them, define private group/folder rows and ordered board membership without changing board authorization or canonical category placement.
20. **Automated-context provenance.** If since-last-read context or scheduled related refresh ships, define input range, rule/version, generated-at time, refresh/error state, and source references separately from the published canonical summary.

### 8.3 Recommended migration groups

Apply additive migrations in dependency order, with feature flags off:

1. Topic status/history, snooze indexes, and assignment model.
2. Group-conversation metadata, membership intervals/events, and DM report targets.
3. Tags, thread tags, aliases, and follow indexes/cleanup support.
4. Reputation ledger and custom-badge definitions/rules/award history.
5. Summary, source-reference, related-topic, wiki-revision, split/merge, and redirect models.
6. Advanced reference metadata and structured-content token support.
7. Link-preview metadata and expanded attachment lifecycle/scanner fields.
8. Polls, custom emoji, and any reaction-key migration.
9. Profile media/signature and personal board-group tables where accepted.
10. Automated-context provenance, refresh-state, and quality-feedback tables where needed.

Each group must be independently deployable while its feature flags remain disabled.

### 8.4 Upgrade and backfill rules

- Existing threads begin in `open` unless a deterministic accepted-answer/archived rule maps them to another canonical state. Backfill is recorded and repeatable.
- Existing accepted-answer threads may map to `solved`; the script must not overwrite a manually set later state on rerun.
- Existing personal thread rows receive no snooze and no assignment by default.
- Existing 1:1 conversations become `direct`; current participants receive membership boundaries that preserve existing access exactly.
- Historical group membership is not invented. Group features start after the migration unless a trustworthy legacy source exists.
- Existing board subscriptions do not become board follows, and existing board follows do not become notification subscriptions.
- Existing threads are not auto-tagged from titles without an approved, reviewable backfill.
- Reputation events are backfilled from canonical reactions, accepted-answer bonuses, and audited adjustments using stable logical keys; reruns create no duplicates.
- Time-window leaderboards do not claim historical precision until the approved backfill completes and reconciles.
- Existing fixed badges remain unchanged. Custom badge rules begin disabled and preview-only; historical backfill runs only after approval.
- Existing posts are not bulk-rewritten for tables/task lists/references. Renderer-version changes invalidate cached HTML lazily or through bounded jobs.
- Existing URLs do not receive previews until viewed or processed by a bounded backfill; remote fetching must never create an unbounded migration job.
- Existing attachments retain their accepted classification. Expanded scan/quarantine requirements are applied prospectively unless a bounded rescan is explicitly approved.
- Existing edited posts do not gain fabricated revision history. Wiki mode can be enabled only after revision storage exists; the current body becomes revision zero with migration attribution.
- Existing related-topic links are not synthesized without a deterministic source and review policy.
- Avatar/signature media, if already present, is inventoried and migrated into the canonical attachment lifecycle before new uploads are enabled.
- No Phase 1–3 table or column is dropped in the same release that introduces a replacement.

### 8.5 Transactional and consistency invariants

- A status transition and its status-history/audit entry commit together.
- A snooze update affects only the current user/thread row; due-time reappearance is idempotent and cannot create a second row.
- One thread has at most one active assignee under the initial policy; assignment and its audit/notification state commit consistently.
- Group creation, initial participant memberships, and the first participant-visible event commit together.
- Adding/removing/leaving a group updates membership boundaries, unread/notification state, and the participant-visible event atomically.
- A group message can be read only when its ID/time lies inside an approved membership interval and the conversation access gate passes.
- Tag application and any derived feed invalidation commit together; deleting/merging a tag cannot leave orphan follows or thread-tag rows.
- A follow action creates at most one logical relationship; it never creates a subscription unless a separate explicit request succeeds.
- A reputation event is applied at most once to the canonical counter; reversal/correction reconciles both ledger and counter.
- A custom badge award is unique per approved rule/user/achievement key; notification is queued only after the award commits.
- A published summary references only source posts the publishing actor can read at publication time; access is rechecked on display.
- Wiki edits always create a revision before changing the current body; a failed revision write leaves the post unchanged.
- Split/merge locks affected threads/posts, moves each post at most once, repairs counters/read state, writes redirects/audit, and commits as one recoverable operation or rolls back.
- Historical notifications, emails, reputation events, reactions, and badge awards are never replayed solely because posts move between threads.
- A preview record cannot become displayable until URL safety, provider policy, metadata sanitization, and parent-content binding succeed.
- A quarantined attachment cannot be downloaded or rendered; scanner failure does not silently mark it safe.
- A poll vote operation enforces the user’s choice limit and uniqueness under concurrency.
- Automated context remains separate from published summaries; publishing or editing a summary creates a new attributed canonical revision rather than mutating computed context.

## 9. Critical acceptance scenarios

| Area | Scenario and expected result |
|---|---|
| Phase ownership | An unaccepted Phase 3 attachment or draft requirement remains in the carryover ledger and is not marked complete because Phase 4 adds related UI. |
| Status taxonomy | Pinned + locked + solved displays as independent states; changing solved does not clear pin/lock, and a personal snooze never changes the thread’s canonical status. |
| Status authorization | OP, scoped moderator, out-of-scope moderator, normal participant, and guest receive exactly the approved transition options and direct-request results. |
| Accepted answer | Selecting an accepted answer applies the configured solved transition once; reopening preserves answer history and does not duplicate reputation or badges. |
| For You privacy | A private thread never appears through reason text, counts, snippets, ranking candidates, or cache entries for an unauthorized user. |
| For You explanation | Every row has a deterministic reason such as mention, reply, participation, watched board, or followed tag; changing the source relationship updates the reason. |
| Snooze | A snoozed thread disappears from normal personal filters, stays visible in Snoozed, returns no earlier than the due time and within the approved interval, and still delivers direct mentions/replies. |
| Assignment | Self-assignment works only on boards with self mode; normal users cannot assign another user; staff scope is rechecked after board moves or role revocation. |
| Group creation | A permitted creator can make a group within the participant cap; blocked, ineligible, banned, or DM-disabled users cannot be added and no partial group remains. |
| Group history | A member added after message 100 can read message 101 onward but receives no body, snippet, attachment, search hit, notification payload, or export entry for messages 1–100. |
| Group removal | A removed member retains only the approved historical interval and receives no future poll, message, attachment, preview, unread, or notification data. |
| Group ownership | Owner departure requires transfer or the defined automatic successor; two concurrent transfers produce one owner. |
| Group report | Reporting one message exposes only that message plus the approved local context to authorized staff; unrelated messages and conversations remain unavailable. |
| Advanced round trip | A corpus containing tables, task lists, references, spoilers, code, and legacy Markdown loads and saves without unapproved semantic changes. |
| Reference privacy | A reference to a private/deleted board/thread/post renders as a generic unavailable reference to an unauthorized viewer and never leaks title, author, or snippet. |
| Preview SSRF | Localhost, private IPs, metadata endpoints, mixed DNS answers, redirect-to-private, oversized responses, unsupported schemes, and slow endpoints are rejected. |
| Preview privacy | A page view makes no third-party request until click-to-load; disabling or removing the preview leaves the underlying link intact. |
| Attachment spoofing | Renamed executables, active HTML/SVG, macro documents, nested/decompression-bomb archives, and scanner failures remain rejected or quarantined. |
| Private attachment | A copied group-DM/private-board file URL returns no file or metadata outside the parent access interval/gate. |
| Tag rename/merge | Old tag URLs redirect safely; follows and thread associations migrate once; no private thread appears in public tag counts. |
| Follow vs subscribe | Following a board changes the feed only; email/in-app delivery remains off until a separate subscription is created. |
| Feed privacy | Following, Latest, and digest feeds exclude blocked, deleted, pending, private, and inaccessible activity and do not expose counts for it. |
| Remove follower | Removing a follower prevents future relationship visibility/feed effects without notifying them and without weakening an existing block. |
| Reputation backfill | Re-running the backfill creates zero duplicate events and reconciles exactly with canonical reactions/solved bonuses. |
| Reaction reversal | Removing/restoring a reacted post adjusts the canonical counter and relevant time-window contribution according to the approved reversal policy. |
| Leaderboard opt-out | An opted-out user is absent from global and board rankings and associated cached/API results but retains their visible profile reputation if policy allows. |
| Badge preview | A rule preview’s eligible count matches the actual idempotent backfill; disabling the rule stops future awards without erasing history. |
| Badge revocation | Revocation removes active display, records actor/reason/time, does not resend on page refresh, and can be re-awarded only under the approved rule. |
| Manual summary | Publishing, editing, reverting, and retiring a summary preserve canonical Markdown, sources, author/reviewer, and revision history. |
| Related topics | A related target that becomes private/deleted disappears for unauthorized readers and never remains in cache or metadata. |
| Wiki edit | Eligible users can edit with attribution; ineligible users fail by direct request; revert restores a prior body through a new revision. |
| Split dry run | The preview lists affected posts, counters, subscriptions, read states, links, and conflicts without modifying data. |
| Split/merge success | Every moved post keeps its ID; old links resolve to the correct destination; counters/search/cache/read state reconcile; no historical notification or rep event is duplicated. |
| Split/merge failure | An injected failure at each transaction stage leaves either the full original state or a documented recoverable operation state, never a silent partial move. |
| Poll concurrency | Parallel votes cannot exceed choice limits or create duplicate logical votes; closed polls reject direct POSTs. |
| Poll privacy | Aggregate results respect the visibility policy; individual votes are not exposed through HTML, API, logs, exports, or moderation views without explicit authorization. |
| Custom emoji | Renaming/disable preserves existing post rendering through an alias/fallback; malformed files and shortcode collisions are rejected. |
| Slash menu | Commands only insert approved content; pasted `/admin`, shell, SQL, URL, or plugin strings never execute. |
| Since-last-read context | The view uses only accessible source posts after the user’s read pointer, links to those posts, handles deletion/access changes, and cannot alter canonical content or status. |
| Automated private context | Private-board context is computed only after the requester’s current read gate; DMs remain excluded from remote fetching; logs contain neither full private bodies nor signed URLs. |
| Avatar/signature | New-user threshold, sanitization, dimensions, viewer hide preference, moderator removal, and monogram fallback all work without broken media. |
| No JavaScript | Status forms, snooze, assignment, group-message send, follows, tagging, badge administration, summary/wiki edits, poll voting, and moderation retain an approved server-rendered path where applicable. |
| Backup/rollback | Disabling any Phase 4 subsystem leaves core read/post/DM/composer behavior intact; restore preserves statuses, memberships, tags, ledgers, revisions, redirects, and provenance. |

## 10. Test and evidence policy

### 10.1 Required test layers

- **Unit tests:** status transitions; For You scoring/reasons; snooze due logic; assignment policy; membership-interval checks; Markdown transforms/round trips; URL normalization and network policy; poll choice rules; tag aliases; reputation ledger; badge rules; summary provenance; split/merge planning.
- **Repository/service integration tests:** migrations; status history; triage queries/counts; group memberships/events/messages; tags/follows/feeds; reputation backfill/reversal; badge preview/backfill/revoke; summary revisions; wiki revisions; split/merge/redirects; previews; attachments; polls; emoji; profile media.
- **Concurrency/idempotency tests:** status races; snooze updates; assignment; participant add/remove/owner transfer; message sends; follows; ledger events; badge awards; poll votes; preview claims; split/merge operation locks; automated-context refresh retries.
- **Application/HTTP tests:** every route’s authentication, CSRF, account state, role/scope, membership interval, block/privacy, private-content, rate-limit, validation, idempotency, cache, feature-flag, and no-JS behavior.
- **Worker/cron tests:** snooze due processing where used; feed digest; reputation backfill/aggregation; badge rules; preview fetch/refresh/purge; attachment scan/preview/cleanup; automated context refresh; retention and repair jobs.
- **Browser tests:** inbox filters/status/snooze/assignment; group creation/membership/reporting; tables/tasks/references; tags/follows/feeds; leaderboards/badges; summaries/related/wiki; split/merge; previews/files; polls/emoji/slash menu; avatars/signatures/folders.
- **Security/privacy tests:** cross-user counts; private-board/tag/feed leakage; group-DM history boundaries; report-context minimization; SSRF/DNS rebinding/redirects; file polyglots/archives; poll privacy; automated-context access isolation; XSS in every new renderer/metadata field.
- **Performance tests:** large inbox filters; For You candidates; group-DM reads; tag/follow feeds; leaderboard windows; badge-rule previews; related-topic queries; wiki revision history; split/merge on large threads; preview/scanner queues; summary jobs.
- **Accessibility evidence:** automated scans plus manual keyboard, screen-reader-critical, focus, zoom/reflow, reduced-motion, touch-target, error/live-region, table, task-list, poll, dialog, combobox, and drag/reorder checks.
- **SEO/discovery evidence:** canonical/status/tag/summary/wiki/leaderboard metadata, redirect chains, sitemap decisions, crawl simulation, and negative tests for personalized/private/group-DM/tokenized/provider-worker surfaces.
- **Operational evidence:** clean install, populated upgrade, backup/restore, queue pause/replay, feature disablement, ledger repair, tag merge, group-DM invite pause, scanner outage, preview kill switch, badge-rule rollback, wiki revert, split/merge repair, provider outage, and disk pressure.

### 10.2 Evidence rules

- A roadmap label, merged branch, route name, or target test filename is not proof of acceptance.
- Every requirement must link to evidence produced on the release candidate commit and named environment/fixture.
- UI-visible behavior requires browser evidence in addition to server-side tests.
- Privacy and authorization claims require negative-path and cross-user/cross-role evidence.
- Performance claims require the same fixture, hardware class, database version, cache state, concurrency, and measurement window before and after changes.
- Automated-context acceptance requires source-range fixtures and private-data isolation evidence; a visually plausible result is not evidence of correctness.
- Split/merge acceptance requires injected-failure and old-link tests, not only a successful happy path.
- Link-preview acceptance requires adversarial network targets and log inspection, not only normal public URLs.
- Attachment acceptance requires adversarial files and scanner-outage behavior.
- Every Gate A/Gate B definition-of-done item must map to an automated test, browser/manual proof, operating exercise, or approved policy record in the evidence index.

### 10.3 Target evidence names

The implementation may use different names, but the evidence index should include equivalents of:

- `tests/Unit/Community/ThreadStatusPolicyTest.php`
- `tests/Unit/Community/ForYouRankerTest.php`
- `tests/Integration/Core/AppThreadStatusTest.php`
- `tests/Integration/Core/AppInboxTriageTest.php`
- `tests/Integration/Core/AppThreadSnoozeTest.php`
- `tests/Integration/Core/AppThreadAssignmentTest.php`
- `tests/Integration/Core/AppGroupDirectMessageTest.php`
- `tests/Integration/Core/AppGroupDmMembershipHistoryTest.php`
- `tests/Integration/Core/AppGroupDmReportPrivacyTest.php`
- `tests/Unit/Composer/AdvancedMarkdownRoundTripTest.php`
- `tests/Integration/Core/AppReferenceCardTest.php`
- `tests/Integration/Worker/LinkPreviewFetchWorkerTest.php`
- `tests/Security/LinkPreviewSsrfTest.php`
- `tests/Integration/Core/AppExpandedAttachmentTest.php`
- `tests/Integration/Worker/AttachmentScanWorkerTest.php`
- `tests/Integration/Core/AppTagFollowTest.php`
- `tests/Integration/Core/AppDiscoveryFeedTest.php`
- `tests/Integration/Worker/FollowDigestWorkerTest.php`
- `tests/Integration/Core/AppReputationLedgerTest.php`
- `tests/Integration/Core/AppTimeWindowLeaderboardTest.php`
- `tests/Integration/Core/AppCustomBadgeTest.php`
- `tests/Integration/Worker/BadgeRuleWorkerTest.php`
- `tests/Integration/Core/AppThreadSummaryTest.php`
- `tests/Integration/Core/AppRelatedTopicsTest.php`
- `tests/Integration/Core/AppWikiPostTest.php`
- `tests/Integration/Core/AppThreadSplitMergeTest.php`
- `tests/Integration/Core/AppThreadRedirectTest.php`
- `tests/Integration/Core/AppPollTest.php`
- `tests/Integration/Core/AppCustomEmojiTest.php`
- `tests/Integration/Core/AppProfileMediaSignatureTest.php`
- `tests/Integration/Worker/CommunityMemorySuggestionWorkerTest.php`
- migration/backfill fixtures, load-test reports, accessibility reports, crawler fixtures, and Playwright/browser evidence for all corresponding paths

These are target evidence names, not claims that the files already exist.

## 11. Progress, metrics, observability, and operating requirements

### 11.1 Atomic progress model

Maintain one requirement ledger. Each atomic requirement has one state:

| State | Meaning |
|---|---|
| **R0 — Conflict/unowned** | Requirement is contradictory, ambiguous, or has no owner |
| **R1 — Approved** | Phase, gate, owner, schema, policy, and acceptance criteria are approved |
| **R2 — Implemented** | Code and migration are merged, normally behind a disabled flag |
| **R3 — Automatically verified** | Required unit, integration, concurrency, and migration tests pass |
| **R4 — Release verified** | Browser, no-JS, security/privacy, performance, and operating evidence pass |
| **R5 — Accepted** | Enabled in the intended environment and formally accepted |

Report separately:

- **Scope coverage** = requirements at R1 or higher ÷ committed requirements.
- **Implementation coverage** = requirements at R2 or higher ÷ committed requirements.
- **Verification coverage** = requirements at R4 or higher ÷ committed requirements.
- **Acceptance coverage** = R5 requirements ÷ committed requirements.

Also report unresolved conflicts, unowned requirements, critical/high defects, approved deferrals, evidence not produced on the current commit, and scope added/removed since the prior baseline.

A gate passes only when every critical requirement is R5; every other committed requirement is R4/R5 or has a signed scope change; critical/high defects are zero; required migration/backup/rollback exercises pass; and product-owner acceptance is recorded. Percent averages cannot override a failed critical invariant.

### 11.2 Product measures

Record cohort, window, denominator, baseline, success threshold, and stretch threshold for each metric:

- **Triage adoption** = active members using status filters, snooze, or assignment ÷ active members.
- **Snooze return accuracy** = due snoozes restored within the approved interval ÷ due snoozes.
- **Needs-answer resolution** = needs-answer threads reaching solved/decision within the target window ÷ eligible needs-answer threads.
- **Assignment completion** = assigned threads reaching an approved terminal state ÷ assigned threads.
- **For You usefulness** = sessions with a For You result click/open ÷ sessions viewing the filter, paired with hide/mute feedback and reason distribution.
- **Group-DM adoption** = active members participating in at least one group DM ÷ active members.
- **Group-DM safety** = reports per 1,000 group messages, median report-resolution time, invite rejection reasons, and participant leave/remove rate.
- **Advanced-content use** = posts using tables/tasks/references/previews/files/polls ÷ eligible posts, with render/fallback error rate.
- **Board/tag follow adoption** and **feed click-through**, measured separately from subscriptions and notifications.
- **Feed quality** = no-result rate, inaccessible-item removal rate, mute/hide rate, and topic-open rate.
- **Leaderboard health** = views, opt-out rate, board/global distribution, and abuse flags; no engagement target may reward compulsive use.
- **Custom badge quality** = award count, revocation/error rate, preview-to-actual variance, and member notification disable rate.
- **Memory reuse** = summary views, related-topic clicks, wiki edit/revert rate, solved/canonical reuse, and duplicate-thread reduction where measurable.
- **Automated-context quality** = source-link coverage, stale/deleted-source handling, correction/report rate, refresh failures, and private-data incidents (target zero).
- **Split/merge quality** = operation success rate, rollback/repair count, broken-link count (target zero), and moderator time saved.
- **Profile completion** = valid avatar/signature adoption and moderation rate without pressuring users to complete profiles.

### 11.3 Numeric technical budgets

At Milestone 0, record success/failure thresholds for:

- p50/p95/p99 latency and query count/time for each new inbox filter, For You, status transition, snooze, assignment, group-DM list/read/send, tag page, Following/Latest feed, leaderboard, summary/wiki, and redirect path;
- group-DM participant/message volume, unread update cost, polling load, and report-context query bounds;
- reputation-event write and rebuild throughput; leaderboard aggregation duration and staleness;
- badge preview/backfill duration, queue age, and duplicate/error rate;
- related-topic query cost and summary/source rendering cost;
- split/merge maximum supported posts, lock duration, transaction time, repair duration, and failure budget;
- preview-fetch queue age, DNS/connect/read timeout, response byte cap, redirect cap, success/failure rate, and blocked-target count;
- attachment scan/preview queue age, scan duration, quarantine backlog, disk bytes, and orphan/retention backlog;
- automated-context refresh age, job latency/error, source-range size limits, and correction backlog;
- page asset bytes, browser interaction latency, accessibility defect count, and worker memory/CPU.

Every measurement record must include route/job, hardware class, database version, data fixture, concurrency, cache state, measurement window, p50/p95/p99, query count/time, peak memory, queue age where relevant, and error rate.

### 11.4 Required telemetry

- Structured correlation IDs across web requests, feed queries, group-message writes, preview/scanner workers, badge/leaderboard jobs, summary jobs, and split/merge operations.
- Status transition, snooze, assignment, filter, and For You reason metrics without logging private titles/bodies.
- Group-DM creation, participant count, invite/add/remove/leave, message/send failure, unread lag, report, and authorization-denial metrics without message bodies.
- Tag/follow/feed counts, query latency, inaccessible-item filtering, cache disposition, and digest metrics.
- Reputation ledger reconciliation differences, event/reversal counts, leaderboard generation age, opt-outs, and abuse flags.
- Badge rule version, preview count, awards, duplicates prevented, revocations, failures, and worker heartbeat.
- Summary/wiki/relation revisions, publication/revert, source count, suggestion review outcome, and provider errors without private content.
- Split/merge plan size, duration, lock time, success/failure stage, redirect checks, and repair result.
- Preview target class, provider, status, bytes, timing, blocked reason, and cache age without query secrets.
- Attachment type/bytes, scan state, quarantine age, preview state, denied private reads, disk thresholds, and cleanup heartbeat.
- Poll creation/vote/close failures and aggregate participation without exposing individual vote choices in ordinary telemetry.
- Accessibility, SEO/crawl, worker/cron heartbeat, and last-success metrics for every Phase 4 background job.

### 11.5 Required runbooks

- Disable status transitions or a specific inbox filter while preserving ordinary inbox reads.
- Repair invalid status/accepted-answer combinations and rebuild status counts.
- Clear/reconcile stuck snoozes and assignment records.
- Pause group-DM creation or invitations while preserving existing reads and reports.
- Remove an ineligible group participant and verify membership-boundary access.
- Disable tags, board/tag follows, Following/Latest feeds, or digests independently.
- Rebuild reputation events and leaderboards; pause custom badge rules and reconcile awards.
- Disable wiki editing, unpublish/revert a summary, remove a related link, and recover a failed split/merge.
- Disable remote preview fetching, purge a malicious preview, and inspect SSRF-block telemetry.
- Pause expanded uploads, inspect/release/delete quarantine, recover scanner outage, and manage disk pressure.
- Disable polls, custom emoji, slash commands, or GIF provider without breaking existing post rendering.
- Disable automated context, purge/rebuild computed rows, and unpublish or correct an inaccurate canonical summary through a new revision.
- Restore backup and reconcile memberships, tags, ledgers, badges, revisions, redirects, media, previews, and provenance.

## 12. Risks and controls

| Risk | Control |
|---|---|
| Phase 4 becomes a catch-all for every P2 idea | Enforce Gate A/Gate B, the explicit deferral list, requirement ownership, and signed scope changes |
| Status chips become contradictory or meaningless | Separate canonical, structural, personal, assignment, and moderation state; fixed vocabulary and transition tests |
| For You becomes opaque or leaks private activity | Deterministic bounded signals, reason labels, canonical read gate, cross-user tests, and no external profiling |
| Snoozed items disappear permanently | Indexed due state, deterministic query fallback, reconciliation command, due-time metrics, and no reliance on a single cron tick |
| Assignment becomes harassment or authority by implication | Board opt-in, self/staff modes, eligible-user limits, notifications/mute, scoped capability checks, audit, and easy unassign |
| Group DMs become unbounded chat | Participant/message limits, durable forum semantics, short-polling, no typing/read receipts, rate limits, and no voice/ephemeral scope |
| Group membership leaks prior or future messages | Membership intervals, joined boundary, current-access query predicate, attachment/notification/export reuse, and dedicated negative tests |
| Staff privacy powers expand through group DMs | Report-only minimal context, no browse route, capability/audit checks, and privacy regression tests |
| Advanced Markdown corrupts legacy posts | Golden round-trip corpus, renderer versioning, no bulk rewrite, textarea fallback, and kill switch |
| Reference cards reveal protected titles/snippets | Resolve through canonical read gate at render time; generic unavailable fallback; cache variation tests |
| Link previews create SSRF or tracking | Allowlist, isolated async fetch, DNS/redirect revalidation, strict limits, sanitized snapshots, click-to-load, and emergency disable |
| Expanded attachments introduce malware or browser execution | Positive allowlist, sniffing, quarantine/scanner, non-exec storage, safe disposition, preview isolation, and fail-closed uploads |
| Tags become spam or duplicate taxonomy | Curated creation, normalization, aliases/merge, board policy, rate limits, moderation, and no free-form auto-create at launch |
| Follow and subscribe confuse members | Distinct labels, separate requests/storage, explicit notification controls, and behavior tests |
| Feeds overload MySQL or become addictive | Query-time bounded pagination, cache/interface seam, capacity budgets, no endless novelty ranking, and no streak mechanics |
| Reputation history cannot reconcile | Stable logical event keys, explicit reversal/correction policy, canonical counter repair, and repeatable backfill |
| Custom badge rules execute unsafe logic | Closed declarative vocabulary, parser validation, preview, versioning, queue limits, and no arbitrary SQL/PHP/HTTP |
| Leaderboards drive unhealthy behavior | Opt-out, recognition-only copy, no homepage banner/prizes/streaks, abuse monitoring, and board/global limits |
| Wiki editing erases authorship | Complete revisions, edit attribution/reasons, eligibility gates, revert, report, and moderation audit |
| Split/merge corrupts counters, links, or unread state | Dry run, locks, one recoverable transaction/operation record, stable post IDs, redirects, repair commands, and fault injection |
| Related topics leak private content or create SEO loops | Read gate on both ends, canonical relation types, cache invalidation, crawler tests, and no public indexing of personalized suggestions |
| Automated context becomes stale or is mistaken for canonical summary | Separate computed/canonical surfaces, source links, rule/version provenance, clear labels, correction path, refresh controls, and instant disable |
| Private content leaves the installation | Data-class deny-by-default, operator policy, DPA/privacy review, payload minimization, egress tests, and DMs excluded |
| Polls expose individual choices or double-count | Aggregate-only default, uniqueness/choice transactions, privacy policy, concurrency tests, and limited admin access |
| Custom emoji become an XSS/storage abuse path | Media pipeline, dimensions/size limits, sanitized shortcode, admin-only catalogue, aliases, and moderation |
| Profile signatures recreate classic spam | 10-post-or-3-day gate, height/character/image caps, nofollow, sanitization, viewer hide control, and mod clear |
| Phase 4 overwhelms the single VPS | Numeric budgets, bounded workers, backpressure, feature flags, staged cohorts, and defer infrastructure swaps to Phase 6 unless triggered |
| Schema docs drift again | Milestone 1 schema approval, migration snapshot CI, source-doc reconciliation, and no undocumented production columns |

## 13. Staged release and rollback

### 13.1 Recommended enablement order

1. Deploy additive Gate A migrations and dark backend code with all Phase 4 flags disabled.
2. Enable status reads/chips for staff, then status writes on test boards, then selected public/private boards.
3. Enable snooze and For You for staff/cohorts; validate due-time accuracy, reason labels, permission filtering, and query budgets.
4. Enable assignment only on an explicit test board, then boards that choose self/staff mode.
5. Enable group-DM creation for staff/test accounts with a low participant cap; expand after membership/report evidence passes.
6. Enable advanced Markdown and references for staff while retaining renderer/editor kill switches and textarea fallback.
7. Enable tags and board/tag follows on selected boards; enable Following, then Latest, then optional digest controls.
8. Backfill and reconcile reputation events in preview mode; enable week/month leaderboards, then custom badge preview/manual awards, then approved auto rules.
9. Enable manual summaries and related topics; enable wiki mode on selected boards; keep split/merge Admin-only until repeated rehearsal passes, then scoped moderators.
10. Run the complete Gate A security, privacy, accessibility, SEO, load, migration, backup, and rollback matrix; record Gate A acceptance.
11. Enable preview fetching for staff and one provider; expand providers only after SSRF/privacy evidence and incident monitoring.
12. Enable expanded files by type, one allowlist change at a time, with scanner/queue/disk monitoring.
13. Enable polls, custom emoji, slash commands, and optional GIF provider independently.
14. Enable automated context on public test content first; expand to private-board content only through explicit policy approval. Keep canonical publication human-controlled.
15. Enable avatars/signatures/board groups if still owned by Gate B.
16. Close Phase 4 only after all Gate B acceptances or approved scope changes are recorded.

### 13.2 Rollback rules

- Disable the affected feature flag before modifying or deleting data.
- Status rollback must preserve canonical history and fall back to `open` presentation without erasing accepted-answer, pin, lock, or archive state.
- Disabling snooze/assignment must restore ordinary inbox visibility and must not delete personal/history records until reviewed.
- Pausing group-DM creation/invites must preserve existing conversation reads, sends if safe, reports, and membership boundaries.
- Disabling advanced rendering must preserve canonical Markdown and fall back to safe plain/reference text; never rewrite stored posts as rollback.
- Disable remote preview fetch and click-to-load rendering before purging metadata or rotating provider policy.
- Pause new expanded uploads before scanner/storage rollback; preserve quarantined and accepted files for inspection and authorized existing reads.
- Disable tag/follow/feed regions independently; a feed rollback must not remove subscriptions or canonical board/thread data.
- Pause badge rules and leaderboard jobs before ledger repair; never delete audit/award history merely to hide a bad rule.
- Unpublish an incorrect summary or relation before changing source data; revert wiki content through a new revision.
- Stop new split/merge operations before application rollback; retain operation/redirect records and run the repair/verifier command.
- Disable polls/custom emoji/GIF/slash commands without breaking existing posts; render stable fallback labels/links.
- Disable automated context before queue cleanup; retain published/manual canonical content and revision provenance.
- Keep Phase 4 migrations additive through the phase; application rollback targets must tolerate the new tables/columns.
- Restore from backup only for proven corruption or unrecoverable loss; feature disablement and repair commands are the first response to logic defects.
- After rollback, rerun route-permission, private-board, membership-boundary, cache-isolation, ledger, redirect, attachment, and worker-health smoke tests.

## 14. Release checklist

### Gate A

- [ ] Phase 3 acceptance or explicit deferrals are recorded.
- [ ] Carryover ledger, Phase 4 scope, owners, requirement ledger, evidence map, product-demand review, and numeric budgets are approved.
- [ ] Deployed schema is reconciled with `SCHEMA.md`; all Gate A migrations pass clean-install and populated-upgrade tests.
- [ ] Topic-state taxonomy, transition matrix, accepted-answer interaction, history, chips, and audit pass.
- [ ] Expanded filters, deterministic For You reasons, private-content count isolation, and query budgets pass.
- [ ] Snooze, due-time return, Snoozed view, direct-notification behavior, and repair command pass.
- [ ] Assignment modes, eligible-user search, scope changes, reassign/unassign, notifications, and audit pass.
- [ ] Group-DM creation, participant cap, membership intervals, owner transfer, mute/unread, block/privacy/account-state, report context, and no-general-browser tests pass.
- [ ] Task lists, tables, horizontal rules, board/thread/post references, round-trip, sanitization, context parity, keyboard/mobile, and textarea fallback pass.
- [ ] Tag catalogue, application, rename/merge, board/tag follows, follow-versus-subscribe behavior, and privacy cleanup pass.
- [ ] Following and Latest feeds pass permission/block/privacy, pagination, no-result, cache, and performance tests.
- [ ] Reputation ledger backfill/reversal/reconciliation and week/month/all-time/board leaderboards pass.
- [ ] Leaderboard opt-out, humane presentation, and no-permission-gating assertions pass.
- [ ] Custom badge definition, asset, manual award, declarative rule, preview, backfill, revoke, notification, and audit tests pass.
- [ ] Manual summaries, source references, revisions, publish/retire/revert, and related-topic curation pass.
- [ ] Wiki eligibility, revisions, attribution, report/moderation, and revert pass.
- [ ] Split/merge dry run, locking, post-ID preservation, counters/read state, redirects/canonical, search/cache invalidation, no-duplicate-events, fault injection, and repair pass.
- [ ] Gate A accessibility, SEO/crawl, security/privacy, load/soak, worker/cron, backup/restore, staged rollout, and rollback evidence are complete.
- [ ] Full Phase 1–3 regression and route/permission matrix remains green.
- [ ] No critical/high defects remain.
- [ ] README, changelog, schema, source docs, runbooks, and evidence index are updated.
- [ ] Gate A product-owner acceptance is recorded.

### Gate B and phase close

- [ ] Link-preview allowlist, asynchronous fetch, SSRF/DNS/redirect controls, metadata sanitization, click-to-load privacy, purge, and kill switch pass.
- [ ] Expanded attachment allowlist, scan/quarantine, safe preview/download, private access, retention, scanner outage, and disk-pressure checks pass.
- [ ] Poll creation/voting/change/close/results/privacy/moderation/no-JS/concurrency tests pass.
- [ ] Custom emoji catalogue/assets/shortcodes/aliases/disable/fallback and approved reaction behavior pass.
- [ ] Slash-command registry and optional GIF provider pass insertion-only, privacy, provider-outage, licensing/storage, and fallback checks.
- [ ] Automated since-last-read context and related-topic refresh pass source-link, provenance, public/private access, stale-source, retry, quality-review, and instant-disable tests.
- [ ] No automated context can publish, change topic status, or become canonical without an authorized human action.
- [ ] Avatar upload/crop/variants/fallback/moderation and signature thresholds/sanitization/height/image/viewer controls pass, or are formally re-scoped.
- [ ] Personal board groups/folders pass private ownership, ordering, migration, and no-authorization-effect checks, or are formally re-scoped.
- [ ] Advanced feed organization/digest behavior passes humane-design, privacy, performance, and unsubscribe checks, or is formally re-scoped.
- [ ] Every Gate B omission has an approved roadmap destination rather than a silent omission.
- [ ] Full Phase 4 regression, security, privacy, accessibility, load, migration, backup, rollback, and operating evidence is indexed.
- [ ] Final product metrics and Phase 5/6 capacity triggers are recorded.
- [ ] Phase 4 product-owner closeout is recorded.

## 15. Post-Phase 4 handoff

After Phase 4 closes, later work should be triggered by explicit product strategy or measured capacity needs rather than unfinished community-content obligations. Carry forward:

- status/filter adoption, snooze accuracy, assignment completion, and For You reason/usefulness data;
- group-DM participant, message, report, leave/remove, and privacy-denial metrics;
- advanced-format, preview, file, poll, emoji, and provider usage/failure data;
- tag/board follow adoption, feed quality, query cost, and digest behavior;
- reputation-ledger reconciliation, leaderboard health, badge rule quality, and abuse signals;
- summary, related-topic, wiki, split/merge, canonical-answer, and automated-context quality data;
- accessibility defects, crawl/indexing behavior, queue/worker load, database/query growth, disk/scanner pressure, and rollback incidents;
- explicit thresholds for a public extension ecosystem, custom roles/governance, passkeys/providers, SSE/WebSockets, external search, Redis, object storage/CDN, fan-out feeds, read replicas, additional workers, PWA/offline, imports, multi-community, and internationalization.

The intended next phase is **Phase 5 — Ecosystem, Identity & Governance**: public plugin/theme distribution with sandbox/review, granular roles/capabilities, passkeys and additional identity providers, and richer governance. Infrastructure capacity swaps remain Phase 6; platform expansion remains Phase 7.

## 16. Source references

- `PHASE_3_PLAN.md` — Phase 3 closeout requirements, explicit deferrals beyond Phase 3, capacity evidence, and the post-Phase 3 handoff.
- `README.md` — product thesis, selected stack, roadmap baseline, interface seams, and completion-evidence policy.
- `DESIGN.md` §§6.5, 6.8, 6.12, 6.18, 8–14 — group DMs, advanced composer/media, profiles, Community Inbox triage/status, architecture, permissions, non-functional requirements, metrics, phasing, and later systems.
- `DECISIONS.md` §§1–8 — authoritative stack, Markdown, short-polling, local storage, embed allowlist, attachment limits, fixed roles, community-memory, leaderboard, badge, plugin, identity, and infrastructure deferrals.
- `SCHEMA.md` §§1–8 — current table shapes, composer/community seams, `reputation_events`, phase map, reconciliation decisions, and foreshadowed topic-status/snooze/assignment schema.
- `COMPOSER.md` §§3–17 — canonical Markdown, P2 syntax, references, files, previews/embeds, custom emoji, slash menu, GIFs/polls, safety, accessibility, unified surfaces, and schema.
- `COMMUNITY.md` §§1–14 — follow targets, feeds, custom badges, time-windowed leaderboards, humane design, reputation ledger, profile elements, and community-memory phasing.
- `ADMIN.md` §§2–5, 8–12 — capabilities, group-message/report privacy principles, split/merge, automation, user/profile moderation, integrations, extension trust, audit, and later custom-role/plugin work.
- `USER.md` §§4–8 — board organization, saved/folders, composing and privacy controls, avatars, signatures, profiles, preferences, and later account/profile work.
