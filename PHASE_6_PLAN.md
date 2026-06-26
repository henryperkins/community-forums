# RetroBoards Phase 6 Plan — Realtime & Scale

**Owner:** Henry  
**Plan type:** Delivery baseline, capacity-triggered release train, and formal phase closeout  
**Plan status:** **Draft — execution is gated by formal Phase 5 closeout, Milestone 0 capacity approval, and at least one evidenced scale trigger**  
**Prepared:** 2026-06-25  
**Source hierarchy:** `DECISIONS.md` is authoritative where documents conflict; `DESIGN.md` is the product source of truth; `SCHEMA.md` owns final database shape; `ADMIN.md`, `USER.md`, `COMPOSER.md`, and `COMMUNITY.md` own their respective surfaces. P0/P1/P2 in the source documents are priority tiers, not delivery-phase numbers.

## 1. Phase objective

Evolve the accepted Phase 5 single-install product from a single-VPS operating shape into a measured, horizontally expandable service without changing the forum’s durable-topic model, authorization semantics, privacy promises, or server-rendered fallbacks.

A member should be able to:

1. Receive notification, presence, inbox, and direct-message freshness with lower delay when Server-Sent Events are justified, while retaining a reliable short-polling fallback.
2. Search public and permitted private forum content through a faster external index without seeing stale, deleted, blocked, private, or out-of-scope results.
3. Open and upload media reliably as storage grows, with public assets accelerated through a CDN and private media still protected by the parent content’s current access rules.
4. Read public pages, inboxes, threads, feeds, profiles, and settings consistently while traffic is spread across multiple application processes or nodes.
5. See their own recent writes immediately even when read replicas or eventually consistent projections are in use.
6. Continue using core reading, posting, authentication, moderation, recovery, and account controls when Redis, the external search engine, the CDN, a replica, an SSE connection, or a projection worker is unavailable.
7. Receive the same privacy, block, membership, moderation, and account-state behavior regardless of which node, worker, cache, search backend, or read path handles the request.
8. Use the product without JavaScript for every previously accepted core action; realtime enhancement must never become a requirement for correctness.

An operator should be able to:

1. Decide whether each infrastructure change is warranted from an approved capacity record rather than installing distributed components by default.
2. Introduce Redis, external search, object storage/CDN, worker pools, read replicas, SSE, horizontal web nodes, and feed materialization independently behind the interfaces established in earlier phases.
3. Classify every data path by source of truth, consistency, privacy, cacheability, rebuildability, and failure behavior.
4. Route strongly consistent reads to the primary database, permit only approved reads on replicas, and force read-your-writes behavior after mutations.
5. Drain, pause, replay, rebuild, fail over, or disable each non-authoritative subsystem without corrupting canonical MySQL data or taking the core forum offline.
6. Observe end-to-end latency, cache behavior, index lag, queue age, replica lag, media health, SSE delivery, projection freshness, capacity headroom, and infrastructure cost from documented dashboards.
7. Perform rolling deployments and node removal without sticky-session dependence, lost jobs, duplicate effects, stale authorization, or user-visible data loss.
8. Rehearse restoration from canonical database and object backups while treating Redis caches, external indexes, and materialized feeds as disposable projections.

Phase 6 is a **capacity-triggered release train**, not a requirement to install every named technology. Gate A establishes the shared scale foundation and executes the external-search, Redis, worker, and media-storage swaps whose approved triggers are met. Gate B adds live delivery and advanced read scaling through horizontal web nodes, SSE, read replicas, and materialized feeds where their independent triggers are met. A component whose trigger remains unhit requires an explicit no-adoption decision with evidence, owner, reassessment date, and preserved interface; it must not be silently marked implemented.

## 2. Entry gate — Phase 5 must be closed and scale must be evidenced

This plan may be refined before Phase 5 closes, but Phase 6 implementation must not begin until all of the following are true:

- Phase 5 Gate A and Gate B have recorded product-owner acceptance, or every incomplete item has an explicit deferral with owner, rationale, risk, and destination phase.
- The Phase 5 evidence index covers package trust, extension isolation, role/capability parity, owner recovery, passkeys/providers, invitations, governance, migrations, accessibility, performance, backup, and rollback.
- No unresolved critical or high-severity Phase 5 supply-chain, sandbox, authorization, identity, privacy, accessibility, data-integrity, extension, or release-operability defect remains.
- The deployed database and extension state have been reconciled against `SCHEMA.md`, including the actual shapes for jobs/events, attachments, search-relevant content, feed relationships, sessions, permissions, packages, service principals, audit, and retention.
- Existing interface boundaries are accepted and testable: `SearchService`, storage/media adapter, cache abstraction, feed service, mailer, webhook/API/event contracts, rate limiter, session repository, worker/job runner, and realtime polling endpoints.
- Every current cache region has an approved variation and invalidation contract. Any region whose authorization dimensions cannot be proven remains non-cacheable.
- Existing canonical operations are idempotent where retries are plausible, and all durable workers have claim, retry, dead-letter, replay, and repair evidence.
- Baselines exist for route p50/p95/p99, database CPU/I/O/connections, query counts and slow queries, buffer-pool behavior, PHP CPU/memory, web concurrency, polling QPS, queue age, worker utilization, search latency/relevance, media bytes and bandwidth, backup/restore duration, feed query cost, audit growth, package-worker load, and incident/rollback history.
- Capacity data includes normal, peak, and failure-mode observations over representative windows rather than a single synthetic spike.
- The owner has approved a **capacity decision record** for every candidate Gate A/B swap. Each record identifies the current bottleneck, sustained trigger, cheaper mitigations already exhausted, expected benefit, added operational/security/privacy cost, rollback path, and success/failure budgets.
- At least one Phase 6 trigger is approved as active. If no trigger is active, Phase 6 remains planned and the product continues on the accepted Phase 5 topology.
- Data-processing, residency, encryption, retention, subprocessors, and breach-response implications are reviewed before private content, identifiers, logs, or media leave the current host or network boundary.
- The canonical failure policy is approved for Redis, search, object storage/CDN, worker pools, SSE, replicas, and feed projections.
- Recovery objectives are approved: database and media RPO/RTO, acceptable index/projection rebuild time, maximum queue replay window, and maximum tolerated realtime freshness loss.
- Every unfinished Phase 5 obligation is placed in a **carryover ledger**. A carryover may block Phase 6, be completed before Gate A, or be explicitly moved later; it must not be renamed as scale work.

**Ownership boundary:** Phase 5 remains responsible for package security, extension isolation, custom-role correctness, identity, governance, service principals, and their operating evidence. Earlier phases remain responsible for product behavior, privacy, authorization, media safety, search semantics, polling fallbacks, feed semantics, and accessibility. Phase 6 changes topology and delivery mechanisms behind accepted contracts; it does not reopen or weaken those contracts.

## 3. Definition of done

Phase 6 is accepted only when all of the following are true:

- Every accepted Phase 1–5 journey remains functional, permission-safe, server-rendered, and usable without JavaScript wherever the underlying action has a no-JS path.
- Every activated infrastructure swap has an approved capacity decision record and measurable before/after evidence on the same fixture, topology class, data volume, concurrency, and observation window.
- Every candidate not activated has an explicit no-adoption decision, preserved interface, reassessment date, and evidence that the trigger is not met.
- Canonical MySQL rows and approved object bytes remain the system of record. Redis, external search documents, CDN objects/caches, replica reads, SSE events, and materialized feeds are never the sole authoritative record of member content, identity, permissions, moderation, security state, or audit.
- Every Phase 6 schema change is additive, documented in `SCHEMA.md`, tested on clean and populated upgraded installations, and compatible with the approved rollback window.
- Canonical writes and their durable outbox/event records commit atomically. A committed write cannot be invisible to all downstream processors, and a rolled-back write cannot publish a valid downstream event.
- Every consumer uses a stable event/job identity, schema version, bounded retry policy, idempotency rule, poison-message handling, and replay-safe repair path.
- Eventual-consistency windows are explicitly documented by surface. The UI does not present an eventually consistent projection as an authoritative security, moderation, account, ownership, or recovery decision.
- Redis, when activated, is used only for approved cache, coordination, rate-limit, lock/lease, pub/sub/stream, or acceleration duties. Canonical access, sessions, roles, bans, recovery, and audit remain recoverable without Redis.
- Redis keys are namespaced, TTL-bound where appropriate, memory-budgeted, privacy-classified, and invalidated/versioned from committed canonical changes. A flush, restart, failover, or total outage cannot corrupt canonical behavior.
- Distributed locks and leases are advisory coordination tools, not substitutes for database uniqueness, transactions, or idempotency.
- Rate limits remain effective across web nodes. High-risk routes use the approved fail-closed or durable fallback policy when the shared limiter is unavailable; ordinary reads degrade without creating an outage loop.
- Session revocation, account-state changes, role/assignment changes, private-board membership changes, block changes, token revocation, and owner recovery propagate across all web nodes within the approved security budget.
- Worker pools are separated by workload and priority where evidence supports it. A large media, preview, feed, search, package, or backfill workload cannot starve authentication email, security actions, moderation, or ordinary notification delivery.
- Worker claims use leases/heartbeats and at-least-once processing with idempotent effects. Crash, timeout, node loss, clock skew, or duplicate delivery cannot silently lose work or create duplicate logical outcomes.
- Scheduled jobs use an approved singleton/lease or partitioning model. Multiple schedulers cannot send duplicate digests, expire the same authority twice, or run overlapping destructive maintenance.
- External search is built through the accepted search interface. The selected engine is replaceable and can be disabled back to MySQL FULLTEXT without changing public routes or product semantics.
- Search indexing is asynchronous, versioned, observable, replayable, and rebuildable from canonical rows. Create, edit, move, visibility, membership, delete, restore, merge/split, username, tag, and permission-sensitive changes produce the correct update or tombstone.
- External search queries are server-generated and include the requester’s current permitted public/private board scope. Every candidate result is rechecked against the canonical read policy before a title, snippet, count, highlight, or URL is returned.
- Search never exposes raw external-engine totals that include filtered or inaccessible content. Permission changes and deletions meet the approved index-removal SLO, and destination routes always recheck access.
- Search-engine outage, lag, partial index, failed reindex, or schema mismatch yields the approved fallback or bounded unavailable state without leaking content or blocking core posting.
- Object storage uses immutable or versioned object identity, checksums, non-executable handling, server-side encryption where applicable, least-privilege credentials, and an approved regional/retention policy.
- Every attachment/media row records enough storage state to locate, verify, migrate, retain, moderate, and delete the object without relying on a public URL as identity.
- Local-to-object migration uses inventory, checksum, dual-read/dual-write or equivalent cutover protection, bounded copy jobs, verification, reconciliation, and a rollback window before local originals are removed.
- Public immutable assets may use CDN caching. Private-board, DM, moderation, account, package-secret, and other protected objects remain non-public and are delivered through an access-gated app/edge path with short-lived credentials or proxying appropriate to their data class.
- A copied or expired private-media URL, stale signed URL, CDN cache key, origin URL, range request, derivative URL, or object metadata request cannot bypass the current parent-content access gate.
- CDN behavior includes explicit cache keys, `Vary` dimensions, signed/private policy, purge/invalidation, origin shielding, range handling, error caching, and an emergency bypass. No authenticated/private HTML is cached publicly.
- Media storage or CDN outage fails according to the approved policy: text reading/posting and unrelated account/moderation operations remain available; uploads may pause safely; existing private media never becomes public as a fallback.
- Multiple web nodes, when activated, are stateless with respect to local files and process memory. They share canonical sessions, storage, cache coordination, rate limits, and invalidation without requiring sticky sessions.
- Rolling deployment uses expand/contract migrations, compatible event/job schemas, node health/drain, graceful worker/SSE shutdown, and mixed-version tests. A node can be removed without dropping committed requests or jobs.
- SSE, when activated, is a one-way enhancement for approved notification, presence, inbox, and conversation invalidations. It does not create chat semantics, typing indicators, per-message read receipts, remote commands, or a WebSocket dependency.
- SSE connections authenticate through the accepted same-origin session model, reveal no token in URLs/logs, validate origin as appropriate, enforce per-user/IP/tab limits, send minimal event data, and never stream private bodies or authorization decisions.
- SSE supports heartbeat, reconnect, `Last-Event-ID` or equivalent bounded replay, deduplication, gap detection, and a canonical resync path. Loss of the event backplane causes resync or short-polling fallback rather than silent stale state.
- Slow consumers, proxy buffering, deploy drain, browser sleep/wake, multiple tabs, network changes, and worker/node loss are handled within the approved connection and freshness budgets.
- Presence delivered through SSE still respects the accepted privacy setting. Hidden, blocked, removed, suspended, or inaccessible users never appear through event payloads, counts, caches, or reconnect history.
- Read replicas, when activated, are used only for approved read classes. Authentication, session/revocation, account state, permission resolution, private membership, security settings, moderation decisions, owner recovery, writes, and read-your-writes paths remain primary-bound unless a stronger proven consistency mechanism is approved.
- Replica lag, schema incompatibility, connection failure, or health degradation removes the replica from routing automatically or through a rehearsed runbook. Requests fall back to the primary without returning stale authorization-sensitive data.
- Read-your-writes is enforced through a commit-position token, primary pin, or another approved mechanism. A member sees a successful post, edit, moderation action, preference change, role change, or membership change immediately enough to meet the locked budget.
- Materialized feeds, when activated, are projections of canonical follows, tags, boards, threads, posts, blocks, privacy, and visibility. They do not become a second social graph, notification source, reputation source, or ranking authority.
- Feed projection uses stable source-event identity, bounded fan-out, hybrid handling for high-fan-out actors if needed, tombstones, rebuild/checkpoint state, and current-access checks at display.
- Feed projection outage or rebuild falls back to the accepted query-time feed. Blocks, private-board removal, deletes, moves, and moderation state changes remove or hide projected entries within the approved privacy SLO.
- Search, cache, feed, media, and realtime projections can all be rebuilt or bypassed independently from canonical data and documented configuration.
- Backup and restore cover canonical database rows, object bytes, encryption/key references, package artifacts required for operation, and configuration. Search indexes, Redis contents, and materialized feeds have tested rebuild procedures rather than being treated as irreplaceable backup sources.
- Infrastructure credentials are separately scoped, rotatable, encrypted, redacted, and unavailable to public extensions. Service ports are private or authenticated; administrative consoles are not exposed as public application routes.
- End-to-end observability preserves correlation across web, outbox, workers, search, storage, CDN, Redis, SSE, replicas, feeds, extensions, and APIs without logging message bodies, private search terms, secrets, tokens, passkey material, or signed URLs.
- Phase 6 meets the numeric availability, latency, queue, freshness, lag, throughput, capacity-headroom, recovery, and cost budgets locked at Milestone 0.
- The full automated suite, migration matrix, adapter contract tests, concurrency tests, fault-injection/chaos tests, browser/no-JS evidence, security/privacy tests, load/soak tests, worker checks, backup/restore, and rollback rehearsals pass.
- No unresolved critical or high-severity security, privacy, authorization, data-integrity, cache, search, media, realtime, replica, queue, projection, deployment, recovery, or release-operability defect remains.

## 4. Scope and release gates

### Gate A — Shared scale foundation and service extraction

Gate A is the minimum Phase 6 release once a scale trigger is active:

- Phase 5 closeout reconciliation, Phase 6 carryover ledger, capacity decision records, service/data classification, topology map, SLO/error budgets, representative scale fixtures, cost baseline, feature flags, and requirement-to-evidence map.
- Durable event and work foundation:
  - transactional outbox for cross-service/projector work;
  - versioned event/job envelopes, stable logical IDs, consumer checkpoints, leases, retries, dead letters, replay, and repair;
  - workload priorities and backpressure;
  - no synchronous dependency of core writes on optional downstream services.
- Redis shared-services foundation where its trigger is approved:
  - cache adapter, namespacing, TTL/version policy, memory/eviction budgets, key privacy classes, cache stampede protection, and global bypass;
  - shared rate-limit/lease/coordination adapter with route-specific failure behavior;
  - session/permission acceleration only as a cache over canonical records;
  - pub/sub or stream foundation for later SSE and invalidation;
  - no canonical-only state.
- Worker-pool and scheduler expansion where queue triggers are approved:
  - separate pools/queues for security and mail, ordinary notifications, media/scanning, preview fetch, search indexing, feed/community projections, package sandbox work, and maintenance/backfills as justified;
  - per-pool concurrency, quotas, priorities, autosizing/manual sizing policy, drain, pause, replay, dead-letter, and health;
  - singleton/partition-safe scheduler and lease behavior.
- External search where its trigger is approved:
  - Meilisearch-first implementation behind `SearchService`, with the final engine/version/topology selected at Milestone 0;
  - index schema/version, public/private scope fields, canonical candidate recheck, sanitised snippets, no raw inaccessible totals, and deleted/visibility tombstones;
  - shadow indexing, parity/relevance corpus, dual-query comparison, bounded backfill, cutover, MySQL FULLTEXT fallback, full reindex, and outage runbook.
- Object storage and CDN where their triggers are approved:
  - S3-compatible storage adapter or another approved object interface;
  - storage-class and visibility taxonomy, immutable key/checksum, metadata reconciliation, encryption, lifecycle, retention, region, and least-privilege credentials;
  - inventory, copy, dual-read/dual-write, verification, cutover, rollback window, and cleanup;
  - CDN for public immutable assets and explicitly approved delivery classes only, with purge/bypass and private-origin controls.
- Scale-ready deployment foundation:
  - externalised mutable files, shared service configuration, health/readiness endpoints, connection budgets, mixed-version compatibility, node identity, drain behavior, and rolling-deploy procedure;
  - a second web node may remain disabled until Gate B, but the application must no longer depend on local process/file state for correctness.
- Full Gate A security/privacy review, accessibility/regression proof, performance and cost evidence, observability, runbooks, staged rollout, rollback, and product-owner acceptance.

### Gate B — Live delivery and advanced read scaling

These items are committed to the broader Phase 6 plan when their independent triggers are approved. They may ship after Gate A, but Phase 6 closeout requires acceptance or an explicit evidence-backed no-adoption decision for each:

- Horizontal web tier:
  - two or more interchangeable app instances behind an approved reverse proxy/load balancer;
  - shared sessions, cache/rate limits, object storage, invalidation, node health, graceful drain, rolling deploy, and no sticky-session correctness dependency;
  - capacity and failure tests for node loss, uneven load, restart, and mixed versions.
- Server-Sent Events:
  - one bounded same-origin stream per active browser context or approved shared-tab strategy;
  - notification-count/new-item, presence, inbox/thread, and DM invalidation events only;
  - minimal payloads, heartbeats, replay/gap/resync, slow-consumer handling, connection limits, deploy drain, privacy checks, and short-polling fallback;
  - shared backplane through the approved Redis stream/pub-sub adapter or equivalent.
- Read replicas:
  - primary/replica connection topology, encrypted replication transport, lag/health monitoring, schema compatibility, read-class policy, query tagging, and automatic/manual route removal;
  - primary pin/read-your-writes, no security-authoritative replica reads, fallback to primary, and recovery/failover rehearsal;
  - no automatic promotion unless a separate database-recovery decision and evidence approve it.
- Materialized community feeds:
  - durable feed projection from canonical events, per-recipient/segment idempotency, bounded fan-out, high-fan-out strategy, checkpoints, tombstones, block/privacy/access filtering, rebuild, and query-time fallback;
  - deterministic chronology/reason semantics retained; no infinite-scroll compulsion, opaque ranking, or duplicate notification/reputation behavior.
- Advanced public delivery and cache topology where justified:
  - public page/fragment edge caching only for data proven public and variation-safe;
  - cache tags/generations, purge/invalidation, stale-if-error policy, origin protection, and immediate bypass for auth/private/moderation/settings/token routes;
  - no shared cache of personalized or private content merely to improve hit rate.
- Resilience and recovery maturity:
  - multi-service backup manifest, database/object consistency checks, restore to a clean environment, search/feed rebuild, Redis cold start, replica recreation, node replacement, and dependency-outage drills;
  - documented RPO/RTO evidence and capacity headroom after restoration.
- Full Gate B hardening, documentation, evidence index, and formal Phase 6 closeout.

### Conditional carryovers — not automatically Phase 6 scope

The following may enter Phase 6 only through the carryover ledger or a signed scope change:

- Any unaccepted Phase 3 cache, media, search, worker, webhook/API, extension, security, accessibility, SEO, backup, or operational requirement.
- Any unaccepted Phase 4 feed, tag, group-DM, attachment, preview, community-memory, moderation, or private-data requirement. Phase 6 may scale an accepted surface only after its canonical behavior is accepted.
- Any unaccepted Phase 5 package, sandbox, role, identity, invitation, governance, service-principal, secret, or publisher requirement. Additional workers do not constitute acceptance of an unsafe extension runtime.
- Product features proposed only because a new service can support them. Infrastructure capability does not automatically add typing indicators, read receipts, chat, recommendation ranking, remote AI, or new data sharing.
- A managed search, Redis, object/CDN, database, or queue provider. Provider selection enters only through an approved data-processing, availability, cost, portability, and exit review.
- Database primary failover or high availability beyond read replicas. It may enter Gate B only through a separate approved recovery decision; replica deployment alone must not imply automatic promotion safety.
- Email-provider migration, because mail remains behind its accepted interface and is not itself a Phase 6 feature unless its current capacity or deliverability trigger is approved.

### Explicitly deferred beyond Phase 6

The following must not delay Phase 6 acceptance unless formally pulled in:

- WebSockets, bidirectional realtime commands, typing indicators, per-message read receipts, live chat semantics, voice/video, ephemeral messaging, or presence precision beyond the accepted privacy-safe model.
- Database sharding, multi-primary writes, active-active regions, global transactional replication, conflict-free replicated data types, or automatic cross-region failover.
- A mandatory microservice rewrite, service mesh, Kubernetes requirement, event-sourcing rewrite, or replacement of canonical MySQL merely because scale tooling exists.
- Arbitrary Redis modules/scripts as application authority, Redis-only queues without approved durability, or cache-dependent authorization.
- Public caching of authenticated HTML, private-board content, DMs, moderation surfaces, settings, token routes, security activity, private search, or signed media responses.
- Cross-install federation, multi-community/multi-tenant operation, organization/workspace tenancy, or shared identity across installations.
- PWA/offline mode, native mobile applications, forum import tooling, full internationalization, and platform-expansion product work; these remain Phase 7.
- Autonomous moderation, machine-written canonical content, opaque recommendation ranking, or third-party processing of private content without a separate approved product/privacy phase.
- Permanent deletion of MySQL FULLTEXT fallback, local-storage rollback data, query-time feed implementation, or single-node deployment support during the Phase 6 rollback window.

## 5. Reconciled and locked implementation decisions

The following decisions are treated as fixed for Phase 6:

1. **Phase ownership stays intact.** Phase 6 scales accepted Phase 1–5 behavior; it does not hide unfinished product, security, identity, governance, or ecosystem work.
2. **Capacity evidence precedes infrastructure.** A named technology is not scope by itself. Every activation requires a sustained trigger, alternatives analysis, owner, budget, and rollback decision.
3. **MySQL remains canonical and single-writer.** Search, Redis, replicas, feeds, and SSE are projections or accelerators. Multi-primary and sharding are outside this phase.
4. **Interfaces remain the contract.** Routes and domain services do not depend directly on vendor clients. Search, storage, cache, rate limiting, queue, realtime, feed, and read routing use replaceable adapters.
5. **Fallbacks are production paths.** MySQL FULLTEXT, local/object dual read, query-time feeds, primary reads, short polling, Redis bypass, and single-node operation are exercised regularly, not left as untested emergency code.
6. **One subsystem changes at a time.** Search, storage, cache, worker, realtime, replica, feed, and web-node cutovers have independent flags and evidence. Multiple simultaneous topology changes require an explicit combined-risk approval.
7. **Canonical write plus outbox is atomic.** Optional systems never receive events from uncommitted state, and a downstream outage never rolls back a valid forum write after commit.
8. **At-least-once delivery is assumed.** Consumers are idempotent; exactly-once marketing claims are not used as a correctness strategy.
9. **Distributed locks are not correctness.** Database constraints, transactions, versions, and idempotency enforce invariants; leases only coordinate work.
10. **Redis is disposable.** It may improve latency and coordination, but a cold start or flush is recoverable and cannot erase user, auth, moderation, package, or audit state.
11. **Cache privacy is deny-by-default.** Public, role-scoped, user-scoped, and non-cacheable classes remain explicit. A missing variation dimension disables caching.
12. **Security-state freshness outranks cache hit rate.** Bans, suspensions, session/token revocation, role grants, owner protection, private membership, blocks, and security settings use primary/validated state and versioned invalidation.
13. **Rate-limit failure policy is route-specific.** Authentication, recovery, invite, passkey, token, package, upload, and moderation routes do not silently become unlimited during shared-store failure.
14. **Durable jobs have a canonical ledger.** Redis may wake or distribute workers, but job identity, state required for recovery, and business-side effects remain durable and inspectable.
15. **Priority protects safety.** Security/recovery/moderation and transactional mail cannot be starved by thumbnails, previews, reindexes, feed backfills, public extensions, or bulk maintenance.
16. **External search is a candidate generator.** Canonical authorization and current-state checks decide what is serialized. External totals and snippets are never trusted blindly.
17. **Meilisearch is the first external-search implementation named by the decisions log, not an irreversible product dependency.** The accepted interface, parity corpus, fallback, and export/rebuild contracts remain engine-neutral.
18. **Private search requires current scope.** The server supplies allowed board IDs or an equivalent approved filter, then rechecks candidates. Client-provided access filters are never authoritative.
19. **Search lag is visible.** The product may indicate bounded indexing delay where useful; it must not fabricate completeness during rebuild or outage.
20. **Object identity is not a URL.** Database rows reference storage driver, key, checksum/version, and visibility class. Delivery URLs are derived, short-lived where protected, and replaceable.
21. **Public and private media have different delivery policies.** CDN acceleration for public immutable assets does not authorize caching protected content.
22. **Private URL expiry is not the only control.** Parent access is checked before issuance or proxying; TTL, audience/context binding where available, origin protection, and no-store/private cache policy reduce residual exposure.
23. **Local originals survive the rollback window.** A storage migration does not delete verified local files until object parity, backup, private access, and rollback evidence are accepted.
24. **Horizontal web nodes are interchangeable.** No correctness depends on local uploads, process memory, local sessions, one-node cron, or sticky load-balancer behavior.
25. **Mixed versions are temporary and compatible.** Deployments use expand/contract schema and versioned events/jobs; incompatible consumers are drained before producers emit unsupported shapes.
26. **SSE is one-way invalidation, not a chat protocol.** Canonical HTTP endpoints still return full state and enforce authorization.
27. **SSE payloads are minimal.** Events carry IDs, counters, versions, or invalidation hints rather than post/DM bodies, email, permissions, or signed media URLs.
28. **Polling remains the fallback.** SSE disablement or unsupported browsers return to the accepted short-poll endpoints with bounded jitter and conditional requests.
29. **Realtime gaps trigger resync.** Clients never assume an unbroken stream. Sequence gaps, expired replay, sleep/wake, or backplane loss cause canonical refresh.
30. **Read routing is explicit by consistency class.** No generic “all SELECTs to replica” rule is permitted.
31. **Read-your-writes is required.** Successful mutations pin or prove freshness before replica reads. The mechanism is tested across tabs/nodes and after retries.
32. **Authorization is primary-bound by default.** Any exception requires a proved bounded-staleness design and must not permit stale privilege or stale revocation.
33. **Replica lag removes capacity rather than correctness.** An unhealthy replica is bypassed; the application may shed optional load but does not serve stale security decisions.
34. **Feed materialization is a rebuildable projection.** The canonical follows, posts, tags, blocks, privacy, and board access remain the source of truth.
35. **Feed ranking remains humane and deterministic.** Phase 6 changes storage/cost, not the accepted topic-anchored semantics or anti-compulsion design.
36. **High fan-out uses a hybrid policy if needed.** The design may fan out ordinary actors and merge high-fan-out sources at read time; a single popular account cannot create unbounded write amplification.
37. **Projection deletion is explicit.** Deletes, moves, blocks, membership revocation, privacy changes, and moderation create tombstone/invalidation work, and display still rechecks access.
38. **Rebuildability is an acceptance criterion.** Search, feeds, caches, Redis streams, and derivatives have deterministic rebuild/checkpoint tools and measured completion time.
39. **Backup protects sources, not caches.** Database/object/configuration backup is mandatory; cache/search/feed backups are optional optimizations only if restore testing proves value.
40. **Service credentials are least privilege.** Search, Redis, object storage, CDN, replica, queue, and metrics credentials are separate, scoped, rotatable, and unavailable to public extensions.
41. **Network boundaries are explicit.** Internal services are private or mutually authenticated; public origin and CDN paths expose only the approved surface.
42. **Privacy review follows the data.** Moving a service off-host or to a managed provider requires processor, region, retention, log, support-access, deletion, and breach-response review.
43. **Cost is a budget.** Capacity gain, operational burden, storage/egress, managed-service cost, and staff time are recorded with the same seriousness as latency.
44. **Schema design precedes implementation.** Any new event, job, projection, storage, or checkpoint table absent from `SCHEMA.md` is reconciled before dependent production code is merged.
45. **Every subsystem has an independent disable path.** Redis cache/limits, each worker pool, external search, object writes, CDN, second web node, SSE, each replica, materialized feeds, and edge caches can be paused or bypassed without disabling core reading/posting.

## 6. Delivery workstreams

| ID | Workstream | Deliverables | Acceptance evidence | Dependency | Gate |
|---|---|---|---|---|---|
| P6-00 | Entry gate, capacity decisions, and SLOs | Phase 5 closeout; carryover ledger; capacity trigger records; source/projection classification; topology/data-flow map; privacy/provider review; fixtures; SLO/error/cost budgets; flags; evidence map | Signed Phase 5 acceptance/deferrals; baseline and trigger report; architecture/privacy review; schema diff; requirement ledger; rollback map | Phase 5 | A |
| P6-01 | Outbox, events, and durable work contracts | Transactional outbox; event/job IDs and versions; consumer registry; checkpoints; claims/leases; retries/dead letters; replay/repair; schema compatibility; idempotent side-effect contract | Commit/rollback tests; duplicate/out-of-order/replay fixtures; poison-job/fault injection; mixed-version compatibility; recovery evidence | P6-00 | A |
| P6-02 | Redis shared services | Cache adapter; key namespaces/classes; TTL/version/invalidation; stampede control; memory/eviction; shared limiter; coordination/leases; optional stream/pub-sub; health/bypass; session/permission cache only | Cold-start/flush/outage/failover tests; cache isolation; stale security-state tests; limiter concurrency/fallback; memory-pressure evidence | P6-01 | A when triggered |
| P6-03 | Worker pools and scheduler | Workload queues/pools; priorities; per-pool concurrency/quotas; scheduler singleton/partitions; drain/pause/replay; dead-letter UI/commands; autosizing/manual policy; worker identities/heartbeats | Crash/lease/duplicate tests; starvation/backpressure/poison-job tests; scheduler duplication tests; queue SLO/load evidence; runbook rehearsal | P6-01; P6-02 where used | A when triggered |
| P6-04 | External search | Meilisearch-first adapter; index schemas/versions; public/private filters; canonical candidate recheck; indexing consumers; tombstones; shadow/dual query; parity/relevance corpus; fallback/reindex | Access-leak negatives; create/edit/move/delete/restore tests; index lag/rebuild/outage; parity/relevance and performance report | P6-01, accepted Phase 2/4 search semantics | A when triggered |
| P6-05 | Object storage and media migration | Storage adapter; visibility/storage classes; immutable keys/checksums; encryption/lifecycle; inventory; dual write/read; copy/verify/reconcile; private delivery; local rollback retention | Missing/corrupt/collision fixtures; checksum migration; private access and signed/proxy URL tests; outage/disk-pressure; backup/restore evidence | P6-00, accepted media lifecycle | A when triggered |
| P6-06 | CDN and public-edge delivery | Public asset/media CDN; origin protection; cache keys/headers; purge/tags; range/error behavior; signed/private policy; emergency bypass; optional public HTML/fragment pilot | Private/auth cache-leak tests; purge/invalidation; stale/error behavior; origin-bypass denial; latency/egress/cost evidence | P6-05 | A/B when triggered |
| P6-07 | Scale-ready web topology and rolling deploys | Externalized mutable state; shared sessions/config; readiness/liveness; node identity; connection budgets; load balancer; graceful drain; mixed-version deploy; node replacement; no sticky correctness | Two-node route/session/write tests; node-loss/uneven-load/deploy tests; session/permission revocation across nodes; rollback proof | P6-02, P6-05, P6-03 | B |
| P6-08 | Server-Sent Events | Same-origin stream; approved event types; minimal payload; heartbeat; replay/gap/resync; backplane; connection/slow-consumer limits; tab coordination; deploy drain; polling fallback | Auth/origin/privacy tests; reconnect/replay/gap/browser-sleep; multi-node delivery; proxy buffering; fallback/browser evidence; connection soak | P6-02, P6-07, accepted polling endpoints | B when triggered |
| P6-09 | Read replicas and consistency router | Replica topology; encrypted replication; health/lag; read classes; query tagging; primary pin/read-your-writes; fallback; schema/version checks; removal/recreation runbook | Lag/stale-state/access-revocation tests; primary-pin tests; replica loss; mixed schema; load evidence; recovery rehearsal | P6-07, stable primary DB | B when triggered |
| P6-10 | Materialized feeds | Feed projection schema; canonical event consumers; recipient/segment idempotency; hybrid fan-out; checkpoints/tombstones; access recheck; rebuild; query-time fallback | Duplicate/out-of-order/backfill tests; block/private/delete/move negatives; high-fan-out load; rebuild/fallback parity | P6-01, accepted Phase 2 + Phase 4 feed semantics _(Fixed 2026-06-26: the Following feed + feed interface originate in Phase 2 P2-09; Phase 4 only extends them.)_ | B when triggered |
| P6-11 | Cache/invalidation and consistency audit | Global cache catalogue; committed invalidation events; generation/tag strategy; route variation; security-state versioning; read-path consistency map; stale-if-error policy | Cross-user/role/private cache tests; write/invalidation race tests; security propagation measurements; bypass/purge rehearsal | P6-02, P6-06, P6-07, P6-09 | A/B |
| P6-12 | Data protection and disaster recovery | Database/object backup manifest; key/config backup; restore clean room; search/feed rebuild; Redis cold start; replica recreation; node replacement; RPO/RTO proof; retention/deletion reconciliation | Restore drills; checksum/object reconciliation; credential rotation; lost-region/provider simulation where approved; recovery timing evidence | P6-04–P6-10 as applicable | A/B |
| P6-13 | Security, privacy, and service isolation | Service accounts/network policy; TLS/auth; managed-provider review; log/query/media minimization; secret rotation; extension denial; admin-port isolation; incident controls | Network/credential abuse tests; private-data log inspection; provider deletion/export; compromised-key rotation; extension boundary tests | All activated services | A/B |
| P6-14 | Observability, performance, and capacity management | Distributed correlation/tracing; service dashboards; SLOs/error budgets; capacity headroom; cost attribution; synthetic/real-user monitoring; load/soak/chaos suite; trigger reassessment | Same-fixture before/after reports; telemetry privacy review; alert exercises; budget and headroom evidence; incident simulation | All activated services | A/B |
| P6-15 | Operations, staged release, and closeout | Console/CLI controls; runbooks; feature flags; migration/cutover plans; staged cohorts; evidence index; docs/schema reconciliation; formal Gate A/final acceptance | Full suite; migration/rollback/backup rehearsals; no critical/high defects; product-owner acceptance and no-adoption decisions | All applicable workstreams | A/B |

## 7. Recommended execution sequence

### Milestone 0 — Close Phase 5 and approve the scale decisions

- Review Phase 5 Gate A/Gate B evidence against the Phase 5 plan rather than relying on roadmap labels.
- Create the carryover ledger and decide whether each item blocks Phase 6, precedes Gate A, or moves to Phase 7/later.
- Capture the deployed topology, schema, object inventory, cache catalogue, queue/job model, worker layout, search implementation, polling load, feed implementation, session model, and backup process.
- Build representative fixtures for large public/private boards, deep threads, heavy inboxes, high-fan-out subscriptions, many followers/tags, group DMs, large media libraries, package workloads, role assignments, audit history, and concurrent authenticated sessions.
- Capture at least normal and peak production-like baselines for web, database, workers, search, media, polling, feed, backup, and package isolation.
- Complete one capacity decision record for Redis, worker pools, external search, object storage/CDN, horizontal web nodes, SSE, read replicas, and materialized feeds.
- Classify every current and proposed data path as canonical, durable projection, ephemeral projection, cache, or derived asset; record privacy class and recovery method.
- Lock numeric SLOs, error budgets, consistency windows, recovery objectives, cost budgets, and capacity-headroom requirements.
- Define independent feature flags, traffic switches, global bypasses, and rollback controls for every Phase 6 subsystem.
- Approve managed/self-hosted service choices, network placement, encryption, credentials, regions, retention, and exit plans where applicable.

**Exit gate:** Phase 5 is formally closed or explicitly re-scoped; at least one Phase 6 trigger is approved; every candidate component has a decision record; topology, data classes, budgets, schemas, evidence targets, and rollback controls are approved.

### Milestone 1 — Establish durable events, jobs, and compatibility contracts

- Reconcile existing notification/email, media, preview, search, feed, package, webhook, retention, and maintenance work against one durable job/event contract.
- Add a transactional outbox or equivalent canonical event ledger for cross-service projections and invalidations.
- Define event type, logical ID, aggregate/source, sequence/version, payload schema, occurred/available time, privacy classification, and retention.
- Define job type, priority, queue/pool, idempotency key, attempts, lease, heartbeat, backoff, dead-letter state, result/error summary, and cancellation/retention.
- Define consumer registrations/checkpoints and compatibility rules for mixed-version producers/consumers.
- Build dispatch, claim, retry, replay, dead-letter, repair, and bounded backfill tools before moving existing work.
- Move one low-risk worker flow through the new contract and prove commit/rollback, duplicate delivery, out-of-order handling, and worker restart.
- Preserve existing domain-specific ledgers where they are the system of record; the common job/event layer references rather than replaces them.

**Exit gate:** Canonical writes publish durable replayable work atomically; duplicate, delayed, missing-consumer, poison, and mixed-version scenarios have deterministic outcomes and repair tools.

### Milestone 2 — Add Redis and separate worker pools where triggered

- Deploy Redis in the approved topology and private network with authentication/TLS where applicable, persistence/failover policy, memory budget, eviction policy, and observability.
- Implement the cache adapter with explicit namespace, value schema/version, TTL, variation class, generation/invalidation, and global bypass.
- Move only one low-risk public cache region first; exercise cold start, stampede, flush, failover, stale value, and bypass.
- Add shared rate-limit and coordination adapters. Define per-route behavior when Redis is slow or unavailable.
- Add session/permission read caching only after revocation/version tests prove bounded security-state propagation; canonical DB checks remain available.
- Add stream/pub-sub capability for invalidation and later SSE, without making it the sole durable event ledger.
- Split workers into approved pools, beginning with safety/transactional work and heavy media/search/backfill work.
- Define per-pool priority, concurrency, resource limits, pause/drain, retry, dead-letter, and autosizing/manual scaling rules.
- Add scheduler singleton/partition leases and prove duplicate scheduler processes cannot duplicate business effects.
- Exercise Redis loss while web, workers, authentication, posting, moderation, and recovery continue under the approved degraded policy.

**Exit gate:** Redis acceleration and worker separation improve the triggered budgets without becoming a source of truth, weakening security controls, or allowing heavy work to starve critical queues.

### Milestone 3 — External search shadow build and cutover

- Select and document the final external search engine/topology, with Meilisearch as the first implementation named by the decisions log.
- Define versioned index aliases/collections for threads, posts, profiles/tags where accepted, and any approved public/private separation.
- Define indexed fields, sanitised snippet source, filterable board/visibility/state fields, sort fields, stop words/synonyms if approved, and maximum document size.
- Build an indexing consumer from canonical outbox events for create, edit, move, merge/split, delete, restore, visibility, tag, username, and permission-sensitive changes.
- Build bounded full reindex and checkpoint/resume tooling with index-version swap rather than in-place destructive schema changes.
- Backfill a shadow index from a consistent canonical snapshot while live events continue; reconcile the gap before declaring it current.
- Run dual-query shadow comparisons against the accepted relevance/permission corpus. Record missing, extra, differently ranked, stale, and unauthorized candidates.
- Recheck every candidate through the canonical read gate before rendering; do not expose raw protected totals.
- Enable staff-only external reads, then a cohort, then public search, then permitted private-board search.
- Rehearse engine outage, partial index, schema mismatch, stale alias, lost event, full rebuild, and MySQL FULLTEXT fallback.

**Exit gate:** External search meets the approved latency/relevance/index-lag budgets and returns zero unauthorized titles, snippets, counts, highlights, or URLs; fallback and reindex are current-build proven.

### Milestone 4 — Object storage and CDN migration

- Finalize storage classes and delivery policies for public images/assets, public attachments, authenticated shared media, private-board media, DMs, moderation evidence, profile media, package artifacts, and temporary/quarantined objects.
- Implement the object adapter with stable key, version/checksum, metadata, encryption, content disposition, content type, range, retention, lifecycle, and delete semantics.
- Inventory local objects and database references; detect missing, duplicate, corrupt, unbound, quarantined, retained, and orphaned items before copy.
- Enable dual write for new objects, verify read-after-write behavior, and preserve local rollback copies.
- Copy historical objects in bounded batches, verify checksum/metadata/derivatives, and record per-object migration state.
- Enable object reads for public staff/test content, then private-board and DM test content through the approved access-gated path.
- Add CDN delivery only for public immutable classes first. Validate origin access, cache headers, keys, purge, range requests, redirects, errors, and content disposition.
- Pilot any private delivery mechanism separately with short-lived access and parent authorization; keep the app-proxy path available.
- Rehearse object provider outage, partial copy, credential revoke, corrupt object, CDN stale cache, private URL leak, disk pressure, and emergency CDN bypass.
- Retain local originals and dual-read ability until migration parity, backup, private-access, and rollback evidence pass for the full observation window.

**Exit gate:** New and migrated media meet integrity, privacy, latency, bandwidth, backup, and cost budgets; no protected object is publicly cacheable or reachable without the approved access path.

### Milestone 5 — Gate A hardening, staged release, and acceptance

- Profile the complete Gate A topology under production-like data, traffic, queue, reindex, media, package-worker, and dependency-failure conditions.
- Measure before/after latency, database load, queue age, search lag, storage bandwidth, cache hit/invalidations, resource headroom, and operating cost.
- Complete security/privacy review for Redis, service credentials, search content, managed providers, object delivery, CDN logs, job payloads, and worker isolation.
- Complete compatibility testing with all optional services disabled and with Phase 1–5 routes running through fallback paths.
- Rehearse Redis bypass, queue pause/replay, search fallback/rebuild, storage dual-read rollback, CDN bypass/purge, credential rotation, backup/restore, and single-node operation.
- Update source documents, topology diagrams, service inventory, budgets, runbooks, and evidence index.
- Roll out Gate A according to §13 and record product-owner acceptance.

**Exit gate:** Gate A is accepted in production with no critical/high defect and no unresolved canonical-data, private-search, media, cache, queue, provider, or rollback incident.

### Milestone 6 — Horizontal web tier and deployment safety

- Remove remaining correctness dependencies on local mutable disk, process-local sessions, process-local rate limits, process-local cache, or one-node cron.
- Define load-balancer health, readiness, connection draining, request timeout, upload handling, forwarded-header trust, TLS termination, and node identity.
- Add a second application instance in a controlled environment using the same canonical database, object storage, Redis adapters, and configuration source.
- Prove login, session rotation/revocation, CSRF, role changes, owner safeguards, uploads, drafts, idempotent submits, moderation, and package controls across alternating nodes.
- Run with sticky sessions disabled; validate that a user may move between nodes on every request.
- Implement expand/contract migration discipline and versioned event/job compatibility for rolling deploys.
- Exercise node crash during request, node drain during upload, rolling deploy with mixed versions, uneven load, service connection exhaustion, and rapid rollback to one node.
- Add capacity-based node addition/removal policy and record minimum headroom after losing one node.

**Exit gate:** Two interchangeable web nodes can serve the accepted product with no sticky correctness dependency, stale security state, lost committed request, local-file divergence, or rolling-deploy incompatibility.

### Milestone 7 — Server-Sent Events with polling fallback

- Define the narrow SSE event catalogue and privacy payload for notification, presence, inbox/thread, and DM invalidations.
- Build the stream endpoint using the accepted same-origin session, origin policy, connection limits, heartbeat, retry hints, and minimal event payload.
- Connect committed domain events to a bounded Redis stream/pub-sub backplane or equivalent, while retaining canonical resync endpoints.
- Implement event IDs, replay window, gap detection, deduplication, full resync, and short-polling fallback.
- Handle browser sleep/wake, network changes, tab duplication, logout, session revoke, role/membership change, account state, and service-worker absence.
- Configure reverse proxy buffering/timeouts/keepalive and graceful stream drain during deploy.
- Add per-user/IP/node connection budgets, slow-consumer detection, memory limits, and load shedding that falls back to polling rather than dropping core access.
- Enable staff, then a cohort, then ordinary users while comparing event freshness, polling QPS reduction, database load, reconnect rate, and fallback success.
- Rehearse Redis/backplane outage, event gap, node loss, proxy restart, session revoke, hidden-presence change, and global SSE disable.

**Exit gate:** SSE lowers the approved freshness/polling cost without leaking private events or becoming required for correctness; every disconnect, gap, and outage converges through resync or polling.

### Milestone 8 — Read replicas and materialized feeds

- Provision the approved read replica from a consistent primary snapshot and verify encrypted replication, schema, collation, time, permissions, and monitoring.
- Classify queries as primary-only, primary-pinned/read-your-writes, replica-eligible public, replica-eligible authenticated non-authoritative, or prohibited.
- Implement the consistency router with explicit repository/query annotations rather than generic SQL heuristics.
- Add lag/health thresholds, automatic route removal, fallback to primary, and alerts. Do not enable automatic promotion by implication.
- Implement and test primary pin/commit-position behavior after posts, edits, deletes/restores, moderation, settings, roles, sessions, memberships, blocks, and security changes.
- Start with public anonymous list/detail reads, then approved non-authoritative authenticated reads. Keep security/authorization and sensitive state on primary.
- Build feed projection schema and consumers from canonical follow/tag/board/content events.
- Shadow materialized results against the accepted query-time feed for membership, blocks, privacy, deletion, moves, tags, chronology, and reason labels.
- Backfill only the approved recent window, reconcile checkpoints, and enable staff/cohort reads with query-time fallback.
- Apply a hybrid fan-out policy for high-fan-out sources if thresholds require it; measure write amplification and freshness.
- Rehearse replica lag/loss, projection lag/loss, duplicate/out-of-order events, block/private revocation, full feed rebuild, and all-reads-to-primary/query-time fallback.

**Exit gate:** Replicas and materialized feeds meet their capacity budgets without violating read-your-writes, security freshness, feed semantics, block/privacy, or canonical fallback.

### Milestone 9 — Phase 6 release candidate and formal closeout

- Run the complete Phase 1–6 regression suite and route/permission matrix in single-node/fallback and activated distributed topologies.
- Rehearse clean install, supported historical upgrades, mixed-version rolling deploy, all optional-service bypasses, Redis cold start, worker pause/replay, search rebuild, object/CDN rollback, node loss, SSE fallback, replica loss, feed rebuild, credential rotation, and clean-environment restore.
- Verify capacity decision records against actual results and record whether each candidate was activated, rejected, or deferred with reassessment date.
- Reconcile `README.md`, `DESIGN.md`, `DECISIONS.md`, `SCHEMA.md`, surface documents, service/data-flow inventory, deployment manifests, runbooks, changelog, and evidence index with the deployed product.
- Capture post-release SLOs, costs, headroom, incident history, fallback use, and Phase 7 platform-expansion constraints.
- Record every accepted Gate A/Gate B requirement and every signed no-adoption/scope-change decision.

**Exit gate:** The Phase 6 evidence index and product-owner closeout are recorded; every activated scale subsystem is operable and reversible, and no hidden Phase 6 obligation remains under an ambiguous “later” label.

## 8. Data and migration plan

### 8.1 Existing tables, interfaces, and behavior to verify before reuse

Phase 6 must verify actual deployment and accepted behavior for:

- canonical forum, account, moderation, privacy, membership, notification, DM, feed, package, role, identity, audit, and retention tables;
- `sessions`, token/revocation records, account status, protected-owner records, role assignments, and permission-cache versioning;
- `attachments` and derivatives, storage keys, checksums, visibility/binding, scan/quarantine, retention, moderation, deletion, and orphan cleanup;
- search service interface, FULLTEXT indexes, search result authorization, snippets, indexable content/state rules, and search repair tools;
- query-time Following/Latest feeds, follows/tags, blocks, profile privacy, board membership, thread status, and delete/move behavior;
- notifications, email/webhook delivery ledgers, domain events if present, idempotency keys, and worker claim/retry behavior;
- plugin/package event and job delivery, sandbox worker queues, service principals, API/webhook credentials, and extension storage;
- cache catalogue, key variation, invalidation, route classification, performance budgets, and emergency bypasses;
- polling endpoints, presence privacy, unread/bell/DM state, and browser fallback behavior;
- backup manifests, database dumps/snapshots, local media backups, encryption/key references, restore procedures, and tested recovery objectives.

A table, adapter, command, dashboard, or runbook named in a prior plan is not evidence that its deployed shape or current-build behavior is accepted.

### 8.2 Schema and durable-state gaps that must be resolved at Milestone 1

1. **Transactional outbox.** Define event ID, logical/idempotency key, event type, schema version, aggregate/source type and ID, aggregate sequence/version where needed, privacy class, payload or payload reference, occurred/available/published timestamps, retention, and indexes. Prevent duplicate logical event publication.
2. **Consumer registry and checkpoints.** Define consumer name/version, event type, partition/shard where used, last checkpoint, lag, status, failure reason, and reset/rebuild history. A checkpoint must not advance before successful durable processing.
3. **General durable jobs.** Define job ID/type, logical key, queue/pool, priority, payload schema/version, available time, attempt count, max attempts, lease owner/expiry, heartbeat, status, result/error summary, created/started/finished/dead timestamps, and retention.
4. **Job attempts and dead letters.** Preserve per-attempt worker, start/end, error class, retry decision, resource use, and redacted diagnostic data. Dead-letter replay creates a linked attempt rather than erasing history.
5. **Worker/scheduler state.** Define worker instance/pool/version, heartbeat, capabilities, drain state, current job, resource summary, and scheduler lease/partition ownership where durable Console visibility is required.
6. **Cache and invalidation versions.** Decide whether generation/version records live in `settings`, a dedicated table, or canonical entity version columns. Security-sensitive invalidation must not depend only on best-effort pub/sub.
7. **Search indexing state.** Define search document version or source row version, index alias/version, last indexed event, tombstone state, index run/checkpoint, errors, and reconciliation samples. Avoid storing a second canonical copy of bodies unless needed for deterministic indexing and retention policy approves it.
8. **Storage object identity.** Extend attachment/package/asset metadata with storage driver, bucket/container, object key, object version/etag where meaningful, canonical checksum, size, encryption/key reference, visibility/storage class, content disposition, migration state, copied/verified timestamps, and last access/health state where needed.
9. **Storage migration ledger.** Define source/destination, batch/run, expected checksum/size, copy attempts, verification, cutover state, rollback-retention deadline, cleanup, and exception reason. Never infer migration completion from URL shape alone.
10. **CDN delivery metadata.** Define public cache generation or immutable asset version, purge/invalidation records, custom-domain/config version, and private-delivery policy references where application-level audit/operation requires persistence. Do not store reusable signed URLs.
11. **Realtime event backplane.** If Redis streams are used, define event ID and retention/replay policy in configuration and tests. Add a SQL `realtime_events` table only if a durable replay requirement cannot be met by canonical domain IDs plus resync; avoid duplicating full private payloads.
12. **Replica consistency tokens.** Prefer transport/session state or commit-position tokens over a table. If durable pin state is required, define user/session, primary-until position/time, reason, expiry, and bounded cleanup without treating the token as authorization.
13. **Feed projection.** Define projected entry ID, recipient or segment, source event ID, source type/ID, actor/target IDs, reason/type, sort time, visibility snapshot fields needed for invalidation, created/tombstoned state, and uniqueness. Do not copy private bodies unnecessarily.
14. **Feed checkpoints/rebuilds.** Define projector version, source range, checkpoint, rebuild run, status, lag, errors, and cutover/rollback generation.
15. **Capacity decisions and topology inventory.** These may remain version-controlled records rather than application tables. If exposed in the Console, store approved metric window, trigger, decision, owner, effective/review date, topology/config version, and linked evidence without allowing the Console record to replace infrastructure-as-code or the evidence archive.
16. **Audit target coverage.** Ensure cache/security bypasses, search cutovers/rebuilds, storage/CDN changes, worker pool controls, SSE global state, replica routing, feed cutovers, provider credentials, and recovery actions have structured audit targets and before/after/config-version references.

### 8.3 Recommended migration and infrastructure groups

Apply additive schema and configuration changes in dependency order, with corresponding features disabled initially:

1. Outbox events, consumer registry/checkpoints, general jobs, attempts/dead letters, worker identities, and scheduler leases.
2. Cache/security generation or entity-version support required for cross-node invalidation.
3. Search indexing run/checkpoint/version/tombstone support and external index configuration references.
4. Storage object identity, checksum/version, migration ledger, delivery class, and CDN generation/purge support.
5. Web-node topology/configuration, readiness/drain metadata where persisted, and shared-service credentials/config versions.
6. Realtime backplane/replay configuration and any minimal durable cursor support proven necessary.
7. Feed projection, checkpoints, generations, tombstones, and rebuild state.
8. Structured audit target additions and capacity/topology Console records where accepted.

Infrastructure changes—Redis, search, object storage, CDN, load balancer, replicas, and worker services—must be versioned and reviewed alongside schema changes. Each group must pass clean-install, populated-upgrade, feature-disabled, mixed-version, backup/restore, and rollback-compatibility tests before dependent behavior is enabled.

### 8.4 Upgrade, backfill, cutover, and cleanup rules

- Existing canonical tables are not bulk-rewritten merely to support a new projection.
- Outbox introduction begins with new writes. Historical projection backfills use explicit bounded jobs and stable logical keys; they do not fabricate historical notifications, emails, audit, or user actions.
- Existing domain-specific delivery rows remain authoritative for their own lifecycle. Migration to common jobs preserves IDs, status, attempts, suppression, and audit rather than resetting them.
- Redis starts empty and warms lazily or through bounded prewarm. No database export is required to restore cache correctness.
- Existing session rows remain canonical. Session cache is introduced read-through/shadow-first and can be bypassed without invalidating sessions.
- Existing rate-limit state is not silently reset during cutover on high-risk routes; use a bounded overlap, durable fallback, or conservative policy approved at Milestone 0.
- The external search index is built under a new version/alias from a consistent snapshot; live events accumulate and are reconciled before alias cutover.
- Search cutover runs shadow/dual query first. MySQL FULLTEXT indexes and code remain through the rollback window.
- A reindex never updates the active alias until completeness, document counts, sampled checksums, permission corpus, and lag checks pass.
- Existing local media is inventoried before copy. Missing or mismatched objects become explicit exceptions; the migration does not create guessed bytes or silently drop references.
- New media uses dual write or another proven rollback-safe pattern before object storage becomes the sole new-write destination.
- Historical media copy is bounded, resumable, checksum-verified, and separated by visibility class. Private content is tested before broad cutover.
- CDN URLs are derived from immutable public object identity. Existing post Markdown/canonical attachment references are not rewritten to vendor URLs.
- Local originals are retained through the approved rollback period and deleted only through a separately reviewed cleanup with backup proof.
- A second web node is added only after all mutable local state has an accepted shared/rebuildable destination. Node-local temporary files use request/job-scoped cleanup and are never the only copy.
- SSE begins with no historical backfill. On connection, the client fetches canonical current state and receives only subsequent invalidations; replay is bounded.
- Replica creation starts from an approved consistent backup/snapshot. Routing remains primary-only until replication catches up, schema matches, and health/lag tests pass.
- Materialized feed backfill covers only the approved recent horizon needed for product usefulness. Older pages can use query-time fallback unless a full backfill is justified.
- Feed generation cutover is atomic by configuration/version. Old projections remain available through the rollback window and are removed only after parity and rebuild evidence.
- No Phase 1–5 column, FULLTEXT index, local file reference, query-time feed path, polling endpoint, or single-node deployment support is removed in the same release that introduces its replacement.

### 8.5 Transactional and consistency invariants

- A canonical write and required outbox records commit or roll back together.
- An outbox event has one stable logical identity. Dispatcher retry cannot create two logical events for one canonical transition.
- A consumer records its business-side effect and advances its checkpoint atomically where possible; otherwise an idempotency key makes replay safe.
- A worker lease expiry may cause duplicate execution, but database uniqueness/version checks prevent duplicate logical effects.
- A dead-letter action preserves original payload identity, attempts, and audit; replay does not masquerade as a new unrelated event.
- Cache invalidation/version advancement occurs only for committed state. A failed write cannot evict into an impossible future version.
- Security-state changes update canonical version/revocation state before or with invalidation. A cache miss cannot restore an older grant.
- A search document version cannot move backward. Late/out-of-order events are ignored or reconciled by canonical version; delete/tombstone wins according to the approved sequence policy.
- Search serialization returns only candidates that pass the requester’s current canonical read policy. Filtering a candidate cannot leak its title/snippet through totals or debug fields.
- An object row cannot become visible until the expected bytes, checksum, metadata, scan/processing state, binding, and authorization class are committed.
- Dual-write failure leaves one clear authoritative copy and a repairable migration state; it never reports upload success while both destinations are unusable.
- Physical object deletion cannot precede canonical deletion/retention/appeal/legal policy and all active references/derivatives.
- CDN purge/generation change follows a committed content/access change. Private access revocation does not depend solely on a best-effort purge.
- A request completed successfully on one web node is visible through shared canonical state to every node. Local memory cannot be required for subsequent authorization or idempotency.
- An SSE event is an invalidation hint. Applying it never changes canonical state; missing or duplicate events converge through canonical fetch.
- Logout, session revoke, ban, suspension, role revoke, owner change, or private-membership removal prevents subsequent protected SSE delivery and protected HTTP access within the approved security budget.
- Replica reads are never used for a query class marked primary-only. A lagging/unhealthy replica is removed before it can answer that class.
- A read-your-writes token/pin follows the user/session across nodes for the bounded period or until the required commit position is visible.
- A feed source event creates at most one active projected entry per approved recipient/segment/logical key.
- Feed tombstone/invalidation and current-access checks prevent a stale projected row from rendering protected content.
- Search indexing, feed projection, cache warming, CDN purge, and SSE publication cannot refire historical notification, email, reputation, badge, moderation, or webhook business effects.
- Redis loss, search loss, feed loss, replica loss, CDN bypass, or worker restart cannot change canonical user permissions, content ownership, counters, audit, or security recovery state.

## 9. Critical acceptance scenarios

| Area | Scenario and expected result |
|---|---|
| Capacity governance | A component whose trigger is not met is not deployed merely because it is listed in Phase 6; the no-adoption record includes evidence, owner, review date, and preserved adapter. |
| Trigger integrity | A one-day spike does not satisfy a sustained trigger unless the approved incident policy says it represents a recurrence risk; cheaper query/index/config fixes are considered first. |
| Canonical source | Redis, external search, feed projection, or CDN data is manually removed; canonical content, accounts, permissions, moderation, and audit remain intact and the projection can rebuild. |
| Outbox commit | A post transaction commits and creates its outbox event exactly once; an injected rollback creates neither visible post nor publishable event. |
| Duplicate event | The same event is delivered repeatedly and out of order to search, feed, cache, and notification consumers; each logical effect remains singular and converges to canonical version. |
| Poison job | A malformed or permanently failing job reaches bounded dead-letter state without blocking the queue partition or being retried forever. |
| Worker crash | A worker dies after claiming and before/after the side effect; lease expiry and idempotency yield either one committed effect or a safe retry. |
| Queue priority | A media backfill and feed rebuild saturate their pools; password recovery, security notifications, moderation, and transactional email remain within their queue-age budgets. |
| Duplicate scheduler | Two schedulers run concurrently; digest, expiry, cleanup, and access-review effects occur once according to their logical keys. |
| Redis cold start | Redis is empty after restart; pages may be slower, but sessions, permissions, reads, writes, moderation, and recovery remain correct and caches warm safely. |
| Redis outage | Shared cache/stream/limiter is unavailable; route-specific fallback applies, SSE falls back/resyncs, and high-risk routes do not become unlimited. |
| Redis stale permission | A role or private-membership revocation occurs while an old cache value exists; the user is denied within the approved security budget on every web node. |
| Cache isolation | Public, role, user, private-board, moderator, settings, and token responses are requested across users/nodes; no cache key or edge entry crosses an authorization boundary. |
| Cache stampede | A hot public key expires under concurrency; request coalescing/jitter/bounds prevent a database collapse and serve no unauthorized stale data. |
| Search shadow parity | External and MySQL queries run on the approved corpus; differences are categorized and no unauthorized external-only result is serialized. |
| Search private scope | A member searches an allowed private board; a non-member using the same query receives no title, snippet, total, highlight, timing-derived count, or URL. |
| Search access revoke | Board membership or role is removed while the external index still contains the document; current-scope filtering and canonical candidate recheck prevent disclosure immediately. |
| Search move/delete | A thread moves from a public to private board or is deleted; stale index candidates are filtered immediately and tombstoned within the index-lag SLO. |
| Search outage | The engine is down or rebuilding; the approved MySQL fallback or bounded unavailable state works without blocking posting or exposing partial private results. |
| Search reindex | A new index version builds while writes continue; checkpoint reconciliation and atomic alias swap produce no missing/duplicate active documents. |
| Search query injection | Client parameters cannot inject arbitrary engine filters, ranking expressions, index names, or admin commands; server-owned query construction applies. |
| Media inventory | A database row references a missing or checksum-mismatched local object; migration records an exception and does not mark it verified or delete the source reference. |
| Dual-write partial failure | Local write succeeds and object write fails, or vice versa; upload response and migration state follow the approved rule and repair can reconcile without duplicate public objects. |
| Object checksum | A copied object differs by one byte; verification fails, the object is not cut over, and the local source remains available. |
| Private media | A private-board or DM object is requested by a guest, former member, removed participant, blocked/ineligible user, or expired signer; no bytes, metadata, range, derivative, or cache status leaks. |
| CDN cache key | Two objects or visibility classes share a filename/path shape; immutable identity and cache policy prevent collision or private/public mixing. |
| CDN stale private data | Access is revoked after a signed URL was issued; the residual exposure stays within the approved short TTL or the proxy/edge authorization denies immediately, and ordinary CDN cache cannot extend it. |
| CDN bypass | CDN is disabled or misconfigured; origin/app delivery remains correct, private, and observable, though slower. |
| Object outage | Object service is unavailable; text content and unrelated actions work, new uploads pause safely, and the application does not redirect private reads to a public fallback. |
| Node interchangeability | Login on node A, post on node B, revoke session on node A, and request on node B; the session is denied within the security budget without sticky sessions. |
| Node loss | One web node is killed during peak traffic; healthy nodes take traffic, in-flight committed writes remain committed, and capacity stays within the N-1 budget. |
| Rolling deploy | Old and new nodes coexist; event/job schemas and database shape remain compatible, and draining prevents dropped uploads/SSE streams/requests. |
| Local state | A node is rebuilt from scratch; no user upload, session, draft, permission, queue, or package state is lost because it lived only on that node. |
| SSE authentication | A logged-out, revoked, banned, cross-origin, or forged request cannot open a protected event stream or infer event existence. |
| SSE payload privacy | Events contain only approved IDs/counts/versions; post/DM bodies, email, role details, private titles, signed URLs, and secrets are absent from wire/logs. |
| SSE reconnect | Browser sleeps beyond the replay window and reconnects with an old event ID; server signals resync and canonical endpoints restore accurate state. |
| SSE duplicate/gap | Duplicate and missing events occur; client deduplicates, detects the gap, resyncs, and reaches the same state as a fresh page load. |
| SSE slow consumer | A client cannot drain events; connection closes within bounds and falls back/reconnects without exhausting node memory or delaying other users. |
| SSE node drain | A deployment drains a node with active streams; clients reconnect to another node and preserve correctness through replay/resync. |
| SSE backplane outage | Redis stream/pub-sub is unavailable; streams close or signal degraded state and clients use short polling. Core writes and notifications persist canonically. |
| Presence privacy | A user disables presence or becomes blocked/inaccessible; no subsequent or replayed event exposes them, and cached counts reconcile. |
| Replica safe read | Public thread reads route to a healthy replica; a replica lag breach removes it and requests fall back to primary. |
| Replica stale ban | A user is banned while a replica is behind; login/write/authorization and sensitive reads use primary state and deny immediately. |
| Read-your-writes | A member creates or edits content and the next request lands on another node; primary pin/commit token shows the successful change without waiting for replica lag. |
| Replica schema mismatch | A migration reaches primary before replica compatibility; routing disables the replica until schema/version checks pass. |
| Replica loss | Replica is stopped; primary handles eligible reads within the approved degraded budget and no request loops or fails open. |
| Feed parity | Materialized and query-time feeds are compared on the corpus; chronology, reason, privacy, block, tag/board follow, and deletion semantics match. |
| Feed access revoke | A projected private entry remains after membership removal; current access check suppresses it immediately and tombstone removes it within SLO. |
| Feed high fan-out | A high-follower actor posts; hybrid policy stays within write/queue budgets and yields one feed entry per eligible viewer without starving critical queues. |
| Feed rebuild | Projection tables are dropped or a generation is corrupted; bounded rebuild plus query-time fallback restores service without re-firing notifications/reputation. |
| Feed duplicate | An event is replayed after a worker crash; unique logical key prevents duplicate visible entries. |
| Backup restore | A clean environment restores database, object bytes, configuration/key references, and package artifacts, then rebuilds search/feed and cold-starts Redis within RPO/RTO. |
| Provider exit | The selected search/object/Redis/CDN service is disabled or credentials are revoked; documented fallback/export/rebuild avoids canonical data loss and records remaining portability gaps. |
| Secret rotation | Search, Redis, object, CDN, replica, and worker credentials rotate without exposing plaintext, breaking public extensions, or requiring full application downtime. |
| No JavaScript | All accepted core actions remain server-rendered; disabling JS only removes SSE/live enhancement, not read/write/account/moderation capability. |
| Single-node fallback | All optional Phase 6 components are bypassed and traffic returns to one web node, primary MySQL, local/object rollback policy, MySQL FULLTEXT, query-time feeds, and polling; core regression remains green. |
| Cost budget | The activated topology meets capacity goals but exceeds approved operating cost; Gate acceptance fails until cost is reduced or the tradeoff is explicitly approved. |

## 10. Test and evidence policy

### 10.1 Required test layers

- **Unit tests:** event/job identity and schemas; retry/backoff; cache keys/variation/generations; consistency classification; primary-pin logic; search document mapping/versioning; storage keys/checksums/delivery classes; SSE event filtering; feed projection keys/tombstones; capacity-decision validation.
- **Repository/service integration tests:** outbox commit; consumer checkpoints; job claims/attempts/dead letters; worker/scheduler leases; cache/security versions; search indexing; storage migration; CDN purge records; feed projection/rebuild; audit targets.
- **Adapter contract tests:** in-memory/fake and selected production adapters for Redis, search, object storage, CDN signer/proxy, queue wakeup, SSE backplane, read router, and feed service must satisfy one shared behavioral contract.
- **Authorization/privacy tests:** every cached, indexed, replicated, projected, streamed, and media-delivered surface across Guest, User, suspended, banned, scoped staff, custom roles, service principals, owner, private-board membership, blocks, group-DM membership intervals, and revoked access.
- **Concurrency/idempotency tests:** outbox dispatch, worker claims, scheduler singleton, rate limits, cache invalidation races, upload dual write, search version races, feed fan-out, replica pin state, SSE reconnect/dedupe, node-drain requests, and rollout switches.
- **Fault-injection/chaos tests:** Redis loss/latency/flush, worker crash, poison job, search outage/partial index, object/CDN outage, corrupt object, node loss, proxy restart, SSE backplane loss, replica lag/loss, feed projection loss, credential revoke, and dependency recovery.
- **Application/HTTP tests:** all affected routes for auth, CSRF, state gates, read routing, cache headers, private no-store behavior, search filtering, media authorization, feature flags, fallback, idempotency, and no-JS behavior.
- **Browser tests:** search cutover/fallback, media upload/read, multi-node session continuity, SSE reconnect/polling fallback, presence privacy, read-your-writes, feed parity, responsive behavior, accessibility, and error messaging.
- **Performance/load/soak tests:** normal/peak/spike traffic; high polling/SSE connections; search query/indexing; object upload/download; CDN hit/miss; queue backlogs; worker pools; node loss; replica lag; high-fan-out feeds; reindex/rebuild concurrent with ordinary use.
- **Security tests:** service authentication/network isolation; Redis/search/object admin-port exposure; cache poisoning; query/filter injection; signed URL misuse; origin bypass; SSRF through adapters; credential leakage; private data in logs/metrics; extension access to infrastructure secrets.
- **Migration/cutover tests:** clean install, populated upgrade, outbox introduction, job migration, shadow/alias search cutover, local-to-object dual-write/copy, second-node enablement, replica bootstrap, feed generation cutover, and old-version rollback compatibility.
- **Operational evidence:** dashboards, alerts, service disable/bypass, queue pause/replay/dead-letter, reindex, storage reconcile, CDN purge, node drain, SSE global disable, replica removal, feed rebuild, credential rotation, backup/restore, and provider-exit exercises.
- **Accessibility/SEO evidence:** realtime status and error announcements remain accessible; no public cache/indexing change alters canonical URLs, metadata, noindex rules, private exclusion, keyboard operation, reduced motion, or screen-reader-critical behavior.

### 10.2 Evidence rules

- A capacity trigger, technology install, Terraform/container manifest, green dashboard, or vendor status page is not proof of product correctness.
- Every activated component must link its decision record, release-candidate commit, topology/config version, data fixture, load profile, before/after result, failure tests, rollback evidence, and acceptance owner.
- Every no-adoption decision must include the measured threshold, observation window, current headroom, next review date, and preserved adapter/test status.
- Performance comparisons use the same hardware/topology class, database version, data volume, concurrency, cache state, and measurement window unless the difference is explicitly part of the decision.
- Security/privacy claims require negative-path, cross-user/cross-role, stale-state, and log/wire inspection evidence.
- Search acceptance requires current-access leak tests and index-lag/rebuild evidence, not only relevance scores.
- Object/CDN acceptance requires checksum inventory, private delivery, origin bypass, signed URL, range, purge, outage, and restore evidence.
- SSE acceptance requires real proxy/browser behavior and connection soak, not only a unit-tested event emitter.
- Replica acceptance requires stale authorization/read-your-writes tests, not only replication health.
- Feed acceptance requires projection/query parity, access revocation, high-fan-out, rebuild, and no-duplicate-business-effect evidence.
- Fallback code paths must be exercised on the same release candidate and intended production topology at least once per release train.
- A gate cannot pass through average percentages when any critical source-of-truth, authorization, privacy, recovery, read-your-writes, or rollback invariant fails.

### 10.3 Target evidence names

The implementation may use different names, but the evidence index should include equivalents of:

- `tests/Unit/Scale/EventEnvelopeTest.php`
- `tests/Integration/Scale/TransactionalOutboxTest.php`
- `tests/Integration/Worker/DurableJobLeaseTest.php`
- `tests/Integration/Worker/QueuePriorityBackpressureTest.php`
- `tests/Integration/Worker/SchedulerSingletonTest.php`
- `tests/Contract/CacheAdapterContractTest.php`
- `tests/Integration/Scale/RedisColdStartFallbackTest.php`
- `tests/Integration/Scale/DistributedRateLimitTest.php`
- `tests/Integration/Core/AppCrossNodeSessionRevocationTest.php`
- `tests/Integration/Core/AppPermissionInvalidationAcrossNodesTest.php`
- `tests/Contract/SearchServiceContractTest.php`
- `tests/Integration/Search/ExternalSearchParityTest.php`
- `tests/Integration/Search/SearchAccessRevocationTest.php`
- `tests/Integration/Search/SearchIndexRebuildTest.php`
- `tests/Integration/Search/SearchFallbackTest.php`
- `tests/Contract/StorageAdapterContractTest.php`
- `tests/Integration/Media/ObjectStorageMigrationTest.php`
- `tests/Integration/Media/PrivateMediaDeliveryTest.php`
- `tests/Security/CdnOriginAndCacheIsolationTest.php`
- `tests/Integration/Media/ObjectStorageOutageFallbackTest.php`
- `tests/Integration/Scale/HorizontalWebNodeTest.php`
- `tests/Integration/Scale/RollingDeployCompatibilityTest.php`
- `tests/Integration/Realtime/SseNotificationStreamTest.php`
- `tests/Integration/Realtime/SsePrivacyAndRevocationTest.php`
- `tests/Integration/Realtime/SseReplayGapResyncTest.php`
- `tests/Browser/SsePollingFallbackTest.php`
- `tests/Performance/SseConnectionSoakTest.php`
- `tests/Integration/Database/ReadReplicaRoutingTest.php`
- `tests/Integration/Database/ReadYourWritesTest.php`
- `tests/Security/ReplicaStaleAuthorizationTest.php`
- `tests/Integration/Community/MaterializedFeedParityTest.php`
- `tests/Integration/Community/FeedAccessRevocationTest.php`
- `tests/Integration/Community/FeedProjectionRebuildTest.php`
- `tests/Performance/HighFanoutFeedTest.php`
- `tests/Operational/DistributedTopologyBackupRestoreTest.php`
- `tests/Operational/AllOptionalServicesBypassTest.php`
- migration/backfill fixtures, service-contract reports, load/soak/chaos results, browser evidence, privacy log inspections, topology diagrams, capacity decision records, and clean-environment restore evidence for all corresponding paths

These are target evidence names, not claims that the files already exist.

## 11. Progress, capacity, observability, and operating requirements

### 11.1 Atomic progress model

Maintain one requirement ledger. Each atomic requirement has one state:

| State | Meaning |
|---|---|
| **R0 — Conflict/unowned** | Requirement, trigger, source-of-truth rule, privacy policy, or owner is contradictory, ambiguous, or missing |
| **R1 — Approved** | Phase/gate, owner, trigger decision, architecture/schema, budgets, acceptance criteria, and rollback are approved |
| **R2 — Implemented** | Code, migration, adapter, and infrastructure configuration are merged/deployed with traffic disabled or limited |
| **R3 — Automatically verified** | Required unit, integration, contract, concurrency, migration, and adapter tests pass |
| **R4 — Release verified** | Browser/no-JS, privacy/security, performance, fault-injection, operations, restore, and rollback evidence pass on the release candidate |
| **R5 — Accepted** | Enabled for the intended topology/cohort and formally accepted, or recorded as an approved no-adoption decision when its trigger is not met |

Report separately:

- **Scope coverage** = requirements at R1 or higher ÷ committed requirements.
- **Implementation coverage** = activated requirements at R2 or higher ÷ activated requirements.
- **Verification coverage** = activated requirements at R4 or higher ÷ activated requirements.
- **Acceptance coverage** = R5 requirements ÷ committed requirements, distinguishing **activated** from **approved no-adoption** outcomes.
- **Trigger coverage** = candidates with a current evidence-backed decision ÷ Phase 6 candidate components.
- **Fallback coverage** = activated components with current-build fallback/rollback evidence ÷ activated components.

Also report unresolved conflicts, unowned requirements, critical/high defects, approved deferrals, temporary waivers, stale trigger reviews, evidence not produced on the current commit/topology, scope added/removed since the prior baseline, and services operating without a completed provider/privacy exit review.

A gate passes only when every critical activated requirement is R5; every other committed item is R4/R5 or has a signed no-adoption/scope-change record; critical/high defects are zero; required migration, dependency-failure, backup, restore, fallback, and rollback exercises pass; budgets and headroom are met; and product-owner acceptance is recorded. Percent averages cannot override a failed canonical-data, authorization, private-media, read-your-writes, recovery, or provider-exit invariant.

### 11.2 Capacity-trigger protocol

Each candidate swap uses one versioned decision record with:

`candidate · owner · current topology · affected user/operator outcome · baseline window · metric and budget · sustained trigger · cheaper mitigations attempted · root-cause evidence · expected gain · added failure modes · security/privacy review · data classes · cost forecast · implementation stages · fallback/rollback · success threshold · abort threshold · reassessment date · approval`

A trigger must normally be sustained across at least the approved peak windows or demonstrate a credible recurrence/availability risk. The exact numeric values are set at Milestone 0 from Phase 5 evidence.

| Candidate | Minimum evidence before activation | Typical trigger categories | Required abort/no-go condition |
|---|---|---|---|
| **Redis shared services** | Cache catalogue; DB/cache pressure profile; cross-node limiter/invalidation need; failure policy | Repeated DB load attributable to reusable reads; multi-node coordination requirement; process-local limiter inconsistency; cache latency budget breach | Core correctness would depend on Redis; security routes lack safe outage behavior; memory/eviction or operational ownership is unbounded |
| **Worker pools** | Queue age by workload; worker CPU/memory; critical-vs-heavy contention; idempotency/lease readiness | Critical queue SLO breaches; one worker saturated; media/search/package/backfill jobs block safety/transactional work | Jobs are not idempotent/durable; no dead-letter/replay; added pools cannot be operated or monitored |
| **External search** | FULLTEXT latency/relevance/load; index migration risk; query corpus; fallback | Search p95/query DB cost or locking exceeds budget; required relevance/filter capability cannot be met safely; search load harms core writes | Canonical access cannot be rechecked; private data processor/storage is unapproved; fallback/reindex is not proven |
| **Object storage** | Disk growth, backup duration, media bandwidth, multi-node requirement, object inventory quality | Local disk headroom/backup/restore or node-locality exceeds budget; media growth makes single-host storage unsafe | Missing checksums/inventory; private delivery is unsafe; provider exit/backup is unproven; migration requires destructive cutover |
| **CDN** | Public media/asset traffic, origin bandwidth, geographic latency, cache-class review | Public immutable delivery consumes origin bandwidth or misses latency budget | Protected content would be publicly cacheable; origin cannot be protected; purge/bypass and cost controls are absent |
| **Horizontal web nodes** | Web CPU/memory/concurrency; maintenance availability; shared-state readiness; N-1 test | One node lacks headroom or maintenance availability budget; traffic growth requires concurrent web capacity | Mutable local state remains; sessions/permissions/rate limits are inconsistent; rolling deploy compatibility is unproven |
| **SSE** | Polling QPS/DB cost; acceptable freshness; proxy/browser connection test; fallback | Polling load or user-visible delay exceeds budget and one-way events solve the measured issue | Connection capacity/proxy support is inadequate; privacy payload/fallback/resync is unproven; feature need is actually bidirectional chat |
| **Read replica** | Read/write load split; primary CPU/I/O; query/index/cache optimization exhausted; consistency map | Replica-eligible reads consume enough primary capacity to threaten write/latency/headroom budgets | Authorization/sensitive queries cannot be separated; read-your-writes is unproven; lag/fallback/operations are unsafe |
| **Materialized feed** | Query-time feed p95/query cost; follow graph and fan-out distributions; parity corpus | Feed queries or DB joins exceed budget at accepted product volume; projection materially reduces cost | Projection cannot enforce current access; write amplification/starvation is unbounded; query-time fallback/rebuild is absent |

Trigger review is repeated after each activated component because one change may remove the need for another. For example, search offload may reduce primary DB pressure enough that a read replica is no longer justified.

### 11.3 Member, product, and operating outcomes

Record cohort, window, denominator, baseline, success threshold, stretch threshold, and guardrail for each applicable metric:

- **Core availability** by public read, authenticated read, write, auth/recovery, moderation, and media class.
- **User-visible latency** p50/p95/p99 for board/thread/inbox/feed/search/media/account/moderation routes, separated by cache, primary, replica, and fallback path.
- **Realtime freshness** = time from committed eligible event to visible bell/presence/inbox/DM refresh, with p50/p95/p99 and fallback rate.
- **Polling reduction** = polling requests and database work per active session before versus after SSE, without increasing stale-state or reconnect errors.
- **Search quality** = successful result-open rate, no-result rate, reformulation rate, stale/missing result rate, permission-filter rate, and reported false-positive/false-negative corpus results.
- **Search freshness** = indexed eligible changes within SLO ÷ eligible changes; deletion/access-removal breaches target zero unauthorized exposure.
- **Media reliability** = successful upload/read rate, checksum failures, private-access denials, object/CDN error rate, and fallback usage.
- **Media performance and cost** = origin bytes avoided, public cache hit ratio, p95 delivery latency, storage growth, egress, and cost per active user or media GB.
- **Queue health** = jobs completed within workload SLO, oldest age, retries, dead letters, poison jobs, and critical-queue starvation incidents.
- **Deployment quality** = change failure rate, rollback rate, user-visible downtime, drain errors, mixed-version incidents, and median deployment duration.
- **Node resilience** = capacity headroom at normal peak and after loss of one web/worker node.
- **Replica quality** = eligible read share, lag distribution, route-removal events, primary fallback, read-your-writes misses, and stale-authority incidents (target zero).
- **Feed quality** = projection freshness, query/projection parity, inaccessible-row suppression, rebuild/fallback rate, topic-open rate, and hide/mute behavior. Infrastructure changes must not introduce engagement-pressure targets.
- **Recovery quality** = achieved database/media RPO/RTO, search/feed rebuild duration, Redis cold-start recovery, and restore reconciliation errors.
- **Operator burden** = incidents, alerts requiring action, false alerts, manual repairs, on-call time, runbook success, and service/provider support burden.
- **Cost efficiency** = total infrastructure cost and staff operating cost per active user, request, post, search, GB stored, and GB delivered, with approved upper bounds.
- **Fallback health** = requests served through MySQL search, query-time feed, polling, primary-only, origin/local media, Redis bypass, or single-node mode and their success/latency.

No success target may reward weaker privacy, stale authorization, addictive feed behavior, suppressed error reporting, or removal of fallback capacity merely to improve an average.

### 11.4 Numeric technical budgets

At Milestone 0, record success and failure thresholds for:

- p50/p95/p99 and error rate for every core route, separated by anonymous/authenticated/private/moderation, node, cache disposition, primary/replica, and dependency state;
- web-node CPU, memory, process/connection count, request concurrency, queueing time, open file descriptors, upload temp usage, and N-1 headroom;
- primary database CPU, I/O, buffer-pool hit rate, connections, lock waits, replication log generation, write latency, slow-query count, and storage growth;
- Redis command p50/p95/p99, connections, memory, fragmentation, eviction, hit ratio, key count/TTL, blocked clients, failover/cold-start duration, stream lag, and error rate;
- cache hit/miss/bypass, stampede wait, invalidation propagation, stale detection, value bytes, and cross-node security-state propagation;
- job enqueue/claim/start/finish p50/p95/p99, oldest age, throughput, attempts, lease expiry, dead-letter rate, pool CPU/memory, priority wait, and drain/recovery time;
- search query p50/p95/p99, result count after canonical filtering, index event lag, full reindex duration, document count/checksum variance, engine CPU/memory/disk, and fallback latency;
- object upload/copy/read/delete p50/p95/p99, checksum error, availability, storage bytes/objects, dual-write failure, migration backlog, lifecycle backlog, and restore throughput;
- CDN hit ratio, origin request/bytes, edge p95, purge propagation, signed/private issuance latency, error-cache rate, range behavior, egress, and cost;
- SSE connect time, concurrent connections per node, event delivery p50/p95/p99, reconnects, replay hits/misses, resync rate, slow-consumer closes, memory/CPU per connection, and fallback rate;
- replica lag p50/p95/p99/max, eligible read percentage, route-removal time, primary fallback, read-your-writes pin duration, query latency, and replay/recreate duration;
- feed projection event lag, fan-out count/distribution, entries/sec, write amplification, queue age, tombstone latency, parity mismatch, rebuild duration, storage growth, and query-time fallback latency;
- rolling-deploy drain time, mixed-version duration, failed requests/jobs/streams, rollback time, and minimum available capacity;
- database/object backup duration/size, restore duration, reconciliation error, search/feed rebuild, Redis cold start, and achieved RPO/RTO;
- total and per-unit infrastructure cost, alert volume, operator intervention, and capacity headroom.

Every measurement record must include service/route/job, topology and hardware class, software/config version, database/index/object fixture, privacy class, concurrency, cache state, consistency route, measurement window, p50/p95/p99, throughput, error rate, resource use, queue/index/replica lag where relevant, and cost estimate.

### 11.5 Required telemetry

- One correlation/trace identity propagated from HTTP write through canonical transaction, outbox dispatch, job claims, search/feed/cache/SSE consumers, webhooks, and result, without logging private payloads.
- Web request route, status, latency, query count/time, memory, node, authenticated/public classification, consistency route, cache disposition, and dependency fallback.
- Primary database health, slow queries, lock waits, connections, storage, replication-log rate, backup status, and schema version.
- Redis latency, connections, memory/eviction, cache region hit/miss, invalidation/version, limiter decisions by policy, stream consumer lag, cold starts, and bypass state.
- Job pool queue depth/age, claim latency, attempts, lease expiry, dead letters, worker heartbeat/version/resource, scheduler lease, and priority starvation signals.
- Search query/index latency, index version, checkpoint/lag, document/tombstone count, rejected/filtered candidate count, fallback use, rebuild state, and error class without raw private search terms or bodies.
- Object storage class, operation, bytes, latency, checksum result, migration state, scan/quarantine, lifecycle, denied private reads, and provider errors without signed URLs or object secrets.
- CDN cache hit/miss/error, origin bytes, purge/generation, delivery class, region/edge aggregate, and private-policy denial without private path/query leakage.
- Web-node health/readiness, active requests/uploads/SSE streams, connection pools, drain state, deploy version, load distribution, and N-1 headroom.
- SSE active connections, event type/count, delivery lag, replay/gap/resync, reconnect reason, slow-consumer close, fallback, and privacy-policy denial without event bodies.
- Replica health, lag, schema/config version, routed query class, primary fallback, pin/read-your-writes state aggregate, and stale-read guard activations.
- Feed event/fan-out/tombstone/rebuild/checkpoint lag, projected entry count, high-fan-out path, parity samples, access suppression, and query-time fallback.
- Backup/restore checkpoints, database/object manifest/checksums, key/config availability, rebuild state, achieved RPO/RTO, and reconciliation exceptions.
- Cost and capacity telemetry by service, environment, workload, storage, traffic, and active-user denominator.
- Alerts tied to an owner, actionable runbook, severity, SLO/error budget, suppression policy, and test history.

### 11.6 Required runbooks

- Determine whether a capacity trigger is still valid and reverse an activation decision safely.
- Globally bypass Redis caches while preserving rate-limit/security policy; cold-start and safely purge one namespace/region.
- Diagnose and repair stale permission/session/private-membership cache state across nodes.
- Pause, drain, resize, replay, or dead-letter each worker pool without starving critical work.
- Recover a lost/expired worker lease, duplicate scheduler, poison job, or stuck backfill.
- Disable external search, route to MySQL FULLTEXT, inspect index lag, repair a missing event, and perform versioned full reindex/alias cutover.
- Freeze object migration, reconcile missing/mismatched objects, switch dual-read preference, pause uploads, and return reads to local/origin policy.
- Purge or bypass CDN, rotate origin credentials, respond to a suspected private-cache exposure, and verify global invalidation.
- Add, drain, remove, replace, and roll back a web node; return safely to one-node operation.
- Disable SSE globally or by event type, verify polling fallback, drain streams for deploy, diagnose reconnect storms, and rebuild/resync after backplane loss.
- Remove an unhealthy replica from routing, force primary reads, diagnose lag, recreate a replica, and verify schema/consistency before re-entry.
- Pin a user/session to primary after a read-your-writes incident and inspect routing evidence.
- Disable materialized feeds, route to query-time feeds, inspect projector lag, apply tombstones, and rebuild/cut over a generation.
- Rotate every service credential and encryption/key reference without logging plaintext or exposing it to packages/extensions.
- Restore database and object data into a clean environment, validate permissions/media/audit, cold-start Redis, rebuild search/feed, recreate replicas, and compare RPO/RTO.
- Operate during managed-provider or network outage, including communication, fallback capacity, data export, and exit.
- Diagnose cost-budget breach, runaway fan-out, egress spike, cache-miss storm, or unbounded storage/job growth.

## 12. Risks and controls

| Risk | Control |
|---|---|
| Phase 6 becomes a technology shopping list | Capacity decision records, sustained triggers, cheaper-mitigation review, independent activation, no-adoption outcomes, and owner approval |
| Infrastructure complexity exceeds product value | Cost/operator budgets, minimum headroom gain, staged components, stop/abort thresholds, and provider-exit review |
| A projection becomes an accidental source of truth | Canonical/projection classification, rebuild tests, destructive projection-loss drills, and no security decisions from projections |
| Event publication loses or invents work | Transactional outbox, stable IDs, schema versions, dispatcher retry, checkpoint rules, and commit/rollback tests |
| At-least-once processing duplicates business effects | Idempotency keys, DB uniqueness/versioning, consumer contracts, replay tests, and side-effect ledgers |
| Distributed lock split-brain corrupts data | Locks coordinate only; canonical DB transactions/constraints and fencing/version tokens enforce invariants |
| Redis outage becomes a site outage | Disposable-state policy, global bypass, route-specific limiter fallback, cold-start tests, and no Redis-only canonical state |
| Redis eviction revives stale authority | Canonical version/revocation checks, short TTL for sensitive caches, security-state invalidation, and primary-bound decisions |
| Cache leaks across users/roles/private boards | Explicit variation classes, deny-by-default, generation/version tests, private no-store, and cross-user/node regressions |
| Cache stampede overloads MySQL | Request coalescing, jitter, bounded stale-if-error for public data, prewarm where justified, and load tests |
| Shared limiter fails open during attack | Route-specific fail-closed/durable fallback, local emergency caps, alerting, and tested degraded policy |
| Heavy workers starve recovery/moderation | Separate priority pools, quotas, backpressure, critical reserve, queue-age SLOs, and starvation tests |
| Worker autoscaling causes duplicate or runaway work | Durable leases, idempotency, max concurrency/rate, resource budgets, drain, and circuit breakers |
| Scheduler duplication repeats global jobs | Singleton/partition leases plus business idempotency and duplicate-scheduler tests |
| Search index leaks private content | Server-owned allowed scope, canonical candidate recheck, filtered totals, minimal logs, tombstones, and direct destination gate |
| Search index is stale after delete/move/revoke | Versioned events, lag alerts, immediate canonical filter, privacy SLO, tombstones, and repair/reindex tools |
| Search provider receives unapproved private data | Self-host/private-network default or explicit processor review, data minimization, region/retention policy, and no DMs unless separately approved |
| Search cutover damages relevance | Golden corpus, shadow dual query, human review, cohort rollout, reversible alias, and MySQL fallback |
| Object migration loses or corrupts media | Inventory, stable checksum/version, dual write/read, resumable copy, per-object verification, local rollback retention, and restore tests |
| Public CDN exposes protected objects | Separate delivery classes/origins, no public private-cache policy, parent gate, short-lived access, origin protection, and leak drills |
| Signed URLs outlive authorization | Short TTL, context/audience controls where available, app proxy for sensitive classes, immediate canonical issuance gate, and documented residual window |
| CDN caches authenticated HTML | Explicit route allowlist, private/no-store defaults, cache-key tests, authenticated bypass, and emergency global disable |
| Object/CDN provider lock-in | S3-compatible/adapter contract, canonical storage metadata, no vendor URLs in Markdown, export tool, and provider-exit rehearsal |
| Horizontal nodes disagree on sessions/permissions | Canonical shared state, versioned invalidation, no sticky correctness, cross-node revoke tests, and primary-bound security checks |
| Rolling deploy mixes incompatible schemas/events | Expand/contract migrations, versioned envelopes, compatibility matrix, producer gating, node drain, and short mixed-version window |
| Node loss overloads survivors | N-1 headroom budget, load shedding for optional work, queue backpressure, health routing, and regular node-loss test |
| SSE exhausts PHP/process/proxy capacity | Trigger validation, dedicated connection budget/process model, proxy tuning, per-user limits, slow-consumer close, soak tests, and polling fallback |
| SSE leaks private data through event payload/replay | Minimal invalidations, session/origin checks, current privacy state, bounded replay, no bodies/signed URLs, and log inspection |
| SSE loss leaves clients silently stale | Heartbeats, gap detection, resync, canonical page fetch, polling fallback, and visible degraded telemetry |
| Reconnect storm overloads dependencies | Jittered retry, connection admission, backoff, shared-tab option, load shedding, and reconnect-storm tests |
| Replica serves stale ban/permission/membership | Explicit primary-only classes, canonical authorization, lag removal, security tests, and no generic SELECT routing |
| User cannot see their own successful write | Commit token/primary pin, cross-node propagation, bounded expiry, and read-your-writes browser tests |
| Replica lag or failure overloads primary | Capacity reserve, circuit breaker, fallback budget, optional-load shedding, and alert/runbook |
| Replica is promoted unsafely | No automatic promotion by default; separate DB recovery decision, data-loss assessment, and rehearsed promotion only if approved |
| Materialized feed leaks removed/private content | Current access recheck, tombstone events, block/membership invalidation, privacy SLO, and query-time fallback |
| Feed fan-out explodes write/queue cost | Distribution analysis, hybrid fan-out, caps/backpressure, high-fan-out tests, and no notification coupling |
| Feed projection changes product semantics | Parity corpus, deterministic reasons/chronology, humane-design guardrails, cohort comparison, and fallback |
| Backups omit new authoritative objects or keys | Multi-service backup manifest, checksum reconciliation, key/config inventory, clean restore, and RPO/RTO evidence |
| Search/cache/feed backups create false confidence | Treat them as rebuildable; test rebuild from canonical sources and record optional backup value separately |
| Service credentials leak to plugins/logs | Separate service identities, secret broker/vault, redaction, extension denial, least privilege, and rotation tests |
| Internal services are internet-exposed | Private networking/firewall, authentication/TLS, admin-port scans, origin protection, and deployment review |
| Observability leaks private queries/content | Structured metadata only, sampling/redaction, log retention/access controls, query hashing/aggregation, and privacy inspection |
| Managed service outage has no exit | Fallback capacity, export/rebuild, provider-exit runbook, contract/data-retention review, and periodic drill |
| Scale improves latency but violates cost budget | Cost budgets as release criteria, per-unit attribution, abort threshold, architecture simplification, and explicit owner tradeoff |
| Single-node fallback rots after distribution | Mandatory fallback regression, scheduled bypass drills, retained code/schema support, and documented maximum fallback load |
| Phase 7 work leaks into scale delivery | Explicit deferral list and requirement ownership; topology does not imply PWA, tenancy, federation, mobile, imports, or i18n |

## 13. Staged release and rollback

### 13.1 Recommended enablement order

1. Deploy additive event/job/checkpoint/storage metadata with every Phase 6 traffic flag disabled.
2. Enable telemetry, correlation, decision dashboards, and baseline collection before changing traffic.
3. Run one low-risk outbox consumer and worker pool in shadow/duplicate-safe mode; then migrate approved background workloads by priority.
4. Enable Redis for one public cache namespace, then shared rate limits/leases, then selected session/permission acceleration only after security propagation tests.
5. Build external search in shadow; run dual query and staff-only reads; expand public search before permitted private-board search.
6. Enable object dual write for new staff/test uploads; copy and verify public historical objects; then private-board/DM classes; keep local reads available.
7. Enable CDN for versioned static assets, then public immutable media, one delivery class at a time.
8. Complete Gate A security, privacy, performance, cost, dependency-outage, backup, and rollback matrix; record Gate A acceptance.
9. Add a second web node with sticky sessions disabled; run staff/cohort traffic and rolling-deploy/node-loss tests before broad balancing.
10. Enable SSE for staff with a low connection/event set, then cohorts; retain polling and compare freshness, QPS, resource, reconnect, and privacy metrics.
11. Add a read replica with routing disabled; validate health/schema/lag, then enable public anonymous read classes, then approved non-authoritative authenticated reads.
12. Build materialized feeds in shadow; compare with query-time results; enable staff/cohorts and retain per-request/global fallback.
13. Pilot optional public edge HTML/fragment caching only after all authenticated/private bypass evidence passes.
14. Run full topology fault injection and clean-environment restore; record all active/no-adoption decisions.
15. Close Phase 6 only after every Gate B candidate is accepted or has a signed evidence-backed no-adoption/scope-change record.

### 13.2 Rollback rules

- Disable the affected traffic/adoption flag before changing canonical data or deleting projection state.
- Redis rollback globally bypasses cache/coordination paths and routes limits according to the approved fallback; never “repair” canonical state from Redis values.
- Pause relevant workers before changing job schemas or replaying dead letters; preserve job/event identity and attempts for investigation.
- Search rollback switches reads to MySQL FULLTEXT, stops new index consumers if necessary, and preserves the current index for inspection. Do not delete FULLTEXT indexes during the Phase 6 window.
- Object rollback pauses new object-only writes, switches dual-read preference to verified local/origin copies, and preserves destination objects for reconciliation. Never bulk-delete either copy during an incident.
- CDN rollback bypasses the edge, purges/changes generation as needed, and serves through the approved origin/app path. Rotate exposed origin/signer credentials before restoring traffic.
- Horizontal-web rollback drains extra nodes and returns to one known-good node without changing canonical sessions, objects, jobs, or schema.
- SSE rollback stops admitting streams, drains existing connections, signals/requires resync, and returns clients to short polling. Do not delete canonical notification/presence state.
- Replica rollback marks every read class primary-only and removes the replica from routing. Do not promote it automatically as part of a read-scaling rollback.
- Feed rollback switches to query-time reads and pauses projectors; preserve projection/checkpoint state for diagnosis and rebuild.
- Edge public-cache rollback bypasses/purges the affected route class. Authenticated/private routes remain bypassed throughout.
- Mixed-version rollback follows the approved compatibility window; incompatible producers/consumers are drained before code rollback.
- Keep Phase 6 migrations additive through the phase. Application rollback targets must tolerate event/job/storage/feed tables and widened metadata.
- Restore from backup only for proven canonical corruption or unrecoverable loss. Projection disable/rebuild, route fallback, credential rotation, and repair commands are the first response to optional-service defects.
- After rollback, rerun canonical write/outbox, route-permission, session/role revocation, private-media, search fallback, cache isolation, polling, primary-read, query-time feed, worker health, and backup-integrity smoke tests.

## 14. Release checklist

### Gate A

- [ ] Phase 5 acceptance or explicit deferrals are recorded.
- [ ] Carryover ledger, capacity decision records, topology/data classifications, service/provider reviews, SLO/error/cost budgets, owners, evidence map, and rollback controls are approved.
- [ ] At least one Phase 6 trigger is active; every inactive candidate has a current no-adoption decision and reassessment date.
- [ ] Deployed schema is reconciled with `SCHEMA.md`; Gate A migrations pass clean-install, populated-upgrade, feature-disabled, mixed-version, backup/restore, and rollback-compatibility tests.
- [ ] Transactional outbox, event/job schemas, stable identities, consumer checkpoints, leases, retries, dead letters, replay, repair, and audit pass.
- [ ] Duplicate, out-of-order, delayed, poison, rollback, worker-crash, and scheduler-duplication tests pass.
- [ ] Activated Redis cache namespaces pass privacy/variation, cold-start, flush, eviction, stampede, invalidation, memory, bypass, and outage tests.
- [ ] Activated shared limiter/coordination passes concurrency, route-specific failure policy, security-state freshness, and cross-node behavior.
- [ ] Activated worker pools pass priority, starvation, backpressure, concurrency/resource, pause/drain/replay, dead-letter, and queue-age budgets.
- [ ] External search, when activated, passes shadow parity/relevance, public/private authorization, candidate recheck, filtered totals, index lag, tombstone, reindex, outage, and MySQL fallback tests.
- [ ] Object storage, when activated, passes inventory, checksum, dual-write/read, bounded copy, private delivery, migration reconciliation, outage, retention, backup, and rollback tests.
- [ ] CDN, when activated, passes public-only class, origin protection, cache key/header, range, purge/generation, stale/error, private denial, bypass, latency, egress, and cost checks.
- [ ] Scale-ready deployment has no correctness dependency on process-local sessions/cache/limits or mutable local files.
- [ ] Cache/security invalidation catalogue and source/projection consistency map are complete.
- [ ] Service accounts, network boundaries, credentials, encryption, logs, provider retention/deletion, and extension denial pass security/privacy review.
- [ ] Gate A availability, route/database/cache/queue/search/media/CDN, recovery, headroom, and cost budgets pass on production-like fixtures.
- [ ] Redis bypass, queue recovery, search fallback/reindex, storage rollback, CDN bypass/purge, credential rotation, single-node operation, and clean restore runbooks are rehearsed.
- [ ] Full Phase 1–5 regression, route/capability matrix, browser/no-JS, accessibility, SEO/private-exclusion, and all-optional-services-disabled paths remain green.
- [ ] No critical/high defects remain.
- [ ] README, changelog, schema, service inventory, topology/data-flow diagrams, capacity records, runbooks, and evidence index are updated.
- [ ] Gate A product-owner acceptance is recorded.

### Gate B and phase close

- [ ] Horizontal web nodes pass no-sticky session, cross-node CSRF/idempotency, upload, session/role/membership revocation, node-loss, N-1 headroom, rolling-deploy, drain, and one-node rollback tests, or have an approved no-adoption decision.
- [ ] SSE passes same-origin authentication, minimal payload, privacy/revocation, heartbeat, replay/gap/resync, slow-consumer, reconnect storm, proxy, browser sleep/wake, node drain, backplane outage, soak, and polling fallback, or has an approved no-adoption decision.
- [ ] Read replicas pass encrypted replication, health/lag/schema checks, explicit read classes, primary-only security paths, read-your-writes, route removal, primary fallback, loss/recreate, and no-automatic-promotion policy, or have an approved no-adoption decision.
- [ ] Materialized feeds pass canonical-event idempotency, parity, high-fan-out policy, blocks/privacy/private-board changes, tombstones, freshness, rebuild, storage, and query-time fallback, or have an approved no-adoption decision.
- [ ] Optional public page/fragment edge caching passes strict allowlist, public variation, invalidation, stale-if-error, auth/private bypass, SEO, purge, and emergency disable, or has an approved no-adoption decision.
- [ ] Cross-node cache/session/permission/security-state propagation meets the approved security budget.
- [ ] Every activated service meets route/event/index/media/replica/feed/deploy/recovery/cost budgets and retains required capacity headroom.
- [ ] Full dependency-failure matrix passes: Redis, search, object storage, CDN, worker pool, one web node, SSE backplane, replica, feed projector, and provider/network outage.
- [ ] Clean-environment restore recovers database/object/configuration/key/package sources and rebuilds Redis/search/feed/replica state within approved RPO/RTO.
- [ ] Provider exit/export and service-credential rotation exercises pass for every activated managed/external component.
- [ ] Every Gate B omission has an approved trigger/no-adoption record and roadmap destination rather than a silent omission.
- [ ] Full Phase 6 regression, security, privacy, consistency, load/soak/chaos, migration, backup, restore, fallback, rollback, and operating evidence is indexed.
- [ ] Final SLOs, costs, capacity headroom, incidents, fallback health, and Phase 7 constraints are recorded.
- [ ] Phase 6 product-owner closeout is recorded.

## 15. Post-Phase 6 handoff

After Phase 6 closes, later work should be driven by platform strategy rather than unfinished scale obligations. Carry forward:

- web/database/cache/search/media/CDN/queue/SSE/replica/feed SLOs, error budgets, headroom, costs, and incident history;
- outbox/job volume, retries, dead letters, consumer lag, worker-pool utilization, scheduler health, and rebuild duration;
- search quality, freshness, permission filtering, fallback/reindex behavior, index size, and provider burden;
- object growth, checksum/reconciliation exceptions, private delivery, CDN origin/egress, backup/restore, and provider-exit evidence;
- session/permission invalidation, cross-node behavior, deploy quality, N-1 capacity, and one-node fallback health;
- SSE adoption, delivery freshness, polling reduction, connection/resource use, reconnect/resync, privacy denials, and fallback success;
- replica eligible-read share, lag, primary fallback, read-your-writes, recreation, and stale-authority incidents;
- feed projection freshness, parity, fan-out distribution, tombstones, rebuild, query-time fallback, and humane-design guardrails;
- RPO/RTO, restore findings, credential/key rotation, privacy/processor reviews, alert quality, operator burden, and total/per-unit cost;
- explicit constraints that Phase 7 must preserve: canonical durable topics, server-rendered/no-JS access, source-of-truth boundaries, permission/privacy semantics, media access classes, extension isolation, and scale fallbacks.

The intended next phase is **Phase 7 — Platform Expansion**: product-level decisions and delivery for PWA/offline support, native-mobile direction, forum imports, multi-community/multi-tenant architecture, full internationalization, and any separately approved cross-install federation. Phase 7 must use the Phase 6 service interfaces and evidence rather than assuming that horizontal capacity automatically solves tenancy, offline conflict, migration, or localization design.

## 16. Source references

- `PHASE_5_PLAN.md` — Phase 5 closeout requirements and the explicit handoff to capacity-triggered SSE, external search, Redis, object storage/CDN, read replicas, additional workers, and materialized feeds.
- `PHASE_4_PLAN.md` — query-time feed extension (the Following feed + feed interface originate in Phase 2 P2-09; Phase 4 extends them), scale triggers, explicit deferral of infrastructure swaps to Phase 6, and community/privacy semantics that projections must preserve.
- `PHASE_3_PLAN.md` — single-VPS performance/caching baseline, short-polling decision, media/search/cache interfaces, worker operation, and the rule that new infrastructure requires measured need.
- `PHASE_2_PLAN.md` — accepted FULLTEXT search, notification/email outbox behavior, short-polling presence/bell, query-time feeds, private-board access, and worker/fallback semantics.
- `README.md` *(orientation pointer only — not a source of ground truth)* — product thesis + selected PHP/MySQL/server-rendered stack overview. _(The design seam placing email, search, media storage, and feeds behind replaceable interfaces is authoritative in DECISIONS §2, not README.)_
- `DESIGN.md` §§6.9–6.15, 8–14 — search, notifications, realtime/presence, request architecture, non-functional requirements, success metrics, phasing, and later SSE/WebSocket/platform direction.
- `DECISIONS.md` §§1–8 — authoritative MySQL/VPS/worker shape, short-polling-first realtime, MySQL FULLTEXT with Meilisearch later, local disk with S3/CDN later, and interface-backed growth decisions.
- `SCHEMA.md` §§1–9 — the consolidated Phase 1–3 tables, FULLTEXT indexes, attachment storage seam, reconciliation rules (§7), and foreshadowed schema (§8). **Note:** Phase 6 infrastructure schema (transactional-outbox/event and job tables, search-projection state, object-storage/media metadata, feed-projection/checkpoint tables) is **not yet in SCHEMA.md**; it is defined in this plan and should be folded back on acceptance.
- `ADMIN.md` §§2–12 — capability/state precedence, notification/worker operation, integration/plugin isolation, audit, privacy, Console operations, and later ecosystem/governance boundaries.
- `USER.md` §§3–8 — canonical session/security behavior, privacy/block/presence settings, media/profile delivery, and cross-device preference requirements.
- `COMPOSER.md` §§7–17 — media/storage interface, canonical Markdown references, upload safety, fallback, and the requirement that delivery changes not rewrite canonical content.
- `COMMUNITY.md` §§4–14 — query-time feed semantics, fan-out-on-write as a later interface swap, privacy/block behavior, and humane-design constraints.
