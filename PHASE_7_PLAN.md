# RetroBoards Phase 7 Plan — Platform Expansion & Portability

**Owner:** Henry  
**Plan type:** Delivery baseline, strategy-gated release train, and formal roadmap closeout  
**Plan status:** **Draft — execution is gated by formal Phase 6 closeout and Milestone 0 platform-strategy approval**  
**Prepared:** 2026-06-25  
**Source hierarchy:** `DECISIONS.md` is authoritative where documents conflict; `DESIGN.md` is the product source of truth; `SCHEMA.md` owns final database shape; `ADMIN.md`, `USER.md`, `COMPOSER.md`, and `COMMUNITY.md` own their respective surfaces. P0/P1/P2 in the source documents are priority tiers, not delivery-phase numbers.

## 1. Phase objective

Turn the accepted Phase 6 product into a portable, installable, multilingual platform that can move communities safely, operate across more than one community when explicitly configured, and support mobile/offline use without weakening the durable-topic model, canonical server authority, privacy rules, or self-hosted ownership promise.

A member should be able to:

1. Install RetroBoards as a Progressive Web App, open an intelligible offline state, revisit explicitly saved public topics, and continue composing drafts without mistaking stale local data for current server truth.
2. Receive privacy-safe Web Push where enabled, manage each device subscription, and open a notification into a current authorization-checked server view rather than receiving private content in the push payload.
3. Use the complete member experience in an approved locale, including plural forms, dates, numbers, email, push, accessibility labels, and right-to-left layout, while retaining a predictable fallback when a translation is missing.
4. Write and read Unicode content independent of the interface language, identify a topic’s content language where useful, and use bidirectional text safely without corrupting Markdown or exposing spoofed interface text.
5. Move an account or community through a versioned, checksum-verified portability package and understand which records were imported, transformed, omitted, quarantined, or require a claim/reset.
6. Join more than one community in the same installation when multi-community mode is enabled, with clear community context, membership, profile, notification, and permission boundaries.
7. Use an approved mobile-client experience—PWA-only, wrapped, cross-platform, or platform-native according to the Milestone 0 decision—without receiving a second, weaker authorization or content model.
8. Interact with separately approved public content from another installation when federation is enabled, while private boards, DMs, security state, and local account credentials remain local.

An operator should be able to:

1. Configure installation and community default locales, timezone, text direction, available locale packs, content-language policy, and translation fallback without editing application code.
2. Install, validate, update, pin, and roll back signed locale packages using the accepted Phase 5 package trust model; missing or malicious translations cannot execute code or bypass escaping.
3. Enable or disable the PWA, offline snapshots, offline draft queue, and Web Push independently; revoke a device and clear its server subscription without disabling ordinary browser use.
4. Preflight, dry-run, stage, resume, reconcile, cut over, and audit a forum import through a canonical interchange format and approved source adapters, with old-link redirects and a documented rollback boundary.
5. Create and administer communities, domains, memberships, quotas, locale/branding defaults, package enablement, data export, and community-level recovery without acquiring unrestricted access to private member content.
6. Prove tenant isolation through route, query, cache, search, media, queue, notification, audit, extension, backup, and domain-routing evidence before serving a second real community.
7. Publish and operate a versioned member/client API only when the approved mobile direction requires it, with device-bound credentials, revocation, compatibility windows, and no embedded administrator secret.
8. Enable federation only after a separate trust, moderation, privacy, protocol, and abuse decision; pause inbound or outbound delivery by server/domain/actor without taking local reading and posting offline.
9. Restore the installation and every authoritative community record from canonical database/object/configuration backups while treating service-worker caches, device stores, import staging, search, feeds, and remote-content projections according to their declared recovery class.
10. Close the original seven-phase roadmap with a complete requirement ledger, evidence index, accepted strategy decisions, and no unresolved feature hidden under a generic “later” label.

Phase 7 is a **strategy-gated release train**, not a big-bang rewrite. Gate A delivers full internationalization, PWA/offline capability, Web Push, and portable import/export. Gate B delivers multi-community operation and the approved mobile-client direction; separately approved public federation may ship within Gate B but is never implied by tenancy or a client API. Phase 7 is not formally closed until both gates are accepted and every conditional platform decision has an explicit accepted adoption or no-adoption record.

## 2. Entry gate — Phase 6 must be closed and platform strategy must be approved

This plan may be refined before Phase 6 closes, but Phase 7 implementation must not begin until all of the following are true:

- Phase 6 Gate A and Gate B have recorded product-owner acceptance, or every incomplete item has an explicit deferral with owner, rationale, risk, and destination outside the seven-phase roadmap.
- The Phase 6 evidence index covers canonical/outbox behavior, cache isolation, search authorization, media delivery, worker durability, backup/restore, provider exit, and rollback — and, for each **capacity-gated** Phase 6 component (horizontal/multi-node sessions, SSE/polling fallback, read-replica consistency, materialized-feed fallback), either activation evidence **or** the recorded evidence-backed no-adoption decision (Phase 6 may legitimately defer any of these — PHASE_6_PLAN §4; this gate does not assume they were activated).
- No unresolved critical or high-severity Phase 6 security, privacy, authorization, data-integrity, cache, search, media, realtime, replica, queue, projection, recovery, or release-operability defect remains.
- The deployed schema, topology, service inventory, object inventory, package state, role model, identity model, and source/projection classifications are reconciled against `SCHEMA.md` and the Phase 6 closeout evidence.
- Canonical MySQL/object/configuration backup and clean-environment restore meet the approved RPO/RTO, and Redis/search/feed/SSE/replica state can be rebuilt or bypassed.
- Single-node, short-polling, MySQL search, query-time feed, origin/local media, and other accepted fallback paths remain current-build proven for any Phase 6 component whose rollback window is still open.
- Product evidence has been reviewed for PWA installation, offline reading/drafts, locale demand, import demand and source platforms, multi-community use cases, custom domains, native-client demand, and federation demand. A broad “platform” label is not sufficient evidence.
- The owner has approved a **platform strategy record** for each Phase 7 family: internationalization, PWA/offline, Web Push, portability/imports, multi-community/tenancy, native-mobile direction, and federation. Each record states the user problem, committed outcome, non-goals, privacy class, owner, budget, rollout, rollback, and adoption/no-adoption decision.
- The initial locale set is approved, including the source locale, one production non-source locale backed by maintainers or funded translation, and an RTL engineering/production plan sufficient to prove bidirectional correctness.
- The PWA offline data classes are approved. The default must distinguish public static assets, public saved content, authenticated personal metadata, private-board content, DMs, moderation/security data, and secrets.
- The import policy is approved: canonical archive format, first source adapter(s), account-claim policy, password-hash policy, private content/DM treatment, attachment scanning, source retention, legal basis, and cutover model.
- The tenancy model is approved: global account versus community membership boundaries, username/display-name rules, community-scoped content and DMs, installation-owner versus community-owner authority, domains, quotas, package scope, and the security statement for shared-schema isolation.
- The mobile direction is approved as one of: PWA-only, packaged web client, cross-platform native client, platform-native client, or a staged combination. The decision includes supported platforms, feature floor, API need, auth model, offline/private-data policy, release ownership, and maintenance budget.
- Federation is recorded as **adopt**, **pilot**, or **no-adopt**. Adoption requires a protocol decision, public-content boundary, moderation/trust policy, remote-data retention, signature/key model, discovery policy, and abuse-response owner.
- Every unfinished Phase 1–6 obligation is placed in a **carryover ledger**. A carryover may block Phase 7 or be explicitly moved to a new post-roadmap initiative; it must not be renamed as offline, mobile, tenancy, import, localization, or federation work.

**Ownership boundary:** Earlier phases remain responsible for canonical posting, privacy/block behavior, media safety, package isolation, capabilities, identity/recovery, scale services, and their evidence. Phase 7 adds client, locale, portability, and community boundaries around accepted behavior; it does not rewrite or weaken those contracts.

## 3. Definition of done

Phase 7 is accepted only when all of the following are true:

- Every accepted Phase 1–6 journey remains functional, permission-safe, server-rendered, and usable without JavaScript wherever the underlying action previously had a no-JS path. PWA, push, native, and federation features are enhancements rather than prerequisites for core web correctness.
- Every Phase 7 schema change is additive wherever possible, documented in `SCHEMA.md`, tested on clean and populated upgraded installations, and attributable to one approved community/locale/device/import/federation domain; additive changes stay within the approved rollback window, while the enumerated non-additive, point-of-no-return conversions — the global→per-community uniqueness/PK changes of §8.2 #11 and the eventual `community_id` removal — are gated behind the same one-way tenancy-cutover controls (see §8.2 #11 and the §13.2 cutover note), not the additive rollback window.
- The platform strategy ledger contains a current accepted adoption or no-adoption decision for every Phase 7 family; a strategy decision is not counted as implementation evidence for a committed feature.
- Locale identifiers use one canonical validated form; invalid, unsupported, or malicious locale values fall back safely without changing authorization, cache scope, or data ownership.
- Locale resolution is deterministic: explicit user/community override where allowed, community default, installation default, then source locale. Browser hints cannot silently override an authenticated user’s saved choice.
- All core/member/admin/error/email/push/PWA strings use stable message keys and approved catalogs. Runtime user data is passed through typed placeholders and normal output escaping; translators cannot inject executable HTML, script, URLs, or permission-affecting values.
- Pluralization, select/gender-neutral variants where needed, date/time/number/list formatting, relative time, timezone display, and collation use the approved locale-aware formatter rather than English string concatenation.
- Every rendered page declares the correct `lang` and `dir`; layout uses logical properties; user-generated bidirectional text is isolated from interface chrome; RTL does not reverse code, identifiers, URLs, or Markdown semantics incorrectly.
- Translation extraction, validation, pseudo-localization, missing-key detection, placeholder parity, package signing, reviewer attribution, coverage reporting, and rollback are automated and documented.
- At least the approved production locale set passes the complete route/email/push/accessibility matrix. A missing translation falls back visibly and safely; it never produces a blank control, hidden warning, or untranslated authorization decision.
- Core route identity and canonical resource IDs remain locale-independent. Locale switching does not create duplicate content, break old links, alter authorization, or fabricate translated user-generated content.
- Topic/content language is distinct from interface locale. Search, metadata, moderation, and accessibility may use a declared content language, but the system does not automatically translate member content without a separately approved service/privacy decision.
- Public discovery metadata reflects the actual interface/content language. Alternate-language metadata is emitted only for real equivalent pages; private, personalized, offline, client-token, import, and federation administration routes remain excluded from indexing.
- The PWA manifest, icons, names, colors, scope, start URL, display mode, shortcuts, and locale behavior are generated from the current community/brand configuration and pass installability checks on the approved browser matrix.
- The service worker is versioned, update-safe, observable, and independently disableable. It never intercepts setup, login, logout, MFA/recovery, admin security, token, payment-like, import secret, or other explicitly excluded routes.
- Service-worker caches are deny-by-default. Hashed static assets and explicitly approved public content may be cached; authenticated/private HTML, DMs, moderation, account security, package secrets, signed URLs, and access tokens are not stored in shared caches.
- Offline pages clearly identify the community, signed-in uncertainty, last successful refresh, data class, and stale state. A cached page never claims that a membership, ban, role, thread state, unread count, or moderation result is current.
- Explicitly saved offline public topics retain canonical IDs and safe sanitized render. Content that becomes deleted, private, or inaccessible is removed or made unavailable at the next validated online synchronization; protected content is not silently retained as an ordinary PWA snapshot.
- Offline drafts remain user/device/context isolated, quota-limited, exportable/recoverable, and — **where Phase 3 server-side draft sync was accepted** — compatible with that server-draft model (otherwise drafts are local-only; P7-05 must not assume server drafts exist — PHASE_3_PLAN §3). Logout, account switch, community switch, browser storage clear, and device revocation have defined outcomes.
- Deferred offline writes use stable idempotency keys and an explicit pending state. Reconnection rechecks authentication, account status, permissions, thread/board state, limits, moderation holds, and current versions before submission; conflicts are shown rather than overwritten or auto-sent blindly.
- Security, moderation, role, provider, package, account recovery, community administration, import cutover, and federation trust actions cannot be performed offline.
- Web Push subscriptions are device/browser/origin/community attributable, revocable, expiring/repairable, rate-limited, and bound to the accepted notification preferences. Push payloads contain only the minimum routing/invalidation data and no private post/DM body, email, token, or signed media URL.
- Push click handling opens the canonical route and rechecks current authorization. Revoked sessions, removed community membership, blocks, deleted content, or lost board access yield the normal safe denial rather than cached notification content.
- The portability format is versioned, documented, deterministic enough for reconciliation, checksum-manifested, Unicode-safe, streaming/bounded, and free of executable application code.
- A community export identifies schema/version, source installation/community, creation time, data classes, object counts, checksums, locale/timezone, package dependencies, omissions, retention constraints, and encryption/signature state where used.
- Import archives are type/size/path/decompression validated, scanned, staged outside executable/public paths, and processed through bounded jobs. An archive cannot overwrite application files, core schema, credentials, or another community.
- Every imported source entity has a stable source-system/source-ID mapping and one deterministic local outcome: created, linked, transformed, skipped, quarantined, conflicted, or failed. Reruns and resumes do not create duplicate logical users, topics, posts, media, reactions, memberships, or redirects.
- Import preflight and dry run report counts, field mappings, unsupported features, duplicate identities, private-content policy, media exceptions, estimated time/storage, required downtime, and rollback boundary before canonical writes begin.
- User attribution and account claims are safe. Email/display-name similarity never silently merges accounts; imported credentials are never plaintext; password hashes are accepted only through an explicitly approved compatibility module and otherwise require a secure claim/reset path.
- Imported Markdown/content is sanitized through the canonical renderer. Legacy raw source, when retained for audit/reprocessing, is access-restricted, retention-bound, and never rendered directly.
- Imported attachments preserve ownership/context, pass the accepted scan/quarantine pipeline, use checksum deduplication only within approved privacy boundaries, and cannot become public merely because the source URL was public.
- Old source URLs resolve through a bounded redirect/mapping layer without open redirects, cross-community leakage, or permanent dependence on an untrusted source host.
- Import batches are auditable, pauseable, resumable, reconcilable, and reversible before the declared interaction/cutover boundary. After members create new target-side activity, recovery uses the approved restore/reconciliation process rather than unsafe batch deletion.
- The canonical export/import path can move an accepted sample community into a clean supported installation and reconcile required entities, references, permissions, media, and checksums within the approved tolerance.
- Multi-community mode is opt-in. An upgraded single-community installation becomes exactly one default community without changing its URLs, memberships, permissions, branding, content visibility, notification behavior, or package behavior before the multi-community flag is enabled.
- Every tenant-owned object has one canonical `community_id` or an equally explicit immutable community ownership path. No object is inferred from a request hostname alone when stored or authorized.
- Global installation identity is separated from community membership. Authentication credentials, provider/passkey identity, and protected installation ownership remain installation-scoped; community roles, profiles, content, feeds, notifications, DMs, moderation, settings, and quotas follow the approved community boundary.
- The initial multi-community model keeps login usernames installation-unique and permits a community-specific display/profile layer only through the approved schema. Display names, badges, reputation, titles, and profile fields never become login keys or cross-community authority.
- Conversations and DMs are community-scoped in the Phase 7 baseline. Cross-community DMs, shared private groups, and implicit member discovery are disabled unless a later separately approved feature defines their privacy and moderation model.
- Every request resolves a verified community context from the route/host and revalidates it at the repository/service boundary. Unknown, disabled, suspended, misdirected, or unverified hosts fail safely.
- Community context is included in database constraints and indexes, cache/search/feed keys, object paths/metadata, outbox/jobs, notifications, push, SSE, webhooks/API, extension storage, audit, rate limits, backups/exports, and observability wherever output or authority varies by community.
- A member, moderator, community owner, package, service principal, worker, search query, import job, or remote actor cannot read or mutate another community’s data without an explicit installation-level capability and an audited purpose.
- Shared-schema multi-community mode is described honestly: it is an application-enforced isolation model, not a substitute for separate deployments where adversarial tenants, legal separation, residency, cryptographic isolation, or independent recovery require stronger boundaries.
- Installation-owner and community-owner capabilities are distinct. A community owner cannot change trust roots, global providers, protected installation owners, another community, global service credentials, or infrastructure; an installation owner’s exceptional cross-community actions remain explicit and audited.
- Community domains are verified, unique, TLS-capable, origin/CSRF safe, and mapped through an auditable lifecycle. Authentication across unrelated custom domains uses the approved one-time handoff or per-domain session model rather than a broad insecure wildcard cookie.
- Per-community registration, invitations, branding, locale, timezone, boards, moderation, packages, webhooks, API/service principals, retention, exports, and quotas are scoped and default safely from the installation policy.
- Resource accounting and limits prevent one community’s imports, media, search, feed fan-out, extensions, push, federation, or background jobs from starving security, recovery, or ordinary operation for another community.
- Community suspension/export/delete follows a staged, reversible, retention-aware workflow. Deleting one community cannot delete a shared global account, credential, package artifact, or media object still referenced by another community.
- Search, feed, unread, reputation, badges, reports, moderation, notifications, email/push, and public discovery return counts and rows only for the current permitted community context.
- Tenant-isolation tests cover direct IDs, alternate hosts, stale sessions, caches, search indexes, media URLs, signed URLs, jobs, logs, exports, extension broker calls, APIs, backups, and failure/fallback modes. Unauthorized cross-community disclosure target is zero.
- The mobile strategy ADR is accepted and linked to evidence. A PWA-only result is valid only if the approved member needs, offline/push requirements, platform limitations, distribution goals, and support cost are explicitly satisfied; it is not a synonym for “not implemented.”
- If a member/client API ships, it is versioned, documented, cursor/idempotency aware, authorization-equivalent to web services, community-scoped, rate-limited, and unable to expose raw internal models, private cache keys, or installation-only capabilities.
- Native or packaged clients authenticate through an approved system-browser/PKCE or platform credential flow. They do not collect passwords in an untrusted embedded webview, embed client secrets, reuse human administrator tokens, or bypass passkey/provider/account-state rules.
- Device refresh credentials are shown/issued only through the approved flow, stored protected on the device, hashed or protected server-side, individually revocable, rotated, scoped, and bound to the intended app/community/account context.
- Native local storage has an explicit classification and purge policy. Logout, token revoke, account switch, community removal, remote wipe signal where available, and app uninstall have documented outcomes; private bodies are not stored merely for convenience.
- Deep links, universal/app links, push routes, uploads, drafts, offline sync, accessibility, locale/RTL, and error recovery converge on canonical server objects and current authorization.
- Client version compatibility, minimum-supported version, API deprecation, feature negotiation, kill switch, staged rollout, crash telemetry, privacy disclosures, store release, and support ownership are documented and rehearsed for every shipped client.
- Federation, if adopted, is limited initially to the approved public data classes. Private boards, hidden content, DMs, moderation notes, security events, email, credentials, device tokens, and protected media are never federated.
- Federation does not create shared login or global account authority. Remote actors and objects have explicit origin identity, local representation, trust state, and current local policy; email/display-name/domain similarity never merges them with local accounts.
- Remote requests, signatures, keys, redirects, discovery, inbox/outbox delivery, retries, and media fetches pass replay, SSRF, DNS, size, timeout, rate-limit, key-rotation, and provenance controls.
- Remote content is sanitized and moderated locally before display. Domain/actor/object allow, silence, reject, block, quarantine, report, and purge actions are available and audited.
- Remote edits/deletes/tombstones, local replies, delivery retries, duplicate activities, move/merge, account suspension, domain blocks, and server disappearance converge according to the approved protocol policy without fabricating local authorship or refiring local reputation/notification effects incorrectly.
- Federation can be disabled inbound, outbound, or by domain/actor while local topics and accounts remain intact. Remote projections and delivery ledgers have retention/export/purge and rebuild rules.
- Every Phase 7 surface passes the accepted accessibility baseline, including keyboard, screen-reader-critical journeys, zoom/reflow, reduced motion, touch targets, offline announcements, locale switch, text expansion, RTL, import/admin tables, community switcher, and native-client platform accessibility where applicable.
- Phase 7 meets the numeric offline, locale, import, tenant-isolation, domain-routing, client/API, push, federation, performance, queue, storage, recovery, and cost budgets locked at Milestone 0.
- The complete automated suite, migration matrix, locale/pseudo/RTL matrix, service-worker/PWA tests, offline conflict tests, push tests, import fixtures, tenant-isolation tests, client contract tests, federation adversarial tests where applicable, browser/native evidence, load/soak/fault tests, backup/restore, and rollback rehearsals pass.
- No unresolved critical or high-severity security, privacy, accessibility, authorization, data-integrity, localization, offline, push, import, tenant-isolation, native-client, federation, recovery, or release-operability defect remains.

## 4. Scope and release gates

### Gate A — Internationalization, installable/offline web, and portability

Gate A is the minimum Phase 7 release:

- Phase 6 closeout reconciliation, Phase 7 carryover ledger, platform strategy records, data-class map, locale/import/offline threat models, representative fixtures, numeric budgets, feature flags, and requirement-to-evidence map.
- Full internationalization foundation:
  - stable message-key catalogue and extraction/lint pipeline;
  - source locale, fallback chain, user/community locale preferences, and validated locale tags;
  - locale-aware plural/select/date/time/number/list/relative-time formatting;
  - `lang`/`dir`, logical layout, bidirectional isolation, text expansion, Unicode normalization policy, and keyboard/accessibility behavior;
  - localized core/member/admin/error/email/push/PWA surfaces;
  - signed locale packages, placeholder parity, coverage, reviewer/version history, pseudo-locales, and rollback;
  - at least the approved production non-source locale plus an RTL verification target.
- Content-language and discovery support:
  - optional thread/content language metadata and inheritance;
  - language-aware search/index hints and fallback;
  - locale-safe canonical URLs, metadata, sitemaps, and real-equivalent alternate-language behavior;
  - no automatic translation of member content.
- PWA and offline-safe web:
  - per-community manifest, icons, install prompts/education, shortcuts, standalone launch, update UX, and emergency disable;
  - service-worker cache limited to approved static/public classes;
  - offline shell, explicitly saved public topics, stale/last-updated indicators, storage quota controls, clear/remove-all controls, and logout/account-switch handling;
  - local draft handling (and local↔server draft interoperability **where Phase 3 server-draft sync shipped** — otherwise local-only), explicit queued submission, idempotency, revalidation, conflict UI, and no offline security/moderation/admin writes;
  - supported-browser install/offline/accessibility evidence while the ordinary server-rendered site remains canonical.
- Web Push:
  - per-device subscription, permission education, preference integration, minimal payloads, click-to-current-route, revoke/expiry/repair, provider/key rotation, rate limits, telemetry, and kill switch;
  - no private bodies or credentials in push payloads.
- Portability and import/export foundation:
  - versioned canonical community archive, manifest, checksums, typed entity streams, media inventory, omissions, and validation tooling;
  - community export plus existing account-data export integration;
  - import source registry, adapter contract, staging area, preflight, dry run, mappings, checkpoints, exceptions, reconciliation, and audit;
  - at least one approved legacy/source adapter selected from actual demand, plus the canonical RetroBoards-to-RetroBoards path;
  - safe identity claim/reset, canonical Markdown conversion, media scanning, old-link redirects, delta/final cutover, backup, and rollback boundary.
- Full Gate A privacy/security/accessibility/SEO/performance review, observability, runbooks, staged rollout, evidence index, and product-owner acceptance.

### Gate B — Multi-community operation, mobile-client direction, and optional federation

These items complete the broader Phase 7 roadmap. Multi-community operation and the approved mobile direction require acceptance; federation requires either acceptance of an approved implementation/pilot or an explicit no-adoption record.

- Multi-community and tenancy foundation:
  - `communities`, verified domains, membership and per-community profile/context;
  - deterministic default-community migration for existing installations;
  - explicit community ownership on all tenant data and community-scoped uniqueness/indexing;
  - installation-owner versus community-owner boundaries, community capabilities, registration/invites, settings, branding, locale/timezone, packages, integrations, retention, export, suspension, and deletion;
  - community-scoped DMs, profiles, follows, feeds, notifications, push, reports, moderation, search, media, audit, jobs, APIs, extensions, and quotas;
  - no cross-community implicit data sharing.
- Tenant-isolation and operating controls:
  - hostname/route resolution, custom-domain verification, TLS/origin/CSRF/session handoff, canonical redirects, and domain disable;
  - cache/search/feed/object/outbox/SSE/webhook/token/extension namespace isolation;
  - per-community resource metering, quotas, queue fairness, storage/export, metrics, incident handling, and data-processing policy;
  - cross-community permission simulator and isolation test corpus;
  - community export/move and clean-install restore/reimport evidence.
- Mobile-client direction:
  - accepted ADR comparing PWA-only, packaged web, cross-platform, and platform-native approaches;
  - a complete supportable outcome for the selected direction rather than an indefinite prototype;
  - versioned member/client API, device credentials, secure storage, deep links, push, sync, drafts, uploads, accessibility, locale/RTL, crash/privacy telemetry, and app release process where required by the decision;
  - a feature-parity matrix that identifies intentionally web-only administration/moderation rather than silently omitting it;
  - PWA-only acceptance requires evidence that the approved mobile needs and distribution/support goals are met without a native binary.
- Separately approved public federation:
  - protocol/compatibility ADR and server identity/key lifecycle;
  - discovery, actor/object provenance, signed requests, inbox/outbox delivery, retries, dedupe, key rotation, and domain policies;
  - public-topic/reply boundary, sanitized remote rendering, local moderation, report/block/quarantine, edits/deletes/tombstones, media policy, and user disclosure;
  - no shared login, federated DMs, private-board content, remote admin authority, or extension code;
  - allowlist-first pilot, independent inbound/outbound kill switches, retention/export/purge, observability, and incident runbooks.
- Full Gate B migration, isolation, recovery, locale, client, federation-where-adopted, documentation, evidence index, and formal Phase 7 closeout.

### Conditional carryovers — not automatically Phase 7 scope

The following may enter Phase 7 only through the carryover ledger or a signed scope change:

- Any unaccepted Phase 1–6 core posting, privacy, moderation, media, package, role, identity, search, queue, cache, SSE, replica, feed, backup, or recovery obligation. Phase 7 may extend only accepted behavior.
- A Phase 6 infrastructure component whose trigger was not met. PWA, tenancy, native, imports, or federation do not automatically justify Redis, replicas, external search, SSE, CDN, or materialized feeds.
- Additional source-forum adapters beyond the approved Gate A set. Each adapter needs demand, legal/data mapping, security, fixtures, maintenance owner, and acceptance evidence.
- Private-board or DM offline storage in the web PWA. It remains disabled unless a separately approved browser-device threat model and purge/revocation design pass.
- Cross-community DMs, shared private groups, shared reputation, shared moderation, shared notification subscriptions, or automatic membership discovery.
- A native binary on every platform. The accepted mobile strategy determines supported targets and must record no-adoption for unsupported platforms.
- Federation beyond the approved public-content subset, including federated login, private groups, DMs, moderation authority, package distribution, payments, or automatic trust.
- Per-community billing, plans, checkout, taxation, subscriptions, or revenue sharing. Multi-community operation in Phase 7 is product/data isolation, not a SaaS commerce system.

### Explicitly deferred beyond the seven-phase roadmap

The following must not delay Phase 7 acceptance unless formally pulled in through a new roadmap decision:

- Real-time chat semantics, typing indicators, per-message read receipts, voice/video, calls, ephemeral messaging, or end-to-end encrypted messaging.
- Database sharding, multi-primary writes, active-active regions, global conflict-free data replication, or automatic cross-region failover.
- Adversarial/regulatory tenant isolation presented as equivalent to separate deployments; dedicated-database/per-tenant encryption variants require a separate architecture and operating model.
- Paid marketplace commerce, community billing, metering invoices, tax handling, revenue sharing, or license enforcement.
- Automatic machine translation of member content, third-party generative rewriting, autonomous moderation, or AI-produced canonical content without separate privacy, quality, cost, retention, and human-control decisions.
- Federated private content, federated authentication authority, cross-install shared credentials, remote administrator capabilities, or unrestricted remote media ingestion.
- Arbitrary offline execution of moderation, security, role, package, provider, import, or community-owner actions.
- Unreviewed import adapters, raw database-dump ingestion into production tables, executable archive migration, or import of plaintext passwords/tokens.
- A mandatory single-page-application rewrite, mandatory microservices rewrite, or removal of the canonical server-rendered web product merely because native/PWA clients exist.

## 5. Reconciled and locked implementation decisions

The following decisions are treated as fixed for Phase 7 unless Milestone 0 records an explicit product-owner amendment:

1. **Phase ownership stays intact.** Phase 7 extends accepted Phase 1–6 behavior; it does not hide unfinished security, privacy, product, ecosystem, or scale work.
2. **The server remains canonical.** Offline stores, service-worker caches, native databases, import staging, search/feed indexes, and remote-object projections are not authoritative for permissions, account state, moderation, or final writes.
3. **Core web remains server-rendered and progressively enhanced.** PWA/native clients may offer richer offline behavior, but the supported web route remains the universal recovery and administration surface.
4. **Phase 7 is not one architecture rewrite.** Locale, PWA, push, import, tenancy, mobile, and federation have independent flags, migrations, evidence, and rollback controls.
5. **Locale and content language are different.** Interface locale affects system presentation; content language describes user content. The platform does not imply translation merely because both are known.
6. **Locale tags and timezones are canonicalized.** Store validated BCP-47-style locale/language tags and IANA-style timezone identifiers; display aliases never become storage keys.
7. **Core routes are locale-independent.** Stable IDs and canonical slugs preserve links. Locale selection uses preference/host/path/query only according to the approved design and cannot change resource identity.
8. **Translation catalogs are code/data, not templates.** Locale packages contain message values and metadata only; no PHP, JavaScript, remote fetch, executable markup, or permission definitions.
9. **Translation placeholders are typed and escaped.** Placeholder names/types must match the source catalogue. Raw HTML translation is prohibited except narrowly reviewed structured components rendered by code.
10. **Fallback is visible and safe.** Source-locale fallback is better than a blank or misleading control. Missing critical warning, consent, permission, or recovery text blocks that locale’s release.
11. **RTL is a first-class layout mode.** Logical CSS, bidi isolation, content `dir=auto`, and platform shortcut behavior are tested; code, URLs, IDs, timestamps, and numbers follow approved direction rules rather than naive mirroring.
12. **Locale packages use the Phase 5 trust chain.** Review binds to an exact digest; updates are pin-able and rollback-able; a registry outage cannot remove installed translations.
13. **Service-worker caching is deny-by-default.** Only explicitly classified routes/assets enter offline caches. A broad cache-first rule for authenticated HTML is forbidden.
14. **Offline read is a snapshot, not truth.** Every offline document carries community/resource identity and freshness metadata; authoritative actions wait for current online validation.
15. **Private PWA offline bodies are off by default.** Public saved topics and drafts are the baseline. Any broader storage requires a separate data-class approval and user-facing device-risk disclosure.
16. **Offline submissions require explicit intent.** Drafts may queue, but reconnect does not silently post stale or changed content without the approved review/validation flow.
17. **Background Sync is an optimization, not correctness.** Unsupported browsers or suspended background execution fall back to foreground retry and visible pending state.
18. **Push is an invalidation/deep-link channel.** Payloads contain minimal identifiers/counts; canonical fetch supplies current text and access decisions.
19. **Push permission is not demanded at first page load.** The product requests permission after an understandable member action and exposes per-device controls.
20. **Portability is a product contract.** The canonical archive format is versioned and documented independently from any one import adapter or database schema.
21. **Imports write through domain services.** Adapters cannot issue arbitrary SQL, bypass validation, skip audit, or write files into executable paths. High-risk adapters run through the accepted isolated package/job boundary where practical.
22. **Source identity is stable.** Every import maps `(source installation, source type, source id)` to a deterministic target; names and emails are matching inputs, never identity authority.
23. **Password and provider secrets are not portable by default.** Default account migration uses secure claim/reset. Compatible password-hash import is exceptional, algorithm-specific, audited, and rehashed after successful login.
24. **Legacy content is normalized once and traceably.** Canonical Markdown/render is the active representation; restricted source payload and transformation version permit review/reprocessing without rendering unsafe source HTML.
25. **Imports start in a staging boundary.** Prefer an unpublished/staging community or disabled target, dry run, and final delta/freeze before public cutover.
26. **Import rollback has a declared point of no return.** Before target-side interaction, batch removal/restore may be safe; afterward use restore/reconciliation rather than deleting interleaved member activity.
27. **Multi-community is opt-in.** Single-community mode remains supported and does not pay avoidable product complexity before the operator enables the feature.
28. **One installation has global accounts and explicit community memberships.** Credentials, passkeys, providers, sessions, and protected installation owners are global; content and ordinary community authority are community-scoped.
29. **Usernames remain installation-unique in the initial tenancy model.** Communities may provide an approved display-name/profile override, but login resolution and public account identity remain unambiguous.
30. **Blocks remain installation-wide unless a later policy says otherwise.** A member who blocks another is not forced into contact through a second community; community-level mute remains a different feature.
31. **DMs are community-scoped.** Existing conversations migrate to the default community. Cross-community DMs are not inferred from shared installation membership.
32. **Every tenant row has explicit ownership.** Derivable parent ownership may support validation, but high-value tables and projections carry `community_id` where needed for constraints, indexes, partitioning, isolation tests, and operations.
33. **Hostnames select context; they do not grant access.** Host/domain resolution identifies a community candidate, then normal membership, role, privacy, and content checks apply.
34. **Unrelated custom domains do not share a wildcard session cookie.** Use per-origin sessions and an approved one-time identity handoff or central identity origin with strict redirect/origin validation.
35. **Installation and community authority are separate.** Community owners can govern their community within policy; protected installation owners retain trust roots, infrastructure, global providers, final recovery, and cross-community emergency control.
36. **Shared-schema isolation is transparent.** It is appropriate for cooperating communities under one operator; hostile or legally isolated tenants should use separate installations unless a later dedicated-isolation architecture is approved.
37. **Packages have install scope and enablement scope.** Registry trust and package bytes are installation-level; capabilities, settings, storage, network grants, jobs, and activation are community-scoped unless explicitly global.
38. **Resource fairness is enforced.** Imports, extensions, media, push, federation, search, feeds, and backfills use community attribution, quotas, priorities, and kill switches.
39. **Per-community deletion does not delete shared identity blindly.** Membership/profile/content policy and account-level deletion are separate workflows; shared global credentials remain until no membership/legal retention requires them.
40. **Mobile strategy precedes API expansion.** Do not create a broad public member API merely to claim native readiness. Every endpoint exists for an approved client journey and shares domain authorization.
41. **Native authentication uses system security primitives.** Browser-based authorization/PKCE, platform passkeys, secure credential storage, universal/app links, and per-device revocation are preferred over embedded credential collection.
42. **Client sync is explicit and bounded.** Cursor/version, ETag, idempotency, conflict, tombstone, and full-resync rules are documented; clients never infer authorization from cached list membership.
43. **Native-only authority is prohibited.** No moderation, owner, package, or security capability exists only in an opaque client; the canonical web/Console remains the recovery path.
44. **Federation is public and protocol-scoped.** The first approved subset does not include DMs, private boards, shared login, remote roles, or package execution.
45. **Remote identity is not local identity.** Remote actor IDs, origin, keys, and trust state remain explicit. Similar names, avatars, profiles, or email claims never merge accounts.
46. **Remote content is locally governed.** Local read gates, blocks, domain policy, sanitization, report/moderation, retention, and user disclosure decide whether a remote object renders.
47. **Federation is asynchronous and non-critical.** Remote delivery failure cannot roll back a valid local post or prevent local reading; retries are idempotent and bounded.
48. **Federation media is conservative.** Prefer proxied/cached validated public media or click-through according to policy; arbitrary remote active content is never embedded.
49. **Every expanded surface is exportable or explicitly non-portable.** Community data, locale settings, device subscriptions, import mappings, and remote projections document export/delete behavior.
50. **Schema design precedes implementation.** Any community, locale, device, import, client, or federation table/column absent from `SCHEMA.md` is reconciled before dependent production code is merged.
51. **Every subsystem has an independent disable path.** Locale pack, locale selection, service worker, saved-offline content, offline queue, push, an import adapter/run, multi-community routing, a community/domain, client API/app version, and federation inbound/outbound can be disabled without disabling core local reading/posting.
52. **Phase 7 closes the planned roadmap.** Any functionality beyond this plan requires a new explicit roadmap rather than being assumed as an untracked Phase 8.

## 6. Delivery workstreams

| ID | Workstream | Deliverables | Acceptance evidence | Dependency | Gate |
|---|---|---|---|---|---|
| P7-00 | Entry gate, strategy, and baselines | Phase 6 closeout; carryover ledger; platform strategy records; data-class/threat maps; locale/source/mobile/federation decisions; fixtures; numeric budgets; flags; evidence map | Signed Phase 6 acceptance/deferrals; strategy approvals; baseline report; schema diff; requirement ledger; rollback map | Phase 6 | A |
| P7-01 | Internationalization runtime | Message catalogue/extraction; locale resolver; plural/select/date/number/list/relative-time formatting; source fallback; `lang`/`dir`; Unicode/bidi policy; locale-aware errors | Catalogue/placeholder tests; route/email/error snapshots; pseudo-locale; timezone/number fixtures; fallback and cache-key evidence | P7-00 | A |
| P7-02 | Locale packages and translation operations | Signed locale-package type; coverage/reviewer/version metadata; install/update/pin/rollback; operator locale settings; translation QA; source/non-source/RTL target | Signature/package tests; missing-key/placeholder parity; full browser/email/push matrix; RTL/text-expansion/accessibility evidence | P7-01, accepted Phase 5 registry | A |
| P7-03 | Content language, search, and discovery | Thread/content language; inheritance/defaults; search analyzer/index hints/fallback; locale-safe metadata/canonical/sitemap; moderation display; no automatic translation | Search corpus by language; Unicode/bidi/XSS tests; canonical/SEO fixtures; private-result isolation; editor round-trip evidence | P7-01, accepted search/composer | A |
| P7-04 | PWA shell and offline public reads | Per-community manifest/icons/shortcuts; service worker; version/update/kill switch; static cache; offline shell; save/remove public topics; stale indicators; quota/storage controls | Installability/browser matrix; cache-class negatives; update/rollback tests; offline browser evidence; logout/account/community-switch purge | P7-00, P7-01 | A |
| P7-05 | Offline drafts and deferred submissions | Local/server draft bridge; per-user/community/context isolation; queued explicit send; idempotency; revalidation; conflict UI; retry/export/discard; no offline privileged actions | Network loss/reconnect/account-state/thread-state/version conflicts; duplicate prevention; storage eviction; multi-account/device tests | P7-04, accepted Phase 3 server drafts if shipped; else local-only fallback _(Fixed 2026-06-26: Phase 3 server-side draft sync is optional/re-scopable — PHASE_3_PLAN §3; P7-05 must not assume it.)_ | A |
| P7-06 | Web Push and device subscriptions | Permission UX; subscription/device registry; minimal payload; notification preference integration; click/deep link; revoke/expiry/repair; key/provider rotation; delivery ledger | Payload inspection; revoke/session/access-change tests; retry/dedupe; browser/platform matrix; private-data negatives; provider outage | P7-04, accepted notifications | A |
| P7-07 | Canonical portability format and export | Versioned archive manifest; typed streams; checksums; media inventory; community/account export; schema adapters; validation/sign/encrypt options; documentation | Deterministic fixture export; checksum/tamper tests; clean-install import round trip; omission/retention report; large-stream evidence | P7-00 | A |
| P7-08 | Import engine and source adapters | Adapter contract; isolated staging; preflight/dry run; mappings; checkpoints/resume; transforms; identity claim/reset; media scan; redirects; delta/cutover; audit/reconciliation | Idempotent rerun; duplicate/collision fixtures; malformed archive/security tests; source adapter corpus; backup/rollback rehearsal | P7-07, accepted jobs/media/package isolation | A |
| P7-09 | Community and membership foundation | `communities`; default-community backfill; membership/profile context; installation/community owner boundaries; community settings/locale/branding/registration; community-scoped DMs | Clean/populated migration; old-vs-new parity; membership/role matrix; default URLs; one-community compatibility; direct-request isolation | P7-00, accepted Phase 5 roles/identity | B |
| P7-10 | Tenant ownership migration and isolation | `community_id` propagation; constraints/indexes; repository context; cache/search/feed/object/job/audit/API/extension namespaces; cross-community negative corpus | Schema/static-query audit; direct-ID/host/cache/search/media/job/export isolation; concurrency; fallback and repair evidence | P7-09 | B |
| P7-11 | Domains, community operations, and fairness | Domain verify/route/TLS/origin/session handoff; community create/suspend/export/delete; package/integration scope; quotas/metering; queue fairness; per-community backups/restore/import | Domain takeover/open-redirect/CSRF tests; noisy-neighbor load; quota enforcement; owner-scope matrix; community move/restore drill | P7-09, P7-10, Phase 6 topology | B |
| P7-12 | Mobile strategy and member client contract | Mobile ADR; feature floor; API need; versioned API/sync contract where selected; device credentials; secure storage; deep links; push; locale/accessibility; support/version policy | Strategy evidence; OpenAPI/contract tests; web parity authorization; token/revoke/PKCE tests; offline sync/conflict; supported-device evidence | P7-00, P7-06, P7-10 where multi-community | B |
| P7-13 | Supported mobile client outcome | PWA-only acceptance or packaged/cross-platform/native client; release signing/store process; crash/privacy telemetry; staged rollout; kill/min-version; feature-parity matrix | Install/store/build provenance; auth/deep-link/push/offline/upload tests; crash-free/accessibility/RTL evidence; rollback/version-block rehearsal | P7-12 | B |
| P7-14 | Public federation gateway | Adopt/pilot/no-adopt record; protocol adapter; server/actor/object identity; signatures/keys; discovery; inbox/outbox; public-content subset; moderation/domain policy; edits/deletes; kill switches | Signature/replay/SSRF/key-rotation tests; allowlist pilot; duplicate/tombstone/block/report fixtures; privacy negatives; outage/rollback | P7-00, P7-10 if multi-community | B conditional |
| P7-15 | Safety, privacy, accessibility, performance, and recovery | Cross-surface threat review; locale/PWA/import/tenant/client/federation privacy; accessibility; SEO; budgets; backup/restore; portability and provider-exit proof | Full route/data-class matrix; accessibility report; load/fault/restore evidence; privacy/log inspection; zero critical/high defects | P7-01–P7-14 | A/B |
| P7-16 | Operations, staged release, and roadmap closeout | Metrics/dashboards; Console/CLI controls; runbooks; release notes; evidence index; schema/source-doc reconciliation; adoption/no-adoption records; final acceptance | Full Phase 1–7 suite; migration/rollback rehearsals; roadmap audit; product-owner Gate A and final closeout | All applicable workstreams | A/B |

## 7. Recommended execution sequence

### Milestone 0 — Close Phase 6 and approve platform strategy

- Review Phase 6 Gate A/Gate B evidence against the Phase 6 plan and create the final carryover ledger.
- Capture the deployed schema, services, hosts/domains, locales/strings, PWA/browser behavior, draft storage, notification providers, account exports, source-import demand, account/role model, packages, and community assumptions.
- Inventory every string-bearing surface: templates, controllers, validation, permissions, email, push, PWA manifest, service worker, JavaScript, CLI, worker errors, package/theme metadata, and native/API errors where planned.
- Inventory every authoritative/derived table and service by installation-global versus future community-owned scope.
- Build representative fixtures: Unicode and RTL names/content, long translations, timezone/plural cases, large offline topics, multiple browser accounts, large archives, duplicate identities, old URLs, private boards/DMs, many communities, custom domains, high-volume imports, packages, feeds, media, and remote objects.
- Approve the source and target locale set, locale-package governance, import sources, archive policy, PWA data classes, push provider/key policy, tenancy model, mobile direction, and federation decision.
- Lock numeric budgets and evidence targets for every Gate A/B requirement.
- Define independent feature flags, emergency disables, and rollback boundaries for every locale, service worker/cache class, offline queue, push provider, import adapter/run, community/domain, member API/client version, and federation direction/domain.

**Exit gate:** Phase 6 is formally closed or explicitly re-scoped; all Phase 7 strategy records, owners, policies, schemas, fixtures, budgets, evidence targets, and rollback controls are approved.

### Milestone 1 — Schema reconciliation and cross-cutting contracts

- Reconcile `SCHEMA.md` with the actual Phase 6 database before adding locale, device, import, community, client, or federation state.
- Define locale/language canonicalization, message-catalog format, placeholder schema, fallback chain, locale package manifest, and translation review workflow.
- Define service-worker route/data classification, cache versioning, storage partitioning, purge, offline document envelope, and draft/action queue contract.
- Define push subscription, device identity, notification payload, revoke, key rotation, and provider-failure behavior.
- Define canonical archive manifest, entity streams, media/checksum model, source mapping, transformation versions, import checkpoints, exception classes, and redirect lifecycle.
- Define the complete tenancy ownership matrix for every table, object, cache, index, event, job, package, token, and route; identify global tables and required `community_id` propagation.
- Define mobile API/auth/sync contracts only to the depth required by the approved strategy.
- Define federation schema/protocol boundaries only if adopted or piloted.
- Add clean-install and populated-upgrade migration tests before dependent feature code is merged.

**Exit gate:** No Gate A feature depends on an undocumented locale/device/import field, and the Gate B community ownership/migration design passes architecture, privacy, authorization, and rollback review.

### Milestone 2 — Internationalization runtime and locale-package pipeline

- Replace inline user-facing strings with stable keys and typed placeholders in bounded, reviewable slices.
- Add locale negotiation, user/community settings, source fallback, locale-aware formatter services, and cache variation.
- Convert date, time, number, list, plural, validation, permission, email, push, and worker-facing user messages.
- Apply logical CSS properties, `lang`, `dir`, bidi isolation, `dir=auto` for user content, and platform-aware keyboard labels.
- Add pseudo-locales for expansion, mirrored/RTL stress, missing-key visibility, and placeholder corruption.
- Implement signed locale packages through the accepted registry, including install, update, pin, rollback, coverage, reviewer, and compatibility.
- Complete the approved production non-source locale and RTL verification target across public, member, settings, moderation, Console, email, push, errors, and PWA surfaces.
- Add content-language metadata and search/index behavior without changing canonical URLs or auto-translating member content.

**Exit gate:** The selected locales pass the full route/email/push/accessibility matrix; source fallback is safe; RTL and long-text layouts remain usable; no untranslated critical control or unescaped placeholder remains.

### Milestone 3 — PWA, offline public reads, drafts, and push

- Generate the per-community/brand manifest, icons, shortcuts, start routes, and locale metadata.
- Install the versioned service worker in asset-only mode first; prove update, rollback, bypass, and kill-switch behavior.
- Add the offline shell and explicit Save Offline action for approved public topic snapshots, with quota, last-updated, remove, and clear-all controls.
- Exclude authenticated/private/security/admin/token/import/federation-control routes and responses from shared offline caches.
- Integrate local drafts with server drafts and add explicit deferred-send records, idempotency, revalidation, conflict display, retry, export, and discard.
- Add offline/online status and accessible announcements without blocking ordinary forms or no-JS behavior.
- Implement Web Push subscription and per-device management, minimal payload, notification preference mapping, deep-link recheck, revoke/expiry, and provider/key rotation.
- Exercise logout, account switch, community switch, session revoke, membership removal, deleted/private content, browser storage eviction, service-worker update failure, and provider outage.

**Exit gate:** The PWA installs and degrades safely; approved offline public content and drafts work; no protected response is cached; push contains no private body and always converges through current server authorization.

### Milestone 4 — Canonical portability and import delivery

- Implement the canonical archive writer/reader, manifest, entity streams, media inventory, checksums, validation, and bounded streaming.
- Export a representative community and import it into a clean supported installation; reconcile counts, references, memberships, permissions, Markdown, media, and redirects.
- Build the source-adapter SDK/broker and the first approved legacy adapter(s) using real sanitized fixtures and documented field mappings.
- Add preflight, dry run, staging community/disabled target, mapping review, identity conflicts, password/claim policy, unsupported-feature report, storage/time estimate, and legal/privacy acknowledgement.
- Implement resumable batches, idempotent source mapping, transformation versions, quarantine, media scanning, old-link redirects, delta import, source freeze/final cutover, and reconciliation commands.
- Exercise malformed/tampered archives, path traversal, decompression bombs, duplicate source IDs, duplicate emails/usernames, deleted/private content, DMs according to policy, unsupported markup, missing media, partial failure, restart, and rollback boundary.
- Publish operator and adapter-author documentation plus a migration support checklist.

**Exit gate:** The canonical round trip and approved source adapter meet mapping, integrity, safety, performance, redirect, recovery, and audit budgets with no duplicate or unauthorized target data.

### Milestone 5 — Gate A hardening, staged release, and acceptance

- Run the complete locale/pseudo/RTL, PWA/service-worker, offline/draft, push, export/import, security/privacy, accessibility, SEO, load, storage, provider-outage, backup, and rollback matrix.
- Measure translation fallback, text overflow, cache bytes, offline freshness, draft conflict, push delivery, export/import throughput, exception rate, and operator workload against approved budgets.
- Rehearse locale-package rollback, source-locale emergency fallback, service-worker kill/unregister, cache purge, pending-draft recovery, push disable/key rotation, import pause/resume/abort, clean restore, and canonical reimport.
- Roll out locale selection and PWA asset shell to staff, then selected members; enable saved offline content, deferred drafts, push, and import tooling in independent cohorts/environments.
- Reconcile source documents, schema, archive specification, locale contributor guide, runbooks, and evidence index.
- Record Gate A product-owner acceptance.

**Exit gate:** Gate A is accepted in production with no critical/high localization, offline-cache, push, import, portability, privacy, or recovery defect.

### Milestone 6 — Default-community migration and tenancy foundation

- Create `communities`, default community, membership/profile context, community settings, domain records, and installation/community owner boundaries behind disabled multi-community routing.
- Backfill every existing installation into one default community while preserving IDs, URLs, sessions, roles, private memberships, DMs, notifications, packages, settings, audit, search, media, and exports.
- Add explicit community context to service/repository calls and high-value tables/projections in bounded migration groups.
- Run old resolver/query behavior in shadow against the community-aware path and record every mismatch.
- Add community-scoped uniqueness, foreign keys/checks, indexes, object namespaces, cache/search/feed keys, outbox/jobs, audit, rate limits, webhooks/APIs, extension storage, and metrics.
- Keep the product in single-community compatibility mode until direct-ID, alternate-host, stale-cache, search/media/job/export, and fallback isolation tests pass.

**Exit gate:** A populated installation operates identically as one default community through the community-aware code path; every tenant-owned object has approved ownership and cross-community negative tests are green.

### Milestone 7 — Multi-community product and operating model

- Add community creation, membership/invite flows, community switcher/context, per-community profile/display layer, settings, branding, locale/timezone, registration, boards, roles, packages, integrations, retention, and quotas.
- Implement verified subdomain/custom-domain routing, TLS/origin/CSRF policy, per-origin sessions, and one-time identity handoff where needed.
- Scope DMs, follows, feeds, unread, notifications, push, reports, moderation, search, media, reputation/badges, exports, and deletion to community.
- Add installation-owner and community-owner Console views, permission simulation, audit, emergency suspension, export, and deletion safeguards.
- Add per-community resource metering, queue priorities/limits, import/media/package/federation quotas, noisy-neighbor controls, and cost attribution.
- Create a second internal/test community, then a controlled external community, and exercise cross-host navigation, account membership, data isolation, owner scope, backup/export/import, and community suspension/recovery.
- Publish the shared-schema isolation statement and guidance for when separate installations are required.

**Exit gate:** Two real communities can coexist with zero cross-community data/authority leakage, predictable resource fairness, independent settings/domains, and a rehearsed community move/export/recovery path.

### Milestone 8 — Mobile-client direction and supported outcome

- Finalize the mobile ADR from Gate A evidence, including install/adoption, push, offline usage, browser limitations, platform demand, distribution, accessibility, privacy, and maintenance cost.
- For PWA-only: close all approved mobile gaps, document platform/browser support and install guidance, prove deep links/push/offline/auth/uploads, and record why a native binary adds insufficient value.
- For packaged/native: define the versioned member API, authorization parity, cursor/sync/tombstone/idempotency contracts, device credentials, system-browser/PKCE/passkey flows, secure storage, deep links, push, offline policy, uploads/drafts, and min-version controls.
- Build the selected supported client outcome, not merely a visual prototype; identify intentionally web-only administration/moderation functions.
- Add build signing/provenance, secret-free configuration, store/release metadata, privacy disclosures, crash/analytics redaction, accessibility, locale/RTL, support matrix, staged rollout, revoke/kill switch, and rollback.
- Exercise token theft/revoke, account/community switch, session ban, private membership removal, offline conflict, deleted content, push deep links, API version skew, server fallback, app downgrade, and lost device.

**Exit gate:** The approved mobile strategy is fully supported and evidenced; shipped clients match web authorization and recovery, or PWA-only acceptance demonstrates that the approved mobile outcomes are met without native binaries.

### Milestone 9 — Public federation pilot where approved

- If federation is no-adopt, record the decision evidence, risks, product alternatives, and reassessment condition; skip implementation without marking it “done.”
- If adopted/piloted, select and document the protocol subset, server identity, key rotation, actor/object model, discovery, delivery envelope, public content types, and local representation.
- Implement signed request verification, replay defense, SSRF-safe discovery/media, inbox/outbox jobs, retries, dedupe, tombstones, domain/actor/object policy, and independent inbound/outbound switches.
- Start allowlist-only between controlled installations; federate public topics/replies only and exclude private boards, DMs, credentials, security/moderation notes, and protected media.
- Add user disclosure, remote provenance, local block/report/moderation, domain quarantine, edit/delete handling, remote server outage, key compromise, purge/export, and abuse runbooks.
- Measure delivery, duplicate, moderation, abuse, remote-content value, operator burden, and privacy incidents before broader enablement.

**Exit gate:** Federation is either accepted within its narrow public boundary with no critical/high defect or formally no-adopted with an approved record; it never becomes a hidden prerequisite for local communities.

### Milestone 10 — Phase 7 release candidate and formal roadmap closeout

- Run the complete Phase 1–7 regression and permission matrix in single-community and multi-community modes, source and translated locales, online/offline/PWA states, supported clients, and federation enabled/disabled where applicable.
- Rehearse clean install, every supported historical upgrade, default-community backfill, second-community creation, domain move, community export/import, locale rollback, service-worker purge, push revoke, client-version disable, federation shutdown, backup restore, and one-community compatibility mode.
- Verify every platform strategy record against actual outcomes and record adoption/no-adoption, owner, support window, incidents, budgets, and remaining limitations.
- Reconcile `README.md`, `DESIGN.md`, `DECISIONS.md`, `SCHEMA.md`, surface documents, archive/API/federation specifications, locale packages, deployment manifests, runbooks, changelog, and evidence index with the deployed product.
- Record every accepted Gate A/Gate B requirement and every signed omission outside the seven-phase roadmap.
- Capture post-release product, isolation, portability, locale, client, federation, cost, and support baselines.

**Exit gate:** The Phase 7 evidence index and product-owner closeout are recorded; the planned seven-phase feature set is either accepted or explicitly excluded through a signed strategy decision, with no hidden obligation under “later.”

## 8. Data and migration plan

### 8.1 Existing tables, interfaces, and behavior to verify before reuse

Phase 7 must verify actual deployment and accepted behavior for:

- global accounts, usernames, email verification, providers, passkeys, TOTP/recovery, sessions/devices, blocks, deactivation/deletion, protected owners, and service principals;
- role/capability definitions, site/category/board assignments, temporary grants, groups, approvals, private-board memberships, and permission-cache versions;
- categories, boards, threads, posts, revisions, tags, polls, summaries, redirects, attachments, DMs, follows, feeds, subscriptions, unread state, notifications, email/push-like delivery seams, reports, moderation, and audit;
- `settings`, `user_preferences`, branding/theme assets, timezone/digest fields, locale-relevant hard-coded values, templates, email subjects, validation/errors, and package-provided strings;
- package registry/installations, locale/theme package capability, extension storage, jobs/events, webhooks, API tokens, remote apps, network grants, and package data export/delete;
- Phase 6 outbox/jobs, caches, search indexes, feed projections, object storage/CDN, SSE/polling, replicas, worker pools, routing, backups, restore, and service credentials;
- existing account-data export/delete, attachment/media export, old slug redirects, import-like maintenance scripts, and any untracked data migration utilities;
- service-worker registrations, browser local/session storage keys, current local drafts, web manifest/assets, and any existing notification subscription experiments;
- route host/origin assumptions, session-cookie domains, CSRF/origin validation, canonical URL generation, OpenGraph/sitemap generation, and email/deep-link host construction.

A table, locale file, service worker, app manifest, import script, client build, API route, federation endpoint, or community field named in documentation is not evidence that its deployed behavior is accepted.

### 8.2 Schema and durable-state gaps that must be resolved at Milestone 1

1. **Communities.** Define stable community ID/slug, internal name, public name, status, default locale/timezone, owner policy, created/activated/suspended/deleted timestamps, configuration version, and default-community marker. Slug/name is not authority.
2. **Community domains.** Define normalized host, community, kind (primary/alias/staging), verification challenge/state, TLS/readiness state, redirect policy, canonical host, enabled/suspended state, created/verified timestamps, and audit. Host uniqueness must be global to the installation.
3. **Community memberships.** Define user, community, membership status, joined/invited/left/suspended timestamps, invitation/source, notification defaults, membership version, and privacy/export/delete state. Authentication does not imply membership.
4. **Per-community profile context.** Define optional display name, bio, avatar/signature reference, locale/timezone override, visibility, title, and profile settings without duplicating global login identity. Decide which existing global fields remain canonical and how fallback works.
5. **Community settings and branding.** Define typed/versioned community settings or extend the existing settings model with explicit installation/community scope, validation schema, inheritance, effective version, secret reference, audit, and cache generation.
6. **Community roles and protected owners.** Extend role assignments and owner records with community scope, installation-only protected capabilities, community-owner invariant, expiry, approval, and audit. Existing site scope must map deterministically to installation or default-community authority.
7. **Tenant ownership on content.** Add or prove explicit `community_id` ownership for categories, boards, threads, posts/revisions, summaries/wiki/relations, polls, tags, and redirects. Child rows may validate against parents, but isolation-critical queries and constraints need approved direct ownership/indexes.
8. **Tenant ownership on communication/social state.** Add community ownership to DMs/conversations/participants/messages, follows, feeds/projections, subscriptions, unread/star/snooze/assignment state, notifications, email/push deliveries, and presence where applicable.
9. **Tenant ownership on safety/operations.** Add community ownership to reports, bans/warnings/notes where scoped, moderation/audit targets, automation rules/holds, appeals, rate-limit dimensions, webhooks/API/app installations, jobs/events, package storage, and exports/imports.
10. **Tenant ownership on media.** Record community on attachments, derivatives, storage objects, previews, avatars/signatures, brand assets, quarantine, retention, and migration ledgers. Object keys/URLs are derived, not ownership.
11. **Community-scoped uniqueness.** Revisit category/board/tag slugs, badge keys, custom fields, package settings, webhooks, roles, invitations, and other natural keys. Decide which remain installation-global and which become unique within community. Replacing a global `UNIQUE` or primary key with a community-scoped composite is non-additive and becomes irreversible once two communities hold colliding natural keys; treat each such change as a one-way cutover under the §13.2 controls, not an additive rollback-window change.
12. **Locale preferences.** Add canonical installation default, community default, user global locale, optional membership/community override, accepted locale list, fallback, and preference version. Avoid free-form unvalidated values.
13. **Content language.** Add optional canonical language tag and detection/source metadata to threads or content roots; posts normally inherit unless the product explicitly supports mixed-language posts. Include search-index version and moderation override where needed.
14. **Locale packages/catalogue state.** Reuse package tables where possible, but record locale identifier, source locale/core compatibility, catalogue digest, coverage, placeholder schema version, reviewer/review state, active/pinned version, and fallback. Do not create a second unsigned update path.
15. **Operator-local translations.** If operators may override community-specific labels/help, define a narrow key allowlist, locale, value, version, reviewer, status, and audit. Core security/permission text should not be arbitrarily overridden.
16. **Client/device installations.** Define device/client ID, user, optional community, origin/app ID, platform, app/browser version, locale, created/last-seen/revoked timestamps, credential/subscription references, and privacy-safe metadata. Device fingerprints are not identity.
17. **Web Push subscriptions.** Define client/device, endpoint hash/protected endpoint, key material references, provider/application key version, community preference scope, status, failure/expiry/revoked timestamps, last success, and uniqueness. Secrets are protected and never logged/exported casually.
18. **Offline synchronization metadata.** Server need not store cached bodies, but define deferred-action/idempotency receipts, client operation ID, user/community/context, action type, base version, status, result/target, conflict/error class, created/received/finished timestamps, and bounded retention where server reconciliation requires it.
19. **Portability runs and packages.** Define export/import run ID, type, community/user, requested actor, format/schema version, manifest digest, encryption/signature state, object counts/bytes, storage reference, status, expiry/retention, error summary, and audit.
20. **Import sources and adapters.** Define source installation/type/version, adapter package/version/digest, configuration hash, source timezone/locale, trust/review state, and last successful preflight/run. Adapter secrets use the accepted secret store.
21. **Import entity mappings.** Define run/source, source entity type/ID, target type/ID, source checksum/version, transformation version, outcome, conflict/claim state, and timestamps. Enforce stable uniqueness to make resume/rerun idempotent.
22. **Import checkpoints and attempts.** Define entity stream/partition, cursor, last source item, counts, lease/worker, attempts, timing, resource use, status, and failure. Checkpoints advance only after durable target/mapping state.
23. **Import exceptions and quarantine.** Define source entity/media, reason code, redacted diagnostic, proposed resolution, resolver, decision, target linkage, and retention. Never store plaintext credentials or unrestricted source dumps in ordinary logs.
24. **Legacy source payload.** If raw source content is retained, define encrypted/restricted storage reference, checksum, content type, transformation version, retention, and purge. It is never rendered directly.
25. **Import redirects.** Define source host/path hash or normalized path, source type/ID, target community/resource, redirect status/canonical behavior, collision, expiry/retention, and enabled state. Prevent arbitrary external redirect destinations.
26. **Community lifecycle/export.** Define suspension, export freeze, deletion/anonymization, retention holds, shared-account/object reference checks, final purge, and restoration metadata. A community delete is not an installation delete.
27. **Resource quotas and usage.** Define per-community configured limits and measured usage for members, content, media, jobs, push, API, packages, imports, federation, and other approved dimensions; include window/version, enforcement mode, warning/limit state, and reconciliation.
28. **Client API credentials.** If shipped, define app/client registration, authorization grants, device refresh credential hash/reference, scope, user/community, issued/rotated/expires/revoked/last-used timestamps, key/version, and audit. Do not reuse admin `api_tokens` as member refresh tokens.
29. **Client synchronization cursors.** If shipped, define user/community/device, collection/surface, cursor/version, full-resync marker, last success/error, and bounded cleanup without making the cursor an authorization grant.
30. **Federation servers and policy.** If adopted, define normalized origin/domain, server identity/key references, discovery state, policy (allow/silence/reject/block/quarantine), trust/review, capabilities, last contact, failure/abuse state, and audit.
31. **Remote actors and objects.** If adopted, define immutable remote URI/origin, actor/object type, current key/provenance, local community visibility, sanitized representation, source timestamps/version, local moderation state, deleted/tombstoned state, fetch/refresh, and retention. Avoid copying unnecessary private data.
32. **Federation activities and deliveries.** If adopted, define stable activity ID, direction, source/destination, type, payload digest/reference, signature/key version, idempotency key, status, attempts, available/finished timestamps, response/error, and dead-letter/replay state.
33. **Federated relationships.** If follows/replies are in the approved subset, define local/remote endpoints, community, state, approval, block, created/ended timestamps, and source activity. They must not merge with local follows without explicit type/origin.
34. **Community-aware search/feed/cache versions.** Decide where community generations, index aliases, feed generations, and security versions live. A global flush/version cannot accidentally re-enable stale data from another community.
35. **Audit target coverage.** Ensure locale changes/packages, service-worker/PWA controls, push devices/keys, portability runs, import mappings/resolutions, community/domain/lifecycle changes, client credentials/app versions, and federation policy/key/delivery actions have structured targets and before/after/config-version evidence.

### 8.3 Recommended migration and configuration groups

Apply additive schema/configuration changes in dependency order with corresponding features disabled initially:

1. Locale/default/preference fields, content-language metadata, catalogue/package metadata, and translation audit targets.
2. Client/device and Web Push subscription/delivery metadata plus any deferred-action receipts.
3. Portability runs/packages, import sources, mappings, checkpoints, exceptions, raw-source references, and redirects.
4. `communities`, domains, memberships, per-community profiles/settings, community-owner records, and lifecycle state.
5. Community ownership columns, constraints, and indexes for core content, communication/social state, safety/audit, media, packages/integrations, jobs/events, and projections.
6. Community-aware configuration/cache/search/feed/object/outbox generations, quotas/usage, and resource-fairness controls.
7. Client app/authorization/device-refresh and sync-cursor tables where the approved mobile strategy needs them.
8. Federation server/actor/object/activity/delivery/relationship tables where federation is adopted.
9. Final compatibility views/columns, structured audit targets, reconciliation state, and cleanup markers needed for the rollback window.

Each group must pass clean-install, populated-upgrade, one-community compatibility, feature-disabled, mixed-version, backup/restore, and rollback-compatibility tests before dependent behavior is enabled.

### 8.4 Upgrade, backfill, import, and cutover rules

- Existing installations create one default community with a stable generated ID/slug and the existing site name, branding, locale, timezone, settings, canonical host, and protected owner.
- Existing users become active members of the default community unless an existing account state or legal policy requires a different outcome. The backfill does not reactivate banned/deactivated accounts.
- Existing site-wide Moderator/Admin assignments map according to the approved installation/community authority table; ambiguous grants are held as unresolved (non-enforcing) and block enforcement for that record until reconciled.
- Existing board/category assignments and private memberships remain in the default community with no broadened scope.
- Existing categories, boards, threads, posts, DMs, tags, follows, feeds, reports, notifications, media, packages, audit, jobs, and projections receive the default community deterministically and idempotently.
- Existing global usernames remain global. Existing profile fields become the global fallback or default-community profile according to the approved field matrix; no information is silently discarded.
- Existing blocks remain installation-wide. Existing DMs are assigned to the default community and retain participant/history access exactly.
- Existing settings are classified as installation-global, default-community, user-global, or membership/community preference before backfill; unknown settings block cutover rather than being guessed.
- Existing package bytes/registry trust remain installation-level. Installed settings, grants, jobs, storage, webhooks, and API/service principals are assigned to global or default-community scope explicitly.
- Existing object/media references gain community metadata without rewriting canonical Markdown or vendor URLs. Objects are not duplicated unless isolation or lifecycle requires it.
- Existing cache/search/feed keys and indexes are versioned into community-aware generations; old generations remain read-disabled but available through the rollback window where safe.
- Existing source-locale strings become the source catalogue. String extraction does not change business logic or route identity.
- Existing timezone and time-format preferences are preserved. Locale begins at the approved default unless a reliable existing preference exists.
- Existing local drafts remain readable. New storage keys include user, community, context, and schema version; migration never merges two accounts’ drafts.
- Service-worker rollout starts with no historical cache. Existing browsers receive the new worker only after asset-only tests pass.
- Push subscriptions start empty unless a prior standards-compatible source can be migrated without reusing invalid/unknown keys.
- Canonical exports are immutable packages with retention/expiry. Re-export creates a new run/version rather than mutating the prior archive.
- Import mapping is created before or with each target entity. A failed mapping write leaves no untraceable target entity.
- Import adapters do not synthesize data that cannot be supported by the source. Unknown timestamps, authors, privacy, or moderation states remain explicit exceptions/defaults documented in the run.
- Imported user records start unclaimed/disabled or active according to the approved identity policy. Email notifications/claims are not sent until the operator approves the target and sender configuration.
- Imported source URLs are normalized only for the approved source hosts. Unrecognized hosts/paths do not become redirect entries.
- Imported DMs, private boards, moderation logs, IPs, deleted content, and security records follow explicit per-data-class policy; default omission is safer than undocumented import.
- Multi-community routing remains disabled until all tenant columns are populated, non-null where required, constraints/indexes are valid, and isolation shadow tests pass.
- A second community is created only after one-community parity is accepted. Existing default-community URLs remain canonical unless the operator explicitly changes them.
- Custom domains begin disabled/unverified and cannot receive authenticated traffic until DNS/HTTP verification, TLS, origin, redirect, and session-handoff checks pass.
- Mobile API/client features begin with staff/test client registrations. Existing admin API tokens are not migrated into member credentials.
- Federation begins with no historical backfill unless a bounded public-content seed is explicitly approved. Local historical notifications/reputation are never re-fired by federation publication.
- No Phase 1–6 column, route, source-locale catalogue, server-rendered form, single-community mode, local draft recovery, polling fallback, or canonical export path is dropped in the same release that introduces its replacement.

### 8.5 Transactional and consistency invariants

- A locale preference update changes one intended user/community scope and its preference/configuration version together; it cannot alter authorization or another community.
- A locale-package activation references one exact package digest/catalogue version and updates effective locale configuration/cache generation atomically. Failed activation leaves the prior catalogue active.
- A rendered translated message uses one catalogue version and placeholder schema; missing/mismatched placeholders fail to the safe source message rather than emitting partial HTML.
- A saved offline snapshot is created only from a successful canonical public response and records community/resource/version/freshness. Service-worker failure cannot change server content.
- A deferred submission has one stable client operation ID/idempotency key. Duplicate reconnects produce one logical target or one stable conflict/failure result.
- Server validation at deferred-send time is authoritative. A local success marker is written only after the canonical result is returned.
- A push subscription is associated with one user/device/origin and approved community preference context. Revocation prevents future sends within the locked budget.
- Notification creation and push delivery remain separate; push retry cannot create a second logical notification.
- An export manifest and referenced entity/blob checksums describe one immutable snapshot/run. A failed export is never reported as complete.
- An import mapping and target entity commit consistently. If cross-batch constraints require staging, the run records an explicit unresolved reference rather than inventing a target.
- Source entity replay with the same source/version/checksum cannot create another logical target. A changed source version follows the approved update/conflict policy.
- An imported post/thread/DM cannot become visible before its author/claim state, parent, community, privacy, body render, media state, and mapping are valid.
- A quarantined import object or media file cannot render/download. Releasing it requires an audited decision and parent/community access.
- A legacy redirect references an approved source host/path and one current target community/resource. It cannot redirect to arbitrary external input.
- A default-community backfill is idempotent. Rerunning it does not create duplicate memberships, role assignments, settings, notifications, package installations, or object records.
- Every tenant-owned insert records community ownership inside the canonical transaction. A request host or actor cannot cause a parent/child community mismatch.
- Foreign keys/checks/service validation prevent a board/thread/post/attachment/report/notification/DM from referencing objects in different communities unless an explicitly global relationship is approved.
- A community membership/role change and security version/invalidation/audit commit together. Stale cache/search/feed/client state cannot restore the prior grant.
- A community/domain creation becomes routable only after settings, owner, verified host state, and required defaults commit; a partial community remains disabled.
- Community suspension prevents new ordinary writes and configured delivery while preserving approved read/export/recovery access. It does not suspend the global user across other communities.
- Community deletion checks shared global users, package artifacts, media dedup references, credentials, and retention holds before physical removal.
- Quota usage and the business write either reconcile through the canonical ledger or fail according to the approved reservation policy; retry cannot double-charge usage.
- Client authorization grants and refresh credentials are bound to one user/client and approved community scope. Rotation revokes/replaces the prior credential atomically.
- A client cursor advances only after its local application of the corresponding canonical page/change succeeds or can be safely replayed; cursor state never proves access.
- A federation activity has one stable origin/activity identity. Duplicate/out-of-order delivery converges through version/tombstone rules and cannot create duplicate local posts or notifications.
- A remote object is displayable only after origin/signature/provenance, sanitization, community policy, content state, and local moderation checks succeed.
- Blocking a federation server/actor prevents new display/delivery according to policy and schedules local projection hide/purge without deleting unrelated local content.
- Locale, PWA, push, import, tenant, client, and federation jobs use the Phase 6 durable work contract; worker retry can duplicate execution but not logical effects.
- Disabling any Phase 7 subsystem changes delivery/presentation paths only and does not delete canonical local content, identity, permissions, audit, or required portability evidence.

## 9. Critical acceptance scenarios

| Area | Scenario and expected result |
|---|---|
| Phase ownership | An unaccepted Phase 6 search, media, permission, backup, or fallback requirement remains in the carryover ledger and is not marked complete because Phase 7 adds another client or community. |
| Locale resolution | A signed-in user’s saved locale wins over browser hints; an unsupported/malformed locale falls back to the community/installation source without a redirect loop or cache leak. |
| Placeholder parity | A locale catalogue missing, renaming, or changing a typed placeholder is rejected or falls back to the complete source message; user data remains escaped. |
| Missing critical translation | A missing MFA/recovery/permission/delete warning blocks that locale’s release or shows the verified source string; the action is never presented with a blank/misleading label. |
| Plural/date/number | Zero/one/many, timezones, daylight transitions, 12/24-hour, decimals, grouping, lists, and relative times match the selected locale fixtures. |
| RTL shell | Sidebar, inbox, thread, composer, menus, dialogs, tables, focus order, icons, and touch interactions work in RTL; code/URLs/IDs do not reverse incorrectly. |
| Bidi spoofing | User content containing mixed RTL/LTR control characters cannot visually replace trusted interface labels or obscure the real author/domain/security warning. |
| Text expansion | Pseudo-localized labels at the approved expansion factor remain readable without clipped actions, hidden errors, or inaccessible horizontal scrolling. |
| Locale cache isolation | The same public/private route requested in two locales/users/communities returns the correct catalogue and content without cross-locale or cross-community cached fragments. |
| Locale package tamper | One changed catalogue byte, wrong digest, expired/revoked signature, incompatible core range, or placeholder mismatch prevents activation. |
| Locale rollback | A broken locale update is pinned/rolled back immediately while the source locale remains available and user preferences remain intact. |
| Content language | UI locale changes without altering the declared topic language or canonical body; search uses the approved language path and still applies current read gates. |
| Canonical URL | Switching locale does not create a second topic identity, break old links, expose private content, or emit false `hreflang` equivalents. |
| PWA install | The approved browser can install the correct community-branded manifest; an unverified alias/other community does not receive the wrong name/icons/start URL. |
| Service-worker exclusion | Login, logout, MFA, recovery, settings security, admin tokens, import secrets, DMs, and private-board HTML are never served from a shared cache. |
| Offline public read | An explicitly saved public topic opens offline with clear community/title/last-updated/stale state and no claim that unread/lock/permission state is current. |
| Offline access change | A saved public topic becomes private/deleted; after the next validated sync it is removed/unavailable, and an online request is denied immediately regardless of cache. |
| Logout purge | Logging out/account switching clears or detaches user-scoped drafts/subscriptions according to policy and never exposes one account’s offline data to another. |
| Service-worker update | A new worker waits/activates according to policy, migrates or invalidates caches safely, and can be killed/unregistered without breaking online core routes. |
| Storage pressure | Browser storage eviction/quota denial preserves canonical data, explains lost/offline-unavailable snapshots, and does not report a draft as synced when it is not. |
| Deferred post | A draft queued offline reconnects after the thread is locked or user is suspended; the server rejects it, preserves the draft, and creates no post. |
| Deferred conflict | A queued edit based on an old version shows the current server body and local draft; neither version is silently overwritten. |
| Deferred duplicate | Multiple reconnects/background and foreground retries with one operation ID create at most one logical post/reply. |
| Push payload privacy | Captured push payload/log contains only approved identifiers/counts and no private title/body, email, token, signed URL, or role detail. |
| Push access revoke | A push is delivered just before board membership removal; opening it fetches canonically and reveals no protected content. |
| Push revoke | Revoking one device stops that endpoint without disabling other devices or in-app/email notifications; provider retries do not resurrect it. |
| Push permission denial | Denying browser permission leaves the notification system usable and does not repeatedly nag or weaken email/in-app settings. |
| Archive tamper | Changed manifest/entity/media bytes, path traversal, symlink, executable content, decompression bomb, or checksum mismatch rejects/quarantines the archive before canonical writes. |
| Canonical round trip | Exporting and importing the approved sample community into a clean installation reconciles required entities, IDs/mappings, links, permissions, Markdown, media, and counts within tolerance. |
| Import dry run | Preflight reports users/content/media/private data, collisions, unsupported features, required storage/time, source freeze, and proposed mappings without changing target data. |
| Import resume | Worker/process failure after a batch resumes from the durable checkpoint; completed source entities are not duplicated and unresolved references remain explicit. |
| Duplicate identity | Two source accounts share an email or collide with an existing username; no silent merge occurs and the operator/user receives an explicit claim/conflict path. |
| Password migration | Plaintext or unsupported hashes are rejected; approved compatible hashes are handled only by the audited module and rehashed after successful login. |
| Legacy markup | Script/raw HTML/unsafe BBCode/embedded active content from the source cannot execute; canonical Markdown and sanitized HTML reflect the documented transformation. |
| Import media | Missing, corrupt, oversized, executable, malicious, or private media is reported/quarantined; safe media binds to the correct community/parent/access class. |
| Import privacy | A source private board/DM/IP/moderation record is imported only under the approved policy and never appears public or to an unrelated operator/community. |
| Import rollback | Before the declared cutover boundary an injected failure returns to the clean target/staging state; after target activity begins the runbook uses restore/reconciliation rather than unsafe deletion. |
| Old URL redirect | Approved source URLs map to the correct target post/thread/community; unknown paths and attacker-supplied external targets do not redirect. |
| Default-community upgrade | A populated single-community installation migrates to one default community with identical public/private reads, writes, sessions, roles, notifications, URLs, packages, and media before the feature flag is enabled. |
| Community host routing | Two verified hosts resolve to their intended communities; unknown/disabled/mismatched hosts fail safely and cannot choose a community by changing a hidden form field. |
| Direct-ID isolation | A member of Community A submits IDs from Community B to every read/write endpoint; the service returns the safe denial and no title/count/timing/debug metadata. |
| Cache isolation | Public/member/private/moderator/settings responses for two communities/locales/users never share a cache entry or stale security grant. |
| Search isolation | A query in Community A returns no Community B title, snippet, total, highlight, suggestion, timing-derived count, or URL, including during fallback/reindex. |
| Media isolation | A copied object/CDN/signed/proxy URL from Community B yields no bytes/metadata/derivative to a Community A-only member or wrong host. |
| Queue isolation | An import/feed/push/package/federation job attributed to Community A cannot mutate Community B even if its payload contains B IDs. |
| Audit/log isolation | Community staff see only their approved audit/metrics; logs/traces contain community IDs but no private cross-community payloads. |
| Community owner scope | A Community A owner can manage A settings/members/boards but cannot change installation trust roots/providers/owners or Community B. |
| Installation owner action | A protected installation owner’s cross-community emergency action requires the approved capability/reauth/approval and writes an explicit audit record. |
| Community membership | A global account joins A but not B; authentication succeeds on B’s host but content/actions follow B’s guest/non-member policy until membership is granted. |
| Global block | A blocked user sharing another community cannot DM/follow/contact the blocker through that community according to the locked installation-wide policy. |
| Community-scoped DM | A conversation belongs to one community; neither participant can surface it through another community’s inbox/search/push/export. |
| Community suspension | Suspending A stops configured writes/deliveries for A without banning its users or interrupting Community B and preserves approved export/recovery access. |
| Community delete | Deleting A does not delete a user still in B, a package artifact used by B, or a shared object still referenced by B; A data follows retention/hold policy. |
| Custom-domain takeover | Expired/changed verification, host reassignment, open redirect, wildcard cookie, or CSRF-origin mismatch cannot expose sessions or route one community to another. |
| Session handoff | Signing in through the approved identity origin creates only the intended per-domain session; replay/wrong redirect/wrong community/expired handoff is rejected. |
| Noisy neighbor | A large import/media/federation/backfill in A reaches its quota/pool limit while security/recovery and ordinary traffic for B stay within budget. |
| Package scope | A package enabled/granted in A cannot read/write B storage/content/webhooks or run B jobs unless an explicit installation-global grant exists and is audited. |
| Community export/move | Exporting A and importing into a clean installation preserves A without B data, global secrets, unrelated users, or cross-community object references. |
| Mobile ADR | The selected mobile direction includes evidence for user need, capabilities, platform constraints, privacy, cost, owner, support, and rollback; “PWA because easier” alone does not pass. |
| Client auth | Forged redirect/state/PKCE, embedded-secret extraction, stolen/revoked refresh token, wrong app, wrong user, or wrong community fails without affecting web recovery. |
| Client parity | The same user/target action through web and client API yields equivalent authorization, account-state, rate-limit, block, moderation, and idempotency result. |
| Client offline conflict | A native/packaged client reconnects after edit/delete/membership change; it resyncs or presents a conflict and never restores stale protected content. |
| Client deep link | A push/app link opens the exact community/resource after current authorization; wrong-host or cross-community links do not leak previews. |
| Client version skew | An unsupported app version receives the documented safe upgrade/degraded response; core web recovery remains available and server data is not corrupted. |
| Lost device | Revoking a device/refresh credential stops API/push access within budget; local private data follows the documented platform purge/residual-risk policy. |
| Federation no-adopt | A no-adoption record names the user need, risks, alternatives, owner, and review condition; no hidden endpoints/jobs/data sharing are enabled. |
| Federation signature | Valid current origin/key/activity verifies; forged origin, replay, wrong digest, revoked/rotated key, stale request, or domain mismatch is rejected. |
| Federation SSRF | Discovery/media requests to localhost/private/metadata/mixed DNS/redirect-to-private/oversized/slow targets are blocked and audited. |
| Federation duplicate | The same remote activity delivered repeatedly/out of order creates one logical remote projection/reply and converges to the current edit/tombstone. |
| Federation privacy | Private-board/DM/hidden/deleted/security/moderation data is never serialized to federation delivery or discovery, even through references/media. |
| Federation moderation | Blocking a server/actor hides/prevents content and delivery according to policy without deleting unrelated local content or notifying the abuser unnecessarily. |
| Federation deletion | A valid remote delete/tombstone removes or marks the projection according to retention/moderation policy; an unverified delete cannot erase local content. |
| Federation outage | A remote server or federation worker is unavailable; local posts remain committed/usable, delivery retries/dead-letters, and inbound/outbound can be disabled independently. |
| Accessibility | Locale switch, RTL, offline banner, save/clear, deferred-send conflict, import tables, community switcher, domain forms, client, and federation controls are keyboard/screen-reader/zoom/reflow usable. |
| Recovery | A clean restore recovers global identity, all communities, locale/package/configuration, canonical content/media, import evidence, device/push revocation state as required, and federation policy; rebuildable caches/projections recover separately. |

## 10. Test and evidence policy

### 10.1 Required test layers

Every completed workstream must include all applicable layers:

1. **Unit tests:** locale canonicalization/resolution, message formatting and placeholder validation, bidi/Unicode helpers, cache classification, offline action transitions, archive/manifest/checksum parsing, source transforms, mapping/idempotency, community context, role/scope resolution, quota math, client token/sync policy, and federation signature/activity logic.
2. **Schema and migration tests:** clean install; every supported historical upgrade; default-community backfill; community ownership/constraints/indexes; locale/device/import/client/federation tables; mixed-version compatibility; idempotent rerun; rollback-window operation; backup/restore.
3. **Repository/service integration tests:** locale preference and catalogue activation, PWA data envelopes, deferred submissions, push subscriptions/delivery, export/import batches, community memberships/settings/domains, tenant-scoped content/social/safety/media operations, client API, and federation deliveries.
4. **Static isolation analysis:** inventory every SQL/repository method, route, cache key, search/feed query, object path, job/event, webhook/API, extension broker call, export, and audit query for required community context. Any approved global path is explicitly allowlisted and reviewed.
5. **Concurrency/idempotency tests:** locale activation, service-worker version transitions where automatable, deferred send, push retry/revoke, export snapshot, import resume/mapping, membership/role changes, domain claims, quota reservation, client token rotation, federation duplicate/out-of-order delivery, and community lifecycle.
6. **Locale and content corpus:** source locale, selected production locale(s), pseudo-expanded locale, pseudo-RTL/real RTL target, plural/date/number/timezone cases, Unicode normalization, mixed-direction text, long labels, missing/extra placeholders, email/push/CLI messages, and search-language fixtures.
7. **Browser/PWA verification:** installability, manifest/branding, service-worker install/update/rollback, offline shell/saved topics/drafts, storage pressure, account/community switch, push permission/subscription/click, responsive/mobile, keyboard, screen-reader-critical, zoom/reflow, reduced motion, and no-JS online regression.
8. **Import adapter corpus:** sanitized production-shaped fixtures for every supported source version; empty/small/large forums; duplicate identities; private boards/DM policy; markup/media variants; missing/deleted records; malformed archives; resume/delta/final cutover; redirect mapping; reconciliation.
9. **Tenant-isolation tests:** Guest, non-member, member, suspended member, community staff, community owner, installation owner, package/service principal, worker, API client, and remote actor across at least two communities and hosts, including direct IDs and stale state.
10. **Security/privacy tests:** XSS, bidi spoofing, translation injection, service-worker/cache poisoning, offline data residue, push payload/log review, archive traversal/decompression, import account takeover, custom-domain takeover, cross-community disclosure, client auth/token theft, federation replay/SSRF/spoofing, secret/log inspection, and data deletion/export.
11. **Client contract tests:** OpenAPI/schema, version negotiation, auth/PKCE, scopes, cursor/tombstone/full-resync, ETag/idempotency, web/client authorization parity, deep links, push, offline conflict, min-version, and revocation for every shipped non-web client.
12. **Federation conformance/adversarial tests:** protocol subset, discovery, signatures, key rotation, duplicate/out-of-order activities, edits/deletes/tombstones, domain policy, moderation, remote media, rate limits, outage, and independent disablement where adopted.
13. **Performance/load/fault tests:** locale overhead, cache variation, PWA asset/cache bytes, push fan-out, export/import throughput, default-community and multi-community query overhead, noisy-neighbor workloads, client API sync, federation queue/verification, worker/provider loss, and storage pressure.
14. **Operational exercises:** locale rollback, service-worker kill/purge, offline draft recovery, push key/provider rotation, import pause/resume/abort, domain move, community suspension/export/delete/restore, quota repair, client version/token revoke, federation block/shutdown, clean restore, and one-community fallback.
15. **Manual product review:** translation quality, terminology, RTL interaction, offline-state comprehension, import exception UX, community context clarity, domain/auth UX, mobile strategy outcome, remote-content provenance, accessibility, and operator runbook usability.

### 10.2 Evidence rules

Each atomic requirement’s evidence record must include:

- requirement ID and exact source/plan section;
- phase/gate, criticality, owner, reviewer, and current R0–R5 state;
- adopted/no-adopt strategy record where relevant;
- schema migration, configuration, package, service-worker, API, client-build, or protocol version;
- commit SHA, deployment/build ID, locale package digest, adapter digest, app version, and environment;
- test-run ID and exact automated/manual/browser/native/operational artifact;
- fixture/source version, communities/hosts/locales/platforms, data volume, concurrency, cache/offline/dependency state, and privacy class;
- numeric target and actual result;
- authorization/tenant/data-class matrix covered;
- defects, waivers, exceptions, import reconciliation differences, and owner/due date;
- rollout cohort, feature flag, kill switch, fallback/rollback evidence, and acceptance owner/date.

Additional evidence rules:

- A translated screenshot, installed PWA icon, successful import count, second host, native build, or federation handshake is not proof of authorization, privacy, recovery, or complete feature behavior.
- Locale evidence must cover full message-key/placeholder coverage and representative journeys; a percentage without critical-string classification is insufficient.
- PWA evidence must include cache contents/headers and excluded-route negatives, not only installability tooling.
- Import evidence must reconcile source totals, target totals, mappings, omissions, quarantines, and checksums. “Completed” job status alone is insufficient.
- Tenant evidence must include negative cross-community tests through every indirect channel, including caches, search, media, jobs, APIs, extensions, logs, exports, and fallback modes.
- Native/client evidence must identify the exact signed build and server/API versions. Simulator-only success does not replace supported-device evidence.
- Federation evidence must identify exact local/remote versions, protocol subset, keys, policy, and activity corpus. A public test-server exchange does not prove privacy or abuse handling.
- Manual translation review records reviewer and locale competence/source. Machine-generated text may assist drafting but cannot be the sole acceptance authority for critical product/security language.
- Evidence is current-build only. Prior-phase artifacts may prove an unchanged invariant only when the release candidate reruns or explicitly validates it.

### 10.3 Target evidence names

Expected evidence targets include, or should be represented by equivalents in the project’s naming conventions:

- `tests/Unit/I18n/LocaleResolverTest.php`
- `tests/Unit/I18n/MessageCatalogueTest.php`
- `tests/Unit/I18n/PlaceholderSchemaTest.php`
- `tests/Unit/I18n/LocaleFormatterTest.php`
- `tests/Unit/I18n/BidiIsolationTest.php`
- `tests/Integration/I18n/TranslatedRouteMatrixTest.php`
- `tests/Integration/I18n/EmailPushLocaleTest.php`
- `tests/Integration/I18n/LocalePackageLifecycleTest.php`
- `tests/Integration/Search/ContentLanguageSearchTest.php`
- `tests/Browser/I18n/PseudoLocale.spec.*`
- `tests/Browser/I18n/RtlJourney.spec.*`
- `tests/Unit/Pwa/CachePolicyTest.php`
- `tests/Integration/Pwa/ManifestControllerTest.php`
- `tests/Integration/Pwa/OfflineDocumentEnvelopeTest.php`
- `tests/Integration/Pwa/DeferredSubmissionTest.php`
- `tests/Browser/Pwa/InstallUpdateRollback.spec.*`
- `tests/Browser/Pwa/OfflineSavedTopic.spec.*`
- `tests/Browser/Pwa/OfflineDraftConflict.spec.*`
- `tests/Integration/Push/PushSubscriptionTest.php`
- `tests/Integration/Push/PushDeliveryPrivacyTest.php`
- `tests/Integration/Push/PushRevokeAccessTest.php`
- `tests/Unit/Portability/ArchiveManifestTest.php`
- `tests/Unit/Portability/ChecksumInventoryTest.php`
- `tests/Integration/Portability/CommunityRoundTripTest.php`
- `tests/Integration/Import/ImportPreflightTest.php`
- `tests/Integration/Import/ImportMappingIdempotencyTest.php`
- `tests/Integration/Import/ImportResumeTest.php`
- `tests/Integration/Import/ImportIdentityCollisionTest.php`
- `tests/Integration/Import/ImportMediaQuarantineTest.php`
- `tests/Integration/Import/LegacyRedirectTest.php`
- `tests/Security/ImportArchiveAdversarialTest.php`
- `tests/Integration/Tenancy/DefaultCommunityMigrationTest.php`
- `tests/Integration/Tenancy/CommunityContextTest.php`
- `tests/Integration/Tenancy/CommunityMembershipTest.php`
- `tests/Integration/Tenancy/CommunityOwnerScopeTest.php`
- `tests/Integration/Tenancy/CommunityDomainTest.php`
- `tests/Integration/Tenancy/CrossCommunityRouteIsolationTest.php`
- `tests/Integration/Tenancy/CrossCommunitySearchIsolationTest.php`
- `tests/Integration/Tenancy/CrossCommunityMediaIsolationTest.php`
- `tests/Integration/Tenancy/CrossCommunityJobIsolationTest.php`
- `tests/Integration/Tenancy/CrossCommunityExtensionIsolationTest.php`
- `tests/Integration/Tenancy/CommunityExportDeleteTest.php`
- `tests/Load/Tenancy/NoisyNeighborTest.php`
- `tests/Static/Tenancy/CommunityScopeInventoryTest.php`
- `tests/Contract/Client/MemberApiContractTest.php`
- `tests/Integration/Client/DeviceAuthorizationTest.php`
- `tests/Integration/Client/ClientWebPermissionParityTest.php`
- `tests/Integration/Client/ClientSyncConflictTest.php`
- `tests/Integration/Client/ClientVersionPolicyTest.php`
- `tests/Integration/Federation/FederationSignatureTest.php`
- `tests/Integration/Federation/FederationDeliveryIdempotencyTest.php`
- `tests/Integration/Federation/FederationModerationPolicyTest.php`
- `tests/Integration/Federation/FederationTombstoneTest.php`
- `tests/Security/Federation/FederationSsrfReplayTest.php`
- supported-browser/device screenshots/video and accessibility reports;
- locale coverage/placeholder/pseudo reports and signed package digests;
- service-worker cache inventory and update/kill-switch artifacts;
- export manifests, source/target reconciliation reports, import exception ledger, and redirect samples;
- community ownership matrix, schema/query inventory, cross-community negative report, and noisy-neighbor load report;
- signed client build/store/release metadata and crash/privacy evidence where applicable;
- federation allowlist-pilot report or signed no-adoption record;
- backup/restore, rollback, provider/key rotation, and final Phase 7 evidence index.

## 11. Progress, platform outcomes, observability, and operating requirements

### 11.1 Atomic progress model

Maintain one requirement ledger. Each atomic requirement has one state:

| State | Meaning |
|---|---|
| **R0 — Conflict/unowned** | Requirement, platform decision, data class, community ownership, privacy policy, or owner is contradictory, ambiguous, or missing |
| **R1 — Approved** | Phase/gate, owner, adoption decision, architecture/schema, policy, budgets, acceptance criteria, and rollback are approved |
| **R2 — Implemented** | Code, migration, catalogue/package, service worker, adapter, configuration, API/client, or federation component is merged/deployed behind disabled or limited rollout |
| **R3 — Automatically verified** | Required unit, integration, migration, locale, contract, concurrency, isolation, and adversarial tests pass |
| **R4 — Release verified** | Browser/no-JS/native, accessibility, privacy/security, performance, import reconciliation, operations, restore, and rollback evidence pass on the release candidate |
| **R5 — Accepted** | Enabled for the intended locales/communities/clients/cohort and formally accepted, or recorded as an approved no-adoption decision only for an explicitly conditional platform candidate |

Report separately:

- **Scope coverage** = committed requirements at R1 or higher ÷ committed requirements.
- **Implementation coverage** = adopted/committed requirements at R2 or higher ÷ adopted/committed requirements.
- **Verification coverage** = adopted/committed requirements at R4 or higher ÷ adopted/committed requirements.
- **Acceptance coverage** = R5 requirements ÷ committed requirements, distinguishing implemented acceptance from conditional no-adoption.
- **Locale coverage** = approved critical keys/routes/messages at R4/R5 ÷ approved critical keys/routes/messages, by locale and surface.
- **Offline safety coverage** = approved PWA/offline data classes with current cache/purge/conflict evidence ÷ approved data classes.
- **Portability coverage** = required entity/data classes reconciled in canonical round trip ÷ required entity/data classes.
- **Tenant-isolation coverage** = isolation matrix cells at R4/R5 ÷ required actor × route/data-channel × community-context cells.
- **Platform-decision coverage** = Phase 7 families with current accepted adoption/no-adoption record ÷ Phase 7 families.
- **Client/federation coverage** = approved platforms/protocol subsets with current-build compatibility, fallback, disable, and support evidence ÷ approved targets.
- **Fallback/rollback coverage** = activated Phase 7 subsystems with current-build fallback/rollback evidence ÷ activated subsystems.

Also report unresolved conflicts, unowned requirements, critical/high defects, approved exclusions, temporary waivers, stale translation reviews, unsupported source versions, import exceptions, isolation gaps, app-version exposure, federation-policy exceptions, evidence not produced on the current commit, and scope added/removed since the prior baseline.

A gate passes only when every critical requirement is R5; every other committed requirement is R4/R5 or has a signed scope change; conditional candidates have accepted adoption/no-adoption records; critical/high defects are zero; required migration/import/backup/restore/rollback exercises pass; and product-owner acceptance is recorded. Percent averages cannot override a failed critical translation, protected cache exclusion, import integrity, tenant-isolation, credential, or privacy invariant.

### 11.2 Platform strategy decision protocol

Every Phase 7 family has a versioned decision record containing:

- problem statement and affected member/operator cohorts;
- evidence window, demand/usage/support data, and alternatives considered;
- adopted outcome and non-goals;
- canonical source of truth and local/derived/offline/remote data classes;
- privacy, security, legal, residency, accessibility, and moderation implications;
- schema/interfaces/services/providers/clients/protocols introduced;
- migration, import/backfill, compatibility, and deprecation impact;
- numeric success/failure/cost/support budgets;
- rollout cohort, kill switch, fallback, rollback, export/exit, and recovery;
- product/engineering/security/privacy/operations owners and review date;
- acceptance evidence or no-adoption rationale and reassessment condition.

A decision becomes stale when its approved review date passes, the supported platform/source/protocol/provider changes materially, a critical incident invalidates assumptions, or user/operator evidence crosses its reassessment threshold.

### 11.3 Member, product, portability, and governance outcomes

Record cohort, window, denominator, baseline, success threshold, and stretch threshold for each adopted metric:

- **Locale adoption** = active users selecting a non-source locale ÷ active users eligible for that locale.
- **Translation completeness** = approved non-source message keys rendered from the selected catalogue ÷ approved keys, reported separately for critical and non-critical surfaces.
- **Fallback rate** = source-locale fallbacks in non-source sessions ÷ localized message renders, with missing critical fallback target zero after release.
- **Localization quality** = member-reported translation issues, correction time, reviewer backlog, text-overflow defects, RTL defects, and accessibility findings per locale.
- **Locale journey success** = registration, first post, settings, recovery, moderation, and community administration completion rates by locale without using locale as a proxy for personal identity.
- **PWA install adoption** = verified installed/standalone active clients ÷ eligible active web members, where measurement is privacy-safe and platform-supported.
- **Offline usefulness** = active users opening saved topics or drafts offline, successful offline opens, storage failures, and remove/clear usage.
- **Offline draft recovery** = drafts recovered after network/browser interruption ÷ recoverable interrupted drafts.
- **Deferred-send quality** = successful validated sends, conflicts, denials, retries, duplicates prevented, and user-discard rate; success targets cannot encourage blind auto-send.
- **Push quality** = eligible notifications with successful push, click-to-current-content, stale/inaccessible click rate, permission acceptance after education, unsubscribe/revoke, and duplicate delivery.
- **Export success** = completed valid exports within SLO ÷ requested exports, with checksum/retrieval/expiry failures.
- **Import success** = accepted source entities with deterministic mapped outcomes ÷ eligible source entities, separated into created/linked/transformed/skipped/quarantined/conflicted/failed.
- **Import reconciliation** = absolute and percentage variance by entity class, broken internal/old links, missing media, unresolved identities, and rerun duplicates (target zero duplicates).
- **Account claim quality** = imported unclaimed accounts successfully claimed through approved proof ÷ claim attempts, plus takeover/security incidents (target zero).
- **Migration operator burden** = preflight-to-cutover elapsed time, hands-on hours, exception resolution, rollback/recovery use, and support cases per import.
- **Multi-community activation** = installations enabling more than one community and communities reaching approved launch criteria; no target should pressure operators into multi-community mode.
- **Community member experience** = successful community switch, wrong-context actions, context-confusion reports, membership conversion, and per-community notification preference use.
- **Tenant-isolation quality** = cross-community disclosure/authority incidents (target zero), denied malicious probes, stale-context blocks, and isolation test coverage.
- **Community operating health** = setup time, domain verification, owner recovery, quota warnings, export/delete completion, per-community incidents, and noisy-neighbor interventions.
- **Resource fairness** = per-community queue wait, route latency, storage, search/feed lag, and critical-work starvation incidents.
- **Mobile outcome** = supported-platform active users, auth success, deep-link success, push success, offline recovery, upload/post success, app/update adoption, and support burden.
- **Client quality** = crash-free sessions where measured, API error/version mismatch, sync conflicts, token revocations, stale/private display incidents (target zero), accessibility defects, and store review/release lead time.
- **Federation value** where adopted = remote public topics/replies opened, followed, replied to, or blocked, balanced with delivery failures, moderation/report rate, domain blocks, abuse incidents, and operator burden.
- **Federation safety** = private-data serialization incidents, forged/replay acceptances, remote impersonation, unhandled deletes, and cross-community leakage (all target zero).
- **Ownership/portability outcome** = successful community moves/restores, provider exit, archive usability, and percentage of authoritative data classes with documented export/delete behavior.

No success target may reward weaker privacy, longer retention, compulsive notification/feed behavior, hidden fallback, silent translation/import errors, lax tenant isolation, forced native installation, or broad federation merely to increase activity.

### 11.4 Numeric technical budgets

At Milestone 0, record success and failure thresholds for:

- locale resolution/catalogue lookup p50/p95/p99, formatter overhead, rendered-page latency delta, catalogue bytes, cache entries, fallback/missing-key rate, and locale-package activation/rollback time;
- maximum text expansion, layout overflow count, RTL critical defect count, pseudo-locale route coverage, email/push snapshot coverage, and locale accessibility defects;
- content-language search query/index latency, analyzer/index growth, fallback rate, result parity, and authorization filtering;
- manifest/service-worker install/update/activation/rollback duration, asset bytes, cache bytes/entries, offline first render, saved-topic open latency, stale age, storage quota/eviction, and service-worker error rate;
- offline draft save/restore p50/p95/p99, maximum draft/attachment bytes, deferred-send queue age, reconnect validation latency, conflict rate, duplicate rate, and purge duration;
- push enqueue/provider/delivery p50/p95/p99, retry age, duplicate rate, invalid/expired endpoints, payload bytes, click/current-fetch latency, and revoke propagation;
- export snapshot/stream throughput, archive bytes/object count, checksum duration/error, memory, temporary storage, retention cleanup, and download/retry rate;
- import preflight/dry-run duration, entities/media per second, queue age, checkpoint interval, memory/CPU, temporary/quarantine storage, mapping/error/duplicate rate, final-delta duration, downtime, redirect lookup latency, and reconciliation variance;
- default-community migration duration, lock/online impact, rows per table, null/mismatch count, index-build time, and one-community latency/query delta;
- multi-community route p50/p95/p99 and query count/time, context resolution, cache/search/feed/object isolation, domain lookup, session handoff, membership/role propagation, and community switch latency;
- per-community queue wait, worker/resource quotas, storage/API/push/federation limits, noisy-neighbor impact on other communities, and fairness/error thresholds;
- custom-domain verification/activation, TLS readiness, redirect, session handoff, and disable propagation;
- client API p50/p95/p99, payload bytes, requests per sync, cursor lag, full-resync duration, idempotency/conflict rate, auth/token issuance/rotation/revoke, and minimum-version propagation;
- supported client cold/warm start, offline open, post/upload, deep-link, push-open, crash-free rate, storage bytes, battery/network budget where measurable, and release rollback time;
- federation discovery/signature verification, inbox/outbox enqueue/delivery p50/p95/p99, retries/dead letters, duplicate prevention, tombstone propagation, remote media bytes/time, per-domain limits, and disable/block propagation;
- backup/export/restore duration and size for installation and individual community move, object/checksum reconciliation, locale/package/config/key availability, and achieved RPO/RTO;
- total/per-community/per-active-user storage, provider, push, translation, import, client, federation, support, and operations cost.

Every measurement record must include route/job/client/protocol, topology/hardware class, software/catalogue/adapter/app/protocol versions, database/object fixture, communities/hosts/locales/platforms/source versions, privacy class, concurrency, online/offline/cache state, measurement window, p50/p95/p99, throughput, error rate, resource use, queue/index lag where relevant, and cost estimate.

### 11.5 Required telemetry

- Locale selection/resolution source, catalogue version, fallback/missing key, formatting error, surface, and locale aggregate without logging private translated content or using locale for sensitive profiling.
- Translation coverage, placeholder mismatch, package activation/rollback, reviewer/version, pseudo/RTL run status, and critical-string release gate.
- Page `lang`/`dir`, layout overflow/accessibility defects, content-language/search analyzer, and locale cache disposition.
- PWA manifest/service-worker version, install/update/activation/error, cache class/bytes/entries, offline open, saved/remove, stale age, storage failure, and kill-switch state without cached body logging.
- Draft/offline operation counts, save/restore, pending age, validation result, conflict/error class, duplicate prevented, discard, and purge without body logging.
- Push subscription/device status, permission outcome, provider/key version, enqueue/delivery/retry/failure, payload byte class, click/current-fetch result, revoke, and endpoint hash rather than plaintext endpoint.
- Export/import run, format/adapter/version, counts/bytes/checkpoints, mappings/outcomes, exceptions/quarantine, checksum/reconciliation, redirect hits/misses, worker/resource, and rollback/cutover state without raw credentials/source bodies.
- Community context on every request/job/event/trace, resolved host, membership/role decision, cache/search/feed/object namespace, denial reason aggregate, and cross-community guard activation.
- Community lifecycle/domain/settings/owner/membership/package/integration changes, quota/usage, queue fairness, storage, search/feed lag, incidents, export/delete, and recovery.
- Client/app ID/version/platform, API version, auth/token operation, request/sync latency, cursor/full-resync, conflict, deep-link/push result, crash/error aggregate, min-version denial, and revoke without device fingerprinting or private bodies.
- Federation server/actor/object/activity identity, policy, signature/key version, delivery lag/attempt/result, duplicate/tombstone, block/quarantine/report, remote media class, and privacy/security denial without unnecessary remote content logging.
- Backup/restore/export package manifest/checksums, locale/package/config/key state, community reconciliation, device/push/federation policy state, achieved RPO/RTO, and exceptions.
- Alerts tied to owner, actionable runbook, severity, SLO/error budget, community/platform scope, suppression policy, and last exercise.

### 11.6 Required runbooks

- Fall back one locale or all users to the source locale; pin/rollback a locale package; diagnose missing placeholders, broken plural rules, or RTL regression.
- Add/update a locale safely, run pseudo/RTL/critical-string gates, and retire a locale without deleting user preferences or content language.
- Disable/unregister the service worker, bump cache generation, purge one/all cache classes, recover a bad update, and verify online no-JS routes.
- Recover/export/discard offline drafts and deferred operations after storage corruption, account switch, duplicate operation, or schema change.
- Disable Push globally/by community/device, rotate application/provider keys, remove invalid endpoints, replay safe deliveries, and inspect a suspected private payload incident.
- Create/validate/download/expire an export; verify checksums; restore/reimport it into a clean environment; respond to a compromised archive.
- Preflight, stage, pause, resume, retry, reconcile, abort, and cut over an import; resolve identity/media/redirect exceptions; perform source freeze/final delta.
- Respond to a malicious/broken adapter, revoke its package, quarantine source archives, rotate credentials, and preserve audit/mappings.
- Return to one-community compatibility mode, diagnose missing/wrong community ownership, repair default-community backfill, and re-run isolation checks.
- Create/suspend/reactivate/export/delete a community; transfer community ownership; preserve the final installation owner; reconcile shared users/objects/packages.
- Verify/activate/move/disable a custom domain, rotate TLS/origin settings, recover a domain takeover risk, and invalidate sessions/handoffs safely.
- Diagnose cross-community cache/search/media/job/API/extension leakage, immediately disable the affected path/community, purge projections, and perform incident notification/repair.
- Enforce or repair community quotas/usage, pause a noisy import/package/federation workload, protect critical queues, and recalculate usage.
- Issue/rotate/revoke client credentials, disable one app version/platform, force full resync, recover an API compatibility incident, and direct users to canonical web recovery.
- Roll back/withdraw a client release, rotate signing/configuration, handle store/provider outage, and preserve server compatibility during the support window.
- Enable federation allowlist pilot, rotate server keys, block/silence a domain/actor, pause inbound/outbound, replay/dead-letter deliveries, purge/quarantine remote projections, and respond to spoofing/abuse.
- Restore the full installation and individual community portability package, reconcile global/community identity and media, restore locale/package/configuration/key state, reissue/validate device/push/federation controls, and prove RPO/RTO.
- Close Phase 7: audit strategy records, requirement ledger, exclusions, support ownership, provider/source/platform/protocol maintenance, and evidence retention.

## 12. Risks and controls

| Risk | Control |
|---|---|
| Phase 7 becomes a rewrite of the working product | Independent workstreams/flags; server-rendered web and canonical domain services remain; no feature requires a SPA or microservice rewrite |
| Earlier defects are renamed as platform work | Entry gate and carryover ledger; original phase ownership and acceptance evidence remain mandatory |
| “Full i18n” means only translating the homepage | Stable catalogue inventory; critical/non-critical route/message coverage; email/push/error/admin matrices; pseudo/RTL gates |
| Translation changes the meaning of security or consent | Critical-string reviewer policy, source fallback, placeholder schemas, no arbitrary HTML, exact-digest package review, release block |
| Translation package executes code or tracks users | Data-only locale package type; Phase 5 signatures/review; no scripts/templates/remote assets/URLs; CSP and package scans |
| Missing translation hides a control or warning | Safe source fallback, missing-key telemetry, critical-key gate, browser screenshots and accessibility tests |
| String concatenation breaks grammar or placeholders | Locale-aware message formatter; typed placeholders; extraction/lint; no user-facing concatenation outside reviewed formatter |
| RTL exposes spoofing or breaks navigation | Bidi isolation, `dir=auto`, logical layout, Unicode-control corpus, security-chrome separation, manual RTL review |
| Locale becomes a sensitive profiling dimension | Minimize telemetry, aggregate counts, access controls, no targeting/authority based on locale, retention limits |
| Localized URLs duplicate content or break links | Locale-independent resource IDs/routes; canonical URL policy; alternate metadata only for real equivalents |
| Service worker caches private/authenticated content | Deny-by-default cache catalogue; excluded-route tests; no broad cache-first; user/community/version keys; cache inventory evidence |
| Stale offline page is mistaken for current authority | Explicit offline/stale state, last refresh, read-only snapshot semantics, canonical recheck for every action |
| Logout leaves private data on a shared device | Public-only snapshot baseline; user-scoped draft partition; purge/detach policy; account-switch tests; clear-all controls |
| Browser storage is presented as encrypted/secure when it is not | Honest threat model and disclosure; private web-offline bodies disabled by default; native secure storage treated separately |
| Offline queue posts after permissions/content changed | Explicit pending state, server-side revalidation, base version, conflict UI, no blind auto-send, idempotency |
| Service-worker update strands or loops clients | Versioned worker, staged asset-only rollout, waiting/activation policy, kill switch, unregister/purge runbook, online fallback |
| Push leaks private content through payload/provider/logs | Minimal identifiers only, payload/log inspection, provider privacy review, canonical click fetch, short retention, key controls |
| Push permission prompts become coercive | Contextual education after user action, preference/device controls, denial respected, no repeated nagging |
| Push endpoint/key compromise | Protected endpoint/key storage, least-privilege provider credentials, rotation/revoke, per-device control, incident runbook |
| Export archive leaks full community data | Authorization/reauth/approval, encryption option, expiring protected download, audit, retention, checksum, access logs |
| Import archive exploits path/decompression/parser weaknesses | Non-exec staging, path normalization, symlink rejection, byte/file/depth limits, scan/quarantine, streaming parsers, adversarial corpus |
| Adapter bypasses validation or writes arbitrary SQL | Broker/domain-service-only contract, package trust/isolation, no direct DB/core schema, scoped credentials, audit |
| Import silently merges the wrong accounts | Stable source IDs; explicit claim/conflict; no email/name auto-merge; operator/user proof; takeover regression tests |
| Password import creates weaker auth | Reset/claim default; allowlisted algorithm module only; no plaintext; rehash on login; rate limits and audit |
| Imported HTML/markup executes or changes meaning | Canonical transformation version, allowlist sanitizer, source payload restricted, golden corpus and manual samples |
| Import duplicates content on retry | Mapping uniqueness, idempotency keys, checkpoints after durable target/mapping, duplicate/restart tests |
| Import is declared successful with missing data | Source/target count and checksum reconciliation by class; explicit omissions/quarantine; acceptance thresholds and exception ledger |
| Import rollback deletes new target activity | Staging/disabled target, declared point of no return, source freeze/final delta, backup restore/reconciliation after interaction |
| Old URL redirects become an open redirect or leak another community | Approved source hosts, normalized path mapping, local target IDs, no arbitrary destination, community access recheck |
| Default-community backfill changes existing behavior | Shadow parity, idempotent backfill, feature flag off, old/new resolver/query comparison, one-community release gate |
| A row has no or wrong community ownership | Non-null/constraint/index plan, ownership inventory/static analysis, repair command, direct-ID tests, cutover blocked on exceptions |
| Hostname is treated as authorization | Host resolves context only; canonical community ownership and membership/capability/read gates still apply |
| Cache/search/feed/media leaks across communities | Community in every namespace/filter/key; canonical access recheck; cross-community negative corpus; purge/disable runbooks |
| Jobs/events mutate another community | Immutable community in envelope and canonical target checks; worker capability scope; poison/adversarial payload tests |
| Installation owner casually browses private tenant content | Capability separation, purpose-bound emergency access, reauth/approval/audit, no general cross-community private-content browser |
| Community owner escalates to installation authority | Protected capability catalogue, scope checks, no self-grant, owner invariants, simulator and direct-request tests |
| Shared-schema mode is sold as hard tenant isolation | Explicit documentation and UI warning; suitability criteria; separate installations recommended for adversarial/legal isolation |
| Custom domains enable cookie theft or login CSRF | Per-origin sessions, no broad wildcard cookie, verified hosts, one-time handoff, strict redirect/origin/state/CSRF, TLS |
| Domain expiration or DNS change routes users incorrectly | Periodic revalidation, status/alerts, disable/hold, canonical fallback, owner confirmation, takeover runbook |
| Community deletion removes shared users/media/packages | Reference graph and retention checks; membership versus account deletion; object/package reference counts; staged purge |
| One community starves others | Per-community attribution/quotas, workload pools/priorities, backpressure, noisy-neighbor tests, critical queue reserve |
| Per-community quotas drift or double-charge | Canonical usage ledger/reconciliation, reservation/idempotency, repair jobs, warning before enforcement, audit |
| Package enabled in one community reads another | Community-scoped grants/storage/jobs/broker calls; explicit global package class; extension isolation corpus |
| Native direction is chosen for prestige, not need | Strategy record with demand/platform/support/cost evidence; PWA-only is valid; owner and maintenance budget required |
| Native API creates a second authorization implementation | Domain services/policies shared; web/client parity tests; no raw table endpoints; centralized account/community gates |
| Mobile app embeds secrets or collects credentials unsafely | System-browser/PKCE/passkeys, public client model, no embedded client secret/admin token, secure platform storage |
| Lost device retains access/private data | Individual token/push revoke, short access tokens/rotation, secure storage, purge policy, remote-expiry, user-visible device list |
| Client version skew corrupts data | Versioned API/schema, feature negotiation, compatibility window, min-version response, idempotency, canary and rollback |
| App-store/provider dependency blocks recovery | Canonical web fallback, signed build archive, staged releases, provider outage plan, no store-only account control |
| Client telemetry leaks private content/device identity | Structured/redacted events, no bodies/tokens, minimize device metadata, retention/access review, opt-out where applicable |
| Federation is enabled merely because multi-community exists | Separate adoption record, protocol/threat/moderation review, allowlist pilot, independent code/flags and acceptance |
| Remote actor impersonates a local member | Explicit remote origin/URI presentation, no account merge by name/email, signature/provenance checks, UI disclosure |
| Federation signatures/keys are replayed or spoofed | Digest/date/nonce/replay window, key origin/rotation/revocation, canonicalization tests, clock policy, audit |
| Federation discovery/media becomes SSRF | Egress broker, DNS/redirect revalidation, private/metadata denial, size/time/type limits, no user cookies, allowlist pilot |
| Remote content bypasses sanitization/moderation | Canonical safe renderer, local policy/holds/reports/domain blocks, no active remote embeds, provenance labels |
| Remote delete erases local content or evidence | Remote/local object distinction, verified tombstone rules, local moderation/retention history, no local-author deletion |
| Federation amplifies spam or queue load | Per-domain/actor quotas, backpressure, reputation-independent trust policy, quarantine, allow/silence/reject/block, pool isolation |
| Federation outage blocks local posts | Asynchronous outbox, local commit first, bounded retries/dead letters, independent inbound/outbound disable |
| Backup omits community/locale/import/client/federation authority | Expanded backup manifest, config/key/package inventory, clean restore, community reconciliation, RPO/RTO evidence |
| Phase 7 closes with conditional work silently omitted | Platform-decision coverage, adoption/no-adoption records, release checklist, roadmap/source reconciliation, product-owner closeout |

## 13. Staged release and rollback

### 13.1 Recommended enablement order

1. Deploy additive locale/preference, device/push, portability/import, and audit metadata with every Phase 7 feature flag disabled.
2. Enable translation extraction/lint, pseudo-locales, missing-key telemetry, and source catalogue without changing the user-selected locale.
3. Enable locale selection for staff on the source and selected production locale; complete public/member routes before security/admin surfaces are opened to ordinary users.
4. Install the service worker for versioned static assets only on an internal host; exercise update, kill, unregister, purge, and online fallback.
5. Enable the per-community manifest/install experience, then the offline shell, then explicit public-topic save/remove for staff and a small member cohort.
6. Enable local/server draft bridging and deferred submissions in review-required mode; observe conflicts/denials before any background optimization.
7. Enable Push for staff devices, then opt-in cohort, one browser/platform/provider combination at a time; inspect payloads and revoke behavior.
8. Enable canonical exports for staff/test communities; validate protected download, checksum, expiry, restore, and reimport before member-facing exports expand.
9. Run the first source adapter only against disposable/staging targets; progress through dry run, partial import, full import, final delta, redirects, and restore rehearsal before a production cutover.
10. Complete Gate A security/privacy/accessibility/performance/import-reconciliation/backup/rollback matrix and record Gate A acceptance.
11. Deploy `communities` and ownership columns; create/backfill the default community with multi-community routing disabled.
12. Run community-aware repositories/resolvers/keys in shadow; correct every parity/ownership exception before enforcement.
13. Switch the existing installation to community-aware single-community mode; monitor routes, sessions, search, media, jobs, packages, and exports through a full observation window.
14. Create a second internal community on a controlled subdomain, then enable memberships, settings, locale/branding, DMs, notifications, packages, quotas, and owner Console incrementally.
15. Enable custom-domain verification/session handoff for test domains before external operators; add one domain/community at a time.
16. Open multi-community creation to the intended operator cohort only after cross-community isolation and noisy-neighbor evidence passes.
17. Finalize the mobile ADR. For PWA-only, close remaining support gaps; for packaged/native, enable staff client/API registrations, then signed beta/cohort, then supported production release.
18. If federation is adopted, enable one controlled local pair in allowlist mode, then selected trusted domains; expand public object types separately and never auto-open federation to all domains.
19. Run the complete Phase 1–7 single/multi-community, locale, online/offline, client, federation-on/off, dependency-failure, backup, and rollback matrix.
20. Reconcile documentation/evidence, record every adoption/no-adoption decision, and record formal Phase 7/roadmap closeout.

### 13.2 Rollback rules

- Rollback is subsystem-specific. Do not roll back locale, PWA, import, tenancy, client, and federation simultaneously unless the incident review approves the combined action.
- A locale/catalogue incident falls back to the prior pinned digest or source locale; user locale preferences remain stored for later restoration.
- A broken translation must not require a database rollback. Catalogue/package/config version controls presentation independently.
- The service worker has a server-side kill response and version bump that stops new interception, guides unregister/purge, and leaves online server routes usable.
- Offline snapshots/caches are disposable. Rollback may invalidate them; offer draft export/recovery before destructive local-store migration where possible.
- Pending deferred actions remain inspectable/exportable and are never marked successful during rollback. The server idempotency receipt prevents duplicate resubmission.
- Push rollback disables new sends by provider/community/device while preserving canonical notifications. Subscription rows remain for diagnosis or are revoked according to policy.
- Export rollback disables new package generation/download while preserving already issued archive audit/retention obligations.
- An import adapter can be disabled/revoked independently. Running imports pause at durable checkpoints; no worker continues with a revoked adapter.
- Before public cutover, restore/drop the staging target according to the runbook. After target-side interaction, use backup restore/reconciliation and do not blindly delete the import batch.
- Import mappings, checksums, exceptions, and audit survive rollback long enough to diagnose/retry or prove what entered the target.
- Multi-community routing can return to one-community compatibility mode only while the default community remains complete and no active second-community traffic would be misrouted. Secondary communities must be suspended/served safely, not merged implicitly.
- Once multiple communities contain canonical activity, removing `community_id` is not an application rollback. Roll back behavior by flags/routes while retaining additive ownership data. The global→per-community uniqueness/PK conversions of §8.2 #11 are likewise non-additive one-way cutovers — not flag-reversible — once colliding natural keys exist across communities.
- A bad community-aware query/cache/search/feed/object path is disabled or forced through the canonical scoped fallback; no global unscoped fallback is permitted.
- A custom domain can be disabled and redirected to the verified canonical host without deleting the community or global account.
- Community suspension, export, and recovery controls remain available even when ordinary multi-community UI is rolled back.
- Quotas may switch to observe-only during a metering incident, but high-risk abuse and infrastructure safety limits retain conservative fallback.
- A client API/app rollback revokes or blocks the affected version/client registration while preserving web access and supported older versions inside the compatibility window.
- Device refresh credentials and Push subscriptions are independently revocable; app rollback does not require resetting the user’s password/provider/passkey.
- A federation rollback stops outbound and/or inbound work, rejects new discovery, preserves local content, and retains remote delivery/audit/tombstone evidence according to policy.
- Remote projections may be hidden/quarantined without deleting local replies or moderation evidence; purge follows the approved retention/export process.
- No rollback replays historical notification, email, push, reputation, badge, moderation, import, webhook, or federation effects merely because a projection/client/community path is re-enabled.
- No destructive schema drop, locale key deletion, source payload purge, import mapping cleanup, community purge, token table removal, or federation ledger deletion occurs until the observation/rollback/retention window closes and backup evidence passes.

## 14. Release checklist

### Gate A

- [ ] Phase 6 closeout, carryover ledger, and Phase 7 platform strategy records are approved.
- [ ] Locale set, source/fallback, reviewer ownership, RTL plan, PWA data classes, push policy, import sources, archive policy, and rollback boundaries are approved.
- [ ] Requirement ledger, schema diff, route/string/data-class inventory, fixtures, budgets, flags, and evidence map are complete.
- [ ] Locale canonicalization/resolution, formatter, message-key extraction, typed placeholders, source fallback, and cache variation pass.
- [ ] Critical and non-critical catalogue coverage is reported by route/surface; missing critical translations are zero for released locales.
- [ ] Source, selected production non-source, pseudo-expanded, and RTL route/email/push/accessibility matrices pass.
- [ ] Signed locale package install/update/pin/rollback, compatibility, placeholder parity, tamper/revoke, and registry-outage behavior pass.
- [ ] `lang`, `dir`, logical layout, bidi isolation, Unicode normalization, text expansion, code/URL handling, and keyboard behavior pass.
- [ ] Content-language metadata/search/discovery behavior passes without changing canonical identity or auto-translating content.
- [ ] PWA manifest/branding/scope/start/shortcuts/installability pass on the approved browser/platform matrix.
- [ ] Service-worker version/update/rollback/kill/unregister and cache inventory pass.
- [ ] Excluded authenticated/private/security/admin/token/import routes are never served from shared offline caches.
- [ ] Offline shell, explicit public-topic save/remove, stale/last-updated state, quota/eviction, and clear-all pass.
- [ ] Local/server drafts, deferred send, idempotency, reconnect validation, conflicts, denial, export/discard, account/community switch, and purge pass.
- [ ] Push permission UX, device subscription, preferences, minimal payload, click/current authorization, retry/dedupe, revoke/expiry, key/provider rotation, and outage pass.
- [ ] Canonical archive format, manifest, typed streams, checksums, media inventory, validation, protected delivery, retention, and documentation pass.
- [ ] Community export → clean-install import round trip reconciles required entities, references, permissions, Markdown, media, redirects, and checksums.
- [ ] Approved source adapter preflight/dry run/mapping/transform/claim/media/redirect/delta/cutover behavior passes on all supported source versions.
- [ ] Import traversal/decompression/parser, XSS/markup, identity takeover, duplicate/rerun, private-data, media quarantine, resume/failure, and rollback tests pass.
- [ ] Import source/target counts, outcomes, exceptions, quarantines, broken links, checksums, and operator sign-off are recorded.
- [ ] Locale/PWA/push/import telemetry, dashboards, alerts, privacy redaction, and runbooks pass.
- [ ] Gate A performance, storage, queue, provider, backup, clean restore, and rollback budgets pass.
- [ ] Full Phase 1–6 online/no-JS regression remains green with Gate A features enabled and disabled.
- [ ] No critical/high localization, accessibility, offline, cache, push, import, portability, privacy, or recovery defect remains.
- [ ] Gate A source docs, schema, archive/locale specifications, contributor/operator docs, changelog, and evidence index are updated.
- [ ] Gate A product-owner acceptance is recorded.

### Gate B and phase close

- [ ] Tenancy model, global identity/community membership/profile rules, DMs/blocks, owner boundaries, domains/sessions, package scope, quotas, deletion, and shared-schema isolation statement are approved.
- [ ] `communities`, domains, memberships, profiles/settings, community owners, lifecycle, quotas, and audit schema pass clean/populated migrations.
- [ ] Default-community backfill is idempotent and preserves existing URLs, sessions, roles, memberships, DMs, notifications, settings, packages, media, search, and audit.
- [ ] Every tenant-owned table/object/projection/job/token/integration has approved community ownership, constraints/indexes, and reconciliation evidence.
- [ ] Community-aware repository/service/permission paths match accepted one-community behavior before multi-community routing is enabled.
- [ ] Direct-ID cross-community route/write denial matrix passes for every actor/account state and sensitive endpoint.
- [ ] Cache, search, feed, unread, notification, email/push, media/CDN/signed URL, job/outbox/SSE, webhook/API, extension, audit/log, export, backup, and fallback isolation tests pass.
- [ ] Community owner versus installation owner, protected owner, reauth/approval, role scope, temporary grants, and permission simulator tests pass.
- [ ] Community-scoped DMs, profiles, follows, feeds, reports, moderation, reputation/badges, settings, packages, and data lifecycle pass.
- [ ] Community create/suspend/reactivate/export/delete/restore and shared-account/object/package reference checks pass.
- [ ] Domain verification, canonical host, TLS readiness, redirect, CSRF/origin, per-origin session, one-time handoff, disable/reassignment, and takeover tests pass.
- [ ] Per-community quota/usage, queue fairness, noisy-neighbor, critical-work reserve, cost attribution, and repair evidence pass.
- [ ] At least two communities and hosts operate through the intended production topology with zero cross-community disclosure/authority incident during the observation window.
- [ ] Community export/move into a clean supported installation preserves only the selected community and required global membership/identity references.
- [ ] Mobile strategy ADR is accepted with evidence, owner, supported platforms, feature floor, privacy/offline/auth model, maintenance cost, and rollback.
- [ ] PWA-only acceptance or the selected packaged/native client delivers the complete approved mobile outcome; an indefinite prototype does not pass.
- [ ] Any member/client API passes schema/version, auth/PKCE, device token, scope, rate-limit, idempotency, sync/cursor/tombstone/full-resync, and web-permission-parity tests.
- [ ] Supported client deep links, push, drafts/offline conflicts, uploads, locale/RTL, accessibility, account/community switch, revoke/lost-device, min-version, privacy/crash telemetry, and release rollback pass.
- [ ] Build/signing/store/release provenance and support/deprecation/kill-switch procedures pass for every shipped client.
- [ ] Federation has an accepted adopt/pilot/no-adopt record with protocol, public-data boundary, privacy/moderation/trust, owner, budgets, and review condition.
- [ ] If adopted, federation discovery/signature/key rotation/replay/SSRF, delivery/dedupe/retry, public serialization, remote provenance, moderation/domain policy, edits/deletes/tombstones, media, outage, and disablement pass.
- [ ] If adopted, private boards, DMs, credentials, security/moderation notes, protected media, cross-community data, and remote authority federation negatives pass with zero leakage.
- [ ] All Gate B locale, accessibility, SEO, privacy, performance, queue, storage, backup/restore, provider/source/platform/protocol exit, and rollback budgets pass.
- [ ] Full Phase 1–7 regression passes in single-community and multi-community modes, source/non-source/RTL locales, online/offline/PWA states, supported clients, and federation enabled/disabled where applicable.
- [ ] Clean install and every supported historical upgrade, default-community backfill, second-community launch, domain move, community export/import, client disable, federation shutdown, and clean restore rehearsals pass.
- [ ] No critical/high security, privacy, accessibility, authorization, data-integrity, localization, offline, import, tenant-isolation, client, federation, recovery, or release-operability defect remains.
- [ ] Every conditional omission has a signed adoption/no-adoption or out-of-roadmap record rather than a silent “later.”
- [ ] Final `README.md`, `DESIGN.md`, `DECISIONS.md`, `SCHEMA.md`, surface docs, locale/archive/API/federation specs, deployment manifests, runbooks, changelog, and evidence index match the deployed product.
- [ ] Final platform/product/isolation/portability/client/federation metrics, support ownership, review dates, and cost baselines are recorded.
- [ ] Phase 7 and seven-phase roadmap product-owner closeout are recorded.

## 15. Post-Phase 7 operating model and roadmap closeout

Phase 7 closes the planned feature-set roadmap described by the supporting documents. Ongoing operation should carry forward:

- locale catalogue/package coverage, reviewer ownership, fallback/missing-key rate, translation defects, RTL/accessibility evidence, and locale retirement/addition process;
- service-worker versions, cache classes/bytes, offline saves/drafts/conflicts, storage failures, push subscription/delivery/revoke, and browser/platform support;
- export/import format and adapter compatibility, source-version fixtures, run/mapping/exception history, redirect health, claim security, and migration support workload;
- community membership/owner/domain/settings/package/quota state, tenant-isolation coverage, cross-community incidents, noisy-neighbor data, resource/cost attribution, and community lifecycle/recovery evidence;
- mobile strategy/support matrix, client/API versions, credentials/revocation, store/release provenance, crash/privacy/accessibility, deprecation, and canonical web fallback;
- federation adoption/no-adoption state, protocol/server/key compatibility, domain policy, delivery/moderation/abuse data, remote-object retention, and independent disablement;
- canonical database/object/configuration backups, portability archives, locale/package/key inventory, clean restore, community move, provider/source/platform/protocol exit, and achieved RPO/RTO;
- the R0–R5 requirement ledger, evidence retention, accepted exclusions, strategy review dates, support owners, and total operating cost.

Further product work—such as real-time chat, voice/video, end-to-end encrypted messaging, hard-isolated SaaS tenancy, billing, active-active global writes, federated private spaces, or machine translation/generative systems—requires a new product and architecture roadmap. It is not an implicit Phase 8 and is not necessary to declare the seven-phase feature set complete.

## 16. Source references

- `PHASE_6_PLAN.md` — Phase 6 closeout, canonical/projection and fallback constraints, service interfaces, capacity evidence, and explicit handoff to PWA/offline, native direction, imports, multi-community, internationalization, and optional federation.
- `PHASE_5_PLAN.md` — signed package/locale trust, passkeys/providers, custom roles/governance, service principals, extension isolation, and explicit Phase 7 platform-expansion decisions.
- `PHASE_4_PLAN.md` — advanced content/community semantics, group-DM privacy, feeds, media, community memory, and explicit deferral of PWA/imports/tenancy/i18n to Phase 7.
- `PHASE_3_PLAN.md` — canonical composer/drafts/media, accessibility/SEO baseline, PWA/import/multi-community/i18n deferral, and product/capacity evidence handoff.
- `PHASE_2_PLAN.md` — notifications/email, search, DMs, private boards, profiles/community state, polling, worker behavior, and explicit Phase 7-class deferrals.
- `PHASE_1_PLAN.md` — universal server-rendered/no-JS core, setup/auth/session/CSRF, posting, admin/moderation, health, and release-evidence baseline that every expanded client/community must preserve.
- `README.md` *(orientation pointer only — not a source of ground truth)* — product thesis and self-hosted PHP/MySQL/server-rendered stack overview. _(The replaceable interface seams are authoritative in DECISIONS §2, and the roadmap + completion-evidence policy in DESIGN §13 — not README; consistent with PHASE_6_PLAN §16.)_
- `DESIGN.md` §§2–3, 6, 8–14 — single-community v1, responsive-web/PWA-later, no v1 federation/import, durable-topic product model, permissions, architecture, accessibility, metrics, and later PWA/mobile/import/multi-community/i18n direction.
- `DECISIONS.md` §§1–8 — authoritative single-install v1 decision, global stack/identity/storage/search/realtime choices, future multi-tenancy, and settled later-work boundaries.
- `SCHEMA.md` §§1–9 — the consolidated Phase 1–3 tables, timezone fields, reconciliation rules (§7), and foreshadowed schema (§8). **Note:** Phase 7 platform schema (per-tenant `community_id` ownership, locale/translation packs, Web Push subscriptions, import source-ID mappings, community domains, and any federation tables) is **not yet in SCHEMA.md**; it is defined in this plan and should be folded back on acceptance.
- `ADMIN.md` §§1–12 — capabilities, protected authority, registration/membership, moderation/private-content limits, branding/localized communications, integrations/plugins/API, Console operations, audit, retention, and later multi-community administration.
- `USER.md` §§2–8 — global account resolution, sessions/security/recovery, locale-adjacent timezone/preferences, board organization, notification/privacy/block behavior, profiles, avatars/signatures, account export/delete, and cross-device expectations.
- `COMPOSER.md` §§3–17 — canonical Markdown, shared composer, drafts/submission resilience, media, accessibility/i18n/RTL, mobile behavior, progressive enhancement, and stable content portability.
- `COMMUNITY.md` §§1–14 — follows/feeds/reputation/badges/leaderboards/community-memory behavior, privacy/block constraints, and humane-design rules that community/client/federation expansion must preserve.
