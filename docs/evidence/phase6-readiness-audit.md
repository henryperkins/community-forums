# Phase 6 Readiness Audit

**Date:** 2026-06-30

This note records an engineering audit of Phase 6 readiness across the current
repository. It is not a product-owner acceptance record, and it does not replace
`PHASE_6_PLAN.md` or any future `PHASE_6_STATUS.md` closeout artifact.

## Scope

This audit reviewed:

- source-of-truth docs: `PHASE_6_PLAN.md`, `PHASE_5_STATUS.md`,
  `PHASE_4_STATUS.md`, `PHASE_7_PLAN.md`, `SCHEMA.md`, `DECISIONS.md`, and
  `DESIGN.md`;
- runtime code under `src/`;
- migrations under `database/migrations/`;
- tests under `tests/`;
- evidence docs under `docs/evidence/`;
- operational commands in `bin/console`.

Repository audit on 2026-06-30 found a Phase 6 plan, but no
`PHASE_6_STATUS.md` file and no `docs/evidence/phase6*` evidence package.

## Executive Summary

- Phase 6 is not ready to begin. The plan is still draft, and implementation is
  gated on formal Phase 5 closeout, approved capacity decision records, and at
  least one active trigger (`PHASE_6_PLAN.md:39-55`).
- Phase 5 is still in progress. The current status file says `Gate A
  prerequisite work in progress`, and most major Phase 5 subsystems remain
  pending implementation or release evidence (`PHASE_5_STATUS.md:3`,
  `PHASE_5_STATUS.md:160-162`).
- The repo has useful Phase 6 groundwork in a few areas: a replaceable search
  seam, DB-backed sessions, strong media metadata plus live access rechecks,
  durable email/webhook job patterns, short-polling fallbacks, and local
  backup/restore rehearsal (`src/Search/SearchService.php:9-21`,
  `src/Security/Session.php:13-20`, `database/migrations/0043_attachments.php:6-18`,
  `src/Repository/EmailDeliveryRepository.php:9-15`,
  `src/Repository/WebhookDeliveryRepository.php:9-25`,
  `docs/evidence/backup-restore/README.md:9-27`).
- The actual Phase 6 infrastructure is still absent: no transactional outbox,
  no Redis/shared cache layer, no worker-pool or scheduler framework, no
  external search backend, no object-storage/CDN layer, no SSE, no read-replica
  router, and no materialized feed projection system.
- `SCHEMA.md` still treats Phase 6 schema as future work defined in the plan,
  not landed schema (`SCHEMA.md:1122-1124`).

## Formal Phase-Entry Readiness

| Requirement | Status | Evidence |
|---|---|---|
| Phase 5 Gate A/Gate B accepted or explicitly deferred | No | Phase 6 says implementation must not begin until Phase 5 is closed (`PHASE_6_PLAN.md:39-41`). Phase 5 status is still `Gate A prerequisite work in progress` (`PHASE_5_STATUS.md:3`). |
| Phase 5 evidence coverage complete | No | Phase 6 requires evidence across package trust, extension isolation, role parity, owner recovery, passkeys/providers, invitations, governance, migrations, accessibility, performance, backup, and rollback (`PHASE_6_PLAN.md:42`). Phase 5 status still marks many of those workstreams pending (`PHASE_5_STATUS.md:160-162`). |
| Existing interface boundaries accepted and testable | Partial | Phase 6 requires accepted/testable seams for search, storage/media, cache, feed, mailer, webhook/API/event contracts, rate limiter, session repository, worker/job runner, and polling endpoints (`PHASE_6_PLAN.md:45`). Search, mailer, limiter, sessions, and polling exist today, but media and feed remain concrete current-architecture implementations rather than full Phase 6 adapters. |
| Cache variation/invalidation contracts approved | No | Required by Phase 6 (`PHASE_6_PLAN.md:46`). Current code only has route-level cache behavior such as public vs private media caching (`src/Controller/MediaController.php:119-125`). |
| Durable worker claim/retry/dead-letter/replay evidence | Partial | Required by Phase 6 (`PHASE_6_PLAN.md:47`). Email and webhook workers have real retry/dead-letter evidence (`tests/Integration/Worker/NotificationEmailWorkerTest.php:202-225`, `tests/Integration/Worker/WebhookDeliveryWorkerTest.php:83-109`), but there is no shared outbox/checkpoint/consumer framework. |
| Performance and capacity baselines | No | Required by Phase 6 (`PHASE_6_PLAN.md:48-49`). No baseline or trigger report is recorded in the repo. |
| Capacity decision records and at least one active trigger | No | Required by Phase 6 (`PHASE_6_PLAN.md:50-51`). No active trigger record or no-adoption decision set was found. |
| Failure policy and recovery objectives approved | No | Required by Phase 6 (`PHASE_6_PLAN.md:53-54`). No Phase 6 runbook or decision package exists yet. |
| Carryover ledger reconciled | Partial | Phase 4 carryovers are explicit (`PHASE_4_STATUS.md:31-40`), but Phase 5 itself is not yet closed. |

## Readiness Legend

- **Blocked**: formally cannot start because the phase entry gate is not met.
- **Partial**: a meaningful reusable subsystem or seam already exists.
- **Groundwork**: useful earlier-phase substrate exists, but the Phase 6
  subsystem itself is not implemented.
- **Absent**: no meaningful implementation was found in the audited scope.

## Phase 6 Readiness Matrix

All rows below are additionally blocked by `P6-00` until Phase 5 closes and at
least one Phase 6 trigger is active.

| Gate | ID | Readiness | Current state |
|---|---|---|---|
| A | `P6-00` Entry gate, capacity decisions, and SLOs | Blocked | The workstream itself is future entry work in the plan (`PHASE_6_PLAN.md:257`). Phase 6 remains draft and cannot begin until Phase 5 closeout and at least one active trigger are recorded (`PHASE_6_PLAN.md:39-55`). |
| A | `P6-01` Outbox, events, and durable work contracts | Groundwork | Notification fan-out already queues durable email rows inside the write path (`src/Service/NotificationService.php:18-21`, `src/Service/NotificationService.php:250-256`). Email has a durable outbox and retry state (`src/Repository/EmailDeliveryRepository.php:25-48`, `src/Repository/EmailDeliveryRepository.php:125-156`). Webhooks have a durable delivery ledger (`src/Repository/WebhookDeliveryRepository.php:16-25`, `src/Repository/WebhookDeliveryRepository.php:54-74`). Hook events have stable IDs (`src/Hook/HookEvent.php:7-15`). Missing are the actual Phase 6 pieces: a shared transactional outbox, event/job versions, consumer registry, checkpoints, common replay/repair, and atomic write plus outbox commit. Posting currently commits the DB transaction first, then emits `topic.created` afterward (`src/Service/PostingService.php:123-178`, `src/Service/PostingService.php:615-623`). |
| A when triggered | `P6-02` Redis shared services | Groundwork | The repo already has a rate-limit abstraction (`src/Security/RateLimiter.php:7-25`) and central policy service. The active implementation is still file-based and explicitly suited to a single VPS (`src/Security/FileRateLimiter.php:7-10`; `src/Core/App.php:601-605`). No Redis client, cache adapter, invalidation bus, session cache, permission cache, stream, or pub/sub layer was found. |
| A when triggered | `P6-03` Worker pools and scheduler | Groundwork | The CLI already exposes many workers: `worker:email`, `worker:digest`, `worker:drafts`, `worker:attachments`, `worker:previews`, `worker:related-topics`, `worker:extensions`, `worker:webhooks`, and others (`bin/console:427-438`). Email and webhook workers already use advisory drain locks (`src/Repository/EmailDeliveryRepository.php:73-87`, `src/Repository/WebhookDeliveryRepository.php:92-100`). Missing are a shared worker-pool framework, scheduler singleton/lease logic, workload priorities, per-pool quotas, worker heartbeats, and common dead-letter/replay controls. `DailyDigestWorker` has no scheduler lease (`src/Worker/DailyDigestWorker.php:45-99`), and `ServerExtensionRepository::claim()` is select-then-update rather than a robust lease claim contract (`src/Repository/ServerExtensionRepository.php:60-83`). |
| A when triggered | `P6-04` External search | Partial | This is one of the best-prepared areas. There is already a replaceable `SearchService` seam (`src/Search/SearchService.php:9-21`), the app binds it centrally (`src/Core/App.php:801-802`), and the current MySQL implementation is read-gated and canonical-content-aware (`src/Search/MysqlSearchService.php:11-19`, `src/Search/MysqlSearchService.php:26-109`). FULLTEXT indexes already exist on thread titles and post bodies (`database/migrations/0013_threads_phase2.php:6-17`, `database/migrations/0014_posts_phase2.php:6-17`), and current search behavior is documented and tested (`docs/PHASE_2_STATUS.md:137-158`). Missing are the actual Phase 6 pieces: external engine integration, async indexing consumers, index schema/version state, shadow or dual-query parity, and reindex/fallback controls. |
| A when triggered | `P6-05` Object storage and media migration | Partial | Attachments already carry future-useful metadata: `storage_key`, `sha256`, visibility, temp/finalized/deleted lifecycle, and parent binding (`database/migrations/0043_attachments.php:6-18`, `database/migrations/0043_attachments.php:24-50`). Upload handling is careful and local-storage-safe (`src/Service/AttachmentService.php:12-25`, `src/Service/AttachmentService.php:108-125`, `src/Service/AttachmentService.php:299-315`), and media delivery always rechecks current access before serving bytes (`src/Controller/MediaController.php:108-125`, `src/Controller/MediaController.php:159-227`). This is solid groundwork for object migration. Storage is still local filesystem only; there is no storage adapter, no object inventory or migration state, no dual-read or dual-write cutover, and no signed or proxied object delivery layer. |
| A/B when triggered | `P6-06` CDN and public-edge delivery | Groundwork | The current app already distinguishes public immutable media from private `no-store` media (`src/Controller/MediaController.php:119-125`, `src/Controller/MediaController.php:149-156`), which is the right baseline before CDN work. No CDN integration, purge/tag system, origin protection, signed/private edge policy, or emergency bypass controls were found. |
| B | `P6-07` Scale-ready web topology and rolling deploys | Groundwork | Sessions are already DB-backed and revocable (`src/Security/Session.php:13-20`, `src/Security/Session.php:42-65`, `src/Security/Session.php:88-121`; `src/Repository/SessionRepository.php:39-100`), and `/healthz` exists (`src/Controller/HealthController.php:14-21`). Those are good prerequisites. The app is not topology-ready yet because mutable state is still local for rate limiting and uploads (`src/Core/App.php:601-605`, `src/Core/App.php:773-783`; `src/Security/FileRateLimiter.php:7-10`). No node identity, readiness/drain model, no-sticky-session validation, or rolling mixed-version deploy proof was found. |
| B when triggered | `P6-08` Server-Sent Events | Absent | The repo has the right polling fallbacks: `/presence` is short-poll JSON and `/notifications/bell` is short-poll JSON (`src/Controller/PresenceController.php:15-20`, `src/Controller/PresenceController.php:23-52`; `src/Controller/NotificationController.php:34-54`; `docs/PHASE_2_STATUS.md:111-126`, `docs/PHASE_2_STATUS.md:246-249`). No SSE stream endpoint, `EventSource` or `Last-Event-ID` logic, backplane, replay/gap detection, reconnect handling, or multi-tab coordination exists yet. |
| B when triggered | `P6-09` Read replicas and consistency router | Absent | The app currently binds a single `Database` object backed by one PDO path (`src/Core/App.php:596-599`; `src/Core/Database.php:15-49`). No replica-aware routing, primary pinning, read-your-writes mechanism, lag checks, or schema/version compatibility logic was found. |
| B when triggered | `P6-10` Materialized feeds | Groundwork | The current feed system is intentionally query-time: `FeedService` explicitly says `No fan-out storage` (`src/Service/FeedService.php:12-13`). The follow graph already supports `user`, `tag`, and `board` targets (`database/migrations/0036_follows.php:10-18`), and feed reads already reapply access constraints (`src/Service/FeedService.php:26-147`; `src/Controller/FeedController.php:21-47`). That gives Phase 6 a good canonical baseline and fallback path. There are no feed projection tables, checkpoints, tombstones, projector workers, or rebuild/parity machinery. |
| A/B | `P6-11` Cache/invalidation and consistency audit | Groundwork | The app already leans on canonical rechecks rather than cached authority: notifications store minimal IDs and reapply access at render/click time (`src/Repository/NotificationRepository.php:9-13`), and media cacheability is determined by live access state (`src/Controller/MediaController.php:108-125`, `src/Controller/MediaController.php:159-227`). Phase 3 also explicitly notes that no fragment/render cache was introduced (`docs/PHASE_3_STATUS.md:60`). There is no global cache catalogue, invalidation-event stream, generation/tag strategy, or consistency map across read paths. |
| A/B | `P6-12` Data protection and disaster recovery | Groundwork | There is real adjacent ops work here. The backup rehearsal checks row counts, checksums, migrations, repair, and restored app boot (`docs/evidence/backup-restore/README.md:9-27`), and the CLI already exposes `repair`, `verify:upgrade`, and multiple workers (`bin/console:415-438`). That is useful groundwork. There is no Phase 6 recovery model yet for Redis cold start, search rebuild, object-store restore, replica recreation, or feed-projection rebuild with Phase 6 budgets. |
| A/B | `P6-13` Security, privacy, and service isolation | Groundwork | Current primitives are strong: SSRF-safe outbound webhook delivery (`src/Security/EgressGuard.php:10-16`, `src/Security/EgressGuard.php:64-80`), hashed DB sessions (`src/Security/Session.php:13-20`), media access rechecks (`src/Controller/MediaController.php:159-227`), deploy-dark service secrets/webhooks (`PHASE_5_STATUS.md:147-159`), and extension execution that fails closed when the runtime is unavailable or not configured (`src/Service/Extension/BubblewrapSandboxAdapter.php:33-43`; `tests/Integration/Worker/ServerExtensionWorkerTest.php:38-71`). Missing is the actual Phase 6 service-isolation layer for Redis/search/object/CDN/replicas because those services do not exist yet. |
| A/B | `P6-14` Observability, performance, and capacity management | Groundwork | There is some local observability: `/healthz` (`src/Controller/HealthController.php:14-21`), queue status counts in repositories/admin views (`src/Repository/EmailDeliveryRepository.php:166-175`), and extension run history in the admin surface (`src/Controller/AdminExtensionController.php:23-28`). Phase 3 also records that production-like load/soak remains a release-environment artifact, not landed engineering evidence (`docs/PHASE_3_STATUS.md:60-61`). No tracing, service dashboards, SLO/error-budget storage, capacity-headroom reporting, or load/soak/chaos suite for Phase 6 was found. |
| A/B | `P6-15` Operations, staged release, and closeout | Partial | This is the strongest non-product readiness area after search/media. The repo already has centralized dark-launch flags (`src/Core/FeatureFlags.php:13-17`, `src/Core/FeatureFlags.php:76-102`), a substantial CLI surface for workers/repair/upgrade (`bin/console:415-438`), backup rehearsal (`docs/evidence/backup-restore/README.md:9-27`), and current browser evidence infrastructure (`PHASE_5_STATUS.md:6`). There are no Phase 6-specific flags for Redis/search/object/CDN/SSE/replicas/materialized feeds, no `PHASE_6_STATUS.md`, and no Phase 6 evidence index or closeout package. |

## Adjacent Evidence Already In Repo

The following evidence is not Phase 6 evidence, but it is useful substrate for a
future Phase 6 implementation.

### Durable workers and queue behavior

- Phase 2 already documents durable notification and email-outbox behavior,
  including instant outbox, idempotency keys, and digest worker semantics
  (`docs/PHASE_2_STATUS.md:111-131`).
- Email worker regression covers advisory-lock concurrency protection, retry, and
  at-most-once drain behavior (`tests/Integration/Worker/NotificationEmailWorkerTest.php:202-225`).
- Webhook worker regression covers retry/backoff, dead-lettering, egress-block
  fail-closed behavior, and circuit-breaker auto-disable
  (`tests/Integration/Worker/WebhookDeliveryWorkerTest.php:70-109`).
- Server-extension worker regression covers dark gating, fail-closed unsupported
  sandbox behavior, and successful async run recording
  (`tests/Integration/Worker/ServerExtensionWorkerTest.php:38-71`).

### Sessions and security-state freshness

- Phase 2 status already records active sessions/devices and presence as shipped
  surfaces (`docs/PHASE_2_STATUS.md:235-252`).
- DB-backed session revocation and device-management behavior exists today
  (`src/Repository/SessionRepository.php:71-100`,
  `src/Controller/SettingsController.php:154-180`).

### Media authorization and private-data safety

- Phase 3 status records media and attachment safety as complete for the current
  architecture (`docs/PHASE_3_STATUS.md:56`).
- Media delivery rechecks the current parent-content access rules on every
  request, which is the correct baseline before any CDN or object-store work
  (`src/Controller/MediaController.php:159-227`).

### Polling fallbacks

- The current product already has short-poll presence and notification-bell
  endpoints, which are the required correctness fallback if Phase 6 later adds
  SSE (`src/Controller/PresenceController.php:15-20`,
  `src/Controller/NotificationController.php:34-54`).

### Search semantics and fallback path

- Search authorization, FULLTEXT fallback, and result gating are already defined
  and tested (`docs/PHASE_2_STATUS.md:137-158`,
  `src/Search/MysqlSearchService.php:95-109`).

### Backup, upgrade, and release operations

- Backup/restore rehearsal is already reproducible and verifies counts,
  checksums, migrations, repair, and restored app boot
  (`docs/evidence/backup-restore/README.md:9-27`).
- The CLI already exposes repair, upgrade rehearsal, and multiple worker drains
  (`bin/console:415-438`).

## Highest-Leverage Blockers

The main blockers to actual Phase 6 work are:

1. Phase 5 is not formally closed, so `P6-00` is blocked by plan
   (`PHASE_6_PLAN.md:39-55`, `PHASE_5_STATUS.md:3`).
2. No Phase 6 schema has landed beyond plan text (`SCHEMA.md:1122-1124`).
3. No Phase 6 flags exist for Redis, external search, object storage, CDN, SSE,
   replicas, or materialized feeds (`src/Core/FeatureFlags.php:76-102`).
4. No shared transactional outbox or common durable-work contract exists
   (`PHASE_6_PLAN.md:258`, `src/Service/PostingService.php:123-178`,
   `src/Service/PostingService.php:615-623`).
5. Mutable runtime state is still local for rate limiting and uploads, so the
   current app is not yet multi-node safe (`src/Core/App.php:601-605`,
   `src/Core/App.php:773-783`).
6. No Gate B substrate exists yet for SSE, replicas, or materialized feeds.

## Net Assessment

- Formal readiness to start Phase 6: **No**
- Gate A substrate: **low to moderate**
- Gate B substrate: **low**
- Green workstreams: **0**

The current repository is best described as **Phase 6-aware, but not Phase 6-implemented**.
