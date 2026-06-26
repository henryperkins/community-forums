# RetroBoards Phase 3 Plan — Polish, Trust & Scale

**Owner:** Henry  
**Plan type:** Delivery baseline, release train, and formal phase closeout  
**Plan status:** **Draft — execution is gated by formal Phase 2 closeout**  
**Prepared:** 2026-06-25  
**Source hierarchy:** `DECISIONS.md` is authoritative where documents conflict; `DESIGN.md` is the product source of truth; `SCHEMA.md` owns final database shape; `ADMIN.md`, `USER.md`, `COMPOSER.md`, and `COMMUNITY.md` own their respective surfaces. P0/P1/P2 in the source documents are priority tiers, not delivery-phase numbers.

## 1. Phase objective

Turn the Phase 2 Community Inbox into a polished, branded, accessible, abuse-resilient product that remains fast and operable on the chosen single-VPS stack.

A member should be able to:

1. Personalize appearance, reading, and composing behavior without weakening server-side permission or privacy rules.
2. Write in one consistent rich Markdown composer across new threads, replies, DMs, and edits, with reliable drafts, preview, keyboard support, and safe media handling.
3. Recover from network failures, reloads, duplicate submissions, and editor fallbacks without losing work or creating duplicate content.
4. Understand the product quickly through a skippable, replayable onboarding tour that never blocks the core experience.
5. Use a site that is accessible by keyboard and assistive technology, performs predictably on desktop and mobile, and exposes clean crawlable public pages.
6. Protect their account with optional TOTP two-factor authentication and recovery codes when Gate B ships.
7. Appeal an eligible moderation action through a narrow, auditable process when Gate B ships.

An operator should be able to:

1. Replace user-facing RetroBoards placeholder branding with the community’s own name, logos, favicon, colors, and supported theme defaults.
2. Configure layered rate limits, new-user restrictions, content filters, approval holds, and spam scoring with every automated action reviewable and audited.
3. Diagnose performance, cache behavior, uploads, spam controls, and extension failures using documented metrics and runbooks.
4. Extend the installation through a first-party/vetted hook system, signed outbound webhooks, and narrowly scoped admin API tokens without opening an unreviewed public plugin ecosystem.
5. Roll back the editor, media, anti-abuse, theme, cache, or extension layer independently without taking the core forum offline.

Phase 3 is a **release train**, not a big-bang release. Gate A delivers the committed product-polish and hardening slice. Gate B delivers the extended trust, account, moderation, theming, and platform slice. Phase 3 is not formally closed until both gates are accepted or every omitted Gate B item has an approved scope-change record.

## 2. Entry gate — Phase 2 must be closed first

This plan may be refined before Phase 2 closes, but Phase 3 implementation must not start until all of the following are true:

- Phase 2 Gate A and Gate B have recorded product-owner acceptance, or each incomplete item has an explicit deferral with owner, rationale, risk, and destination phase.
- The Phase 2 evidence index exists and covers migrations, regression tests, permission matrices, browser evidence, queue/cron operation, rollback, and production enablement.
- No unresolved critical or high-severity Phase 2 security, privacy, authorization, delivery, or data-integrity defect remains.
- The deployed database has been reconciled against `SCHEMA.md`, including any migrations that were planned but not actually applied.
- Baselines have been captured for route latency, query counts, slow queries, PHP memory, worker/queue lag, notification/search/DM usage, report resolution, polling load, and disk growth.
- Phase 2 feature flags, backup/restore procedures, counter-repair commands, worker controls, and rollback runbooks have been exercised.
- All unfinished Phase 2 obligations are placed in a **carryover ledger**. A carryover may block Phase 3, be delivered before Gate A, or be explicitly deferred; it must not be silently renamed as Phase 3 work.

**Ownership boundary:** notification preferences, notification digests, presence, privacy/block controls, OAuth, sessions/devices, core community features, and their Phase 2 evidence remain Phase 2 obligations. Phase 3 may improve their usability or performance, but it does not reset their acceptance criteria.

## 3. Definition of done

Phase 3 is accepted only when all of the following are true:

- Every accepted Phase 1 and Phase 2 journey remains functional, permission-safe, server-rendered, and usable without JavaScript where the underlying action supports a no-JS path.
- Every Phase 3 schema change is additive, documented in `SCHEMA.md`, tested on both clean and populated upgraded installations, and reversible at the application/feature-flag level.
- User preferences have a versioned, validated schema with safe defaults; malformed or unknown values cannot break rendering or bypass server-side rules.
- Appearance preferences support the approved System/Light/Dark modes, density, font size, and reduced motion; reading and composing preferences are enforced consistently across sessions and devices.
- The shared composer behaves consistently in New Thread, Reply, DM, and Edit contexts and stores only canonical Markdown plus allowlist-sanitized cached HTML.
- The selected editor passes the approved Markdown round-trip corpus. Loading and serializing a no-op edit cannot silently alter valid canonical content.
- The composer has a working plain-`textarea` fallback, keyboard-accessible controls, mobile behavior, validation, idempotent submission, optimistic rollback where applicable, and error states that preserve the draft.
- Spoilers, code blocks, limited headings, emoji, preview, toolbar overflow, and character/attachment limits work through the same server render and sanitization path.
- Local drafts are isolated by user and context, restore after reload, survive failed sends, appear in the Drafts view, and clear only after confirmed success or explicit discard.
- If server-side draft sync ships in Gate B, it is quota-limited, conflict-aware, privacy-safe, and compatible with existing local drafts.
- Image uploads are type-sniffed, dimension/size-limited, processed outside an executable path, access-gated for private boards and DMs, attributable to an owner, and cleaned up when orphaned.
- Non-image attachments, if accepted for Gate B, use a restricted allowlist, safe download headers, quarantine/scanning policy, access checks, and documented retention behavior.
- Rate limiting covers authentication, registration, posting, reactions/follows where needed, DMs, mentions, uploads, password reset, TOTP verification, API calls, and webhook administration using trusted-proxy-aware client identification.
- New-user throttles, link/word filters, duplicate/flood detection, optional approval holds, and spam-provider scoring are centrally enforced, reviewable, and audited; enforcement defaults to flag/hold rather than irreversible silent action.
- Automated moderation writes immutable audit records with a system actor, rule/version, reason, inputs needed for review, and outcome.
- Eligible users can file at most one active appeal per moderation action; only authorized staff can resolve it; uphold/overturn outcomes are linked to the original action and audited.
- TOTP enrollment, verification, recovery codes, disable/reset, and account-recovery flows prevent replay and brute force, never log secrets, and do not strand the user without a recovery path.
- Branding removes hard-coded user-facing RetroBoards placeholders from pages, email metadata, manifests, OpenGraph defaults, and uploaded/default assets while preserving the internal product/repository name where appropriate.
- Theme changes cannot weaken semantic structure, responsive behavior, focus visibility, or contrast requirements; a safe-mode/reset path bypasses invalid custom styling.
- Performance budgets are approved from the Phase 2 baseline before optimization begins, and the release candidate meets them without permission leakage or stale-cache correctness defects.
- Caches are explicitly classified as public, role-scoped, user-scoped, or non-cacheable; no authenticated, private-board, DM, moderation, or preference-specific data is served across authorization boundaries.
- The accessibility audit and manual keyboard/assistive-technology matrix are complete for all changed member and admin surfaces; no unresolved critical or high-impact accessibility defect remains.
- Public pages have correct titles, descriptions, canonical URLs, OpenGraph metadata, sitemap inclusion rules, redirect behavior, and indexing controls; private, hidden, deleted, moderation, settings, DM, and tokenized URLs are excluded from discovery.
- The product tour can be skipped, completed, and replayed; completion persists for signed-in users; the forum remains fully understandable and usable when the tour script fails or JavaScript is disabled.
- Internal extensions are first-party/vetted only, capability-declared, disable-on-error, version-compatible, and unable to take down the core request path.
- Webhook delivery is HMAC-signed, retryable, observable, SSRF/egress-controlled, and idempotent enough for consumers to deduplicate safely.
- Admin API tokens are shown once, stored only as hashes, scoped, expirable, revocable, rate-limited, and fully audited.
- The full automated suite, migration matrix, security tests, load tests, no-JS smoke, browser evidence, accessibility evidence, SEO checks, worker/cron checks, and rollback rehearsals pass.
- No unresolved critical or high-severity security, privacy, accessibility, data-integrity, extension, upload, or release-operability defect remains.

## 4. Scope and release gates

### Gate A — Phase 3 core polish release

Gate A is the minimum Phase 3 release required by the top-level roadmap:

- Phase 2 carryover reconciliation, production baselines, numeric performance budgets, and a Phase 3 traceability/evidence map.
- Completed settings shell for **appearance, reading, and composing** preferences. Phase 2 notification/privacy/session controls are linked into the same information architecture without being rebuilt.
- Shared rich Markdown composer across new thread, reply, DM, and edit, including:
  - approved editor spike and architecture decision;
  - bold, italic, strikethrough, inline code, blockquotes, lists, links, code blocks, limited headings, spoilers, emoji, preview, active toolbar state, responsive overflow, character counters, and keyboard shortcuts;
  - progressive enhancement from a server-rendered `<textarea>`;
  - idempotent submit, duplicate protection, optimistic send/rollback where appropriate, and edit-mode parity;
  - local autosave, restore, Drafts view, explicit discard, and signed-out-to-signed-in recovery.
- Safe **image** upload, clipboard paste, and drag/drop with progress, thumbnail, alt text, reorder/remove, board/new-user limits, local-disk storage abstraction, authorization-aware delivery, moderation, and orphan cleanup.
- Central rate-limit service plus new-user throttles, word/link rules, duplicate/flood detection, optional first-post/board approval holds, and a spam-scoring provider seam.
- **IP-retention purge job**: a scheduled worker that purges/anonymises login and post IPs (`sessions.ip`/`posts.ip`, captured in Phase 2) after 90 days, Admin-only and audited (ADMIN §5.5 — closing the Phase-2 IP-capture seam).
- Admin controls for registration mode and the Gate A anti-abuse settings that are actually enforced by the application.
- Operator branding: site name, light/dark logo, favicon, primary/accent colors, supported light/dark default, preview, reset, audit, cache busting, and retirement of user-facing placeholder branding.
- Performance and caching pass for the single-VPS architecture: query/index review, N+1 removal, bounded pagination, rendered-Markdown cache review, safe fragment/data caching, static asset compression/cache headers, OPcache/deployment configuration, and production-like load evidence.
- Full accessibility pass for the public shell, settings, composer, upload flow, moderation holds, and branding controls.
- SEO pass for public boards, threads, profiles, redirects, canonical/meta/OpenGraph, sitemap/robots behavior, and non-indexable/private surfaces.
- New-user product tour targeting final Gate A DOM nodes, with skip, replay, persistence, responsive behavior, and graceful failure.
- Operational dashboards/logs, runbooks, staged rollout, release notes, rollback evidence, and formal Gate A acceptance.

### Gate B — Phase 3 extended trust and platform closeout

These items are committed to the broader Phase 3 window by the surface-specific roadmaps. They may ship after Gate A, but Phase 3 requires acceptance or an approved re-scope for each:

- TOTP two-factor authentication, hashed one-time recovery codes, reauthentication for security-sensitive actions, security notifications, and an audited admin reset path.
- Lightweight moderation appeals: one appeal per eligible action, staff queue, uphold/overturn, restoration where safe, notifications, and linked audit history.
- Category-scoped moderator assignment and capability resolution without expanding the fixed role set.
- Retro 2002 skin as an optional site/user choice, plus advanced token controls and guarded custom CSS with safe mode, validation, audit, and instant rollback.
- Internal hook/plugin system GA for **first-party or vetted extensions only**, including manifest/capability declarations, lifecycle, migrations, settings panels, scheduled jobs, disable-on-error, compatibility checks, and health visibility.
- First-party spam-scoring and outbound-webhook integrations built on the hook system; durable webhook delivery attempts, HMAC signatures, retries, replay controls, and delivery history.
- Minimal versioned admin API with scoped, hashed, expiring tokens and a deliberately narrow supported endpoint set.
- Advanced account polish: reversible deactivation, **user avatar uploads** (via the P3-04 media pipeline, stored as `users.avatar_path` with `avatar_source='upload'`) and privacy-safe optional Gravatar support, bookmark folders for saved threads, and a limited custom-profile-field model with sanitization and visibility controls.
- Export/delete only when it was formally deferred from Phase 2; it remains governed by the already approved retention, anonymization, grace-period, and audit policy rather than receiving a new Phase 3 interpretation.
- Server-side draft sync with local/remote conflict handling, quotas, pruning, and cross-device recovery.
- Restricted non-image file attachments and selected P2 formatting extensions only after the upload security, scanning/quarantine, storage, and accessibility gates are approved.
- Full Gate B hardening, documentation, evidence index, and formal Phase 3 closeout.

### Conditional carryovers — not automatically Phase 3 scope

The following may enter Phase 3 only through the carryover ledger or a signed scope change:

- Any unaccepted Phase 2 notification, digest, presence, OAuth, sessions/devices, search, DM, moderation, profile, follow, badge, or mobile-polish requirement.
- _(Reconciled 2026-06-26 — no longer carryover candidates: `ADMIN.md` v0.8 settled the phasing. The notification matrix and digests are **Phase 2**; outbound webhooks and spam scoring are **net-new Phase 3** scope owned by P3-13. Phase 2 ships neither webhooks nor spam integration, so there is nothing to carry over or guard against double-implementing.)_
- Data export/delete that Phase 2 accepted; Phase 3 may polish the UX but must not reopen the policy or duplicate the pipeline.

### Explicitly deferred beyond Phase 3

The following must not delay Phase 3 acceptance unless formally pulled in:

- Public plugin marketplace, arbitrary uploaded PHP extensions, third-party sandbox/review ecosystem, and theme-package marketplace.
- Granular custom roles or user-defined capabilities; fixed Guest/User/Moderator/Admin roles remain.
- Passkeys/WebAuthn, additional OAuth providers, SMS authentication, or mandatory 2FA for ordinary members.
- WebSockets, real-time chat, or replacing short-polling solely for novelty; SSE may be reconsidered only from measured need.
- Meilisearch/Elastic, Redis, CDN/object storage, or a distributed architecture without a documented capacity trigger and migration decision.
- Group DMs, voice/video, ephemeral messaging, or unrestricted DM administration.
- Link unfurls/embeds, arbitrary remote media fetches, GIF search, polls, custom emoji, slash-command menus, and server-side execution of pasted content.
- Public file types outside the approved attachment allowlist.
- PWA/offline mode, native mobile applications, imports from other forums, multi-community/multi-tenant support, and full internationalization.
- Community memory features such as generated summaries, related-topic generation, wiki posts, split/merge, or automated canonical-answer generation.
- Time-windowed leaderboards, custom badges, tag/board follows, fan-out social feeds, and other community P2 items unless separately approved from measured demand.

## 5. Reconciled and locked implementation decisions

The following decisions are treated as fixed for Phase 3:

1. **Phase ownership stays intact.** Presence and notification controls are Phase 2. Phase 3 improves or scales accepted behavior; it does not conceal incomplete Phase 2 work.
2. **“Settings and preferences” in Phase 3 means the remaining appearance, reading, composing, security, and account-polish work.** Notification, privacy, block, and session/device behavior must already satisfy Phase 2.
3. **“Attachments” in Gate A means images.** Non-image files are Gate B and require a stronger scanning/quarantine and download policy.
4. **Markdown remains canonical.** Editor-specific JSON, HTML, or proprietary document formats are never the source of truth.
5. **Milkdown is the first spike, not a predetermined acceptance result.** Tiptap/ProseMirror or a CodeMirror/live-Markdown fallback may win only if the documented round-trip, accessibility, mobile, mention, upload, and progressive-enhancement criteria are met.
6. **One composer, four contexts.** New Thread, Reply, DM, and Edit share one behavior and one server validation/render pipeline; wrapper differences do not justify divergent editors.
7. **Server-rendered and progressively enhanced remains non-negotiable.** Disabling the rich editor must reveal a usable Markdown `<textarea>`, not remove the ability to post.
8. **Media stays on local VPS storage behind an interface.** Files live outside the executable/public path; private-board and DM media is served through the same current-access gate as its parent content. Object storage/CDN remains a later swap.
9. **Short-polling remains the realtime mechanism.** Phase 3 performance work optimizes it before considering SSE; WebSockets are out of scope.
10. **“Scale” means measured optimization of the selected single-VPS PHP/MySQL architecture first.** New infrastructure is introduced only after evidence shows the interface-backed current implementation is the bottleneck.
11. **Anti-abuse is centralized and reviewable.** Rules may allow, flag, hold, or block; destructive or punitive automation is not the default. Every system action is auditable.
12. **Reputation remains cosmetic.** Neither reputation, badges, account age, nor title grants moderation or administrative authority.
13. **Plugins are trusted code.** Phase 3 supports first-party/vetted extensions only; the UI never accepts arbitrary executable code or `eval`-style content.
14. **Custom CSS never enables JavaScript.** It is isolated behind an advanced control, versioned/audited, and bypassable by a server-side safe-mode switch.
15. **API and webhook secrets are write-only from the operator’s perspective after creation.** Tokens are hashed; webhook secrets are protected and rotatable; logs never include them.
16. **All cache keys include the authorization and variation dimensions that affect output.** When safe variation cannot be proven, the response is not cached.
17. **Numeric performance budgets are locked at Milestone 0 from the Phase 2 baseline.** Phase 3 cannot close on subjective statements such as “feels faster.”
18. **Schema design precedes implementation.** Any new table or column absent from `SCHEMA.md` must be reconciled and approved there before the corresponding production migration is merged.

## 6. Delivery workstreams

| ID | Workstream | Deliverables | Acceptance evidence | Dependency | Gate |
|---|---|---|---|---|---|
| P3-00 | Entry gate, scope, and baselines | Phase 2 closeout review; carryover ledger; route/permission inventory; data-volume snapshot; performance, accessibility, SEO, disk, and abuse baselines; numeric budgets; flags and rollback map | Signed Phase 2 acceptance/deferrals; baseline report; traceability matrix; schema diff; rollback rehearsal | Phase 2 | A |
| P3-01 | Preferences and settings IA | Unified `/settings` navigation; appearance, reading, composing preferences; versioned preference schema/defaults; server/client enforcement; reset/export of preferences | Preference validation/default/upgrade tests; cross-device behavior; no-JS forms; browser evidence; malformed-JSON recovery | P3-00 | A |
| P3-02 | Composer engine and shared core | Milkdown-first spike/ADR; shared component; canonical Markdown transforms; toolbar/shortcuts; code/headings/spoilers/emoji/preview; edit parity; textarea fallback | Round-trip corpus; sanitization/XSS tests; surface-parity matrix; keyboard/mobile/no-JS evidence; editor kill-switch smoke | P3-00 | A |
| P3-03 | Drafts and submission resilience | Context-isolated local drafts; Drafts view; restore/discard; signed-out recovery; idempotency keys; optimistic rollback; retry/error preservation; optional server sync | Reload/crash/network/double-submit tests; multi-user/browser isolation; conflict tests for Gate B; quota/prune tests | P3-02 | A/B |
| P3-04 | Media and attachment safety | Image upload/paste/drop; processing/thumbnails/alt text; ownership/binding; private delivery; moderation/removal; quotas; orphan cleanup; optional restricted files | MIME spoof/polyglot/oversize/dimension tests; private-access tests; cleanup and disk-pressure tests; mobile upload/browser evidence | P3-00, P3-02, P3-05 | A for images; B for files |
| P3-05 | Rate limits and anti-abuse automation | Shared limiter; new-user policies; word/link filters; duplicate/flood checks; approval holds; spam provider contract; observe/flag/hold/block modes; audited system actions; 90-day IP-retention purge/anonymise job (`sessions.ip`/`posts.ip`, ADMIN §5.5) | Boundary/concurrency/proxy tests; false-positive workflow; hold/release tests; rule-version audit assertions; IP-purge cutoff/idempotency/audit tests; load impact | P3-00 | A |
| P3-06 | Appeals and moderator scope | One-appeal-per-action workflow; staff queue; uphold/overturn; linked notifications/audit; category-scoped moderator resolution | Authorization/scope matrix; duplicate appeal tests; restoration tests; immutable history; browser/no-JS evidence | Accepted Phase 2 moderation | B |
| P3-07 | Branding and themes | Logo/favicon/colors; light/dark defaults; placeholder retirement; live preview/reset; token validation; retro skin; guarded custom CSS and safe mode | Brand asset safety tests; contrast/token checks; cache-busting; literal-placeholder scan; safe-mode/rollback browser proof | P3-00 | A core; B retro/CSS |
| P3-08 | Performance, queries, and caching | Route/query budgets; slow-query/index pass; N+1 removal; bounded queries; public/user cache classification; invalidation; asset/OPcache tuning; production-like load suite | Before/after report; EXPLAIN evidence; cache isolation/invalidation tests; p95/p99 and resource budgets; load/soak results | P3-00 and stable feature paths | A |
| P3-09 | Accessibility and interaction quality | Semantic landmarks; focus/order; menus/dialogs/comboboxes; live regions; reduced motion; contrast; zoom/reflow; touch targets; admin/member keyboard flows | Automated scans plus manual keyboard and approved assistive-technology matrix; defect log; desktop/mobile screenshots/video where useful | P3-01–P3-07 | A/B |
| P3-10 | SEO and public discovery | Page titles/descriptions; canonical/OpenGraph; sitemap/robots; pagination/redirect rules; public profile/thread metadata; exclusion of private/tokenized surfaces | Crawler fixtures; metadata snapshots; redirect tests; private/noindex leakage tests; sitemap validation | P3-00, accepted read gates | A |
| P3-11 | Onboarding and learnability | Final-DOM tour targets; first-sign-in trigger; skip/replay; server completion flag; responsive/focus behavior; graceful failure | Fresh-user/browser tests; skip/replay/cross-device tests; no-JS usability proof; moderated learnability baseline | P3-01, P3-02, P3-07 | A |
| P3-12 | Account security and polish | TOTP/recovery; sensitive-action reauth; deactivate/reactivate; avatar upload (`users.avatar_path`, via the P3-04 media pipeline) + optional Gravatar; bookmark folders; limited custom profile fields; conditional export/delete carryover | Enrollment/recovery/lockout tests; secret-handling review; account-state matrix; privacy/moderation tests; migration fixtures | Accepted Phase 2 account/privacy work | B |
| P3-13 | Internal extensions, webhooks, and API | Hook/event/filter registry; manifest/capabilities; lifecycle/migrations; disable-on-error; first-party spam/webhook modules; delivery ledger/retries; scoped API tokens/endpoints | Fault-injection tests; plugin compatibility/migration tests; signature/retry/SSRF tests; token scope/revoke/rate-limit audit tests | P3-00, P3-05 | B |
| P3-14 | Operations, release, and closeout | Health/metrics; runbooks; backup/restore; evidence index; staged rollout; security/privacy review; release notes; final docs/schema reconciliation | Full suite; smoke matrix; load and rollback rehearsals; no critical/high defects; product-owner acceptance | All applicable workstreams | A and B |

## 7. Recommended execution sequence

### Milestone 0 — Close Phase 2, lock scope, and establish budgets

- Review Phase 2 Gate A and Gate B evidence against the Phase 2 plan rather than relying on roadmap labels.
- Create the carryover ledger and decide whether each item blocks Phase 3, precedes Gate A, or moves later.
- Capture a schema snapshot and reconcile it with `SCHEMA.md`.
- Inventory public, authenticated, private-board, DM, moderation, settings, worker, webhook, and admin API routes.
- Capture production-like baseline data volume and generate representative fixtures for large boards, long threads, many subscriptions, private boards, DMs, reports, and attachments.
- Lock numeric budgets for latency, query count, memory, queue lag, polling cost, upload processing, disk growth, error rate, and page asset weight.
- Map every Phase 3 definition-of-done statement to an automated test, browser/manual proof, operational check, or approved policy record.
- Define feature flags and emergency disables for rich editor, drafts sync, uploads, each anti-spam action mode, branding/custom CSS, caching by route, tour, 2FA, appeals, webhooks, plugins, and API.

**Exit gate:** Phase 2 is formally closed or explicitly re-scoped; Phase 3 scope, budgets, schema gaps, evidence targets, and rollback controls are approved.

### Milestone 1 — Architecture spikes and schema reconciliation

- Run the editor spike against the real post corpus and the round-trip/accessibility/mobile criteria; record the winning engine in an ADR.
- Specify the attachment lifecycle: temporary upload, ownership, binding, visibility, processing, moderation, deletion, orphan cleanup, and private delivery.
- Specify the preference JSON schema/versioning and default-merging behavior.
- Specify the rate-limit store and trusted-proxy model without adding an unnecessary distributed dependency.
- Specify cache interfaces, variation rules, invalidation ownership, and safe bypass behavior.
- Reconcile missing Gate B schema for drafts, TOTP/recovery, appeals, automation rules, webhook deliveries, bookmark folders, custom profile fields, and expanded plugin metadata.
- Add clean-install and populated-upgrade migration tests before feature code depends on the new shape.

**Exit gate:** The editor, upload, limiter, cache, and new-schema designs pass review; no production feature depends on an undocumented table or column.

### Milestone 2 — Preferences, branding foundation, and composer core

- Build the unified settings navigation and validated appearance/reading/composing preferences.
- Add brand asset/color controls, preview, reset, audit, and safe fallback defaults.
- Implement the shared composer and server-rendered textarea fallback.
- Add canonical formatting transforms, toolbar active state, keyboard behavior, preview, edit reuse, validation, and submission idempotency.
- Implement local drafts, Drafts view, restore/discard, failed-send preservation, and the editor kill switch.
- Keep notification/privacy/presence links routed to their accepted Phase 2 pages.

**Exit gate:** A user can change preferences and use the same lossless Markdown composer in every context; disabling JavaScript or the rich-editor flag leaves posting functional.

### Milestone 3 — Images, anti-abuse, and approval controls

- Add image upload/paste/drop, processing, thumbnails, alt text, limits, and safe storage.
- Bind uploads to posts/DMs transactionally or through a durable finalize step; schedule orphan cleanup.
- Serve private-board and DM images only after rechecking current access.
- Implement the shared limiter and new-user throttle across all abuse-prone routes.
- Add word/link rules, duplicate/flood checks, approval holds, and an observe-only spam provider integration.
- Add admin settings for only the controls that are wired into enforcement, with audit history and dry-run/preview where feasible.
- Progress spam automation from observe-only to flag/hold after reviewing false positives; hard-block only narrow, proven rules.

**Exit gate:** Images cannot escape their parent content’s authorization boundary; abuse controls are measurable, reviewable, and do not silently destroy legitimate content.

### Milestone 4 — Performance, accessibility, SEO, and onboarding

- Profile the complete Gate A paths under production-like data and concurrency.
- Remove N+1 queries, add/adjust indexes, bound pagination, and verify query plans.
- Introduce caching route-by-route only after variation and invalidation tests exist.
- Tune rendered-Markdown caching, static assets, compression, cache headers, and PHP OPcache/deploy settings.
- Complete the accessibility audit and remediate shared components first so fixes propagate to all surfaces.
- Complete metadata, canonical, sitemap/robots, redirect, and indexability rules.
- Add the product tour only after the DOM is stable; verify skip/replay/focus/mobile behavior and no-JS learnability.
- Run the Gate A security, load, browser, no-JS, accessibility, SEO, backup, and rollback matrix.

**Exit gate:** Gate A meets approved performance budgets, accessibility and SEO criteria, and has a complete evidence index and rollback rehearsal.

### Milestone 5 — Gate A staged release and acceptance

- Deploy additive migrations and dark code with flags disabled.
- Enable settings and branding for staff, then a member cohort, then all users.
- Enable the rich editor while retaining the textarea fallback and kill switch.
- Enable image upload on staff/test boards, then selected public/private boards, then all eligible contexts.
- Run anti-spam in observe-only mode before enabling flag/hold; monitor false positives and queue volume.
- Enable cache paths one at a time; verify authenticated/private bypasses after each change.
- Enable SEO metadata/sitemap and onboarding after final UI verification.
- Record release metrics and product-owner acceptance.

**Exit gate:** Gate A is accepted in production with no critical/high defects and no unresolved authorization, cache, upload, or anti-abuse incident.

### Milestone 6 — Extended trust, account, moderation, and platform work

- Implement TOTP and recovery codes with security-sensitive reauthentication and support runbooks.
- Add appeals and category-scoped moderator assignments through the central capability resolver.
- Add retro skin, advanced token controls, and custom CSS safe mode.
- Add server-side draft sync and selected file/format extensions only after their Gate B security designs pass.
- Implement the internal hook system, first-party spam/webhook modules, webhook delivery ledger, and narrow admin API.
- Add deactivation, optional privacy-safe Gravatar, bookmark folders, and limited custom profile fields.
- Deliver export/delete only if it remains in the approved carryover ledger.

**Exit gate:** Each Gate B surface passes its security, privacy, scope, migration, observability, and rollback criteria independently.

### Milestone 7 — Phase 3 release candidate and formal closeout

- Run the complete Phase 1–3 regression suite and route-permission matrix.
- Rehearse clean install, Phase 1→latest upgrade where supported, Phase 2→Phase 3 upgrade, feature disablement, safe-mode theme recovery, plugin failure isolation, worker pause/replay, token revocation, and backup restore.
- Reconcile `README.md`, `DESIGN.md`, `SCHEMA.md`, surface-specific docs, route inventory, runbooks, changelog, and completion evidence with the deployed product.
- Record all accepted Gate A/Gate B items and every explicit deferral.
- Capture post-release baselines and capacity triggers for later infrastructure decisions.

**Exit gate:** The Phase 3 evidence index and product-owner closeout are recorded; no hidden Phase 3 obligation remains under an ambiguous “later” label.

## 8. Data and migration plan

### 8.1 Pre-existing tables (Phases 1–2) to verify before reuse

Phase 3 must verify whether these tables/columns are actually deployed and populated before assuming availability:

- `user_preferences`
- `settings`
- `sessions`
- `moderation_log.before_json` / `after_json`
- `email_deliveries` if reused for generalized delivery patterns
- _(`plugins`, `webhooks`, and `api_tokens` are **created in Phase 3** — P3-13 — not inherited: Phase 2 ships none of them (it explicitly defers `plugins`/`api_tokens` and ships no webhooks). They are listed under §8.2 #6/#7 and §8.3 group 7 as create-in-final-form, not verify-and-extend. Reconciled 2026-06-26.)_
- profile/privacy fields and any Phase 2 account columns. _(Note: `attachments`, `users.avatar_path`, and `users.onboarded_at` are **created in Phase 3** — P3-04 / P3-12 / P3-11 — not inherited; see §8.2. `users.avatar_source` is inherited from Phase 2 — OAuth avatar-import.)_

A table’s presence in `SCHEMA.md` is not evidence that its migration or feature shipped.

### 8.2 Schema gaps that must be resolved at Milestone 1

The current consolidated schema does not fully specify several Phase 3 domains. Their final DDL must be approved before implementation:

1. **Attachment lifecycle.** The current table lacks explicit temporary/finalized state, storage key/hash, visibility/binding lifecycle, deletion/moderation timestamps, processing state, and orphan-cleanup support. The same media pipeline governs **avatar/signature image files**: a user's current avatar is referenced by `users.avatar_path` (`avatar_source='upload'`, or `'gravatar'` resolved from the email hash), distinct from per-post `attachments`. _(Added 2026-06-26: gives user avatar uploads + `users.avatar_path` a Phase-3 owner per DECISIONS §5 #4 / PHASE_1_MIGRATIONS §4; Phase 4 P4-13 only extends this with crop/variants — see PHASE_4 §8.2 #18.)_
2. **Server drafts.** `drafts` is foreshadowed but not committed. Define uniqueness by user/context, title/body, revision/version, device/source timestamps, conflict handling, quotas, and expiry/pruning.
3. **TOTP and recovery codes.** Define encrypted-at-rest TOTP secret storage, enable/verified timestamps, last-used step or replay defense, hashed one-time recovery codes, reset history, and key-rotation strategy.
4. **Appeals.** Define the original moderation-action reference, appellant, single-active-appeal uniqueness, status, assigned/resolved staff member, decision, timestamps, and audit linkage.
5. **Automation rules and holds.** Decide whether simple rules remain typed `settings` values or require an `automation_rules` table with version, action mode, scope, priority, enabled state, and audit history. Existing `threads.is_pending` and `posts.is_pending` can hold content but do not describe the triggering rule.
6. **Webhooks + delivery ledger (created in Phase 3 — P3-13).** `webhooks` is first introduced this phase (Phase 2 ships none). A bare `last_status` field is insufficient for retries, idempotency, replay, per-attempt errors, and dead-letter operation, so introduce `webhooks` together with a durable delivery/attempt model from the start.
7. **Plugins (created in Phase 3 — P3-13).** `plugins` is first introduced this phase; specify it in final form — beyond slug/name/version/config: manifest digest, capabilities, core-version compatibility, lifecycle/migration state, error/disabled reason, and last health result. (`SCHEMA.md` carries a base `plugins`/`api_tokens` row, but per the rule above its presence is not evidence it shipped — Phase 3 creates them.)
8. **Bookmark folders.** Define folders and folder membership without creating a second “saved” primitive; membership must reference existing starred threads.
9. **Custom profile fields.** Define admin field definitions, supported value types, ordering, visibility, validation, and per-user values. Keep the first version deliberately narrow.
10. **Rate-limit storage.** If implemented in MySQL, define bounded-window keys, expiry, cleanup, and indexes; if implemented behind another shared store, document failure behavior and ensure web processes share limits.
11. **Brand/media assets.** Decide whether logo/favicon metadata reuses `attachments` with a purpose/owner type or uses a separate controlled asset model.

### 8.3 Migration groups

Recommended additive migration order:

1. Preference schema/version metadata and any missing user settings columns.
2. Attachment lifecycle and brand-asset support.
3. Anti-abuse rule/hold/audit support and rate-limit storage where needed.
4. Draft sync tables and indexes.
5. TOTP/recovery and appeals.
6. Category-moderator scope changes.
7. Create `plugins` (with full metadata), `webhooks` (with a durable delivery ledger), and `api_tokens` (scoped, hashed) — all first introduced in Phase 3.
8. Bookmark folders and custom-profile-field tables.

Each group must be independently deployable with its corresponding feature flags off.

### 8.4 Upgrade and backfill rules

- Existing users without `user_preferences` rows inherit defaults lazily; avoid a large eager insert unless measurement shows it is safer.
- Preference JSON is versioned. Reads merge defaults, ignore unknown keys, and migrate recognized old values without deleting forward-compatible data.
- Existing Markdown is not bulk-rewritten by the editor migration. New renderer capabilities use a renderer-version/cache-invalidation strategy; raw canonical text remains unchanged.
- Existing cached `body_html` is rebuilt lazily or by a bounded job when the sanitizer/renderer version changes.
- Existing local drafts remain local until the user opts into or first uses server sync. On divergence, present an explicit local/remote choice; never overwrite silently.
- Existing media, if any, is inventoried and classified before attachment lifecycle constraints become mandatory.
- Branding falls back to safe built-in assets/tokens when a setting or uploaded asset is missing, invalid, or removed.
- TOTP is opt-in; there is no mass enrollment or secret backfill.
- Appeals apply prospectively unless product/legal explicitly approves a historical-action window.
- Plugin/webhook migrations begin disabled; failed extension migrations disable that extension without rolling back core schema.
- No Phase 1 or Phase 2 table/column is dropped in the same release that introduces a replacement.

### 8.5 Transactional and consistency invariants

- A logical submit creates at most one thread/post/DM message for a user/context/idempotency key.
- A media row cannot become visible before its parent content and authorization context are committed.
- A post/DM cannot reference an upload owned by another user or outside its allowed context.
- Deleting/moderating a parent content item updates media visibility and schedules physical cleanup according to retention policy; physical deletion does not precede rollback/appeal requirements.
- Cache invalidation is dispatched only after a successful transaction; failed writes cannot invalidate into an impossible state.
- A rule-triggered hold and its system audit entry are committed together.
- One moderation action has at most one active appeal per eligible user; resolution and any restoration are committed/audited consistently.
- Enabling TOTP occurs only after a valid challenge; recovery codes are generated once, shown once, stored hashed, and consumed atomically.
- API token creation stores only a hash after the one-time plaintext display; revocation is immediate on every endpoint.
- Webhook delivery uses a durable event/delivery identity so retries do not create unbounded duplicate logical events.
- Plugin enablement occurs only after compatibility, capability consent, and migrations succeed; a failed enable leaves the plugin disabled.

## 9. Critical acceptance scenarios

| Area | Scenario and expected result |
|---|---|
| Phase ownership | A Phase 2 item lacking evidence appears in the carryover ledger and is not marked complete merely because Phase 3 work begins. |
| Preferences | A user with no preference row receives safe defaults; a later preference change persists across devices; malformed/unknown JSON keys do not break the page or bypass privacy/permission rules. |
| Theme variation | Site default, user override, OS/system mode, and signed-out default resolve deterministically without a flash that makes controls unreadable. |
| Composer parity | The same Markdown fixture edited in New Thread, Reply, DM, and Edit produces equivalent canonical Markdown and sanitized output. |
| Round trip | A valid corpus item loaded and immediately saved is byte-equivalent or differs only by an explicitly approved normalization recorded in fixtures. |
| No JavaScript | With JavaScript disabled or the editor flag off, a user can create, reply, DM, and edit through the textarea and server render path. |
| Draft survival | Reload, navigation, network failure, locked-thread response, and auth interruption preserve the correct draft; confirmed success clears only that context’s draft. |
| Double submit | Double-click, retry, browser resend, and optimistic-client retry result in one logical post/message. |
| Editor failure | A rich-editor initialization exception falls back to the textarea and does not hide existing text or the submit control. |
| Sanitization | Pasted HTML, scripts, event handlers, malformed Markdown, dangerous URLs, and crafted spoiler/code content cannot execute or escape the allowlist. |
| Image spoofing | A file renamed `.jpg` with a disallowed or malformed payload is rejected by content sniffing; oversized dimensions/decompression bombs are rejected before resource exhaustion. |
| Private media | A copied media URL from a private board or DM returns no content to a guest, non-member, removed member, blocked user where applicable, or user whose access was revoked after upload. |
| Orphan cleanup | Abandoned temporary uploads expire and are removed; attached or retained-for-appeal media is not deleted prematurely. |
| Disk pressure | When configured storage thresholds are reached, new uploads fail safely with a clear message while reading/posting text remains available. |
| Alt text | An image can receive/edit alt text; the rendered image exposes it; decorative-empty behavior follows the approved UI rule. |
| Rate limits | Limits apply consistently across parallel PHP workers, respect trusted proxy configuration, reset as documented, and return a usable retry interval without revealing sensitive state. |
| New-user policy | A new account that exceeds post/link/DM/upload limits is throttled or held; an established account follows the standard policy; no reputation value grants an exemption. |
| Spam observe mode | A spam scorer can log/flag a high score without changing visibility; switching to hold affects only new matching content and is auditable. |
| False positive | Authorized staff can review and release a held post; the author receives the correct state; release does not duplicate notifications or counters. |
| Automated audit | A word/link/flood/spam rule records rule ID/version, action mode, target, reason, and system actor in the immutable audit trail. |
| Appeal scope | Only the actioned user can appeal an eligible action, only once; unrelated users and out-of-scope moderators cannot view or resolve it. |
| Appeal overturn | An overturn restores only the content/account state that is safe and intended, records before/after state, and does not erase the original action history. |
| Category moderator | A category-scoped moderator can act on boards in that category and is denied after a board moves out or the assignment is revoked. |
| TOTP enrollment | A secret is not enabled until a valid code succeeds; enrollment, disable, reset, and recovery require the approved reauthentication path. |
| TOTP replay/lockout | Reused or rapidly guessed codes are rejected/rate-limited; a one-time recovery code works once; remaining recovery paths prevent accidental permanent lockout. |
| Branding | Changing name/logo/colors updates pages and email/metadata defaults, invalidates the right caches, and leaves no user-facing hard-coded placeholder except approved legal/internal references. |
| Custom CSS safe mode | Invalid CSS can be bypassed through a server-side safe-mode/reset path available to an Admin without relying on the broken styled UI. |
| Cache isolation | A cached public, member, private-board, moderator, settings, notification, or DM response is never served to a requester with a different access/variation context. |
| Cache invalidation | Editing/deleting/moving content, changing membership, changing branding, changing preferences, or revoking moderation scope invalidates or bypasses affected caches immediately enough to meet the approved policy. |
| Performance | Production-like large-board/thread/search/sidebar/polling/upload cases meet the locked latency, query, memory, and error budgets with no unbounded query growth. |
| Accessibility | All new controls are operable by keyboard; focus returns correctly from menus/dialogs/pickers; live upload/send errors are announced; zoom/reflow and reduced-motion behavior remain usable. |
| SEO visibility | Public canonical pages appear in sitemap/metadata fixtures; hidden/private/deleted/DM/settings/admin/token pages never appear and return the correct indexing directives/access response. |
| Redirects | Board slug and username redirects preserve the read gate before redirecting and terminate at one canonical URL without chains/loops. |
| Product tour | A new user can complete, skip, or replay the tour on desktop/mobile; a missing target skips safely; no-JS users are not blocked or left in an incomplete state. |
| Plugin failure | A throwing listener, failed scheduled job, or incompatible plugin is disabled/logged without breaking the core request or worker loop. |
| Webhook security | Payload signatures validate; retries preserve event identity; redirects/private-network destinations follow the approved egress policy; secrets and payload-sensitive fields are not logged. |
| API token scope | A token can call only its scopes, cannot recover its plaintext, stops immediately after revoke/expiry, is rate-limited, and leaves an audit record. |
| Deactivation | A user can deactivate/reactivate according to policy; profile attribution and login behavior match the spec without corrupting posts, follows, DMs, or moderation history. |
| Backup/rollback | A release can disable the rich editor, uploads, spam actions, caches, custom CSS, plugins, webhooks, and API independently; backup restore preserves canonical Markdown, media references, audit history, and security state. |

## 10. Test and evidence policy

### 10.1 Required test layers

- **Unit tests:** preference schema/defaults; Markdown transforms and round trips; sanitizer extensions; rate-limit calculations; filter/rule matching; TOTP/recovery primitives; HMAC signing; token scope checks; cache key/variation logic.
- **Repository/service integration tests:** migrations; draft revisions; attachment ownership/finalization; hold/release; appeals; category scope; plugin lifecycle; webhook delivery/retry; API token lifecycle; branding settings; custom profile/bookmark models.
- **Application/HTTP tests:** every route’s auth, CSRF, account-state, role/scope, private-content, rate-limit, validation, idempotency, cache, and no-JS behavior.
- **Worker/cron tests:** image processing/cleanup where asynchronous; draft pruning; webhook retry/dead-letter; plugin scheduled jobs; cache warm/rebuild jobs; retention jobs (incl. the 90-day IP purge/anonymise of `sessions.ip`/`posts.ip`).
- **Browser tests:** settings, editor, drafts, uploads, spoilers/preview, mobile keyboard/toolbars, approval states, branding/theme, tour, 2FA, appeals, custom CSS safe mode, and admin extension screens.
- **Security tests:** XSS/Markdown edge cases; upload MIME/polyglot/path traversal/decompression; private media access; CSRF; session/re-auth; TOTP brute force/replay; SSRF/redirect/DNS handling; API token leakage/scope; plugin fault isolation.
- **Performance tests:** production-like fixtures, concurrent reads/writes/polls/uploads, slow-query capture, query-count assertions, cache hit/miss behavior, worker lag, disk growth, and soak testing.
- **Accessibility evidence:** automated scan plus manual keyboard, focus, zoom/reflow, reduced motion, contrast, screen-reader/live-region, and mobile touch-target checks on every changed shared component.
- **SEO evidence:** metadata/canonical snapshots, sitemap/robots validation, redirect tests, crawl simulation, and negative tests for private/hidden/deleted/tokenized surfaces.
- **Operational evidence:** clean install, populated upgrade, backup/restore, feature disablement, editor fallback, custom CSS safe mode, plugin disable, token revoke, worker pause/replay, cache purge, and storage-pressure rehearsal.

### 10.2 Evidence rules

- A roadmap/status label is never proof of completion.
- UI-visible changes require browser evidence in addition to server-side tests.
- Performance claims require before/after measurements on the same representative fixture and environment.
- Accessibility completion requires a defect log and manual evidence; automated scanners alone are insufficient.
- Upload acceptance requires adversarial fixtures, not only normal images.
- Cache acceptance requires cross-user/cross-role/private-board isolation tests.
- Security-sensitive claims require negative-path tests and log inspection proving secrets/private content are absent.
- Every Gate A/Gate B definition-of-done item must link to a test, report, screenshot/video, runbook exercise, or approved policy record in the evidence index.

### 10.3 Target evidence names

The implementation may use different names, but the evidence index should include equivalents of:

- `tests/Unit/Preferences/PreferenceSchemaTest.php`
- `tests/Unit/Composer/MarkdownRoundTripTest.php`
- `tests/Integration/Core/AppComposerParityTest.php`
- `tests/Integration/Core/AppComposerNoJsTest.php`
- `tests/Integration/Core/AppDraftRecoveryTest.php`
- `tests/Integration/Core/AppSubmitIdempotencyTest.php`
- `tests/Integration/Core/AppImageUploadTest.php`
- `tests/Integration/Core/AppPrivateMediaAccessTest.php`
- `tests/Integration/Worker/OrphanAttachmentCleanupTest.php`
- `tests/Integration/Core/AppRateLimitTest.php`
- `tests/Integration/Core/AppContentApprovalTest.php`
- `tests/Integration/Core/AppAutomationAuditTest.php`
- `tests/Integration/Core/AppAppealTest.php`
- `tests/Integration/Core/AppCategoryModeratorScopeTest.php`
- `tests/Integration/Core/AppTotpTest.php`
- `tests/Integration/Core/AppBrandingThemeTest.php`
- `tests/Integration/Core/AppCacheIsolationTest.php`
- `tests/Integration/Core/AppSeoVisibilityTest.php`
- `tests/Integration/Core/AppProductTourTest.php`
- `tests/Integration/Core/AppPluginIsolationTest.php`
- `tests/Integration/Worker/WebhookDeliveryWorkerTest.php`
- `tests/Integration/Core/AppAdminApiTokenTest.php`
- migration/upgrade fixtures, load-test reports, accessibility reports, crawler fixtures, and Playwright/browser evidence for all corresponding UI paths

These are target evidence names, not claims that the files already exist.

## 11. Performance, observability, and operating requirements

### 11.1 Budgets locked at Milestone 0

At minimum, record numeric success and failure thresholds for:

- p50/p95/p99 latency for public index, board list, thread read, authenticated inbox, settings, composer submit, search, notification poll, DM list/send, reports queue, admin dashboard, and media delivery;
- database query count and total query time per representative route;
- PHP peak memory and CPU per request/worker job;
- concurrent polling cost and database load;
- image upload/processing time, failure rate, disk bytes per active user/post, and orphan backlog;
- cache hit ratio, stale/error rate, purge/invalidation latency, and bypass rate;
- webhook queue age/retry/failure rate and plugin job duration/error rate;
- anti-spam evaluation latency, hold/flag rate, false-positive release rate, and approval-queue age;
- page asset bytes and render/startup metrics for the supported browser/device matrix;
- error budgets for core requests and background work.

Phase 3 must not regress accepted Phase 1/2 route budgets without a documented tradeoff and product-owner approval.

### 11.2 Required telemetry

- Structured request/correlation IDs across web, image processing, cleanup, webhook delivery, plugin jobs, and API calls.
- Route latency, status, query count/time, memory, authenticated/public classification, and cache disposition without logging private content.
- Slow-query samples and query-plan evidence with sensitive parameters redacted.
- Cache hit/miss/bypass/invalidation metrics by cache region and variation class.
- Upload counts/bytes/types, processing failures, orphan count/age, disk usage/thresholds, and denied private-media requests.
- Rate-limit hits by route/policy, rule matches by rule version/action, held-content counts/age, spam-score distribution, and staff release/confirm rates.
- TOTP enrollment/disable/recovery/failure/rate-limit events without secrets or codes.
- Appeal counts/age/outcomes and category-scope authorization denials.
- Plugin enable/disable/error/compatibility state, scheduled-job heartbeat, webhook queue and delivery history, and API token use/deny/revoke events.
- SEO route/indexability validation errors and sitemap generation status.
- Worker/cron heartbeats and last-success timestamps for cleanup, webhooks, retention, and optional draft pruning.

### 11.3 Required runbooks

- Disable the rich editor and force textarea fallback.
- Pause new uploads while preserving existing media reads.
- Clean or reconcile orphaned attachments and recover from disk-pressure thresholds.
- Switch anti-spam rules to observe-only, release held content, and roll back a bad rule version.
- Purge or bypass each cache region and diagnose stale/incorrect output.
- Reset branding/custom CSS through safe mode.
- Disable a failing plugin and skip its scheduled jobs.
- Pause, inspect, replay, or dead-letter webhook deliveries.
- Revoke API tokens or rotate webhook secrets.
- Assist a user who lost TOTP access without exposing secrets or bypassing audit.
- Rebuild sitemap/metadata caches and verify private-content exclusion.
- Restore backup and reconcile media files, database references, audit history, and extension state.

## 12. Risks and controls

| Risk | Control |
|---|---|
| Phase 3 becomes a dumping ground for unfinished Phase 2 work | Enforce the entry gate and carryover ledger; require explicit owner/rationale/destination for every Phase 2 deferral |
| Rich editor corrupts canonical Markdown | Acceptance-driven spike, golden round-trip corpus, textarea fallback, renderer versioning, and kill switch |
| Separate composer behaviors drift | One shared component/server pipeline plus a cross-context parity test matrix |
| Drafts leak between accounts or contexts | User/context-scoped keys, logout/account-switch handling, server authorization, encryption-at-rest policy where needed, quotas, and conflict UI |
| Double submissions create duplicate posts | End-to-end idempotency key with durable uniqueness and retry tests |
| Uploads enable code execution or resource exhaustion | Non-executable storage, content sniffing, image re-encode/limits, path isolation, dimension/decompression checks, quotas, and adversarial tests |
| Private media leaks through static URLs or caches | Authorization-gated delivery, unguessable storage keys, private responses non-public-cacheable, access recheck, and revoke tests |
| Orphaned uploads exhaust VPS disk | Temporary/final state, scheduled cleanup, disk alerts/thresholds, quotas, and text-post fallback when uploads pause |
| Anti-spam blocks legitimate users | Observe-first rollout, flag/hold defaults, rule versioning, clear user state, staff release, metrics, and emergency disable |
| Rate limits are bypassed or punish shared networks | Account + IP/device-aware policies, trusted-proxy validation, route-specific thresholds, and measured false-positive review |
| Approval queues overwhelm moderators | Capacity thresholds, scoped queues, aging metrics, batch tools, and conservative rollout |
| Cache serves another user’s/private content | Formal cache classification, variation keys, no-cache default for sensitive surfaces, isolation tests, and instant global bypass |
| Performance work optimizes the wrong bottleneck | Baseline first, numeric budgets, profiles/query plans, before/after evidence, and interface-preserving changes |
| Theme/custom CSS makes admin UI unusable | Token validation, preview, versioned audit, safe mode, server-side reset, and custom CSS Gate B only |
| Branding assets introduce unsafe files | Restricted image formats, processing, controlled dimensions/size, non-executable storage, and fallback assets |
| SEO exposes private or stale content | Canonical read-gate reuse, sitemap exclusion, noindex/robots rules, destination access checks, and crawl-negative tests |
| Tour harms accessibility or blocks navigation | Enhancement-only design, skip everywhere, focus management, missing-target tolerance, and no-JS usability |
| TOTP causes account lockout | Verify-before-enable, one-time recovery codes, reauth, support/reset policy, auditable reset, and staged opt-in rollout |
| Appeals silently rewrite history | Append-only original action, linked appeal/decision, explicit restoration transaction, and full audit snapshots |
| Category moderator scope becomes inconsistent | Central capability resolver, board-move/revoke invalidation, direct-request tests, and no UI-only enforcement |
| Plugin failure takes down the site | First-party/vetted only, compatibility checks, fault isolation, disable-on-error, circuit breaker, and core path without extension dependency |
| Plugin capabilities overreach | Manifest declarations, explicit enable consent, least-privilege service APIs, audit, and no arbitrary UI code upload |
| Webhooks become an SSRF/exfiltration path | HTTPS/host validation, private-network/redirect/DNS policy, timeout/size limits, HMAC signing, secret redaction, and egress tests |
| API tokens expose high-impact admin powers | Narrow endpoint set, explicit scopes, hash-only storage, expiry/revoke, rate limits, reauth for creation, and audit |
| New schema is under-specified | Milestone 1 schema reconciliation and migration tests before feature code |
| Single VPS is overloaded by processing/plugins | Resource budgets, async bounded jobs where justified, timeouts, queue backpressure, disk monitoring, and per-feature kill switches |
| Accessibility regressions return after the audit | Shared-component fixes, CI scans, browser/manual regression checklist, and evidence required for UI-visible releases |

## 13. Staged release and rollback

### 13.1 Recommended enablement order

1. Deploy additive migrations and dark backend code with all Phase 3 feature flags off.
2. Enable telemetry and capture a fresh pre-change baseline.
3. Enable preference reads/writes and branding preview for staff; then enable site branding with safe defaults.
4. Enable the shared composer for staff while retaining the server textarea and rich-editor kill switch.
5. Enable local draft UX and idempotent submission for a member cohort, then all users.
6. Enable image uploads on a staff/test board, then selected public/private boards, then replies/DMs after access tests pass.
7. Run rate limits and spam scoring in observe-only mode; enable flag, then hold, then narrowly approved block rules.
8. Enable performance/cache changes one route/region at a time with post-change isolation and invalidation smoke.
9. Enable SEO metadata/sitemap and the product tour after the final Gate A DOM and accessibility pass.
10. Accept Gate A before enabling 2FA, appeals, category scopes, custom CSS, plugins, webhooks, API, server drafts, or non-image files broadly.
11. Enable each Gate B feature independently for staff/test accounts before public/operator rollout.
12. Close Phase 3 only after all Gate B acceptances or approved scope changes are recorded.

### 13.2 Rollback rules

- Disable the affected feature flag before changing or deleting data.
- The rich editor must be rollback-safe to the canonical Markdown textarea; never roll back by transforming stored Markdown into editor-specific state.
- Pause new uploads before media rollback; preserve existing files and references for inspection and current reads.
- Switch anti-spam to observe-only before disabling rule evaluation; do not silently release or delete held content without an audited decision.
- Purge/bypass caches before rolling back code that changes cache shape or variation.
- Activate theme safe mode before reverting branding/custom CSS; retain the last known-good settings version.
- Disable a failing plugin/webhook/API module before reverting core application code.
- Revoke compromised tokens/secrets immediately; rotation must not require schema rollback.
- Keep Phase 3 migrations additive through the phase; application rollback targets must tolerate new tables/columns.
- Restore from backup only for proven corruption or unrecoverable loss; feature disablement and repair commands are the first response to logic defects.
- After rollback, rerun route-permission, private-media, cache-isolation, draft/idempotency, audit, and worker-health smoke tests.

## 14. Release checklist

### Gate A

- [ ] Phase 2 acceptance or explicit deferrals are recorded.
- [ ] Carryover ledger, Phase 3 scope, evidence map, owners, and performance budgets are approved.
- [ ] Deployed schema is reconciled with `SCHEMA.md`; all Gate A migrations pass clean-install and populated-upgrade tests.
- [ ] Editor spike/ADR is approved and Markdown round-trip corpus passes.
- [ ] Shared composer parity, textarea fallback, preview, spoilers/code/headings/emoji, edit reuse, validation, and keyboard/mobile behavior pass.
- [ ] Local drafts, Drafts view, failure recovery, explicit discard, and idempotent submit pass.
- [ ] Image upload/storage/processing/alt-text/private-access/moderation/orphan-cleanup tests pass.
- [ ] Rate limits, new-user controls, filters, duplicate/flood checks, approval holds, observe/flag/hold modes, and system audit pass.
- [ ] The 90-day IP-retention purge/anonymise job (`sessions.ip`/`posts.ip`) runs on schedule, is audited, and passes cutoff and idempotency tests (ADMIN §5.5).
- [ ] Settings appearance/reading/composing preferences pass defaults, validation, cross-device, no-JS, and malformed-data tests.
- [ ] Branding/logo/favicon/colors/light-dark preview/reset/audit and placeholder-retirement checks pass.
- [ ] Numeric latency/query/memory/polling/upload/cache budgets are met on production-like fixtures.
- [ ] Cache classification, cross-user/role/private isolation, invalidation, bypass, and purge tests pass.
- [ ] Accessibility audit, manual matrix, responsive evidence, and critical defect closure are complete.
- [ ] SEO metadata/canonical/sitemap/robots/redirect and private-exclusion tests pass.
- [ ] Product tour skip/replay/persistence/mobile/focus/graceful-failure tests pass.
- [ ] Operational logs/metrics, runbooks, backup/restore, staged rollout, and rollback rehearsals pass.
- [ ] Full Phase 1–2 regression and route-permission matrix remains green.
- [ ] No critical/high defects remain.
- [ ] README, changelog, schema, source docs, runbooks, and evidence index are updated.
- [ ] Gate A product-owner acceptance is recorded.

### Gate B and phase close

- [ ] TOTP enrollment, challenge, reauth, recovery, disable/reset, rate-limit, secret-handling, and support scenarios pass.
- [ ] Appeals and category-scoped moderation pass authorization, uniqueness, restoration, notification, and audit tests.
- [ ] Retro skin and custom CSS pass accessibility, preview, audit, safe-mode, and rollback checks.
- [ ] Server-side drafts pass local/remote conflict, quota, prune, privacy, and migration tests, or are formally re-scoped.
- [ ] Restricted non-image attachments/selected P2 formatting pass security, scanning/quarantine, access, accessibility, and retention gates, or are formally re-scoped.
- [ ] Internal hook/plugin lifecycle, compatibility, capabilities, migrations, fault isolation, scheduled jobs, and health visibility pass.
- [ ] Webhook signing, egress/SSRF controls, durable retries, idempotency, replay, secret rotation, and delivery history pass.
- [ ] Admin API endpoint/version/scope/token/expiry/revoke/rate-limit/audit tests pass.
- [ ] Deactivation, **avatar upload (`users.avatar_path`) and optional Gravatar**, bookmark folders, and custom profile fields pass privacy, moderation, sanitization, and migration tests, or are formally re-scoped.
- [ ] Any Phase 2 export/delete carryover is accepted under approved policy or explicitly moved later.
- [ ] Every Gate B omission has an approved roadmap destination rather than a silent omission.
- [ ] Full Phase 3 regression, security, privacy, accessibility, load, migration, backup, and rollback evidence is indexed.
- [ ] Final production baselines and capacity triggers are recorded.
- [ ] Phase 3 product-owner closeout is recorded.

## 15. Post-Phase 3 handoff

After Phase 3 closes, later work should be triggered by measured product or capacity needs rather than by unfinished polish obligations. Carry forward:

- route, query, memory, polling, cache, queue, disk, upload, and webhook baselines;
- anti-spam precision/false-positive data and approval-queue capacity;
- accessibility defect trends and learnability results;
- public discovery/crawl behavior and highest-value landing pages;
- editor fallback/error, draft recovery, and attachment-use metrics;
- plugin/webhook/API adoption and failure rates;
- account-security adoption and recovery burden;
- explicit thresholds for considering SSE, Redis, object storage/CDN, external search, read replicas, or additional workers.

Later roadmap candidates remain public plugin ecosystem/sandbox, passkeys, additional providers, advanced composer embeds/commands, time-windowed community features, PWA/offline, imports, multi-community, internationalization, community memory, and infrastructure swaps justified by the recorded thresholds.

## 16. Source references

- `PHASE_2_PLAN.md` — Gate A/Gate B ownership, Phase 2 closeout requirements, explicit deferrals, and Phase 3 handoff expectations.
- `README.md` — current live status, top-level Phase 3 scope, selected stack, and evidence policy.
- `DESIGN.md` §§6.5, 6.13–6.18, 9–13 — composer, settings, theming, presence ownership, onboarding, inbox direction, architecture, non-functional requirements, success measures, and phasing.
- `DECISIONS.md` — authoritative stack, Markdown/editor, storage, polling, plugin trust, role, anti-abuse, OAuth, 2FA timing, and deferred-system decisions.
- `SCHEMA.md` §§1–8 — authoritative deployed/future table shapes, Phase 3 build cut, and foreshadowed schema gaps.
- `COMPOSER.md` §§1–17 — shared composer contract, canonical syntax, media, drafts, submission resilience, safety, accessibility, mobile behavior, architecture, schema, and P0/P1/P2 boundaries.
- `ADMIN.md` §§2–11 — capabilities, appeals, automation/anti-abuse, branding/themes, integrations/hooks/webhooks/API, Console UX, schema, and Phase 3 admin roadmap.
- `USER.md` §§3–8 — settings IA, appearance/reading/composing preferences, security/2FA, account data/deactivation, avatars/Gravatar, bookmark folders, profile fields, onboarding, and account phasing.
- `COMMUNITY.md` §§2–14 — cosmetic reputation boundary, anti-abuse/humane-design constraints, optional later community work, and reputation-event schema seam.
