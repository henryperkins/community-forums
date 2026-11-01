# Verified email change implementation plan

> **For agentic workers:** Use superpowers:executing-plans for the parent-owned
> integration and focused parallel implementation where the contracts below agree.

**Goal:** Deliver recoverable, authenticated verification of a new contact email.

**Architecture:** A dedicated service owns the transition; thin controllers reuse
signed OAuth state, native forms, current factors and the existing email outbox.

**Tech Stack:** PHP 8.2+, MySQL/MariaDB, progressive HTML/JavaScript, PHPUnit,
Playwright, existing Mailer and feature flags.

**Spec:** `docs/superpowers/specs/2026-11-01-verified-email-change-design.md`.

## Global constraints

Feature `email_change` defaults false. One-hour token TTL clamped to 300–86400
seconds. Request 5/hour; hashed destination 3/hour; confirmation 10/15 minutes.
Provider setup 10/15 minutes, with actual issuance budgets charged on the verified
return rather than at setup to prevent retained-intent replay from bypassing them.
All mutations use POST/CSRF, WriteGate and transactions. No new migrations or
dependencies. Preserve unrelated work. One final full PHP suite and one publication.

## Review focus

OAuth reauth must not link/provision or accept another subject/session. Mail
scanners cannot consume a GET token. Failed sends cannot leave a usable unseen
link or retire a newer replacement. Unique-address races and credential changes
cannot replay a stale token. Old-address notice must survive retries and paused
member notifications while preserving address suppression.

### Task 1: Domain transition and old-address notice

Files: `src/Service/EmailChangeService.php`, `UserRepository.php`,
`VerificationRepository.php`, `NotificationEmailWorker.php`, focused service and
worker integration tests. No controller, App, config or feature-flag edits here.

Produces constructor `(Database, UserRepository, VerificationRepository,
SessionRepository, EmailDeliveryRepository, ModerationLogRepository, Mailer, Config,
ReauthGate, WriteGate, FeatureFlags, OAuthIdentityRepository, ?PasskeyService=null,
?EmailDomainVerifier=null, ?SettingRepository=null)` and public methods:
`prepare(User,string):string`; `issue(User,string,?string,?string,string,
?NormalizedIdentity=null):void`; `pending(User):?array`;
`findConfirmation(User,string):?array`; `confirm(User,string,?string):string`;
`cancel(User):void`. `issue` arguments after email are raw password, passkey
assertion, session binding and server-verified OAuth identity. OAuth identity is
checked against the existing identity repository under the actor's lock.

- [x] Write failing tests for the behavior/security cases in the spec.
- [x] Run focused tests and record RED before production edits.
- [x] Implement repositories/service and durable security notice rendering.
- [x] Run focused service, recovery and worker tests; record GREEN.

### Task 2: Feature gate, HTTP forms and provider round trip

Files: `EmailChangeController.php`, `OAuthController.php`, `AccountController.php`,
`App.php`, `FeatureFlags.php`, `config.php`, account templates, `passkeys.js` and
HTTP/OAuth/feature-flag integration tests. Consumes Task 1's interface unchanged.

Routes: GET/POST `/settings/account/email`; POST suffix `/cancel`;
GET/POST suffix `/confirm`; POST `/settings/account/email/oauth/{provider}`.
Standard `issue` accepts only request factors. Only the verified provider callback
may supply `NormalizedIdentity`; signed state binds actor, session and candidate.

- [x] Write HTTP RED tests: default-dark, factor, CSRF/owner/state and read-only GET.
- [x] Implement thin form/controller routes and binding, preserve safe old address.
- [x] Add strict linked-provider reauth callback and wrong-identity/session tests.
- [x] Reuse passkey enhancement with native validation and provider/password fallback.
- [x] Run focused account/OAuth/passkey/feature tests and record GREEN.

### Task 3: Qualification, docs and ordinary publication

Files: focused browser fixture/spec, runbook, ADR 0014/USER/product source updates,
CHANGELOG, evidence index and generated assets only when needed.

- [x] Run Chromium/WebKit desktop/mobile JavaScript and no-JavaScript flows.
- [x] Review final diff, update durable docs and record exact input hashes.
- [x] Run final `composer test`, asset build/freshness/Worker checks and dry run.
- [x] Perform a fresh whole-change security review and address concrete findings.
- [ ] Commit scoped changes; publish once; verify source/revision/Worker/job parity,
  health and changed assets. Keep deployment receipts outside another publication.
