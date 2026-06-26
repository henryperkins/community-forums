# RetroBoards Phase 5 Plan — Ecosystem, Identity & Governance

**Owner:** Henry  
**Plan type:** Delivery baseline, release train, and formal phase closeout  
**Plan status:** **Draft — execution is gated by formal Phase 4 closeout and Milestone 0 trust-model approval**  
**Prepared:** 2026-06-25  
**Source hierarchy:** `DECISIONS.md` is authoritative where documents conflict; `DESIGN.md` is the product source of truth; `SCHEMA.md` owns final database shape; `ADMIN.md`, `USER.md`, `COMPOSER.md`, and `COMMUNITY.md` own their respective surfaces. P0/P1/P2 in the source documents are priority tiers, not delivery-phase numbers.

## 1. Phase objective

Turn the mature Phase 4 community product into a safely extensible, passwordless-capable, least-privilege platform without weakening the single-install, server-rendered, self-hostable operating model.

A member should be able to:

1. Register and sign in with a passkey where supported, retain an understandable fallback, and manage named credentials without being stranded by device loss or a browser change.
2. Link additional approved identity providers through the same explicit account-resolution rules used by the accepted Google, Apple, and GitHub integrations.
3. Accept a secure, expiring invitation when a community uses invite or approval-based registration, without an invitation silently granting excessive authority.
4. Understand which community or staff responsibilities they hold, where those responsibilities apply, when they expire, and why a requested action is allowed or denied.
5. Use operator-installed themes and extensions with clear disclosure of publisher, version, permissions, data access, network access, and support status.
6. Verify ownership of selected profile links and use richer typed profile fields without exposing private account data or turning profile claims into an authority mechanism.
7. Continue using the core forum when the registry, an extension, an identity provider, a passkey authenticator, or JavaScript is unavailable.

An operator should be able to:

1. Browse a signed public catalogue, inspect a package’s provenance and requested permissions, preview it, install it, update it, pin it, disable it, roll it back, export its data, and uninstall it without editing core files.
2. Distinguish first-party and separately vetted in-process extensions, reviewed declarative or remote packages, isolated server-side packages, and local developer packages; no trust tier may be implied merely by being installable.
3. Run third-party server code only through a verified isolation profile with bounded CPU, memory, time, filesystem, network, secret, and data access; unsupported hosts must fail closed to safer package types.
4. Define custom roles from a fixed capability catalogue, assign them at approved scopes, delegate narrow administrative work, simulate effective access, schedule expiry, and review privilege without changing authorization call sites.
5. Preserve immutable built-in recovery authority, prevent self-escalation and last-owner lockout, and require reauthentication or approval for high-impact role, extension, identity, and trust-root changes.
6. Configure generic OIDC and approved provider packages, invitation rules, passkey policy, privileged-account MFA requirements, and recovery behavior from an auditable Console.
7. Operate a package review and security-response process covering publisher identity, immutable releases, signatures, dependency inventory, automated checks, manual review, vulnerability advisories, revocation, and emergency disablement.
8. Diagnose extension, permission, passkey, provider, invitation, approval, and access-review behavior through numeric budgets, structured telemetry, and rehearsed runbooks.

Phase 5 is a **release train**, not a big-bang release. Gate A establishes the signed ecosystem, passkeys, generic identity expansion, and database-backed least-privilege governance. Gate B opens the isolated server-extension path, completes the publisher/review workflow, and adds advanced delegation, access review, identity assurance, and profile verification. Phase 5 is not formally closed until both gates are accepted or every omitted Gate B item has an approved scope-change record.

## 2. Entry gate — Phase 4 must be closed first

This plan may be refined before Phase 4 closes, but Phase 5 implementation must not begin until all of the following are true:

- Phase 4 Gate A and Gate B have recorded product-owner acceptance, or every incomplete item has an explicit deferral with owner, rationale, risk, and destination phase.
- The Phase 4 evidence index covers migrations, route/permission matrices, group-DM privacy, advanced Markdown round trips, attachment and preview safety, feeds/tags, reputation/badges, community-memory provenance, accessibility, SEO, performance, backup, and rollback.
- No unresolved critical or high-severity Phase 4 security, privacy, accessibility, authorization, data-integrity, private-message, attachment, community-memory, moderation, or release-operability defect remains.
- The deployed database has been reconciled against `SCHEMA.md`, including the actual Phase 3/4 shape for `plugins`, webhook delivery, API tokens, attachments, provider configuration, custom fields, tags, group conversations, status history, reputation events, badge rules, summaries, wiki revisions, and split/merge redirects.
- The Phase 3 first-party/vetted extension runtime is accepted: manifests, declared capabilities, lifecycle, migrations, scheduled jobs, disable-on-error, compatibility checks, health reporting, webhooks, and API-token revocation have current-build evidence.
- Existing extension, webhook, API, theme, custom-CSS, provider, TOTP, recovery, and backup kill switches have been exercised. A public ecosystem cannot be used to mask an unaccepted trusted-extension foundation.
- Baselines exist for extension error rate and job duration, webhook/API usage, theme/custom-CSS recovery, authentication success/failure, TOTP enrollment and recovery, OAuth provider success, role and moderator assignments, authorization denials, audit volume, queue lag, PHP memory/CPU, database growth, disk pressure, and operator workload.
- Product evidence supports opening an ecosystem and adding governance complexity. At minimum, the owner has reviewed actual demand for third-party packages, custom roles, delegated administration, passkeys, additional providers, invitation-only registration, and verified profile links.
- The Phase 5 trust model is approved before code begins: package classes, trust tiers, registry ownership, signing-key custody, review criteria, vulnerability handling, permission taxonomy, extension data classes, host isolation requirements, and unsupported-host behavior.
- The permission catalogue and protected-capability list are reviewed. Every current role/scope decision has an explicit migration destination, and the state-first/account-status rules remain authoritative.
- The canonical HTTPS origin and WebAuthn RP ID are approved, including the domain-change and disaster-recovery policy.
- The first additional identity-provider strategy is approved. The default is a generic OIDC configuration plus reviewed provider packages, not provider-specific account-resolution forks.
- Every unfinished Phase 4 obligation is placed in a **carryover ledger**. A carryover may block Phase 5, be completed before Gate A, or be explicitly moved later; it must not be silently renamed as Phase 5 work.

**Ownership boundary:** Phase 3 remains responsible for the accepted trusted hook/plugin runtime, webhooks, API tokens, TOTP, recovery, theming safe mode, and secret handling. Phase 4 remains responsible for advanced community/content behavior. Phase 5 builds public distribution, isolated third-party execution, database-backed capabilities, passkeys, provider expansion, invitations, and governance on those accepted foundations; it does not retroactively accept them.

## 3. Definition of done

Phase 5 is accepted only when all of the following are true:

- Every accepted Phase 1–4 journey remains functional, permission-safe, server-rendered, and usable without JavaScript wherever the underlying action has a no-JS path. Passkey use may require browser JavaScript/WebAuthn APIs, but email/password, approved provider, TOTP, and recovery fallbacks remain understandable and operable.
- Every Phase 5 schema change is additive or reversibly migrated, documented in `SCHEMA.md`, tested on clean and populated upgraded installations, and reversible at the application or feature-flag level. _(Fixed 2026-06-26: not strictly additive — §8.2 #15 converts `oauth_identities.provider` from a fixed ENUM to a string/FK; the widen-vs-convert choice is committed at Milestone 1.)_
- The public registry protocol uses immutable package versions, content digests, signed metadata, explicit source pinning, freshness/expiry rules, key rotation, revocation, and a cached-offline failure mode. A mutable download URL or package name is never treated as sufficient identity.
- Package identity is globally namespaced. Dependency and update resolution cannot silently switch a package to another registry, publisher, or digest.
- Every installed package records package ID, version, digest, source registry, publisher, trust class, review status, declared permissions, granted permissions, compatibility range, install actor, install time, update history, and current health state.
- Enabling or updating a package displays a human-readable permission and data-access summary. Any increase in permissions, data classes, outbound hosts, scheduled jobs, or privileged hooks requires new consent and, where policy requires it, an approval.
- Public catalogue availability never permits arbitrary PHP upload or execution from the Console. Unsigned, tampered, revoked, expired, incompatible, or unreviewed packages fail closed.
- Gate A public packages are declarative themes, declarative automations, or remote applications using accepted webhooks/API scopes. They cannot run untrusted PHP in the web request, modify core files/schema, read the database directly, or inject arbitrary browser JavaScript.
- Gate B server extensions execute only through the approved isolation adapter under a dedicated identity and resource policy. They have no direct core database, session, secret, executable-path, or arbitrary network access; every privileged operation passes through a capability-checked broker.
- The installation detects whether the host satisfies the isolation profile. When it does not, server-extension installation and enablement are unavailable while declarative themes and remote apps remain usable.
- A public server extension cannot become a synchronous dependency of login, authorization, posting, reading, moderation, or recovery. Timeout, crash, malformed output, excessive resource use, or sandbox denial disables or quarantines the extension without taking down the core request or worker loop.
- Public extension state lives in an extension-owned, quota-limited storage service or approved isolated schema. Public packages cannot ship migrations that alter core tables. Export, retention, uninstall, restore, and orphan-cleanup behavior are declared and tested.
- Extension events use durable identities and idempotency keys. Redelivery, worker restart, or update cannot create unbounded duplicate side effects.
- Theme packages are declarative by default: token sets, approved local assets, metadata, and optional stylesheet modules constrained to approved theme hooks. They contain no PHP, JavaScript, remote fonts, trackers, `@import`, arbitrary external URLs, or template replacement.
- Theme preview is isolated from the live site. Accessibility checks, asset scanning, CSS-policy validation, cache busting, safe mode, last-known-good restore, and one-action rollback pass before a package can become the site default.
- The publisher/review process records publisher verification, source location, license, package manifest, dependency lock/SBOM, automated scan results, manual review, permission-risk classification, test evidence, reviewer, review date, and the exact approved digest.
- Vulnerability advisories can warn, block new installs, force-disable under an approved emergency policy, or revoke a publisher/release. Every action is signed, auditable, reversible where safe, and available through a registry-independent local emergency control.
- Built-in Guest, User, Moderator, and Admin behavior is migrated to protected database-backed role definitions without changing accepted access. The original role column/assignments remain available as a compatibility/rollback source during the phase.
- Custom roles are bundles of known capabilities, not executable policy. The first version has no arbitrary expressions, SQL, PHP, negative-deny rules, or unbounded role inheritance.
- Effective permissions are computed state-first, then from the union of active grants, then by scope, then by the canonical content/privacy read gate. Banned/suspended state, private-board membership, blocks, and object ownership cannot be bypassed by a custom role.
- Supported grant scopes are explicit and bounded: site, category, and board for delegated authority; self remains a core ownership rule. A custom role cannot invent a new scope or make a board-scoped capability site-wide.
- Protected capabilities—site ownership, trust-root management, break-glass recovery, signature override, audit integrity, and any equivalent approved list—cannot be placed in ordinary custom roles or delegated through a plugin.
- Role definitions, capability changes, assignments, renewals, expirations, revocations, group membership, and approval decisions write immutable before/after audit records.
- An operator can simulate `can(actor, capability, target, time)` and see the decisive state, role/grant, scope, and read-gate reason without seeing private target content they could not otherwise read.
- Temporary grants expire automatically and cease authorizing requests at or before the approved expiry tolerance. Cache invalidation and direct-request tests prove no stale privilege remains.
- The installation always retains at least one protected owner/primary administrator with an active login and recovery path. Ownership transfer, last-owner removal, and deletion/deactivation of the final recovery authority are blocked or follow the approved dual-control recovery workflow.
- High-impact operations require recent reauthentication. Gate B approval policies can require a second eligible approver; an actor cannot approve their own elevation, ownership transfer, trust-root change, or equivalent protected action.
- Passkey registration and authentication validate challenge, origin, RP ID, credential ID, public key, signature, user presence, and the approved user-verification policy. Challenges are short-lived, one-time, account/context-bound, and never logged in reusable form.
- Passkey credentials are named, individually revocable, and visible with created/last-used metadata and safe transport/device hints. Private keys never reach or reside on the server.
- Synced and device-bound passkeys are supported. Signature-counter anomalies are treated as risk signals rather than an automatic permanent lockout because valid synchronized credentials may not expose a strictly monotonic counter.
- Adding a passkey, removing the last strong credential, changing privileged-auth policy, or performing a sensitive operation requires the approved reauthentication/step-up path. A newly authenticated session cannot silently attach a passkey to a different account.
- Passkey loss does not create a weaker bypass. Recovery uses the accepted verified-email, TOTP/recovery-code, provider, and operator-support policy; privileged-account resets are audited and may require approval.
- Passwordless/usernameless passkey sign-in, if enabled in Gate B, resolves only by credential identity; it never guesses or merges by email, display name, username, or provider claim.
- Generic OIDC configuration validates issuer, discovery/JWKS source, redirect URI, state, nonce, PKCE, token signature, audience, authorized party where applicable, time claims, and stable subject identity. Provider configuration secrets are encrypted at rest and never redisplayed in plaintext.
- Additional provider packages normalize into the accepted identity contract. Authentication-critical provider packages require the highest review class and cannot broaden account resolution or silently auto-link by email.
- Existing Google, Apple, and GitHub identities continue to resolve by stable provider identity. Migrating the provider column from a fixed enum to a registry-backed identifier causes no account duplication, orphaning, or ban bypass.
- A provider outage or disablement does not orphan an account. Unlink remains blocked when it would remove the last usable method, and recovery guidance is available before disabling a provider used as the sole method.
- Invitations are random, single-use, hash-only, expiring, revocable, rate-limited, and optionally bound to an email or approved domain. Redemption and account creation are atomic.
- Invitations may propose ordinary membership, board membership, or a non-privileged onboarding role only under an approved policy. Granting sensitive custom roles or administrative authority always requires a separate authenticated assignment/approval path.
- Service/remote-app installations use independent principals and scoped credentials rather than impersonating an administrator. Credentials are hash-only or protected by the accepted secret store, expirable, revocable, rate-limited, and audited.
- Verified profile links prove control through an approved DNS or HTTPS challenge, expire or recheck according to policy, and are displayed as ownership verification—not identity, employment, safety, or platform endorsement.
- Richer custom profile fields remain typed, length-limited, sanitized, visibility-aware, and independent from authorization. Extensions cannot turn a profile value, badge, reputation score, or verified link into an implicit capability.
- Extension, role, passkey, provider, invitation, and governance surfaces pass the Phase 3 accessibility baseline plus keyboard, screen-reader-critical, zoom/reflow, reduced-motion, mobile, and no-JS checks appropriate to each function.
- Public catalogue/theme/profile pages have correct indexing behavior. Admin, package-token, approval, credential, invitation, provider-callback, private package-data, audit, and security-recovery URLs are excluded from discovery.
- Phase 5 meets numeric signature verification, registry, install/update, sandbox, permission-resolution, passkey, provider, invitation, queue, database, disk, and error budgets approved at Milestone 0 on production-like fixtures.
- The complete automated suite, migration matrix, supply-chain tests, sandbox adversarial tests, permission matrices, browser evidence, no-JS smoke, accessibility evidence, security/privacy tests, worker checks, backup/restore, and rollback rehearsals pass.
- No unresolved critical or high-severity security, privacy, accessibility, authorization, supply-chain, identity, privilege-escalation, extension-isolation, data-integrity, or release-operability defect remains.

## 4. Scope and release gates

### Gate A — Signed ecosystem, passkeys, and least-privilege governance

Gate A is the minimum Phase 5 release:

- Phase 4 closeout reconciliation, Phase 5 carryover ledger, threat models, permission catalogue, protected-capability list, registry/signing policy, canonical WebAuthn origin/RP ID, representative fixtures, numeric budgets, feature flags, and requirement-to-evidence map.
- Public registry and package-trust foundation:
  - registry/source identifiers, signed expiring catalogue snapshots, trust roots, key rotation/revocation, immutable releases, content digests, source pinning, compatibility metadata, licenses, dependency inventory, and advisory status;
  - package manifest v2 with type, publisher, version, core range, hooks/events, API scopes, data classes, outbound-host policy, jobs, storage quota, settings schema, install/uninstall policy, and support links;
  - package catalogue, detail, permission summary, compatibility check, install plan, preview, pin/update/disable/uninstall, health, history, and emergency disable controls;
  - signed declarative themes and remote/declarative integrations only; no public untrusted PHP execution in Gate A.
- Theme-package foundation:
  - token and approved local-asset packages;
  - isolated preview, contrast/accessibility checks, asset scanning, deterministic build/cache key, safe mode, and last-known-good rollback;
  - no PHP, JavaScript, template replacement, remote assets, tracking, or unrestricted CSS.
- Public-package governance:
  - permission-risk tiers and explicit consent;
  - update permission diff and re-consent;
  - advisory/yank/revoke behavior;
  - package data export/uninstall and registry-outage behavior;
  - first maintainer-operated review workflow with automated evidence and manual approval tied to an exact digest.
- Database-backed capability and role model:
  - protected built-in role seeds preserving Guest/User/Moderator/Admin behavior;
  - fixed capability catalogue, namespaced extension capabilities, role definitions, role-capability mappings, scoped user assignments, start/expiry dates, and audit history;
  - state-first permission resolver, shadow comparison against the old resolver, cache invalidation, permission simulator, role preview, impact count, and emergency fallback;
  - no deny rules, role inheritance, arbitrary policy code, or plugin-defined core capabilities.
- Delegated governance foundation:
  - narrow custom administrative roles;
  - site/category/board scopes;
  - temporary grants and automatic expiry;
  - protected owner/last-admin safeguards;
  - recent-reauthentication gate for high-impact changes;
  - immutable role/assignment/trust/provider/package audit views.
- Passkeys:
  - WebAuthn registration, sign-in, credential naming/list/removal, step-up, audit, rate limits, recovery integration, RP-ID/origin checks, and device/synced-passkey-compatible counter handling;
  - optional-member enrollment, staff pilot, and a policy that can require passkey-or-TOTP for selected privileged capabilities after a grace period;
  - existing email/password, OAuth, TOTP, and recovery paths remain available according to policy.
- Identity-provider expansion:
  - provider registry replacing the fixed provider enum;
  - generic OIDC configuration with discovery/JWKS, claim mapping, encrypted client secrets, health/test flow, issuer pinning, and disable/fallback behavior;
  - at least one additional approved provider configuration or package selected at Milestone 0;
  - unchanged explicit collision/linking and last-login-method protections.
- Invitations:
  - admin-created and, where allowed, member-created invitations;
  - hash-only token, expiry, revoke, email/domain binding, usage limit, abuse controls, registration-mode integration, and audit;
  - no privileged-role grant through an invitation alone.
- Full Gate A accessibility, security, performance, observability, runbooks, staged rollout, rollback evidence, and product-owner acceptance.

### Gate B — Isolated server extensions and governance maturity

These items are committed to the broader Phase 5 window. They may ship after Gate A, but Phase 5 requires acceptance or an approved re-scope for each:

- Sandboxed public server extensions:
  - host capability probe and approved isolation adapter;
  - dedicated OS identity, isolated working directory, read-only package image, no core filesystem/database/session/secret access, network denied by default, explicit outbound-host grants, and brokered services;
  - CPU, memory, wall-time, process, output, storage, event-rate, and job-concurrency quotas;
  - durable event delivery, idempotency, backpressure, timeout/circuit breaker, quarantine, and disable-on-error;
  - no synchronous dependency of core read/write/auth/authorization paths.
- Public extension SDK and broker:
  - versioned event envelope, capability-checked RPC, typed settings, extension storage, scheduled jobs, outbound HTTP broker, content/public-data access, notification/webhook actions, and redacted observability;
  - compatibility contract, deprecation windows, conformance suite, sample packages, and migration guidance;
  - authentication-provider code remains restricted to first-party or highest-review packages; generic declarative OIDC mappings are preferred.
- Publisher and review operations:
  - publisher account and signing-key lifecycle;
  - submission, immutable build artifact, automated static/dependency/malware/license/manifest tests, sandbox conformance, manual review, rejection reasons, resubmission, approval, release channels, and exact-digest publication;
  - security advisory, coordinated fix, key compromise, publisher suspension, release revocation, emergency disable, and appeal/re-review process;
  - signed transparency record or equivalent append-only publication history for released/revoked digests.
- Advanced theme packages:
  - restricted stylesheet modules expressed through approved selectors/properties and package-local assets;
  - AST validation, CSP compatibility, anti-phishing/security-UI checks, accessibility report, preview, version pinning, and instant safe-mode bypass;
  - public themes still cannot ship JavaScript, PHP, arbitrary templates, remote tracking, or executable media.
- Governance groups and workflows:
  - operator teams/groups, group membership, group role assignments, and deterministic effective-permission explanation;
  - access requests, temporary elevation, renewal, expiry, and scheduled access review/certification;
  - configurable approval policies for sensitive role assignments, ownership transfer, trust-root changes, high-risk package install/update, identity-provider changes, and privileged recovery;
  - no self-approval, no approval by an actor lacking the target capability/scope, and no workflow that can remove the final owner.
- Service principals and remote apps:
  - app installation records, scoped service identity, per-install credentials, callback/webhook verification, token rotation, suspension, rate limits, audit, and uninstall/data-retention behavior;
  - service principals cannot inherit member identity, use browser sessions, or receive human-only capabilities.
- Advanced passkey and privileged-auth policy:
  - usernameless/discoverable-credential sign-in where supported;
  - passkey-first UX with explicit account selection/fallback;
  - privileged-capability MFA policy, enrollment grace, break-glass codes or equivalent protected recovery, and audited operator reset;
  - risk events inform review without permanently blocking valid synced credentials solely for a counter anomaly.
- Provider-package and identity-assurance expansion:
  - reviewed provider packages using the normalized contract;
  - domain/issuer restrictions, provider health monitoring, key rotation, claim-change handling, and controlled migration between provider configurations;
  - SAML, SCIM, SMS, government-ID, biometric identity proofing, and broad enterprise directory sync remain outside Phase 5 unless separately approved.
- Verified profile links and richer typed custom fields:
  - DNS/HTTPS proof, revalidation/expiry, revoke, moderation, privacy, and clear non-endorsement copy;
  - additional approved field types, validation, per-field visibility, ordering, export/delete, and extension-safe rendering;
  - no authorization, trust-level, queue-priority, or anti-abuse bypass based on a profile field or verification mark.
- Full Gate B hardening, documentation, evidence index, and formal Phase 5 closeout.

### Conditional carryovers — not automatically Phase 5 scope

The following may enter Phase 5 only through the carryover ledger or a signed scope change:

- Any unaccepted Phase 3 extension, webhook, API-token, TOTP, recovery, theme, custom-CSS, security-secret, accessibility, cache, or operational requirement.
- Any unaccepted Phase 4 status, group-DM, attachment, preview, feed, badge, community-memory, profile-media, or moderation requirement. Phase 5 may expose extension points only after the underlying feature is accepted.
- Authentication provider work already committed to Phase 2. Phase 5 adds generic/provider-package expansion; it must not relabel unfinished Google/Apple/GitHub linking, collision, or orphan-prevention work.
- Registration-mode controls already committed to earlier admin work. Phase 5 adds invitation lifecycle and governance; it does not substitute a token table for unimplemented registration enforcement.
- Custom profile fields or verified links already accepted earlier. Phase 5 should extend the approved model rather than create a second profile system.
- External search, Redis, object storage/CDN, fan-out feeds, read replicas, additional worker pools, or other capacity changes. They enter only through the Phase 6 capacity-trigger process.
- Multi-community or organization tenancy. Delegated administration in Phase 5 applies to one installation and approved category/board scopes; it must not smuggle in tenant boundaries.

### Explicitly deferred beyond Phase 5

The following must not delay Phase 5 acceptance unless formally pulled in:

- Unreviewed or unsigned arbitrary PHP upload, in-process execution of public community code, direct plugin database access, core-file patching, remote code fetch at runtime, `eval`, shell access, or unrestricted filesystem/network access.
- End-user installation of extensions. Package installation remains an operator action governed by capabilities and approval policy.
- Paid marketplace checkout, subscriptions, revenue sharing, tax handling, license enforcement, affiliate rankings, or commercial dispute resolution.
- Arbitrary browser JavaScript, template replacement, remote fonts/trackers, or unconstrained CSS in public theme packages.
- SMS authentication, mandatory 2FA for ordinary members, government-ID verification, biometric identity proofing, SAML/SCIM directory sync, or a centralized cross-install identity service.
- SSE/WebSockets, typing indicators, per-message read receipts, real-time chat semantics, voice/video, or ephemeral messaging.
- Meilisearch/Elastic, Redis, object storage/CDN, read replicas, distributed queue infrastructure, materialized feed fan-out, or other Phase 6 capacity swaps without an approved trigger.
- PWA/offline mode, native mobile applications, forum-import tooling, multi-community/multi-tenant operation, cross-install federation, and full internationalization; these remain Phase 7 platform-expansion concerns.
- Autonomous moderation, auto-published generated content, third-party generative access to private content, or extension permissions that allow unsupervised punitive decisions.
- A plugin-defined authorization language, arbitrary deny rules, recursive role inheritance, per-record policy scripts, or reputation/badge/profile-field-based authority.

## 5. Reconciled and locked implementation decisions

The following decisions are treated as fixed for Phase 5:

1. **Phase ownership stays intact.** Phase 5 extends accepted Phase 1–4 behavior; it does not hide incomplete earlier work.
2. **The core architecture remains server-rendered PHP/MySQL on a single installation.** Public ecosystem work must operate safely on the current product before Phase 6 infrastructure swaps.
3. **Public marketplace does not mean arbitrary code upload.** The Console never loads unsigned community PHP into the web process.
4. **There are explicit extension trust classes.** Built-in/first-party and separately vetted in-process code retain the Phase 3 path; public declarative/remote packages use no local code execution; public server packages use the Gate B isolated worker; local developer packages require an explicit developer mode and never acquire marketplace trust automatically.
5. **Public packages are immutable by version and digest.** Package name, URL, version string, or publisher display name alone is never sufficient identity.
6. **Registry metadata is signed and expires.** The installer pins the registry and digest, verifies the trust chain, handles key rotation/revocation, and refuses stale or unverifiable install decisions according to the approved policy.
7. **Permissions are declared and granted, not inferred.** Manifest declarations are a ceiling, local grants are the actual authority, and permission expansion on update requires new consent.
8. **High-risk data access is exceptional.** Private-board content, DMs, user email/PII, moderation data, auth events, and security configuration require separate named permissions, enhanced review, visible disclosure, and the least data needed for the package function.
9. **Public extensions cannot modify core schema.** They use versioned extension storage. Core-schema changes remain core migrations reviewed through the normal release process.
10. **Public server extensions are asynchronous and non-critical.** Core login, reads, writes, authorization, moderation, and recovery must remain correct when every public extension is disabled.
11. **Isolation is a verified host capability, not a marketing label.** When the approved OS/process controls are unavailable, server packages remain disabled; the application does not pretend timeouts or PHP namespaces form a security sandbox.
12. **The broker is the authority boundary.** Sandboxed packages receive no direct DB handle, session object, environment secrets, filesystem path, or raw network socket. Every service call is capability-checked, scoped, metered, and audited.
13. **Remote apps use service identities.** They do not borrow an Admin session or share a human API token.
14. **Theme packages are primarily declarative.** Public themes may change approved tokens/assets and, in Gate B, constrained style hooks; they cannot execute JavaScript/PHP, replace security templates, or contact third parties on page load.
15. **Safe mode always wins.** An operator can bypass package themes and public extensions from a server-side route or environment switch that does not depend on the affected package.
16. **Review approval binds to an exact digest.** A publisher cannot replace approved bytes in place; every changed byte creates a new release and review decision.
17. **Advisories and revocation are first-class.** Local operators can inspect, pin, disable, or acknowledge according to policy, but a revoked signature or locally blocked digest cannot execute.
18. **Built-in roles remain protected compatibility anchors.** Guest, User, Moderator, and Admin definitions are seeded as system roles and cannot be deleted or silently weakened during migration.
19. **Custom roles are additive capability bundles.** The first version has no explicit deny, no arbitrary conditions, and no recursive inheritance. Multiple active grants combine by union, then scope/read gates narrow the result.
20. **State remains stronger than role.** Suspension, ban, private membership, block, ownership, and content-state checks cannot be overridden by a custom role unless an existing explicit moderation capability already permits that action.
21. **Scopes remain explicit.** Site/category/board are the delegated scopes. Self-ownership rules remain core. A plugin cannot create a new core scope through a manifest.
22. **Protected capabilities are non-delegable.** Site ownership, trust-root management, break-glass recovery, signature override, and other approved recovery/integrity powers stay with protected system authority.
23. **Cosmetic identity remains separate from authority.** Public titles, badges, reputation, profile fields, verified links, and theme labels do not imply or grant capabilities. Custom staff-role display is separately controlled.
24. **Temporary authority has an expiry.** Expiration is checked during authorization, not only by a cleanup job, and permission caches include assignment version/expiry.
25. **Permission simulation uses the real resolver.** It cannot be a UI approximation, and it never reveals target content the simulator actor cannot read.
26. **High-impact changes require recent reauthentication.** Gate B approval policies add dual control where configured; no actor can approve their own escalation.
27. **At least one protected owner remains recoverable.** The application blocks removal, deactivation, provider unlinking, passkey removal, or policy changes that would strand the final owner.
28. **Passkeys use WebAuthn against one approved RP ID.** HTTPS origin and RP-ID changes are migrations with a runbook, not ordinary branding settings.
29. **Attestation is not trusted by default.** The baseline accepts standards-compliant credentials without turning device attestation into identity proof or a fingerprint. An allowlist policy would require a separate approved decision.
30. **Synced-passkey counter behavior is handled cautiously.** A non-increasing counter is logged and risk-scored; it is not by itself proof of compromise or grounds for permanent lockout.
31. **Passkeys augment accepted recovery.** Gate A does not remove passwords or providers globally. Usernameless/passkey-first behavior is Gate B and retains explicit fallback.
32. **Provider identity is issuer/provider plus stable subject.** Email is a contact and collision signal, never the stable login key and never a silent merge key.
33. **Generic OIDC is the primary expansion seam.** Additional providers should normally be configuration or reviewed provider packages, not custom account-resolution code.
34. **Auth-provider packages receive enhanced review.** Community packages cannot alter core collision, ban, account-state, last-login-method, or recovery rules.
35. **Provider/client secrets use the accepted encrypted secret service.** They are write-only after save, redacted in logs/exports, versioned, and rotatable.
36. **Invitations are onboarding evidence, not authority.** A token may admit a user under policy but cannot bypass account-state, email-verification, anti-abuse, or privileged-role approval.
37. **Service principals are distinct actors.** Audit records identify the app installation and initiating human/configuration where applicable.
38. **Verified links prove control only.** They do not certify identity, employment, trustworthiness, safety, or ownership of the RetroBoards account beyond the challenged resource.
39. **Schema design precedes implementation.** Any table or column absent from `SCHEMA.md` must be reconciled and approved before dependent production code is merged.
40. **Every subsystem has an independent disable path.** Registry fetch, new installs, automatic updates, themes, remote apps, sandbox workers, custom roles, group grants, approvals, passkeys, each provider, invitations, verified links, and service principals can be paused without disabling core reading/posting.
41. **Per-board post gating migrates with the role enum.** `boards.post_min_role` is superseded by a board-scoped post capability / minimum-role-rank check. Its `user`/`moderator`/`admin` values map deterministically to the seeded system-role IDs and are preserved in parallel as a rollback source until the capability path reaches parity; the board minimum-to-post gate is never silently dropped or broadened during migration.

## 6. Delivery workstreams

| ID | Workstream | Deliverables | Acceptance evidence | Dependency | Gate |
|---|---|---|---|---|---|
| P5-00 | Entry gate, threat models, and baselines | Phase 4 closeout; carryover ledger; extension/identity/privilege threat models; capability inventory; trust roots; RP ID; provider/invitation decisions; fixtures; numeric budgets; flags; evidence map | Signed Phase 4 acceptance/deferrals; threat-model review; baseline report; schema diff; requirement ledger; rollback map | Phase 4 | A |
| P5-01 | Registry protocol and package identity | Registry IDs; signed/expiring catalogue snapshots; trust roots; key rotation/revocation; immutable releases/digests; source pinning; compatibility; dependency/SBOM metadata; advisories | Signature/tamper/replay/staleness/key-rotation tests; dependency-confusion fixtures; offline-cache behavior; registry contract evidence | P5-00 | A |
| P5-02 | Package manifest, install, update, and lifecycle | Manifest v2; package types; permission/data/network/job declarations; compatibility plan; install preview; enable/disable; pin/update/rollback; uninstall/export; health/history; permission diff and re-consent | Manifest schema tests; install/update failure injection; permission-diff tests; rollback/data-retention evidence; browser/no-JS admin forms | P5-01, accepted Phase 3 runtime | A |
| P5-03 | Theme packages | Token/assets package; catalogue preview; asset scan; deterministic build; contrast/accessibility validation; CSP/cache integration; safe mode; Gate B restricted stylesheet modules | Malicious asset/CSS fixtures; external-request checks; contrast/keyboard/browser evidence; preview isolation; safe-mode and last-known-good rollback | P5-01, accepted Phase 3 theming | A/B |
| P5-04 | Public declarative and remote integrations | Declarative automation/connector packages; webhook/API scope mapping; settings schema; remote-app consent; local data class declaration; disable/uninstall | Scope/consent tests; webhook/API isolation; secret redaction; remote outage behavior; uninstall/data export evidence | P5-02, accepted webhooks/API | A |
| P5-05 | Sandboxed server-extension runtime | Host capability probe; isolation adapter; package image; dedicated identity/workdir; brokered RPC; network/storage/resource limits; durable events; quotas; circuit breaker; quarantine | Sandbox escape/adversarial suite; direct DB/file/env/network denial; CPU/memory/timeout tests; worker crash/restart; core-path isolation proof | P5-02, P5-04 | B |
| P5-06 | Extension SDK and compatibility | Versioned envelopes/RPC; typed services; extension storage; outbound HTTP broker; jobs; samples; conformance suite; compatibility/deprecation policy; package migration guidance | SDK contract tests; old/new core matrix; idempotency/retry fixtures; sample package conformance; documentation review | P5-05 | B |
| P5-07 | Publisher review and security response | Publisher verification/keys; submission; automated checks; manual review; release channels; exact-digest approval; advisory; key compromise; revoke/yank; transparency history; re-review/appeal | Malicious/dependency/license fixtures; reviewer authorization; signed publication/revocation tests; emergency-disable rehearsal; audit evidence | P5-01, P5-02 | A foundation/B portal |
| P5-08 | Roles and capability catalogue | Protected built-in roles; capability registry; namespaced extension capabilities; role definitions; role-capability mappings; resolver; compatibility shadow mode; permission simulator | Old-vs-new parity corpus; state/scope/read-gate matrix; protected-capability tests; performance evidence; browser/no-JS role editor | P5-00 | A |
| P5-09 | Scoped assignments and temporary grants | User assignments; site/category/board scope; effective dates/expiry; renewal/revoke; impact preview; cache/version invalidation; legacy moderator and board post-gate migration | Scope matrix; concurrent assign/revoke; expiry tolerance; stale-cache tests; rollback to compatibility resolver | P5-08 | A |
| P5-10 | Governance groups, approvals, and access review | Operator groups; group assignments; access requests; approval policies; no-self-approval; ownership transfer; protected owner; scheduled certification; findings/remediation | Privilege-escalation tests; owner-lockout tests; approval concurrency; reviewer-scope matrix; expiry/review worker evidence; browser/no-JS | P5-09 | B |
| P5-11 | Passkeys and privileged authentication | WebAuthn challenge/credential model; registration/login; naming/list/remove; step-up; rate limits; audit; recovery; staff pilot; privileged MFA policy; Gate B usernameless flow | Origin/RP/challenge/replay/signature/user-verification tests; synced-counter fixtures; recovery/lockout matrix; supported-browser evidence | Accepted Phase 3 TOTP/recovery, P5-00 | A/B |
| P5-12 | Provider registry and generic OIDC | Provider registry; migrate fixed enum; generic OIDC discovery/JWKS/claims; encrypted secrets; test/health; account-linking reuse; provider disable/fallback; first added provider | Issuer mix-up/JWKS rotation/nonce/state/PKCE/audience tests; migration identity reconciliation; outage/collision/orphan-login evidence | Accepted Phase 2 OAuth, P5-00 | A |
| P5-13 | Invitations and identity policy | Invite creation/revoke/redeem; email/domain binding; expiry/use limits; registration integration; anti-abuse; optional board/onboarding grant; security activity/audit | Token entropy/hash/replay/concurrency tests; expired/revoked/domain mismatch; privilege-injection denial; browser/no-JS flow | P5-08, accepted registration controls | A |
| P5-14 | Service principals and remote-app installations | App installations; service identity; scopes; credentials; rotation/revoke; callback/webhook verification; rate limits; audit; data lifecycle | Human-vs-service separation; scope/revoke/expiry tests; secret handling; app uninstall/export; impersonation-negative tests | P5-04, P5-08 | B |
| P5-15 | Verified links and richer profile fields | DNS/HTTPS challenge; revalidation/revoke; moderation; typed fields; visibility; export/delete; extension rendering contract; non-endorsement UX | Challenge takeover/expiry tests; SSRF-safe verifier; sanitization/privacy tests; authorization-independence tests; browser evidence | Accepted profile/custom fields | B |
| P5-16 | Safety, accessibility, performance, and closeout | Supply-chain/security review; extension privacy; authorization audit; auth hardening; shared-component accessibility; SEO exclusions; budgets; telemetry; runbooks; staged rollout; evidence index | Full suite; migration/rollback/backup rehearsals; adversarial review; no critical/high defects; Gate A and final product-owner acceptance | All applicable workstreams | A/B |

## 7. Recommended execution sequence

### Milestone 0 — Close Phase 4 and lock the Phase 5 trust boundaries

- Review Phase 4 Gate A/Gate B evidence against the Phase 4 plan and create the carryover ledger.
- Inventory every current capability check, role string check, moderator assignment, API scope, plugin hook, theme path, provider callback, credential/recovery path, invitation/registration setting, and high-impact admin action.
- Capture current role/assignment and plugin/provider data on a representative populated fixture.
- Approve package types and trust classes; define what may run in-process, remotely, declaratively, or in the isolated worker.
- Approve registry trust roots, signing-key custody, key-rotation ceremony, advisory authority, emergency disable policy, and offline/stale metadata behavior.
- Approve the public permission taxonomy, high-risk data classes, non-delegable capabilities, and human-readable consent vocabulary.
- Approve the isolation profile and host support matrix. Define the exact fail-closed result for unsupported Linux/VPS configurations.
- Approve the WebAuthn RP ID, allowed origins, recovery policy, privileged-auth policy, and browser support matrix.
- Approve generic OIDC, the first additional provider, invitation defaults, and profile-link verification methods.
- Lock numeric budgets and feature flags for catalogue fetch, install/update, themes, remote apps, sandbox workers, custom roles, approval workflows, passkeys, each provider, invitations, service principals, and verified links.

**Exit gate:** Phase 4 is formally closed or explicitly re-scoped; Phase 5 trust boundaries, owners, policies, schemas, budgets, evidence targets, and rollback controls are approved.

### Milestone 1 — Schema reconciliation and compatibility architecture

- Reconcile the deployed Phase 3/4 `plugins`, `api_tokens`, webhooks, provider identities, TOTP/recovery, user roles, moderator scopes, settings, custom fields, and audit targets against `SCHEMA.md`.
- Design the package registry/install schema, permission grants, releases, advisories, trust roots, extension storage, jobs, and history.
- Design protected roles, capabilities, assignments, group grants, approvals, access reviews, and owner records.
- Design WebAuthn credentials/challenges and provider registry/configuration; replace the provider enum safely.
- Design invitations, service principals/app installations, and verified profile links.
- Define dual-write/shadow-read stages for role migration and a compatibility fallback that preserves accepted access.
- Define the package rollback model before allowing package-owned data changes.
- Add clean-install and populated-upgrade migration tests before feature code depends on the new shape.

**Exit gate:** No Gate A feature depends on an undocumented table/column; migration, compatibility, trust, and rollback designs pass security and data-integrity review.

### Milestone 2 — Registry, signed packages, and safe themes

- Implement registry/source identity, signed expiring snapshots, trust roots, immutable releases, digest verification, compatibility resolution, source pinning, and advisory status.
- Build package catalogue/detail and an install-plan service that resolves exact artifacts without executing them.
- Implement manifest validation, permission/data/network summaries, license/dependency display, and update permission diff.
- Implement token/asset theme packages with isolated preview, asset scanning, contrast/accessibility validation, deterministic build/cache keys, and safe-mode rollback.
- Add package history and local emergency blocklist independent of registry availability.
- Exercise registry outage, stale snapshot, key rotation, revoked digest, publisher key compromise, and dependency-confusion fixtures.

**Exit gate:** An operator can inspect and preview a signed declarative theme package, but tampered, stale, incompatible, source-switched, or revoked artifacts cannot install or execute.

### Milestone 3 — Public declarative/remote package lifecycle

- Implement install, enable, disable, pin, update, rollback, export, uninstall, health, and audit for declarative themes, declarative automations, and remote integrations.
- Map remote apps to accepted webhook and API scopes with independent service credentials; do not reuse human Admin tokens.
- Require explicit re-consent for permission/data/network/job expansion.
- Implement package settings validation, secret storage, remote endpoint health, rate limits, data-retention declaration, and uninstall cleanup.
- Establish the first maintainer-operated submission/review workflow tied to exact digests.
- Publish sample packages that prove the manifest, permission, theme, remote-app, and rollback contracts.

**Exit gate:** A reviewed public package can be installed and removed without core-file changes, privilege ambiguity, secret leakage, or loss of core availability.

### Milestone 4 — Database-backed roles and scoped assignments

- Seed protected system roles and the capability catalogue.
- Import `users.role`, site-wide moderators, `board_moderators`, category scopes, and existing special grants into the new assignment model without broadening access.
- Translate `boards.post_min_role` to the board-scoped post capability / minimum-role-rank check (mapping `user`/`moderator`/`admin` to the seeded system-role IDs); carry it in the shadow-mode parity corpus and switch board post-gating to the capability path only after parity, preserving the enum as a rollback source.
- Run the new resolver in shadow mode beside the accepted resolver; record every mismatch and classify it before enforcement.
- Build role creation/edit/clone, capability selection, risk labeling, assignment scope, effective dates, expiry, impact preview, and audit.
- Build the permission simulator on the real resolver with safe target redaction.
- Implement cache/version invalidation and direct-request tests for assign, revoke, expiry, board move, category move, account-state change, and private membership change.
- Protect the final owner/admin and provide a server-side emergency fallback to system roles.

**Exit gate:** The new resolver matches accepted access on the full parity corpus; custom roles can delegate narrow scope without self-escalation, stale privilege, or loss of owner recovery.

### Milestone 5 — Passkeys, generic OIDC, and invitations

- Implement WebAuthn challenge and credential repositories, registration, login, naming, listing, removal, step-up, audit, rate limits, and recovery integration.
- Pilot passkeys with staff accounts and multiple authenticators, including synced and device-bound credentials.
- Add generic OIDC discovery/JWKS/configuration, encrypted secrets, claim mapping, health/test flow, and provider disable/fallback.
- Migrate existing identities from the fixed provider enum to provider-registry identifiers and prove no duplicates/orphans.
- Add the selected additional provider through configuration or a reviewed package using the same normalized identity contract.
- Add invitation create/revoke/redeem, email/domain binding, expiry/use limits, registration integration, and anti-abuse.
- Verify last-login-method and final-owner recovery protections across passkeys, passwords, TOTP, providers, and invitations.

**Exit gate:** Staff can use passkeys and the new provider safely; existing identities remain intact; invitations admit eligible users exactly once and cannot grant privileged authority by themselves.

### Milestone 6 — Gate A hardening, staged release, and acceptance

- Complete supply-chain, permission, account-takeover, invitation, theme-phishing, secret-handling, and role-escalation threat-model tests.
- Complete accessibility and no-JS checks for catalogue, package consent, role management, permission simulation, passkey fallback, provider settings, and invitations.
- Measure registry, signature, install/update, role-resolution, passkey, provider, invitation, and audit budgets on production-like fixtures.
- Rehearse registry outage, trust-root rotation, digest revocation, theme safe mode, package rollback, resolver fallback, owner recovery, provider disable, passkey recovery, and backup restore.
- Roll out Gate A according to §13 and record product-owner acceptance.

**Exit gate:** Gate A is accepted in production with no critical/high supply-chain, privilege, identity, theme, or recovery defect.

### Milestone 7 — Isolated server extensions and SDK

- Build the host capability probe and isolation adapter under a dedicated OS identity.
- Build immutable package images, isolated workspaces, brokered RPC, extension storage, outbound HTTP broker, event/job delivery, quotas, metering, timeout, circuit breaker, and quarantine.
- Prove no direct DB, environment-secret, session, core-file, executable-path, or arbitrary network access.
- Add durable event identity, idempotency, replay bounds, backpressure, and worker-restart recovery.
- Publish the versioned SDK, conformance suite, sample packages, compatibility policy, and deprecation guidance.
- Prevent public extensions from registering synchronous authorization/authentication/core-render dependencies.

**Exit gate:** A hostile test package cannot cross the approved boundary; a crash, fork/process attempt, resource bomb, malformed response, or denied broker call cannot interrupt the core forum.

### Milestone 8 — Governance maturity, publisher operations, and identity assurance

- Build publisher account/signing-key lifecycle, submission, automated tests, manual review, release channels, signed publication, advisory, revocation, and re-review/appeal.
- Add operator groups, group assignments, access requests, temporary elevation, renewal, scheduled access review, and remediation.
- Add approval policies for protected/high-risk changes with no-self-approval and owner safeguards.
- Add service principals/app installations, scoped credentials, rotation, suspension, rate limits, audit, and uninstall lifecycle.
- Add usernameless/passkey-first flow and privileged-capability MFA policy after Gate A credential/recovery evidence is stable.
- Add reviewed provider packages, provider migration/health/key-rotation behavior, verified profile links, and richer typed custom fields.
- Add restricted theme stylesheet modules only after AST, CSP, anti-phishing, accessibility, and safe-mode acceptance.

**Exit gate:** Publisher/review and governance operations are auditable and recoverable; sandboxed code, delegated privilege, advanced identity, and profile verification pass their independent security gates.

### Milestone 9 — Phase 5 release candidate and formal closeout

- Run the complete Phase 1–5 regression suite and route/permission matrix, including old/new resolver parity and extension-disabled operation.
- Rehearse clean install, supported historical upgrades, role import, permission-cache invalidation, owner recovery, registry/signing-key rotation, package revoke/rollback, sandbox disable, worker pause/replay, passkey/provider recovery, invitation pause, service-token revoke, theme safe mode, and backup restore.
- Reconcile `README.md`, `DESIGN.md`, `SCHEMA.md`, surface documents, route/capability inventory, extension SDK, review policy, runbooks, changelog, and completion evidence with the deployed product.
- Record all accepted Gate A/Gate B requirements and every explicit deferral.
- Capture post-release adoption, incident, privilege, authentication, and host-capacity baselines for Phase 6 and Phase 7 decisions.

**Exit gate:** The Phase 5 evidence index and product-owner closeout are recorded; no hidden ecosystem, identity, or governance obligation remains under an ambiguous “later” label.

## 8. Data and migration plan

### 8.1 Existing tables and behavior to verify before reuse

Phase 5 must verify actual deployment and accepted behavior for:

- `users`, account state, password/email verification, TOTP/recovery, sessions/devices, deactivation/deletion, and final-admin safeguards;
- `oauth_identities`, provider-specific data, linking/unlinking, verified-email collision handling, and sole-login-method protection;
- `plugins`, manifest/capabilities, lifecycle, configuration, compatibility, migrations, error/disable state, scheduled jobs, and health;
- webhook endpoints/delivery attempts, `api_tokens`, their scopes, expiry/revocation, and audit;
- `settings`, encrypted or protected secrets, branding/theme tokens, retro/custom-CSS safe mode, and asset cache behavior;
- `board_moderators`, category moderator assignments, `users.role`, `boards.post_min_role`, private-board membership, role checks, capability checks, and permission caches;
- `moderation_log`/audit coverage for settings, roles, providers, extensions, tokens, secrets, and account recovery;
- custom profile fields, user profile values, avatar/signature assets, export/delete behavior, and visibility rules.

A table or column appearing in `SCHEMA.md` or an earlier plan is not evidence that its migration, data, indexes, service behavior, or acceptance tests shipped.

### 8.2 Schema gaps that must be resolved at Milestone 1

1. **Registry sources and trust roots.** Define registries, canonical source IDs, base URLs, signing roots, key versions, validity windows, revocation state, last successful snapshot, and local block/override history. Trust-root private keys do not belong in the application database.
2. **Packages, releases, and publishers.** Replace or extend the existing `plugins` row with package namespace/type, publisher, immutable releases, digest, source, license, compatibility, manifest, review state, trust class, release channel, and advisory status. Keep installation state separate from registry metadata.
3. **Installed-package history.** Define install/update/pin/rollback/enable/disable/quarantine/uninstall events, actor, prior/new version and digest, permission snapshot, approval reference, failure stage, and timestamps.
4. **Package permissions.** Normalize declared and granted permissions, data classes, outbound hosts, jobs, broker services, and risk class. An update must preserve the exact prior grant until re-consent is complete.
5. **Review and advisories.** Define submissions, build artifacts, automated checks, manual reviews, findings, reviewer decisions, publisher keys, releases, advisories, revocations, and append-only publication history. The public registry service may own some records, but the local install must cache the signed evidence it relied on.
6. **Extension state and jobs.** Define quota-limited key/value or document storage, schema/version, export/retention state, event deliveries, attempts, scheduled jobs, leases, idempotency keys, resource metrics, quarantine, and cleanup. Public packages do not receive arbitrary core-table migrations.
7. **Theme packages.** Define theme package/release, token schema version, asset references/digests, constrained stylesheet modules, preview/build state, validation results, active/default version, last-known-good, and safe-mode bypass.
8. **Capabilities and roles.** Define a capability registry with namespace, scope type, risk class, delegability, source/core version, and retirement state; roles with protected/system/custom state; and role-capability mappings. Core capability meaning remains code-owned.
9. **Role assignments.** Define subject user or group, role, scope type/id, grantor, starts/ends, reason, approval, revoked state, and assignment version. Enforce one logical active assignment per approved key or define deterministic union behavior.
10. **Governance groups.** Define operator groups, membership, membership role, starts/ends, group assignments, owner, and audit. Group names are administrative and do not create tenant boundaries.
11. **Approval workflows.** Define request type, target payload digest, requester, required policy, eligible approvers, decisions, quorum, expiry, applied result, and immutable history. Approved data must be bound to the payload actually executed.
12. **Access reviews.** Define review campaign, scope, reviewer, assignments included, evidence snapshot, keep/revoke/modify decision, due/completed state, and remediation linkage.
13. **Protected owner authority.** Define one or more protected owner records, recovery status, and transfer history without introducing a cosmetic public role. The last active owner invariant must be enforceable transactionally.
14. **WebAuthn challenges and credentials.** Define credential ID, user, public key/COSE data, sign count, AAGUID where retained, transports, discoverable/backup flags where available, nickname, created/last-used/revoked timestamps, and challenge purpose, session/user binding, expiry, and one-time use.
15. **Provider registry and configuration.** Change `oauth_identities.provider` from a fixed enum to a stable string or provider FK; define issuer/configuration, protocol/type, discovery/JWKS cache, encrypted client secret reference, claim mapping, enabled/health state, and migration aliases. Identity uniqueness must include provider configuration/issuer plus stable subject as approved.
16. **Invitations.** Define token hash, creator, intended email/domain, registration/onboarding grant, max uses, used count, expires/revoked state, accepted user, and audit. Privileged grants must not be encoded as an unaudited token claim.
17. **Service principals and app installations.** Define package/app installation, service principal, granted scopes/capabilities, tenant/install scope (single site), credentials, callback/webhook keys, created/rotated/revoked timestamps, last used, and actor attribution.
18. **Verified profile links.** Define user, URL/host, challenge method, challenge hash/token reference, verification time, expiry/recheck, failure/revoke state, and moderation state. Never store a reusable DNS/HTTP proof secret in plaintext after verification.
19. **Richer custom fields.** Extend field definitions with approved type, validation schema, visibility options, extension owner, version, and retirement/migration behavior; extend values with normalized/sanitized representation and export/delete handling.
20. **Audit target coverage.** Ensure extension packages/releases, trust roots, publisher/review decisions, role definitions/assignments, groups, approvals, passkeys, providers, invitations, service principals, verified links, and recovery events can be represented without lossy free-text targets.

### 8.3 Recommended migration groups

Apply additive migrations in dependency order with all corresponding features disabled initially:

1. Registry sources, trust roots, publishers, packages, releases, installed-package metadata, permissions, history, and advisories.
2. Theme package, asset, validation, build, and last-known-good state.
3. Capability registry, protected roles, role-capability mappings, user assignments, assignment versions, and owner protection.
4. WebAuthn challenges/credentials and provider registry/configuration; widen/migrate provider identity keys.
5. Invitations and registration-policy linkage.
6. Extension storage, durable event/job deliveries, broker grants, resource/quarantine state, and service principals/app installations.
7. Governance groups, approval requests/decisions, temporary grants, and access-review campaigns.
8. Verified profile links and richer custom-field schema.

Each group must pass clean-install, populated-upgrade, rollback-compatibility, backup/restore, and feature-disabled application tests before dependent behavior is enabled.

### 8.4 Upgrade and backfill rules

- Existing `plugins` rows migrate to installed packages with an explicit local/first-party/vetted trust class; migration never relabels them as public-registry reviewed.
- Existing package files are hashed and inventoried. A mismatch between recorded version and local bytes is surfaced for operator review, not silently blessed.
- Existing custom CSS remains an operator-local configuration. It is not automatically published, signed, or converted into a public theme package.
- Existing themes become protected local/system themes with deterministic IDs and last-known-good state.
- Seed protected roles that exactly reproduce accepted Guest/User/Moderator/Admin behavior. Import user and moderator assignments, then shadow-evaluate before switching authority.
- Preserve `users.role`, `boards.post_min_role`, `board_moderators`, and category-scope data through Phase 5 as rollback/compatibility sources; do not drop them in the same release that changes the resolver.
- Role migration must not broaden a board/category assignment to site scope. Any ambiguous legacy grant is held as unresolved (non-enforcing) and blocks enforcement for that record until resolved.
- Existing API tokens remain human-created tokens until explicitly migrated. Do not silently convert them into service principals or attach them to a public app.
- Existing Google/Apple/GitHub identity rows map deterministically to provider-registry records. Stable provider user IDs, email/relay values, verification status, and last-login timestamps are preserved.
- Widening a provider enum/string happens before new provider values are enabled. Old application versions used for rollback must tolerate the new values or provider expansion remains disabled until the rollback window closes.
- Passkeys are opt-in; there is no credential backfill. Privileged MFA enforcement begins only after enrollment and recovery grace criteria are met.
- Invitations start empty. Existing users are never reclassified as invited or approved retroactively.
- Existing custom profile fields migrate to typed definitions where unambiguous. Unknown/unsafe legacy values remain readable through a safe fallback and require review before editing or public display.
- Existing package/provider secrets are migrated into the approved encrypted secret service without plaintext logging or export. Rotation is preferred where old provenance is uncertain.
- No Phase 1–4 table/column is dropped in the same release that introduces its replacement.

### 8.5 Transactional and consistency invariants

- A package installation references one exact registry, publisher, release, digest, manifest, review decision, permission request, and approval payload. The enabled package cannot differ from the reviewed bytes.
- Permission consent and the installed grant snapshot commit together. A package does not execute with newly requested permissions before re-consent/approval completes.
- A revoked or locally blocked digest cannot be newly enabled; emergency disable updates execution eligibility and audit atomically.
- A package update either activates the new version with its compatible extension-state version or leaves the previous version active. A failed update cannot strand a half-enabled package.
- Extension event deliveries have one durable logical event ID per package/handler and bounded retries. Worker replay cannot create duplicate logical side effects where the broker exposes an idempotent operation.
- Public extension storage is attributable to package/install and quota. Uninstall/export/retention state cannot physically delete data before the approved rollback or legal-retention window.
- A theme becomes active only after package verification, validation, build, asset availability, and last-known-good capture succeed.
- A role change and its capability mapping version/audit record commit together. An assignment never references an unpublished or deleted role version.
- A scoped assignment is authorized by the grantor at the same or broader scope. The grantor cannot grant a capability they do not possess or one marked non-delegable.
- Temporary assignment expiry is enforced by the resolver even if cleanup/notification jobs are delayed.
- Protected-owner transfer creates the successor’s active, recoverable authority before removing the predecessor. The transaction cannot leave zero active owners.
- An approval decision is bound to an immutable target payload digest. Editing the role, package version, permissions, provider config, or recovery request invalidates prior approvals.
- One actor cannot satisfy both requester and required independent approver roles for a no-self-approval policy.
- A WebAuthn challenge is consumed exactly once with the expected user/session/purpose, origin, RP ID, and credential operation.
- Registering a passkey stores only public credential material after successful verification. Removing a credential cannot violate last-method or final-owner recovery invariants.
- An identity-provider callback links or creates at most one local account for the provider configuration/issuer and stable subject. Email collision never auto-merges.
- Disabling a provider does not delete identities or secrets needed for rollback/recovery until the approved retention window.
- Invitation redemption increments/consumes the token and creates/links the account/onboarding grant atomically. Concurrent redemption cannot exceed `max_uses`.
- Service-principal credential creation shows plaintext once and stores only a hash or encrypted secret as appropriate; revoke is effective on every request immediately enough to meet the approved budget.
- Verified-link challenge completion records the exact normalized resource, method, proof, and expiry. A changed host/path or failed recheck cannot remain displayed as current verification.

## 9. Critical acceptance scenarios

| Area | Scenario and expected result |
|---|---|
| Phase ownership | An incomplete Phase 4 plugin, TOTP, provider, theme, or custom-field requirement appears in the carryover ledger and is not marked complete merely because Phase 5 begins. |
| Registry signature | A valid signed snapshot and package digest verify; one changed byte, wrong source, wrong key, expired metadata, or untrusted key causes install/update rejection. |
| Source pinning | A dependency/package with the same name on another registry cannot replace the pinned source or satisfy an update. |
| Key rotation | A registry rotates from an approved old key to an approved new key through the signed transition; an attacker-supplied new key is rejected. |
| Revoked release | A revoked digest cannot be newly installed or enabled; the operator sees reason, affected version, safe action, and audit. Existing execution follows the approved emergency policy. |
| Registry outage | Existing pinned packages and core forum continue to run; cached signed metadata may be viewed within policy; new unverifiable installs/updates fail safely. |
| Manifest validation | Unknown required fields, invalid capability names, undeclared jobs/network hosts, incompatible core range, or malformed settings schema reject the package before files are activated. |
| Permission increase | An update that adds private-content access, outbound hosts, jobs, or write capability remains staged until fresh consent and required approval; the old version keeps its old grant only. |
| Permission reduction | Removing a permission takes effect immediately on activation and cannot remain authorized through a stale broker or role cache. |
| Tampered local files | Changing installed package bytes produces a digest/health failure and quarantine or disable according to policy; the modified package is not reported as reviewed. |
| Install rollback | A failed validation, settings write, remote-app handshake, or state migration leaves the previous package/version active and records the exact failure stage. |
| Uninstall/data | Uninstall disables execution first, offers/creates the approved export, observes retention, removes credentials/jobs, and cannot leave an active service principal. |
| Theme safety | A theme containing JS, PHP, remote fonts, `@import`, external tracking URLs, disallowed selectors/properties, or executable media is rejected. |
| Theme phishing | A stylesheet attempting to hide/replace login, MFA, permission consent, warnings, or safe-mode controls fails policy/review; safe mode restores the system theme independently. |
| Theme accessibility | Token/style package that causes approved contrast/focus/reflow failures cannot become the default without an explicit policy that still forbids critical violations. |
| Remote app scope | A connector granted `webhook.receive` and selected read scopes cannot call content-mutation, user-PII, role, provider, or owner endpoints. |
| Human-token separation | A remote app cannot reuse or extract the installer’s browser session/API token; audit identifies its service principal. |
| Sandbox unsupported | On a host failing the isolation capability probe, public server-extension install/enable is unavailable while declarative themes/remote apps and core forum remain functional. |
| Sandbox database | A hostile package cannot open the core DB socket/credentials, read environment variables, or call PDO against application tables. |
| Sandbox filesystem | A package cannot read core source/config/session/upload files, write executable/public paths, escape its workspace, or follow symlinks outside its allowed tree. |
| Sandbox network | Network is denied by default; only approved hosts/schemes/ports through the broker work; private/metadata/redirect/DNS-rebinding targets remain blocked. |
| Sandbox resources | Infinite loop, memory allocation, fork/process attempt, output flood, disk fill, or slow request hits quota/timeout and quarantines or disables the package without harming core availability. |
| Sandbox crash | Worker/package crash records correlation and health state, retries only idempotent work, trips the circuit breaker, and never breaks login/read/post/moderation routes. |
| Broker authorization | A package call names the package/install, permission, target, and actor context; missing/wrong scope is denied even if the package declared the capability. |
| Event replay | Worker restart/redelivery preserves event identity; duplicate broker writes are prevented or safely deduplicated. |
| Review digest | Reviewer approval for digest A cannot publish digest B under the same version. Any byte change creates a new submission/review. |
| Publisher compromise | Revoking a publisher key blocks future releases, identifies affected installed versions, supports safe pin/disable, and does not erase historical provenance. |
| Built-in role parity | Migrated Guest/User/Moderator/Admin behavior matches the accepted resolver for every route, state, object, and scope fixture before enforcement switches. |
| Custom role scope | A “Board Helper” with selected capabilities on Board A can perform only those actions on A and is denied on Board B, category siblings, private content, and site settings. |
| Non-delegable capability | A custom role, plugin capability, group assignment, API request, or approval cannot grant site ownership, trust-root management, break-glass, signature override, or other protected powers. |
| Grantor authority | A board-scoped manager cannot assign a site-scoped role or a capability they do not hold; direct HTTP requests fail and are audited. |
| State precedence | Suspended/banned users with custom roles, group grants, or temporary elevation remain subject to the accepted state gate. |
| Private read gate | A custom role lacking explicit accepted private-board membership/read authority receives no title, count, simulator detail, search hit, notification, or extension payload for that content. |
| Temporary grant | A grant works at the start time, stops by the approved expiry tolerance without waiting for cleanup, and cannot persist through cache/session/API-token reuse. |
| Role edit | Removing a capability from a role revokes it for all assignments after one version/cache transition; impact count and audit match the affected principals. |
| Permission simulator | Simulation and real direct request agree; an out-of-scope simulator actor sees a generic target and no protected content. |
| Last owner | Attempts to deactivate, delete, demote, revoke all credentials from, or transfer away the final protected owner are blocked until a recoverable successor exists. |
| No self-approval | An actor requesting their own privileged role, ownership transfer, package trust override, or recovery cannot count as the independent approver. |
| Approval payload | Approval for package v1 or role capability set A does not authorize v2 or set B after the request changes. |
| Access review | Review lists the exact active assignments at snapshot time; keep/revoke/modify decisions produce auditable remediation and do not silently alter unrelated grants. |
| Passkey registration | Valid challenge/origin/RP/signature/user verification creates one credential; replay, cross-account challenge, wrong origin/RP, duplicate credential, or stale challenge is rejected. |
| Passkey sign-in | Registered credential signs in the correct account; unknown credential and altered signature fail without revealing account information. |
| Synced counter | A valid synced passkey with a non-increasing counter triggers a risk/audit signal but follows the approved review policy rather than an automatic unrecoverable ban. |
| Passkey removal | A user can revoke one credential; removal of the last usable method or the final owner’s recovery path is blocked until another method is established. |
| Passkey fallback | Unsupported browser, disabled JavaScript, authenticator cancellation, or platform outage leaves clear password/provider/TOTP/recovery alternatives according to policy. |
| Privileged MFA | A user receiving a sensitive role gets a grace notice; after grace, privileged actions require passkey or TOTP while ordinary permitted member actions follow the approved policy. |
| RP/domain change | Changing the site hostname does not silently make credentials appear corrupt; the runbook warns, preserves old access during migration where possible, and provides recovery. |
| OIDC issuer mix-up | Callback token from another issuer/config, wrong audience/client, invalid nonce/state/PKCE, stale time claims, or untrusted JWKS is rejected. |
| OIDC key rotation | Valid provider JWKS rotation succeeds through issuer-pinned refresh; attacker-controlled key URL/redirect does not. |
| Provider collision | A verified email matching an existing account still requires proof/linking; unverified/no-email claims never auto-merge. |
| Provider migration | Existing Google/Apple/GitHub users log into the same local accounts after provider-registry migration; no duplicate identity or ban bypass appears. |
| Provider disable | Operator sees affected sole-method accounts before disable; existing identities are retained; users receive approved fallback/recovery guidance. |
| Invitation replay | First valid redemption succeeds; concurrent/repeated use beyond the approved limit fails without duplicate account or grant. |
| Invitation binding | Wrong email/domain, expired, revoked, guessed, or altered token fails; raw token is absent from DB/logs. |
| Invitation privilege | Editing a token/request or direct POST cannot convert ordinary invitation onboarding into Admin/owner/protected capability. |
| Service principal | App credential can use only granted scopes, cannot open a human session, stops after revoke/expiry, and leaves package/install attribution in audit. |
| Verified link | Correct DNS/HTTPS challenge verifies the normalized resource; removing/changing proof causes expiry/revocation; verification does not imply platform endorsement. |
| Profile authority separation | A verified link, custom field, badge, title, reputation value, or extension-rendered claim cannot satisfy any capability check. |
| Backup/restore | Backup restores registry pins/evidence, installed digests, role/assignment state, passkey public credentials, providers, invitations, service principals, audit, extension state, and last-known-good theme without restoring revoked plaintext secrets. |
| Global emergency disable | Operator can disable all public extension execution and package themes through a server-side control while retaining core auth, reading, posting, moderation, and recovery. |

## 10. Test and evidence policy

### 10.1 Required test layers

- **Unit tests:** manifest/schema validation; canonical package IDs; digest/signature/trust-chain verification; registry freshness; compatibility/dependency resolution; permission diff/risk classification; theme token/CSS policy; role/capability union and scope logic; expiry; approval quorum; WebAuthn parsing/signature policy; OIDC claim validation; invitation token policy; verified-link normalization.
- **Repository/service integration tests:** migrations; package/release/history; install/update/rollback; extension state/jobs; roles/assignments/groups; approvals/access reviews; WebAuthn credentials/challenges; provider migration/configuration; invitations; service principals; verified links/custom fields.
- **Authorization tests:** every core and extension-exposed route as Guest, User, suspended, banned, built-in Moderator/Admin, each custom role, group member, expired grant, service principal, out-of-scope actor, and protected owner.
- **Supply-chain/security tests:** package tampering, signature/key rotation/revocation, stale metadata, dependency confusion, malicious archives/symlinks, manifest lies, permission expansion, malicious CSS/assets, registry compromise simulation, publisher-key compromise, secret/log inspection.
- **Sandbox adversarial tests:** direct DB/file/environment/session access, path traversal, symlink escape, network/SSRF/DNS rebinding, process spawn, fork bomb, CPU/memory/disk/output exhaustion, malformed RPC, event storm, timeout, worker crash, broker privilege escalation, and sandbox-disabled host behavior.
- **Concurrency/idempotency tests:** package install/update/rollback, event replay, role assign/revoke/edit, approval decisions, owner transfer, passkey challenge consumption, provider linking, invitation redemption, service-token rotation/revoke, and verified-link challenge completion.
- **Application/HTTP tests:** all catalogue, package, theme, role, assignment, approval, passkey fallback, provider, invitation, service-app, and profile-verification routes for auth, CSRF, reauthentication, validation, scope, feature flags, audit, and no-JS behavior.
- **Browser tests:** package catalogue/permission consent, theme preview/safe mode, role editor/simulator, assignment/approval/access-review flows, passkey enrollment/login/fallback, provider setup/test, invitation acceptance, service-app management, verified links, responsive behavior, focus, and error recovery.
- **Worker tests:** registry refresh, advisory evaluation, extension event/job delivery, sandbox execution, resource quarantine, temporary-grant expiry notification/cleanup, access-review scheduling, provider health/JWKS refresh, invitation expiry cleanup, verified-link recheck.
- **Performance tests:** registry and signature throughput, package install/update, resolver/cache latency, assignment changes, audit volume, sandbox queue/broker overhead, passkey authentication, OIDC/JWKS behavior, invitation redemption, and large installed-package/role fixtures.
- **Accessibility evidence:** automated scans plus manual keyboard, focus, screen-reader-critical, zoom/reflow, reduced-motion, contrast, live-region, and mobile checks for every changed shared component.
- **Operational evidence:** clean install, populated upgrade, role shadow/parity, registry outage, trust-root rotation, publisher/package revoke, package/theme rollback, sandbox global disable, worker pause/replay, owner recovery, RP/domain change rehearsal, provider disable/secret rotation, backup/restore, and old-version rollback compatibility.

### 10.2 Evidence rules

- A package being listed in a registry is not proof of safety, compatibility, review, or acceptance.
- A signature proves byte provenance under a key; it does not prove code quality or benign behavior. Review, permissions, isolation, and runtime evidence remain separate requirements.
- A process timeout or PHP namespace is not accepted as a sandbox. Evidence must show the approved OS/process boundary and broker denial behavior on the supported host.
- UI-visible package, role, passkey, provider, invitation, and profile changes require browser evidence in addition to server-side tests.
- Authorization completion requires direct-request evidence against the real resolver; hidden controls and permission simulator output alone are insufficient.
- Resolver migration requires an archived old-versus-new parity report on the same fixture and commit.
- Passkey completion requires real supported-browser/authenticator evidence plus protocol-negative fixtures; mocked cryptography alone is insufficient.
- Provider completion requires issuer/JWKS/claim negative paths and account-resolution tests; a successful happy-path callback alone is insufficient.
- Performance claims require the same representative environment/fixture before and after change.
- Security-sensitive evidence must show that private content, message bodies, tokens, challenges, secrets, credential material, and PII are absent from ordinary logs and package telemetry.
- Every Gate A/Gate B definition-of-done item must link to a test, report, browser artifact, review record, runbook exercise, or approved policy in the evidence index.

### 10.3 Target evidence names

The implementation may use different names, but the evidence index should include equivalents of:

- `tests/Unit/Extensions/PackageManifestTest.php`
- `tests/Unit/Extensions/PackageSignatureTest.php`
- `tests/Unit/Extensions/RegistryTrustChainTest.php`
- `tests/Unit/Extensions/CompatibilityResolverTest.php`
- `tests/Unit/Extensions/PermissionDiffTest.php`
- `tests/Unit/Extensions/ThemePolicyTest.php`
- `tests/Integration/Core/AppExtensionCatalogTest.php`
- `tests/Integration/Core/AppExtensionInstallTest.php`
- `tests/Integration/Core/AppExtensionUpdateRollbackTest.php`
- `tests/Integration/Core/AppExtensionPermissionConsentTest.php`
- `tests/Integration/Core/AppThemePackageTest.php`
- `tests/Integration/Core/AppRegistryAdvisoryTest.php`
- `tests/Integration/Worker/ExtensionSandboxWorkerTest.php`
- `tests/Security/ExtensionSandboxBoundaryTest.php`
- `tests/Security/ExtensionBrokerAuthorizationTest.php`
- `tests/Integration/Worker/ExtensionEventIdempotencyTest.php`
- `tests/Integration/Core/AppPublisherReviewTest.php`
- `tests/Unit/Auth/CapabilityResolverTest.php`
- `tests/Integration/Core/AppRoleMigrationParityTest.php`
- `tests/Integration/Core/AppCustomRoleTest.php`
- `tests/Integration/Core/AppScopedRoleAssignmentTest.php`
- `tests/Integration/Core/AppTemporaryGrantExpiryTest.php`
- `tests/Integration/Core/AppPermissionSimulatorTest.php`
- `tests/Integration/Core/AppGovernanceApprovalTest.php`
- `tests/Integration/Core/AppProtectedOwnerTest.php`
- `tests/Integration/Core/AppAccessReviewTest.php`
- `tests/Unit/Auth/WebAuthnPolicyTest.php`
- `tests/Integration/Core/AppPasskeyRegistrationTest.php`
- `tests/Integration/Core/AppPasskeyLoginTest.php`
- `tests/Integration/Core/AppPasskeyRecoveryTest.php`
- `tests/Integration/Core/AppGenericOidcProviderTest.php`
- `tests/Integration/Core/AppProviderMigrationTest.php`
- `tests/Integration/Core/AppProviderAccountLinkingTest.php`
- `tests/Integration/Core/AppInvitationTest.php`
- `tests/Integration/Core/AppServicePrincipalTest.php`
- `tests/Integration/Core/AppVerifiedProfileLinkTest.php`
- migration/upgrade/rollback fixtures, package corpus, malicious package/theme corpus, resolver parity reports, sandbox host-capability reports, supported-browser WebAuthn evidence, accessibility reports, load-test reports, and backup/restore evidence

These are target evidence names, not claims that the files already exist.

## 11. Progress, metrics, observability, and operating requirements

### 11.1 Atomic progress model

Maintain one requirement ledger. Each atomic requirement has one state:

| State | Meaning |
|---|---|
| **R0 — Conflict/unowned** | Requirement is contradictory, ambiguous, or has no owner |
| **R1 — Approved** | Phase, gate, owner, schema, policy, threat model, and acceptance criteria are approved |
| **R2 — Implemented** | Code and migration are merged, normally behind a disabled flag |
| **R3 — Automatically verified** | Required unit, integration, concurrency, migration, and adversarial tests pass |
| **R4 — Release verified** | Browser, no-JS, security/privacy, supply-chain, performance, and operating evidence pass |
| **R5 — Accepted** | Enabled in the intended environment and formally accepted |

Report separately:

- **Scope coverage** = requirements at R1 or higher ÷ committed requirements.
- **Implementation coverage** = requirements at R2 or higher ÷ committed requirements.
- **Verification coverage** = requirements at R4 or higher ÷ committed requirements.
- **Acceptance coverage** = R5 requirements ÷ committed requirements.

Also report unresolved conflicts, unowned requirements, critical/high defects, approved deferrals, evidence not produced on the current commit, scope added/removed since the prior baseline, and packages/role/provider policies still operating under temporary waivers.

A gate passes only when every critical requirement is R5; every other committed requirement is R4/R5 or has a signed scope change; critical/high defects are zero; required migration, trust-root, recovery, backup, and rollback exercises pass; and product-owner acceptance is recorded. Percent averages cannot override a failed signature, owner, authorization, passkey, provider, or isolation invariant.

### 11.2 Product and governance measures

Record cohort, window, denominator, baseline, success threshold, and stretch threshold for each metric:

- **Catalogue adoption** = installations that viewed a package detail/permission summary ÷ eligible operator installations.
- **Package activation** = reviewed package installs successfully enabled ÷ install attempts, split by type and trust class.
- **Install/update quality** = successful install/update/rollback rate, median operator time, permission re-consent rate, and failure stage distribution.
- **Extension safety** = confirmed extension incidents per 1,000 active package-install months; private-data incidents and sandbox escapes target zero.
- **Extension reliability** = package disable/quarantine rate, worker timeout/error rate, and core-route impact attributable to extensions; core-route impact target zero.
- **Advisory response** = median time from signed advisory receipt to operator acknowledge/update/disable, plus affected active installs.
- **Theme adoption and recovery** = active package themes, preview-to-enable conversion, accessibility rejection rate, safe-mode use, and rollback rate.
- **Permission comprehension** = consent cancellation, permission-detail expansion, and post-install permission-revocation rates, paired with usability testing rather than optimizing for acceptance.
- **Custom-role adoption** = installations with at least one active custom role ÷ eligible installations, with roles and assignments by scope.
- **Privilege hygiene** = expired grants enforced on time, assignments with no owner/reason, protected-capability denials, orphan role definitions, and stale-access incidents; stale privilege target zero.
- **Delegation effectiveness** = eligible admin work completed by delegated roles, median request/approval time, and escalation/rework rate without broadening privilege.
- **Access-review completion** = assignments reviewed by due date ÷ assignments in review scope; track revoke/modify/keep outcomes and overdue age.
- **Passkey enrollment** = eligible active users and privileged users with at least one passkey, reported separately and without coercive engagement targets.
- **Passkey success** = successful passkey authentications ÷ passkey attempts, split by platform/synced/device-bound where observable without fingerprinting.
- **Recovery health** = passkey/provider recovery starts, successful recoveries, support interventions, time to restore access, and account-lockout incidents; unrecoverable owner lockout target zero.
- **Provider quality** = successful callbacks, new-account creation, explicit link completion, collision rate, no-email flow, issuer/JWKS failures, and sole-method exposure.
- **Invitation quality** = issued, delivered where measurable, redeemed, expired, revoked, abuse-denied, and time-to-first-post; no metric may reward indiscriminate invitation volume.
- **Verified-link quality** = verification success, recheck failure, revoke/moderation rate, and user misunderstanding reports; false implication of endorsement target zero.
- **Publisher/review operations** = submission-to-first-review time, rejection reasons, resubmission count, review defects, advisory count, and reviewer workload.

### 11.3 Numeric technical budgets

At Milestone 0, record success/failure thresholds for:

- registry snapshot fetch/verify/cache duration, maximum snapshot/package size, signature verification throughput, staleness tolerance, and key-rotation/revocation propagation;
- package plan/install/enable/update/rollback/uninstall p50/p95/p99 duration, disk growth, failure rate, and recovery time;
- theme validation/build/preview/apply duration, asset bytes, cache-bust propagation, safe-mode activation time, and accessibility defect count;
- extension event queue age, broker RPC p50/p95/p99, package CPU/memory/wall-time/output/storage, timeout/quarantine rate, worker recovery time, and core-route overhead;
- role resolver p50/p95/p99, cache hit/invalidation latency, assignment-change propagation, simulator duration, and old/new parity mismatch count;
- approval/access-review queue age, expiry-worker delay, and remediation completion time;
- WebAuthn challenge/register/authenticate p50/p95/p99, challenge-store cleanup, failure rate, rate-limit behavior, and browser-interaction error rate;
- OIDC discovery/JWKS/token callback p50/p95/p99, cache age, provider health, key-rotation recovery, and error rate;
- invitation creation/redemption latency, concurrent redemption behavior, abuse-limit hits, and cleanup backlog;
- service-principal/API request latency, token-revocation propagation, rate-limit behavior, and audit throughput;
- verified-link challenge/recheck queue age, DNS/HTTP timeout/byte/redirect caps, and failure backlog;
- database row/storage growth per package, role assignment, passkey credential, invitation, event/job, audit record, and active user;
- backup and restore duration/size for extension state, roles, credentials, provider config, and audit.

Every measurement record must include route/job, hardware class, operating-system/isolation profile, PHP and database version, data fixture, installed-package/role count, concurrency, cache state, measurement window, p50/p95/p99, query count/time, peak memory/CPU, queue age where relevant, and error rate.

### 11.4 Required telemetry

- Structured correlation IDs across registry refresh, package install/update, extension event/job, broker calls, role/assignment changes, approvals, WebAuthn ceremonies, provider callbacks, invitation redemption, service-principal calls, and verification jobs.
- Registry source/snapshot/key version, verification result, staleness, advisory count, and refresh failures without logging private keys or package secrets.
- Package ID/version/digest/trust class, permission-diff class, install/update stage, health, disable/quarantine reason, resource usage, and rollback result.
- Broker calls by package/service/permission/result/latency with private payload fields redacted; denied high-risk data access counted separately.
- Sandbox CPU/memory/time/output/storage/network denies, worker restart, circuit-breaker state, and host-capability status.
- Role/capability/assignment version, scope, grant/revoke/expiry, resolver denial reason, parity mismatch, simulator usage, and cache invalidation without logging protected target content.
- Approval request type, payload version, age, decision, quorum, and apply result; access-review due/completed/remediation counts.
- Passkey register/login/remove/step-up success/failure, credential count, risk signal, rate-limit, and recovery event without credential IDs/public keys in ordinary logs where not needed.
- Provider discovery/JWKS/token/claim/callback health, stable provider configuration ID, collision/no-email/orphan-prevention outcomes, and secret-rotation state without tokens/claims containing PII.
- Invitation issued/revoked/redeemed/expired/denied reason and creator policy without raw tokens.
- Service-principal create/rotate/revoke/use/deny and package/install attribution without plaintext credentials.
- Verified-link challenge/recheck/revoke state and normalized host/method according to privacy policy; do not log reusable proof tokens.
- Worker/cron heartbeat and last-success for registry refresh, advisory evaluation, extension jobs, expiry, access review, provider health, invitation cleanup, and link recheck.

### 11.5 Required runbooks

- Disable registry refresh or all new package installs while preserving existing pinned package operation.
- Rotate a registry trust root, revoke a compromised key, apply a local digest block, and verify advisory propagation.
- Quarantine/disable one package, one publisher, all public server extensions, or all public packages without disabling core forum functions.
- Roll back an extension or theme to the last-known-good version and reconcile extension-owned state.
- Recover from failed package install/update/uninstall and export retained extension data.
- Inspect and clear stuck extension event/job deliveries; recover worker crash, resource exhaustion, and broker outage.
- Diagnose a host that fails the sandbox capability probe and keep only safe package types enabled.
- Enter theme safe mode and restore built-in/system branding without loading package CSS/assets.
- Fall back from database-backed roles to the protected compatibility resolver; reconcile parity mismatches and repair assignment caches.
- Revoke an excessive grant immediately, expire temporary access, recover a failed approval, and complete an overdue access review.
- Recover or transfer the protected owner without self-approval, secret disclosure, or loss of audit history.
- Pause passkey enrollment/sign-in, preserve existing credentials, and guide users through approved fallback/recovery.
- Handle a lost authenticator or privileged-account recovery; investigate counter/risk anomalies without automatic destructive action.
- Change the canonical domain/RP ID with impact inventory, communications, fallback, and recovery validation.
- Disable an identity provider, rotate OIDC client secret, refresh JWKS, and identify sole-method accounts before change.
- Pause/revoke invitations and investigate token abuse without exposing token values.
- Revoke/rotate a service-principal credential and verify immediate loss of access.
- Revoke/recheck a verified profile link and remove a misleading or compromised claim.
- Restore backup and reconcile registry evidence, package bytes/digests, extension state, role assignments, owner authority, passkey public credentials, providers, invitations, service principals, themes, and audit.

## 12. Risks and controls

| Risk | Control |
|---|---|
| Public marketplace becomes an arbitrary-code upload path | Separate package types/trust classes; signed immutable releases; declarative/remote Gate A; isolated server code only in Gate B; no Console `eval` or in-process public PHP |
| Signature is mistaken for safety | Treat signature as provenance only; require permissions, automated/manual review, isolation, runtime telemetry, and advisory response |
| Registry or publisher key compromise | Offline/protected trust roots, key rotation/revocation, exact-digest approvals, transparency history, local blocklist, emergency disable, and rehearsed response |
| Dependency confusion or source substitution | Globally namespaced IDs, source pinning, immutable lock metadata, no implicit registry fallback, and dependency-resolution fixtures |
| Package update escalates permissions silently | Versioned grant snapshots, manifest diff, re-consent/approval, staged activation, and old-version pinning |
| Review approves different bytes than users install | Review and publication bound to exact digest; immutable object storage/index; installer verifies digest and signed metadata |
| “Sandbox” is not actually isolated | Approved OS/process profile, host capability probe, dedicated identity, no direct DB/files/env/network, broker-only access, and adversarial escape tests |
| Public extension breaks core requests | No synchronous core-path dependency; async events; strict timeout/quota; circuit breaker; disable-on-error; global kill switch |
| Extension exfiltrates private data | Named high-risk data permissions, enhanced review, scoped broker, payload minimization/redaction, outbound-host grants, telemetry, and revocation |
| Extension event replay causes duplicate actions | Durable event IDs, idempotency keys, bounded retries, broker-side dedupe, and replay tests |
| Extension uninstall loses or strands data | Declared data lifecycle, export, retention, disable-before-delete, quota/ownership, rollback window, and restore tests |
| Theme package performs phishing/tracking | No JS/PHP/templates/remote assets; restricted tokens/selectors/properties; CSP; anti-phishing review; preview isolation; safe mode |
| Registry outage blocks the forum | Existing packages are local and pinned; signed cache; no runtime dependency on registry; new unverifiable installs fail closed |
| Custom roles broaden authority unexpectedly | Fixed capability catalogue, protected non-delegable powers, no deny/inheritance/scripts, shadow parity, scope matrix, impact preview, and direct-request tests |
| Plugin defines dangerous core capability | Core owns capability semantics; plugin capabilities are namespaced and cannot override/alias protected core capabilities |
| Role grantor delegates more than they possess | Grant-time resolver check, same-or-narrower scope rule, approval policy, and immutable audit |
| Stale permission cache preserves revoked access | Assignment/version keys, expiry in resolver, active invalidation, low bounded TTL where used, and revoke/expiry smoke |
| Final owner/admin is locked out | Protected owner invariant, last-method checks, recovery inventory, transactional transfer, break-glass runbook, and no-self-approval |
| Approval workflow creates bureaucracy without safety | Apply only to classified high-risk actions; numeric queue-age budgets; single-owner fallback policy; review effectiveness metrics |
| Approval is replayed against changed action | Immutable payload digest, expiry, version checks, and reapproval on any material change |
| Passkey deployment strands users | Optional Gate A enrollment, multiple credentials, accepted fallback/recovery, grace periods for privileged policy, and support runbook |
| WebAuthn origin/RP misconfiguration breaks credentials | Canonical HTTPS/RP decision, config validation, startup health, domain-change runbook, and browser evidence |
| Synced passkey counter triggers false compromise | Treat counter as signal, combine with other evidence, avoid automatic permanent lockout, and document review policy |
| Passkey added to attacker-controlled session | Recent reauth/step-up, challenge bound to user/session/purpose, security notification, and credential-management audit |
| Generic OIDC is vulnerable to issuer mix-up or claim errors | Issuer/audience/nonce/state/PKCE/time validation, pinned discovery/JWKS, normalized claims, negative tests, and provider health |
| Additional provider reintroduces silent email merge | Core account-resolution service is not replaceable by provider package; stable subject key and explicit proof-to-link remain mandatory |
| Provider disable or outage orphans accounts | Sole-method inventory, last-method block, fallback guidance, retained identities, secret rotation, and staged disable |
| Invitation tokens become a privilege or spam vector | High entropy/hash-only, expiry/revoke/use limits, email/domain bind, rate limits, anti-abuse, no privileged grant, and audit |
| Service app impersonates a human Admin | Separate service principal, no browser session, explicit scopes, independent credential, audit attribution, and human-only protected capabilities |
| Verified link is mistaken for endorsement | Explicit “control verified” language, expiry/recheck, moderation/revoke, no authority effect, and usability testing |
| Single VPS is overloaded by extension workers | Numeric resource budgets, quotas, bounded concurrency, backpressure, independent worker disable, and Phase 6 capacity triggers |
| Extension ecosystem degrades accessibility | Package/theme accessibility gates, shared-component restrictions, browser/manual evidence, and disable/rollback path |
| Audit volume becomes unusable | Structured target/action taxonomy, filters/retention, correlation IDs, export, high-risk alerts, and performance budgets without deleting required history |

## 13. Staged release and rollback

### 13.1 Recommended enablement order

1. Deploy additive schema and dark services with all Phase 5 features off.
2. Seed protected roles/capabilities and run resolver shadow mode on production-like and then production traffic without changing decisions.
3. Enable registry refresh and catalogue browsing for staff only; keep install disabled while signature, source, advisory, and offline behavior are observed.
4. Enable signed token/asset theme preview for staff, then allow selected first-party/reviewed themes; retain system-theme safe mode.
5. Enable declarative/remote package install for a small operator cohort with automatic updates off; require manual pin/update/rollback.
6. Enable role editor/simulator for owners, then pilot one narrow custom role on a test board/category; compare audit and denial behavior.
7. Switch the database-backed resolver for staff/test scopes, then broader scopes only after parity mismatch is zero for critical fixtures.
8. Enable temporary grants and recent-reauthentication policy; keep approval/group/access-review features off until Gate B.
9. Enable passkey enrollment for owners/staff, then privileged users, then optional members. Do not enforce privileged MFA until recovery/grace criteria pass.
10. Enable generic OIDC in test mode, migrate existing provider identity references, then enable the selected additional provider for a cohort.
11. Enable invitation creation for Admins with short expiry/use limits, then approved member invitation policy if desired.
12. Accept Gate A before enabling public sandboxed server packages, publisher self-service, usernameless sign-in, group grants, dual approvals, access reviews, or service principals broadly.
13. Enable the sandbox host probe and isolated worker with hostile/sample packages only; then selected reviewed publishers/packages under strict quotas.
14. Enable publisher submissions/review publication, service-app installs, operator groups/approvals/access reviews, advanced passkey policy, provider packages, verified links, and richer fields independently.
15. Close Phase 5 only after every Gate B item is accepted or has an approved scope-change destination.

### 13.2 Rollback rules

- Disable catalogue install/update before changing registry trust or package metadata.
- A registry rollback never changes the recorded digest of an installed release. Pin or disable packages; do not relabel bytes.
- Global public-extension disable and theme safe mode must work without loading extension code, package assets, custom roles, or provider integrations.
- Package rollback activates only a previously verified compatible digest and preserves/handles extension state according to its declared migration/rollback contract.
- Quarantine the package before inspecting a suspected compromise; revoke service credentials and outbound grants before restoring operation.
- Role-engine rollback switches to the protected compatibility snapshot only after capturing mismatch/audit state. New custom grants become inactive under fallback rather than being approximated as Admin.
- Never drop protected roles, owner records, old role columns, moderator assignments, provider identifiers, or credentials during the Phase 5 rollback window.
- Revoking a role/assignment or service credential is safer than rolling back schema; apply revocation first.
- Passkey rollback disables new ceremonies or passkey-first UX but preserves credential rows for later recovery; existing password/provider/TOTP paths remain available according to policy.
- Provider rollback disables the configuration without deleting identity mappings or secret versions required to restore service.
- Invitation rollback pauses issuance/redemption; preserve hashes/audit for investigation and expiry.
- Theme rollback enters safe mode and restores last-known-good system/local branding before code rollback.
- Keep Phase 5 migrations additive through the phase; application rollback targets must tolerate new tables/columns and widened provider values.
- Restore from backup only for proven corruption or unrecoverable loss; package disable, resolver fallback, credential/provider disable, and repair commands are the first response to logic defects.
- After rollback, rerun core route/permission, owner recovery, package-disabled, provider fallback, invitation denial, audit, worker-health, and backup-integrity smoke tests.

## 14. Release checklist

### Gate A

- [ ] Phase 4 acceptance or explicit deferrals are recorded.
- [ ] Carryover ledger, trust models, package classes, review policy, permission taxonomy, protected capabilities, RP ID/origins, provider/invitation decisions, evidence map, owners, and numeric budgets are approved.
- [ ] Deployed schema is reconciled with `SCHEMA.md`; all Gate A migrations pass clean-install, populated-upgrade, rollback-compatibility, and backup/restore tests.
- [ ] Registry source identity, signed/expiring snapshots, trust roots, key rotation/revocation, immutable digests, source pinning, compatibility, advisories, and offline behavior pass.
- [ ] Manifest v2, package types, permission/data/network/job declarations, settings schema, lifecycle, and human-readable risk summary pass.
- [ ] Tampered, stale, incompatible, source-switched, revoked, or unreviewed packages fail closed.
- [ ] Declarative themes pass package verification, asset scanning, preview isolation, contrast/accessibility, CSP/cache, safe-mode, and rollback checks.
- [ ] Declarative/remote integration install, consent, pin/update/rollback, disable, export/uninstall, secret handling, scope, and outage tests pass.
- [ ] Exact-digest maintainer review, automated evidence, manual approval, publication, advisory, and emergency-disable foundation passes.
- [ ] Protected built-in roles/capabilities are seeded and old-versus-new resolver parity is complete for critical routes/states/scopes.
- [ ] Custom role creation, scope, grantor authority, temporary expiry, cache invalidation, audit, simulator, direct-request denial, and compatibility fallback pass.
- [ ] Protected owner/last-admin and recent-reauthentication safeguards pass.
- [ ] Passkey registration/login/list/remove/step-up/fallback/recovery/origin/RP/replay/rate-limit/synced-counter scenarios pass on the supported browser matrix.
- [ ] Provider registry migration preserves Google/Apple/GitHub identities and generic OIDC passes issuer/JWKS/nonce/state/PKCE/audience/claim/collision/outage tests.
- [ ] The selected additional provider works through the normalized identity contract.
- [ ] Invitation create/revoke/redeem/bind/expire/use-limit/abuse/no-privilege-escalation tests pass.
- [ ] Accessibility, responsive, keyboard, screen-reader-critical, and no-JS evidence is complete for Gate A surfaces.
- [ ] Registry/signature/install/theme/role/passkey/provider/invitation budgets pass on production-like fixtures.
- [ ] Trust-root rotation, package revoke/rollback, registry outage, theme safe mode, resolver fallback, owner recovery, passkey recovery, provider disable, invitation pause, and backup/restore runbooks are rehearsed.
- [ ] Full Phase 1–4 regression and route-permission matrix remains green with all public packages disabled.
- [ ] No critical/high defects remain.
- [ ] README, changelog, schema, capability catalogue, review policy, runbooks, and evidence index are updated.
- [ ] Gate A product-owner acceptance is recorded.

### Gate B and phase close

- [ ] Host capability probe and isolation adapter pass supported/unsupported environment behavior.
- [ ] Public server extensions pass direct DB/file/env/session/network denial, broker authorization, resource quota, timeout, crash, event replay, quarantine, and core-path isolation tests.
- [ ] Extension SDK, versioned broker/events, storage, jobs, outbound HTTP, compatibility/deprecation, samples, and conformance suite pass.
- [ ] Publisher identity/key lifecycle, submission, automated checks, manual review, exact-digest publication, channels, advisory, key compromise, revoke/yank, transparency history, and re-review/appeal pass.
- [ ] Restricted public theme stylesheet modules pass AST/CSP/external-request/anti-phishing/accessibility/preview/safe-mode checks, or are formally re-scoped while token/asset packages remain accepted.
- [ ] Operator groups, group assignments, access requests, temporary elevation, approval policy, no-self-approval, ownership transfer, access reviews, and remediation pass.
- [ ] Service principals/app installations pass independent identity, scope, credential, rotation/revoke, rate-limit, callback/webhook, audit, uninstall, and no-human-impersonation checks.
- [ ] Usernameless/passkey-first and privileged MFA policy pass fallback, grace, recovery, owner, and supported-browser checks.
- [ ] Reviewed provider packages and provider migration/health/key-rotation behavior pass, or each omitted provider target is formally re-scoped.
- [ ] Verified profile links and richer typed fields pass proof, recheck/expiry, privacy, moderation, export/delete, sanitization, and no-authority-effect tests.
- [ ] Every Gate B omission has an approved roadmap destination rather than a silent omission.
- [ ] Full Phase 5 regression, supply-chain, sandbox, authorization, identity, privacy, accessibility, load, migration, backup, rollback, and operating evidence is indexed.
- [ ] Final product/governance metrics and Phase 6/7 triggers are recorded.
- [ ] Phase 5 product-owner closeout is recorded.

## 15. Post-Phase 5 handoff

After Phase 5 closes, later work should be triggered by measured capacity or platform-expansion needs rather than unfinished ecosystem, identity, or governance obligations. Carry forward:

- registry fetch/signature/advisory health, package/theme adoption, install/update/rollback quality, permission acceptance/revocation, package incidents, and publisher-review workload;
- sandbox resource use, broker latency, queue age, quarantine/error rates, host compatibility, and core-route overhead;
- custom-role adoption, assignment scope/expiry, denied escalation, owner/recovery health, approval queue age, access-review completion, and stale-privilege incidents;
- passkey enrollment/authentication/recovery, privileged MFA compliance, provider quality, invitation conversion/abuse, and sole-login-method exposure;
- service-principal use, token rotation/revocation, remote-app incidents, verified-link rechecks, and profile-field moderation;
- database/query growth, audit volume, worker CPU/memory, disk/package storage, backup/restore duration, accessibility defects, and rollback incidents;
- explicit thresholds for SSE, external search, Redis, object storage/CDN, read replicas, additional workers, materialized fan-out feeds, and other Phase 6 infrastructure swaps;
- explicit product decisions for PWA/offline, native mobile, imports, multi-community/multi-tenant operation, cross-install federation, and full internationalization in Phase 7.

The intended next phase is **Phase 6 — Realtime & Scale**: capacity-triggered infrastructure evolution behind the accepted interfaces, including SSE where justified, external search, Redis, object storage/CDN, read replicas, additional workers, and feed materialization. Phase 7 remains platform expansion.

## 16. Source references

- `PHASE_4_PLAN.md` — Phase 4 closeout requirements, explicit Phase 5 handoff, product/capacity evidence, and the boundary between ecosystem work and Phase 6/7.
- `PHASE_3_PLAN.md` — accepted first-party/vetted hook runtime, plugin lifecycle, webhooks, admin API tokens, theming safe mode, TOTP/recovery, and explicit public-ecosystem/custom-role/passkey deferrals.
- `README.md` — product thesis, selected single-VPS stack, interface seams, roadmap baseline, and completion-evidence policy.
- `DESIGN.md` §§2, 6.6, 6.14, 9–14 — identity, plugin/theme direction, permissions, non-functional requirements, success metrics, phasing, and later platform systems.
- `DECISIONS.md` §§1–8 — authoritative fixed-role, plugin trust, provider, passkey, single-install, storage, search, realtime, and later-work decisions.
- `SCHEMA.md` §§1–9 — the consolidated Phase 1–3 table shapes, reconciliation decisions (§7), and foreshadowed schema (§8). **Note:** Phase 5 ecosystem/identity/governance tables (packages/manifests, custom roles/capabilities, passkey credentials, generic-OIDC provider config, invitations, service principals, verified links) are **not yet in SCHEMA.md**; the schema **requirements** are defined in §8.2; DDL is authored at Milestone 1, then folded into SCHEMA.md on acceptance. _(Fixed 2026-06-26: this plan carries schema requirements only, not DDL.)_
- `ADMIN.md` §§1–2, 5–6, 8–12 — capability catalogue/resolver, custom-role seam, invitations, theming, plugin architecture/lifecycle/security, extension points, Console UX, and public-ecosystem review dependency.
- `USER.md` §§2–3, 5, 7–9 — provider abstraction and account resolution, passkeys, security/recovery, connections, verified links, richer custom fields, and identity roadmap.
- `COMPOSER.md` and `COMMUNITY.md` — extension-visible content/community contracts, privacy and humane-design constraints, and the rule that profile/reputation/community data does not grant authority.
