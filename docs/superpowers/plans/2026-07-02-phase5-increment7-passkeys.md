# Phase 5 Increment 7 — Passkeys (P5-11) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land P5-11 deploy-dark behind `passkeys`: a hand-rolled, pure WebAuthn protocol core (`src/Security/WebAuthn/*` — CBOR/COSE/clientData/authenticatorData parsing, ES256 mandatory + RS256, attestation accepted but never trusted), passkey enrollment + named/list/revoke credential management on the account-security page, email-first passkey sign-in integrated with the existing login/TOTP flow, passkey step-up as a `ReauthGate` factor beside the password, recovery riding the existing TOTP/recovery fallback, the `LastOwnerGuard` final-method block, synced-counter anomalies as risk signals (never lockouts), CDP virtual-authenticator browser evidence, and the TM-ID-05…09 adversarial fixtures — **no privileged-MFA enforcement and no usernameless sign-in (both Gate B)**.

**Architecture:** A pure verification layer (`CborDecoder` → `CoseKey` → `AuthenticatorData` → `WebAuthnVerifier`, with `RelyingParty` deriving origin/RP-ID from `APP_URL` per A5/D6) does every stateless check and throws coded, fail-closed `WebAuthnException`s. Stateful rules (one-time session-bound challenges, credential uniqueness, counter bookkeeping, last-method/final-owner invariants, audit, notification email) live in `PasskeyService` over two thin repositories wrapping the **already-landed, inert `0051` tables** (`webauthn_credentials`, `webauthn_challenges`). Controllers stay thin: `PasskeyController` (settings surface, JSON ceremony endpoints + no-JS-capable rename/revoke forms) and two `AuthController` actions (login ceremony). `public/assets/passkeys.js` is strictly progressive enhancement — WebAuthn requires JS by nature, so the no-JS story is "the password/TOTP/recovery paths remain fully usable and the UI says so."

**Tech Stack:** PHP 8.3 (`ext-openssl` for ES256/RS256 via `openssl_verify`, `ext-sodium` already required), vanilla JS (no build step), MySQL/MariaDB via the existing `Database` helper, in-process kernel tests (`Tests\Support\TestCase`), Playwright chromium + CDP `WebAuthn.*` virtual authenticator + axe.

## Global Constraints

*Every task implicitly includes this section. Values copied from CLAUDE.md, PHASE_5_PLAN.md, ADR 0004, the A5 artifact, and the Gate A program plan.*

- **Deploy-dark.** `passkeys` stays **default `false`** in `FeatureFlags::DEFAULTS` (it already is at `src/Core/FeatureFlags.php:86` — do not touch the map). Every new route throws `NotFoundException` when the flag is off, with regressions in `tests/Integration/Core/AppFeatureFlagTest.php`. Gate order is the house order: `requireUser()`/`requireAdmin()` **then** the flag gate (302/403 resolve before the dark 404); guest login-ceremony routes gate first (there is nothing else to resolve).
- **No new migration.** `0051_phase5_webauthn.php` already defines both tables (post-`credential_id VARBINARY(1023)` widen). Privileged-MFA policy (the conditional `0074`) does **not** ship — Gate A boundary is "enrollment + step-up only; NO privileged-MFA enforcement" (program plan §D Inc 7; ADR 0012 A7 default-off). `tests/Unit/Core/MigrationLedgerTest.php` stays untouched.
- **PUBLIC key material only (decision #28/`0051` header).** The server stores raw COSE **public** keys and raw credential-id bytes. No production/runtime `src/` code path generates, stores, or logs private-key material. Test/budget private keys exist only inside `Tests\Support\Phase5\WebAuthnHarness`; committed performance fixtures contain public keys, challenges, authenticator data, and signatures only.
- **Fail closed.** Every ceremony failure is a thrown, coded `WebAuthnException`; no default-allow branch. Tampered clientData, wrong type, wrong origin, wrong rpIdHash, missing UP, missing-required UV, stale/reused/cross-user/cross-session challenge, unknown credential, altered signature, unsupported algorithm, and malformed CBOR all refuse.
- **Origin/RP-ID come from config, never the request (A5 §3).** Derive from `app.url` (`APP_URL`); never read `Host`/`X-Forwarded-*` for ceremony validation. Production non-HTTPS non-localhost ⇒ **hard-refuse** all ceremonies (`insecure_origin`), per the A5 owner sign-off.
- **Attestation is not trusted (decision #29).** Request `attestation: 'none'`; accept any well-formed `fmt`/`attStmt` without verifying attestation chains; retain AAGUID as informational only.
- **Counter anomalies are risk signals (decision #30, TM-ID-08).** A non-increasing `signCount` writes a `moderation_log` row + telemetry and the sign-in still succeeds. Never auto-revoke, never lock.
- **Fresh factor before credential add/remove (decision #26, TM-ID-06).** Registration challenges are minted only after `ReauthGate::requireFactor` (password or passkey step-up); the short challenge TTL (300 s) is the recency window — consistent with the F7 "window zero / present the factor with the request" posture.
- **Write path.** Controllers thin (marshal → one service call → map exceptions); services own rules inside `$db->transaction(fn)`; repositories `final`, prepared statements only, assoc arrays. Controllers catch `ValidationException` themselves and re-render/JSON-422 with `->errors` (+ `->old` where a form re-renders).
- **PDO `EMULATE_PREPARES=false`:** never bind `LIMIT`/`OFFSET`; never reuse a named placeholder; `UTC_TIMESTAMP()`/`gmdate()` everywhere; binary columns take raw bytes.
- **Strict CSP / PE.** No inline `<script>`/`<style>`; all JS in `public/assets/passkeys.js`, included from `templates/layout.php` only when the flag is on; hooks via `data-*`; every non-ceremony flow (rename, revoke-with-password, fallback sign-in) works as server-rendered forms without JS.
- **CSRF on every POST.** Ceremony fetches append `_token` (read from `input[name="_token"]`) to `FormData` exactly like `public/assets/composer.js`. No new CSRF exemptions.
- **Rate limits (P5-11 requirement).** New named policies `passkey_login` `[10, 900]` and `passkey_challenge` `[30, 900]` in `config/config.php`; management mutations consume the existing (currently unused) `mfa_settings` `[10, 900]`. Login policies key per-subject (lowercased email) via `enforceSubject` — never raw PII in keys — and successful passkey login clears the `passkey_login` subject window. JSON ceremony endpoints catch `HttpException` thrown by `RateLimitService` and return the same JSON error envelope as validation failures (`{"ok":false,"errors":...}`) with status 429; later service `HttpException`s must be rendered as JSON/form errors with their real status and **not** as `rate_limit` (e.g. `WriteGate` 403 remains a 403). No passkey fetch endpoint may fall through to the app-level HTML error renderer.
- **Audit.** Every credential mutation and every passkey sign-in writes `moderation_log` (`target_type='user'`, `reason='account security'`) mirroring `MfaService::log`: `passkey_registered`, `passkey_renamed`, `passkey_revoked`, `passkey_login`, `passkey_counter_anomaly`. Add/remove also send a best-effort, fail-closed security-notification email via the `Mailer` seam (mirror `EmailVerificationService::sendEmail`).
- **Session invalidation after credential changes.** Adding or revoking a passkey is an account credential change: after a successful mutation, call `Controller::revokeOtherSessionsFor($user)` so every parallel session is revoked while the current session survives (USER §3.3 / CLAUDE.md invariant). Rename is metadata only and does not revoke sessions. Cover add and revoke with explicit two-session regressions.
- **Evidence (DESIGN §13; PHASE_5_PLAN §10.2 "mocked cryptography alone is insufficient"; §F distributed discipline):** real-crypto unit fixtures (openssl-signed, via `WebAuthnHarness`), the four spec-pinned test files (`tests/Unit/Auth/WebAuthnPolicyTest.php`, `tests/Integration/Core/AppPasskeyRegistrationTest.php`, `AppPasskeyLoginTest.php`, `AppPasskeyRecoveryTest.php`), CDP virtual-authenticator browser journey + axe on desktop+mobile, the `webauthn.ceremony_p95` D11 budget measured (target 2000 ms), TM-ID-05…09 flipped to `implemented` with real test paths, runbook, and the ledger row `GA-DOD-13` advanced in the same commits that land its evidence.
- **Strict PHPUnit:** every test ≥1 assertion; no output; no warnings. Per-test isolation is one rolled-back transaction — **no savepoints**: code under test that opens its own transaction does not really roll back in tests; assert observable HTTP/DB behavior, not rollback effects.
- **Never `git add -A` / `git add .`** — the working tree may still carry another session's files. Stage explicit paths in every commit. End every commit message with `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.

## Context — what already exists (read before Task 1)

- **`database/migrations/0051_phase5_webauthn.php`** — the two inert tables this increment animates. `webauthn_credentials(id, user_id FK CASCADE, credential_id VARBINARY(1023) UNIQUE uq_webauthn_credid, public_key VARBINARY(1024) /* COSE, PUBLIC only */, sign_count BIGINT UNSIGNED, aaguid BINARY(16), transports VARCHAR(190), is_discoverable/is_backup_eligible/is_backed_up TINYINT(1), nickname VARCHAR(120), created_at, last_used_at, revoked_at, KEY idx_webauthn_user(user_id, revoked_at))`. `webauthn_challenges(id, user_id NULL, session_token_hash CHAR(64), purpose ENUM('register','login','step_up'), challenge VARBINARY(255), created_at, expires_at, consumed_at, KEY idx_webauthn_chal_user, KEY idx_webauthn_chal_expires)`. RP ID/origins deliberately **not** in the DB.
- **`src/Core/FeatureFlags.php:86`** — `'passkeys' => false` already reserved; `tests/Integration/Core/AppFeatureFlagTest.php` already asserts it dark-by-default and isolation-safe (`test_phase5_foundation_flags_default_dark`).
- **`src/Security/ReauthGate.php`** — `requirePassword(User, string, string $field='current_password', ?string $missingPasswordError=null): void`, `verifyPassword(User, string): bool`; docblock reserves `FACTOR_PASSKEY` for this increment; window-zero semantics.
- **`src/Service/MfaService.php`** — the service template: ctor `(MfaRepository, UserRepository, ReauthGate, SecretBox, Totp, WriteGate, ModerationLogRepository, Config)`; `enabledForUser(int): bool`; `beginLoginChallenge(User, Request, string $next): string`; `completeLoginChallenge(string $token, string $code): array{user:User,next:string,method:string}`; private `log()` writing `moderation_log`. The TOTP interstitial holds pending-2FA state as a DB row, not session state.
- **`src/Controller/AuthController.php`** — `login()` (:43): rate `enforceSubject('login', …, strtolower($email))`, `AuthService::attempt`, banned check with generic copy, TOTP interstitial (`auth/mfa` view carrying a hidden `mfa_token`), `session()->login($user)`, `safeNext()`. `completeMfa()` (:93). New passkey actions live in this controller.
- **`src/Security/Session.php`** — `login(User)` rotates id + `csrf_secret`; `csrfSecret(): string` works for guests (guest cookie) **and** members — the uniform session-binding source for challenges. `currentSessionId(): ?string`.
- **`src/Security/LastOwnerGuard.php`** — `assertNotLastOwner(User, string $field='account'): void`, `assertNotLastOwnerForUpdate(...)` (FOR-UPDATE variant); parity-safe when `protected_owners` is unseeded (falls back to last-active-admin). Bound unconditionally in the container at `App.php:1186`; its docblock lists "passkey removal (Inc 7)" as a pending call site.
- **`src/Repository/OAuthIdentityRepository.php`** — `countForUser(int): int`, `forUser(int): array` — the third leg of the usable-method inventory (password / OAuth identity / active passkey).
- **`src/Security/Registry/TrustChainVerifier.php`** — the hand-rolled-crypto precedent (pure verifier, coded exceptions, native extension does the math). Mirror its shape, not its encoding (registry uses standard base64; WebAuthn is base64url).
- **`src/Controller/OAuthController.php:273`** — the only base64url code in `src/` today (private method). Task 2 promotes the idiom to `App\Support\Base64Url`; do **not** refactor OAuthController in this increment.
- **`src/Mail/Mailer.php`** — `send(string $to, string $subject, string $textBody, ?string $htmlBody = null): string`, `isConfigured(): bool`; fail-closed pattern at `src/Service/EmailVerificationService.php:97-124`.
- **`src/Core/Telemetry.php`** — `emit(string $event, array $context)`; dark unless `telemetry.enabled`; contexts pass `LogRedactor`.
- **`templates/account/security.php`** — password panel then the "Two-factor authentication" `scribe-panel` (ends ~line 123). The Passkeys panel slots directly after it. Rendered by `AccountController::securityView(User, array $data=[], int $status=200)` (:279).
- **`templates/layout.php:79-82`** — flag-conditional script includes (`composer.js`, `wysiwyg-composer.js`, `tour.js`); `$features` is a view global. `templates/auth/login.php` — the login form (passkey slot goes below the submit row).
- **`public/assets/composer.js:31-34, 489-496`** — `tokenField()` / `_token`-append / `X-Requested-With` / `credentials:'same-origin'` fetch discipline to mirror.
- **`config/config.php:197-213`** — named rate-limit policies; `'mfa_settings' => [10, 900]` exists and is currently unconsumed.
- **`tests/Support/TestCase.php`** — `actingAs`, `get`/`post` (auto `_token`), `makeUser`/`makeAdmin`, `assertStatus`/`assertRedirect`/`assertSeeText`; per-test rolled-back transaction.
- **`tests/Integration/Core/AppMfaTest.php`** — the end-to-end no-JS TOTP journey (enroll → recovery login → rotate → disable) and its HTML-parsing helper style.
- **`tests/browser/`** — `playwright.config.ts` (chromium `desktop`+`mobile` projects, PHP dev server, screenshots to `docs/evidence/browser/<project>/`), `seed.php` (admin/alice/bob @ `password123`, `$settings->set('features', $evidenceFeatures)` — `passkeys` **not** in that list, correct: specs toggle it themselves), `wysiwyg-composer.spec.ts` (the `runPhp()` flag-toggling pattern), `a11y.spec.ts` (axe pattern). **No CDP/virtual-authenticator harness exists yet.**
- **Ledger + threat models:** `docs/superpowers/plans/2026-07-01-phase5-foundation-remainder.md` — `GA-DOD-13` (P5-11, `state:"R1"`, `:2019`), `SLICE-TOTP` (`state:"R3"`, "browser/no-JS evidence outstanding", `:2041`), fixtures JSON `TM-ID-05…09` all `"status":"stub"` (`:2535-2539`); mirrored in `docs/phase5/threat-models/fixtures.json` + `identity-account-takeover.md`; enforced by `tests/Unit/Core/Phase5EvidenceMapTest.php` and `tests/Unit/Core/ThreatModelIndexTest.php` (an `implemented` fixture must name an existing `test` file).
- **Budgets:** `src/Support/Phase5Budgets.php` row `webauthn.ceremony_p95` (2000 ms, `PENDING (inc7)`); `bin/console verify:phase5-budgets` regenerates `docs/evidence/phase5/performance-budgets.md`; `src/Service/BaselineMetricsService.php` shows the measurement pattern.
- **A5:** `docs/phase5/canonical-origin-and-rp-id.md` — RP ID = registrable domain of `APP_URL`, exact-equality origin, localhost dev exception, production HTTPS hard-refuse, §5 domain-change runbook.

## Decisions to record (surface these to the owner in the Task 19 status update — they refine, not contradict, the approved artifacts)

1. **RP-ID resolution.** True eTLD+1 derivation requires the Public Suffix List — a dependency the no-library posture excludes, and a naive "last two labels" rule would derive the public suffix `co.uk` for `*.co.uk` operators (browsers then refuse every ceremony). Implementation: optional env `WEBAUTHN_RP_ID` (validated: equal to, or a dot-suffix of, the `APP_URL` host), **default = the full `APP_URL` host**. The full host is always a valid, strictly-narrower RP ID; operators who want A5 §2 subdomain portability set `WEBAUTHN_RP_ID` to their registrable domain (documented in the runbook + `.env.example`). Browsers enforce the PSL boundary client-side.
2. **Algorithm allowlist:** ES256 (−7, mandatory per program plan) + RS256 (−257, for Windows Hello-era authenticators). Everything else refuses with `unsupported_algorithm`.
3. **User-verification policy:** ceremonies request `userVerification:'preferred'`; the server **requires** UV for `step_up`; for `login`, UV is required iff the account has TOTP enrolled (see #4); `register` records the UV bit as reported. UP is always required.
4. **TOTP × passkey sign-in:** a UV-verified passkey assertion is multi-factor by itself and signs the user straight in (no TOTP interstitial). If the account has TOTP enrolled and the assertion lacks UV, the sign-in refuses with guidance ("use a passkey with a screen lock, or sign in with your password and code") — this avoids splicing a JSON ceremony into the HTML interstitial and never weakens a TOTP-enrolled account.
5. **Fresh-factor scope:** credential **add** and **revoke** require a present factor (password or passkey step-up assertion); **rename** requires only session + CSRF.
6. **TM-ID-09 clause 2** ("provider disable lists sole-method accounts"): no provider-disable surface exists until Inc 8. This increment ships the tested capability `OAuthIdentityRepository::soleMethodAccounts(string $provider)` and records an explicit Inc 8 handoff (wire it into the provider-disable UI) in PHASE_5_STATUS; the fixture flips to `implemented` on the strength of the removal-block + detector tests.
7. **No `0074`, no privileged-MFA policy scaffolding, no enrollment-audience config.** The §13.1 step-9 staff pilot is an operational procedure (enable the flag for the pilot window; runbook documents it), not a code gate.
8. **`ext-openssl`** is added to `composer.json` `require` (it was undeclared; ES256/RS256 verification now depends on it).

## Locked interfaces (all tasks must match these exactly)

```
App\Support\Base64Url                       static encode(string $raw): string · static decode(string $encoded): ?string

App\Security\WebAuthn\WebAuthnException     extends \RuntimeException; __construct(public readonly string $code, string $message)
App\Security\WebAuthn\CborDecoder           static decode(string $bytes): mixed            (throws WebAuthnException 'malformed_cbor'; must consume all bytes)
                                            static decodeFirst(string $bytes): array{0:mixed,1:string}   (value + remaining bytes)
App\Security\WebAuthn\CoseKey               static fromCbor(string $cborBytes): self · public readonly int $alg
                                            verify(string $data, string $signature): bool · toPem(): string
App\Security\WebAuthn\AuthenticatorData     static parse(string $bytes): self
                                            readonly string $rpIdHash · readonly int $flags · readonly int $signCount
                                            readonly ?string $aaguid · readonly ?string $credentialId · readonly ?string $credentialPublicKey
                                            userPresent(): bool · userVerified(): bool · backupEligible(): bool · backedUp(): bool
App\Security\WebAuthn\RelyingParty          __construct(string $appUrl, ?string $rpIdOverride, string $appEnv)
                                            origin(): string · rpId(): string · rpIdHash(): string · assertUsable(): void
App\Security\WebAuthn\RegisteredCredential  readonly: string $credentialId, string $publicKey, int $signCount, ?string $aaguid,
                                            string $transports, bool $userVerified, bool $backupEligible, bool $backedUp
App\Security\WebAuthn\AssertionResult       readonly: bool $userVerified, int $signCount, bool $counterAnomaly
App\Security\WebAuthn\WebAuthnVerifier      __construct(RelyingParty $rp)
                                            verifyRegistration(array $credential, string $expectedChallenge): RegisteredCredential
                                            verifyAssertion(array $credential, string $expectedChallenge, string $publicKeyCbor,
                                                            int $storedSignCount, bool $requireUv): AssertionResult

App\Repository\WebAuthnCredentialRepository __construct(Database) · activeForUser(int): array · activeForUserForUpdate(int): array
                                            countActiveForUser(int): int · findActiveByCredentialId(string $rawId): ?array
                                            findForUser(int $userId, int $id): ?array · create(array $row): int
                                            rename(int $userId, int $id, string $nickname): bool · revoke(int $userId, int $id): bool
                                            updateOnUse(int $id, int $signCount): void   # refreshes last_used_at and only raises stored sign_count
App\Repository\WebAuthnChallengeRepository  __construct(Database) · mint(?int $userId, string $sessionHash, string $purpose,
                                            string $challenge, int $ttlSeconds): int
                                            consume(string $challenge, string $sessionHash, string $purpose, ?int $userId): bool
                                            purgeExpired(): int

App\Security\ReauthGate (additions)         const FACTOR_PASSKEY = 'passkey'   # const FACTOR_PASSWORD = 'password' ALREADY EXISTS (line 21) — do NOT re-declare
                                            requireFactor(User $actor, ?string $currentPassword, ?\Closure $passkeyProbe = null,
                                                          string $field = 'current_password'): string

App\Repository\OAuthIdentityRepository (addition)  soleMethodAccounts(string $provider): array   # accounts whose ONLY sign-in method is this provider

App\Service\PasskeyService
  __construct(WebAuthnCredentialRepository, WebAuthnChallengeRepository, WebAuthnVerifier, RelyingParty, UserRepository,
              OAuthIdentityRepository, MfaService, ReauthGate, WriteGate, LastOwnerGuard, ModerationLogRepository,
              Mailer, Config, Database, ?Telemetry $telemetry = null)
  static sessionBinding(Session $session): string
  status(User $user): array                             # {supported: bool, credentials: list<row>}
  beginRegistration(User $user, string $sessionHash): array          # PublicKeyCredentialCreationOptions (JSON-ready)
  completeRegistration(User $user, string $sessionHash, string $credentialJson, ?string $nickname): array
  beginLogin(?string $email, string $sessionHash): array             # PublicKeyCredentialRequestOptions (JSON-ready; fixed-shape enumeration-safe)
  completeLogin(string $credentialJson, string $sessionHash): array  # {user: \App\Domain\User, used_uv: bool}
  beginStepUp(User $user, string $sessionHash): array
  verifyStepUp(User $user, string $sessionHash, string $credentialJson): void
  assertFreshFactor(User $user, ?string $currentPassword, ?string $assertionJson, string $sessionHash): string
  rename(User $user, int $credentialId, string $nickname): void
  remove(User $user, int $credentialId, ?string $currentPassword, ?string $assertionJson, string $sessionHash): void

App\Controller\PasskeyController            challenge · store · stepUpChallenge · rename · revoke
App\Controller\AuthController (additions)   passkeyChallenge · passkeyLogin

Tests\Support\Phase5\WebAuthnHarness        __construct(string $rpId = 'localhost', string $origin = 'http://localhost:8000')
                                            createCredential(): array{privateKey:\OpenSSLAsymmetricKey, credentialId:string, coseKey:string}
                                            registrationPayload(array $cred, string $challenge, array $overrides = []): string   # JSON
                                            assertionPayload(array $cred, string $challenge, int $signCount, array $overrides = []): string
                                            rs256Credential(): array   # same shape, RSA key
```

Routes (register in `App::buildRouter()` beside the TOTP block at `src/Core/App.php:1604-1609`):

```
POST /settings/security/passkeys/challenge           PasskeyController::challenge         (JSON)
POST /settings/security/passkeys/step-up-challenge   PasskeyController::stepUpChallenge   (JSON)
POST /settings/security/passkeys                     PasskeyController::store             (JSON)
POST /settings/security/passkeys/{id}/rename         PasskeyController::rename            (form, PRG)
POST /settings/security/passkeys/{id}/revoke         PasskeyController::revoke            (form, PRG)
POST /login/passkey/challenge                        AuthController::passkeyChallenge     (JSON, guest)
POST /login/passkey                                  AuthController::passkeyLogin         (JSON, guest)
```

## Wire contract (consumed by `passkeys.js`; documented for the runbook)

All ceremony endpoints are same-origin `fetch` POSTs of `FormData` (so the kernel CSRF gate sees `_token`), with `X-Requested-With: XMLHttpRequest`, returning `{"ok":true,...}` or `{"ok":false,"errors":{field:msg}}`:

```
POST /settings/security/passkeys/challenge   body: _token, current_password? , passkey_assertion?
  → {"ok":true,"options":{rp:{id,name},user:{id,name,displayName},challenge,pubKeyCredParams:[{type:'public-key',alg:-7},{...alg:-257}],
      timeout:300000,excludeCredentials:[{type:'public-key',id,transports}],authenticatorSelection:{residentKey:'preferred',
      userVerification:'preferred'},attestation:'none'}}
POST /settings/security/passkeys             body: _token, credential (JSON string), nickname?
  → {"ok":true}
POST /settings/security/passkeys/step-up-challenge   body: _token
  → {"ok":true,"options":{challenge,rpId,timeout:300000,allowCredentials:[...],userVerification:'required'}}
POST /login/passkey/challenge                body: _token, email
  → {"ok":true,"options":{challenge,rpId,timeout:300000,allowCredentials:[...],userVerification:'preferred'}}   (identical shape for unknown emails)
POST /login/passkey                          body: _token, email, credential (JSON string), next?
  → {"ok":true,"redirect":"/..."}
```

`credential` is the JSON serialization built by `passkeys.js`: `{id, rawId, type:'public-key', transports?, credProps?, response:{clientDataJSON, attestationObject}}` for create, `{id, rawId, type:'public-key', response:{clientDataJSON, authenticatorData, signature, userHandle?}}` for get — every binary field base64url. All `challenge`/`id` fields in options are base64url strings; `user.id` is the 8-byte big-endian user id.

## Out of scope (do not build here)

- **Usernameless/discoverable sign-in, passkey-first UX** — Gate B (§13.1 step 12). `webauthn_challenges.user_id` stays non-NULL on every path this increment mints.
- **Privileged-MFA enforcement or its policy/grace machinery, migration `0074`** — Gate B / owner opt-in (A7). Nothing reads or writes a privileged-MFA setting.
- **Provider-disable UI + sole-method listing surface** — Inc 8 (the detector lands here, the surface there).
- **Migrating existing `ReauthGate::requirePassword` call sites (themes, roles, TOTP settings, lifecycle) to accept passkeys** — only the new passkey surfaces consume `requireFactor` in this increment; broader adoption is a recorded follow-up.
- **Flag default flip / staged-rollout config, `docs/evidence/deploy-dark-features.md` edits** — rollout is §13.1 step 9, after Gate A evidence.
- **Refactoring `OAuthController`'s private base64url onto `App\Support\Base64Url`.**

---

## Task 0: Branch + preconditions

- [ ] **Step 1: Confirm the tree is clean enough to branch**

```bash
cd /home/henry/community-forums
git status --short
```

Expected: **no modified tracked files** (the 2026-07-02 admin-UX Codex slice must be committed/landed first — if `src/Core/App.php`, `src/Repository/ReportRepository.php`, `templates/admin/_nav.php`, `templates/errors/error.php`, `templates/partials/topbar.php`, or the two test files still show `M`, STOP and hand back). Untracked leftovers (`storage/local-server.pid`, stray `tests/browser/*.mjs`) are fine — never stage them.

- [ ] **Step 2: Create the working branch**

```bash
git switch main && git pull --ff-only && git switch -c phase5-inc7-passkeys
```

Expected: branch `phase5-inc7-passkeys` at the current main tip.

- [ ] **Step 3: Verify the baseline is green**

```bash
composer test 2>&1 | tail -5
```

Expected: the full suite passes (PHASE_5_STATUS baseline: 1268 tests at Inc 4 close; the admin-UX slice may have added more). Do not proceed on a red baseline.

---

### Task 1: TOTP browser/no-JS retrofit evidence (the B1 predecessor)

ADR 0004 B1: *"Build passkey registration/enforcement only after this fallback remains green in full-suite **and browser/no-JS evidence**."* The §F retrofit list names "TOTP browser/no-JS" as outstanding, and ledger row `SLICE-TOTP` carries the same note. This task pays that debt first so every later task builds on an evidenced fallback.

**Files:**
- Create: `tests/browser/totp.spec.ts`
- Modify: `docs/superpowers/plans/2026-07-01-phase5-foundation-remainder.md` (ledger row `SLICE-TOTP`, ~line 2041)

**Interfaces:**
- Consumes: seeded users from `tests/browser/seed.php` (`alice@retro.test` / `password123`); the TOTP routes from `src/Core/App.php:1604-1609`.
- Produces: `docs/evidence/browser/<project>/totp-*.png` screenshots consumed by Task 18's evidence index.

- [ ] **Step 1: Write the failing spec**

`tests/browser/totp.spec.ts` — note `javaScriptEnabled: false` makes the whole journey a no-JS proof. The TOTP code is computed in-node from the enrolment secret (RFC 6238, SHA-1, 30 s step, 6 digits) so the spec needs no PHP shell-out:

```ts
import { expect, test } from '@playwright/test';
import * as crypto from 'node:crypto';

test.use({ javaScriptEnabled: false });

// Empty string lets Playwright resolve relative paths against use.baseURL.
// E2E_BASE_URL matches tests/browser/playwright.config.ts; RB_BASE_URL is a
// legacy/manual override for ad-hoc runs. Do not hard-code the server port.
const BASE = process.env.E2E_BASE_URL ?? process.env.RB_BASE_URL ?? '';

function b32decode(s: string): Buffer {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let bits = 0, value = 0;
  const out: number[] = [];
  for (const ch of s.replace(/=+$/, '')) {
    value = (value << 5) | alphabet.indexOf(ch);
    bits += 5;
    if (bits >= 8) { out.push((value >>> (bits - 8)) & 0xff); bits -= 8; }
  }
  return Buffer.from(out);
}

function totp(secret: string, at: number = Date.now()): string {
  const counter = Math.floor(at / 1000 / 30);
  const msg = Buffer.alloc(8);
  msg.writeBigUInt64BE(BigInt(counter));
  const h = crypto.createHmac('sha1', b32decode(secret)).update(msg).digest();
  const off = h[h.length - 1] & 0x0f;
  const code = ((h.readUInt32BE(off) & 0x7fffffff) % 1_000_000).toString().padStart(6, '0');
  return code;
}

function shot(name: string, projectName: string): string {
  return `../../docs/evidence/browser/${projectName}/${name}.png`;
}

test('TOTP enroll, second-factor login, and disable all work without JavaScript', async ({ page }, testInfo) => {
  // Sign in with password only (forms, no JS).
  await page.goto(`${BASE}/login`);
  await page.fill('input[name="email"]', 'alice@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);

  // Enroll: start, read the secret off the page, confirm with a live code.
  await page.goto(`${BASE}/settings/security`);
  await page.fill('form[action="/settings/security/totp/enroll"] input[name="current_password"]', 'password123');
  await page.click('form[action="/settings/security/totp/enroll"] button[type="submit"]');
  const secret = (await page.locator('[data-totp-secret], input[name="totp_secret"], code.totp-secret').first().textContent()
    ?? await page.inputValue('input[readonly][value]').catch(() => '')) as string;
  const cleaned = (secret || '').trim().replace(/\s+/g, '');
  expect(cleaned).toMatch(/^[A-Z2-7]{16,}$/);
  await page.screenshot({ path: shot('totp-01-enroll', testInfo.project.name), fullPage: true });

  await page.fill('form[action="/settings/security/totp/confirm"] input[name="current_password"]', 'password123');
  await page.fill('form[action="/settings/security/totp/confirm"] input[name="totp_code"]', totp(cleaned));
  await page.click('form[action="/settings/security/totp/confirm"] button[type="submit"]');
  await expect(page.locator('body')).toContainText(/recovery code/i);
  await page.screenshot({ path: shot('totp-02-recovery-codes', testInfo.project.name), fullPage: true });

  // Sign out, sign back in: the interstitial must appear and accept a live code.
  await page.click('form[action="/logout"] button[type="submit"]');
  await page.goto(`${BASE}/login`);
  await page.fill('input[name="email"]', 'alice@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await expect(page.locator('body')).toContainText(/verification/i);
  await page.screenshot({ path: shot('totp-03-interstitial', testInfo.project.name), fullPage: true });
  await page.fill('input[name="code"]', totp(cleaned));
  await page.click('form[action="/login/mfa"] button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);

  // Disable so the seed state stays reusable for other specs in the same run.
  await page.goto(`${BASE}/settings/security`);
  await page.fill('form[action="/settings/security/totp/disable"] input[name="current_password"]', 'password123');
  await page.fill('form[action="/settings/security/totp/disable"] input[name="disable_code"]', totp(cleaned));
  await page.click('form[action="/settings/security/totp/disable"] button[type="submit"]');
  await expect(page.locator('form[action="/settings/security/totp/enroll"]')).toBeVisible();
});
```

The field names above are pinned to the current templates: enrollment confirmation uses `totp_code`, login interstitial uses `code`, and disable uses `disable_code`. Keep those names exact; a generic `code` field on the settings forms will silently fail the no-JS proof.

- [ ] **Step 2: Run it to make sure it fails usefully (before selector fixes) then passes**

```bash
cd tests/browser && npx playwright test totp.spec.ts --project=desktop
```

Expected after selector alignment: 1 passed. Then run both projects: `npx playwright test totp.spec.ts` → 2 passed, PNGs written under `docs/evidence/browser/desktop/` and `.../mobile/`.

- [ ] **Step 3: Advance the `SLICE-TOTP` ledger row in the same change**

In `docs/superpowers/plans/2026-07-01-phase5-foundation-remainder.md` (~line 2041) replace the row:

```json
{ "id": "SLICE-TOTP", "gate": "A", "workstream": "B1", "title": "TOTP + recovery codes (opt-in)", "state": "R3", "evidence": ["tests/Unit/Security/TotpTest.php"], "notes": "browser/no-JS evidence outstanding before flag-flip (program plan §F retrofit)" }
```

with:

```json
{ "id": "SLICE-TOTP", "gate": "A", "workstream": "B1", "title": "TOTP + recovery codes (opt-in)", "state": "R4", "evidence": ["tests/Unit/Security/TotpTest.php", "tests/Integration/Core/AppMfaTest.php", "tests/browser/totp.spec.ts"], "notes": "browser/no-JS journey landed with Inc 7 Task 1 (B1 predecessor); PNGs under docs/evidence/browser/*/totp-*.png" }
```

Run the ledger guard:

```bash
vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php
```

Expected: PASS. If the test constrains which states may carry which evidence shapes, read its assertions and satisfy them (states are bumped only in the commit landing the evidence — this is that commit).

- [ ] **Step 4: Commit**

```bash
git add tests/browser/totp.spec.ts docs/superpowers/plans/2026-07-01-phase5-foundation-remainder.md docs/evidence/browser/desktop/totp-01-enroll.png docs/evidence/browser/desktop/totp-02-recovery-codes.png docs/evidence/browser/desktop/totp-03-interstitial.png docs/evidence/browser/mobile/totp-01-enroll.png docs/evidence/browser/mobile/totp-02-recovery-codes.png docs/evidence/browser/mobile/totp-03-interstitial.png
git commit -m "test(browser): no-JS TOTP enroll/login/disable evidence; SLICE-TOTP -> R4 (B1 predecessor for passkeys)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: `App\Support\Base64Url`

WebAuthn round-trips every binary field (challenges, credential ids, clientDataJSON, signatures) as base64url. The only base64url code in the repo is a private method on `OAuthController` — promote the idiom to a shared, strict helper. Strictness matters: `decode` returns `null` (never a mangled string) on any non-canonical input, so callers can fail closed.

**Files:**
- Create: `src/Support/Base64Url.php`
- Test: `tests/Unit/Support/Base64UrlTest.php`

**Interfaces:**
- Produces: `Base64Url::encode(string $raw): string`, `Base64Url::decode(string $encoded): ?string` — consumed by every WebAuthn class, the harness, and `PasskeyService`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Base64Url;
use PHPUnit\Framework\TestCase;

final class Base64UrlTest extends TestCase
{
    public function test_round_trips_binary_including_url_unsafe_bytes(): void
    {
        $raw = "\xfb\xff\xfe" . random_bytes(61);
        $encoded = Base64Url::encode($raw);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $encoded);
        self::assertStringNotContainsString('=', $encoded);
        self::assertSame($raw, Base64Url::decode($encoded));
    }

    public function test_decodes_known_vector(): void
    {
        self::assertSame('f', Base64Url::decode('Zg'));
        self::assertSame('Zg', Base64Url::encode('f'));
        self::assertSame('', Base64Url::decode(''));
    }

    public function test_rejects_invalid_input(): void
    {
        self::assertNull(Base64Url::decode('a'));          // impossible length (4n+1)
        self::assertNull(Base64Url::decode('Zg=='));       // padding not accepted
        self::assertNull(Base64Url::decode('Zg+/'));       // standard-alphabet chars
        self::assertNull(Base64Url::decode("Zg\n"));       // whitespace
        self::assertNull(Base64Url::decode('AB'));         // non-zero pad bits; canonical form for "\0" is "AA"
        self::assertNull(Base64Url::decode('Zh'));         // non-zero pad bits; canonical form for "f" is "Zg"
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

```bash
vendor/bin/phpunit tests/Unit/Support/Base64UrlTest.php
```

Expected: FAIL — `Class "App\Support\Base64Url" not found`.

- [ ] **Step 3: Implement**

`src/Support/Base64Url.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Strict base64url (RFC 4648 §5, unpadded). decode() returns null on any
 * input that is not the canonical unpadded encoding of some byte string.
 */
final class Base64Url
{
    public static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function decode(string $encoded): ?string
    {
        if ($encoded === '') {
            return '';
        }
        if (preg_match('/^[A-Za-z0-9_-]+$/', $encoded) !== 1) {
            return null;
        }
        $remainder = strlen($encoded) % 4;
        if ($remainder === 1) {
            return null;
        }
        $b64 = strtr($encoded, '-_', '+/');
        if ($remainder > 0) {
            $b64 .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode($b64, true);
        if ($decoded === false) {
            return null;
        }
        return self::encode($decoded) === $encoded ? $decoded : null;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
vendor/bin/phpunit tests/Unit/Support/Base64UrlTest.php
```

Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Support/Base64Url.php tests/Unit/Support/Base64UrlTest.php
git commit -m "feat(support): strict shared base64url helper for WebAuthn field encoding

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: `WebAuthnException` + `CborDecoder`

The attestation object and COSE keys are CBOR. This is a deliberately minimal RFC 8949 subset decoder: definite lengths only, integers/byte-strings/text-strings/arrays/maps/false/true/null, hard caps on depth (8), container size (1024) and string length (1 MiB), duplicate-map-key refusal, numeric-string map-key refusal (so PHP can never collapse CBOR `1` and `"1"` into the same array slot), and no floats/tags/indefinite forms — everything a WebAuthn payload legitimately needs and nothing more. All failures throw the increment's coded exception type.

**Files:**
- Create: `src/Security/WebAuthn/WebAuthnException.php`
- Create: `src/Security/WebAuthn/CborDecoder.php`
- Test: `tests/Unit/Security/WebAuthn/CborDecoderTest.php`

**Interfaces:**
- Produces: `WebAuthnException` (with `public readonly string $code`) — thrown by every WebAuthn class; `CborDecoder::decode(string): mixed` (whole input, refuses trailing bytes) and `CborDecoder::decodeFirst(string): array{0:mixed,1:string}` (value + remaining bytes — used by `AuthenticatorData` to find the COSE key's byte length).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security\WebAuthn;

use App\Security\WebAuthn\CborDecoder;
use App\Security\WebAuthn\WebAuthnException;
use PHPUnit\Framework\TestCase;

final class CborDecoderTest extends TestCase
{
    public function test_decodes_the_webauthn_subset(): void
    {
        // {"fmt": "none", "attStmt": {}, "authData": h'AABB'} — hand-encoded.
        $bytes = "\xa3" . "\x63fmt" . "\x64none" . "\x67attStmt" . "\xa0" . "\x68authData" . "\x42\xaa\xbb";
        self::assertSame(['fmt' => 'none', 'attStmt' => [], 'authData' => "\xaa\xbb"], CborDecoder::decode($bytes));
    }

    public function test_decodes_cose_style_integer_keys_and_negative_integers(): void
    {
        // {1: 2, 3: -7, -1: 1} — negative key -1 is 0x20, -7 is 0x26.
        $bytes = "\xa3\x01\x02\x03\x26\x20\x01";
        self::assertSame([1 => 2, 3 => -7, -1 => 1], CborDecoder::decode($bytes));
    }

    public function test_decode_first_returns_the_remainder(): void
    {
        [$value, $rest] = CborDecoder::decodeFirst("\x02rest");
        self::assertSame(2, $value);
        self::assertSame('rest', $rest);
    }

    public function test_rejects_trailing_bytes_in_full_decode(): void
    {
        $this->expectException(WebAuthnException::class);
        CborDecoder::decode("\x02extra");
    }

    public function test_rejects_indefinite_length_items(): void
    {
        $this->expectException(WebAuthnException::class);
        CborDecoder::decode("\x5f\x41\x01\xff"); // indefinite byte string
    }

    public function test_rejects_duplicate_map_keys_including_int_string_aliasing(): void
    {
        // {1: 0, "1": 0} — PHP would silently merge these array slots, so numeric-string keys are malformed.
        $bytes = "\xa2\x01\x00" . "\x611" . "\x00";
        $this->expectException(WebAuthnException::class);
        CborDecoder::decode($bytes);
    }

    public function test_rejects_truncated_input_and_excess_depth(): void
    {
        try {
            CborDecoder::decode("\x42\xaa"); // bstr claims 2 bytes, has 1
            self::fail('Truncated CBOR must throw');
        } catch (WebAuthnException $e) {
            self::assertSame('malformed_cbor', $e->code);
        }
        $this->expectException(WebAuthnException::class);
        CborDecoder::decode(str_repeat("\x81", 9) . "\x01"); // 9 nested arrays > depth cap 8
    }

    public function test_rejects_floats_and_tags(): void
    {
        try {
            CborDecoder::decode("\xfa\x3f\x80\x00\x00"); // float32
            self::fail('Float must throw');
        } catch (WebAuthnException) {
        }
        $this->expectException(WebAuthnException::class);
        CborDecoder::decode("\xc0\x60"); // tag 0
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

```bash
vendor/bin/phpunit tests/Unit/Security/WebAuthn/CborDecoderTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

`src/Security/WebAuthn/WebAuthnException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

/**
 * Coded, fail-closed ceremony failure (mirrors RegistryVerificationException).
 * Codes are stable identifiers for tests/telemetry; messages are operator-facing.
 */
final class WebAuthnException extends \RuntimeException
{
    public function __construct(public readonly string $code, string $message)
    {
        parent::__construct($message);
    }
}
```

`src/Security/WebAuthn/CborDecoder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

/**
 * Minimal CBOR decoder for the WebAuthn subset (RFC 8949, definite lengths only).
 * Accepts unsigned/negative integers, byte strings, text strings, arrays, maps,
 * and false/true/null. Refuses indefinite lengths, tags, floats, duplicate map
 * keys, oversized containers, and (in decode()) trailing bytes.
 */
final class CborDecoder
{
    private const MAX_DEPTH = 8;
    private const MAX_ITEMS = 1024;
    private const MAX_BYTES = 1048576;

    public static function decode(string $bytes): mixed
    {
        [$value, $rest] = self::decodeFirst($bytes);
        if ($rest !== '') {
            throw new WebAuthnException('malformed_cbor', 'Trailing bytes after CBOR value.');
        }
        return $value;
    }

    /** @return array{0:mixed,1:string} value + remaining (undecoded) bytes */
    public static function decodeFirst(string $bytes): array
    {
        $offset = 0;
        $value = self::item($bytes, $offset, 0);
        return [$value, substr($bytes, $offset)];
    }

    private static function item(string $b, int &$o, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new WebAuthnException('malformed_cbor', 'CBOR nesting exceeds depth cap.');
        }
        $initial = self::byte($b, $o);
        $major = $initial >> 5;
        $info = $initial & 0x1f;
        if ($info === 31) {
            throw new WebAuthnException('malformed_cbor', 'Indefinite-length CBOR is not accepted.');
        }

        return match ($major) {
            0 => self::length($b, $o, $info),
            1 => -1 - self::length($b, $o, $info),
            2, 3 => self::str($b, $o, self::length($b, $o, $info)),
            4 => self::arr($b, $o, self::length($b, $o, $info), $depth),
            5 => self::map($b, $o, self::length($b, $o, $info), $depth),
            7 => match ($info) {
                20 => false,
                21 => true,
                22 => null,
                default => throw new WebAuthnException('malformed_cbor', 'Unsupported CBOR simple or float value.'),
            },
            default => throw new WebAuthnException('malformed_cbor', 'Unsupported CBOR major type ' . $major . '.'),
        };
    }

    private static function byte(string $b, int &$o): int
    {
        if ($o >= strlen($b)) {
            throw new WebAuthnException('malformed_cbor', 'Unexpected end of CBOR input.');
        }
        return ord($b[$o++]);
    }

    private static function length(string $b, int &$o, int $info): int
    {
        if ($info < 24) {
            return $info;
        }
        $extra = match ($info) {
            24 => 1,
            25 => 2,
            26 => 4,
            27 => 8,
            default => throw new WebAuthnException('malformed_cbor', 'Reserved CBOR additional-info value.'),
        };
        $len = 0;
        for ($i = 0; $i < $extra; $i++) {
            $len = ($len << 8) | self::byte($b, $o);
        }
        if ($len < 0 || $len > self::MAX_BYTES) {
            throw new WebAuthnException('malformed_cbor', 'CBOR length out of accepted range.');
        }
        return $len;
    }

    private static function str(string $b, int &$o, int $len): string
    {
        if ($o + $len > strlen($b)) {
            throw new WebAuthnException('malformed_cbor', 'CBOR string exceeds available input.');
        }
        $s = substr($b, $o, $len);
        $o += $len;
        return $s;
    }

    /** @return list<mixed> */
    private static function arr(string $b, int &$o, int $count, int $depth): array
    {
        if ($count > self::MAX_ITEMS) {
            throw new WebAuthnException('malformed_cbor', 'CBOR array exceeds item cap.');
        }
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = self::item($b, $o, $depth + 1);
        }
        return $out;
    }

    /** @return array<int|string, mixed> */
    private static function map(string $b, int &$o, int $count, int $depth): array
    {
        if ($count > self::MAX_ITEMS) {
            throw new WebAuthnException('malformed_cbor', 'CBOR map exceeds item cap.');
        }
        $out = [];
        $seen = [];
        for ($i = 0; $i < $count; $i++) {
            $key = self::item($b, $o, $depth + 1);
            if (!is_int($key) && !is_string($key)) {
                throw new WebAuthnException('malformed_cbor', 'CBOR map key must be an integer or string.');
            }
            if (is_string($key) && preg_match('/^-?(?:0|[1-9][0-9]*)$/', $key) === 1) {
                throw new WebAuthnException('malformed_cbor', 'Numeric-string CBOR map keys are not accepted.');
            }
            $tag = (is_int($key) ? 'i:' : 's:') . $key;
            if (isset($seen[$tag])) {
                throw new WebAuthnException('malformed_cbor', 'Duplicate CBOR map key.');
            }
            $seen[$tag] = true;
            $out[$key] = self::item($b, $o, $depth + 1);
        }
        return $out;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
vendor/bin/phpunit tests/Unit/Security/WebAuthn/CborDecoderTest.php
```

Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Security/WebAuthn/WebAuthnException.php src/Security/WebAuthn/CborDecoder.php tests/Unit/Security/WebAuthn/CborDecoderTest.php
git commit -m "feat(webauthn): coded exception type + minimal definite-length CBOR decoder

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: `CoseKey` — COSE_Key → DER SPKI → `openssl_verify` (+ declare `ext-openssl`)

Parses the two accepted COSE key types (EC2/P-256 for ES256, RSA for RS256), assembles the DER `SubjectPublicKeyInfo` PEM natively (no ASN.1 library — two fixed OID prefixes plus a minimal DER INTEGER/length encoder), and verifies signatures through `openssl_verify`. Everything else refuses with `unsupported_algorithm` — this is the algorithm-confusion guard.

**Files:**
- Create: `src/Security/WebAuthn/CoseKey.php`
- Modify: `composer.json` (add `"ext-openssl": "*"` to `require`, after `"ext-mbstring"`)
- Test: `tests/Unit/Security/WebAuthn/CoseKeyTest.php`

**Interfaces:**
- Consumes: `CborDecoder::decode`, `WebAuthnException`.
- Produces: `CoseKey::fromCbor(string $cborBytes): self`, `public readonly int $alg`, `verify(string $data, string $signature): bool`, `toPem(): string`; constants `CoseKey::ALG_ES256 = -7`, `CoseKey::ALG_RS256 = -257`.

- [ ] **Step 1: Write the failing test**

The test builds real keys with openssl and hand-encodes their COSE form — real cryptography, no mocks (PHASE_5_PLAN §10.2). The tiny CBOR-encoding helpers here are the same ones Task 7's `WebAuthnHarness` centralizes; keep them private to the test for now.

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security\WebAuthn;

use App\Security\WebAuthn\CoseKey;
use App\Security\WebAuthn\WebAuthnException;
use PHPUnit\Framework\TestCase;

final class CoseKeyTest extends TestCase
{
    public function test_es256_cose_key_verifies_a_real_openssl_signature_and_rejects_tampering(): void
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        self::assertNotFalse($key);
        $d = openssl_pkey_get_details($key);
        $cose = self::coseMap([1 => 2, 3 => -7, -1 => 1, -2 => self::pad32($d['ec']['x']), -3 => self::pad32($d['ec']['y'])]);

        $parsed = CoseKey::fromCbor($cose);
        self::assertSame(CoseKey::ALG_ES256, $parsed->alg);

        $data = 'authenticator-data||client-data-hash';
        openssl_sign($data, $sig, $key, OPENSSL_ALGO_SHA256);
        self::assertTrue($parsed->verify($data, $sig));
        self::assertFalse($parsed->verify($data . 'x', $sig));
        $sig[4] = chr(ord($sig[4]) ^ 0x01);
        self::assertFalse($parsed->verify($data, $sig));
    }

    public function test_rs256_cose_key_verifies_and_pem_matches_openssl_view_of_the_key(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        $d = openssl_pkey_get_details($key);
        $cose = self::coseMap([1 => 3, 3 => -257, -1 => $d['rsa']['n'], -2 => $d['rsa']['e']]);

        $parsed = CoseKey::fromCbor($cose);
        self::assertSame(CoseKey::ALG_RS256, $parsed->alg);
        self::assertSame(trim($d['key']), trim($parsed->toPem()));

        $data = 'assertion-bytes';
        openssl_sign($data, $sig, $key, OPENSSL_ALGO_SHA256);
        self::assertTrue($parsed->verify($data, $sig));
    }

    public function test_rejects_unsupported_algorithms_and_malformed_keys(): void
    {
        // EdDSA (kty OKP=1, alg -8) is deliberately not in the allowlist.
        $okp = self::coseMap([1 => 1, 3 => -8, -1 => 6, -2 => random_bytes(32)]);
        try {
            CoseKey::fromCbor($okp);
            self::fail('EdDSA must be refused');
        } catch (WebAuthnException $e) {
            self::assertSame('unsupported_algorithm', $e->code);
        }

        // EC2 point with a 31-byte X coordinate.
        $bad = self::coseMap([1 => 2, 3 => -7, -1 => 1, -2 => random_bytes(31), -3 => random_bytes(32)]);
        try {
            CoseKey::fromCbor($bad);
            self::fail('Short coordinate must be refused');
        } catch (WebAuthnException $e) {
            self::assertSame('malformed_key', $e->code);
        }

        // EC2 point with valid lengths but not a valid P-256 public point.
        $offCurve = self::coseMap([1 => 2, 3 => -7, -1 => 1, -2 => str_repeat("\0", 32), -3 => str_repeat("\0", 32)]);
        try {
            CoseKey::fromCbor($offCurve);
            self::fail('Off-curve point must be refused before storage');
        } catch (WebAuthnException $e) {
            self::assertSame('malformed_key', $e->code);
        }

        // RSA key with valid byte lengths but an unusable public exponent.
        $badRsa = self::coseMap([1 => 3, 3 => -257, -1 => "\x80" . str_repeat("\0", 255), -2 => "\x02"]);
        try {
            CoseKey::fromCbor($badRsa);
            self::fail('Even RSA exponent must be refused before storage');
        } catch (WebAuthnException $e) {
            self::assertSame('malformed_key', $e->code);
        }

        $this->expectException(WebAuthnException::class);
        CoseKey::fromCbor("\x01"); // not a map
    }

    private static function pad32(string $bin): string
    {
        return str_pad(ltrim($bin, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    /** @param array<int, int|string> $entries int values encode as CBOR ints, strings as byte strings */
    private static function coseMap(array $entries): string
    {
        $out = chr(0xa0 + count($entries));
        foreach ($entries as $k => $v) {
            $out .= self::cborInt($k);
            $out .= is_int($v) ? self::cborInt($v) : self::cborBstr($v);
        }
        return $out;
    }

    private static function cborInt(int $v): string
    {
        return $v >= 0 ? self::cborHead(0, $v) : self::cborHead(1, -1 - $v);
    }

    private static function cborBstr(string $s): string
    {
        return self::cborHead(2, strlen($s)) . $s;
    }

    private static function cborHead(int $major, int $value): string
    {
        $m = $major << 5;
        if ($value < 24) {
            return chr($m | $value);
        }
        if ($value < 256) {
            return chr($m | 24) . chr($value);
        }
        if ($value < 65536) {
            return chr($m | 25) . pack('n', $value);
        }
        return chr($m | 26) . pack('N', $value);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

```bash
vendor/bin/phpunit tests/Unit/Security/WebAuthn/CoseKeyTest.php
```

Expected: FAIL — `Class "App\Security\WebAuthn\CoseKey" not found`.

- [ ] **Step 3: Implement**

`src/Security/WebAuthn/CoseKey.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

/**
 * COSE_Key (RFC 9052) parser for the accepted WebAuthn algorithms only:
 * ES256 (kty EC2, crv P-256, alg -7) and RS256 (kty RSA, alg -257).
 * Builds a DER SubjectPublicKeyInfo PEM so ext-openssl verifies signatures;
 * anything outside the allowlist refuses (algorithm-confusion guard).
 */
final class CoseKey
{
    public const ALG_ES256 = -7;
    public const ALG_RS256 = -257;

    private function __construct(
        public readonly int $alg,
        private readonly string $pem,
    ) {
    }

    public static function fromCbor(string $cborBytes): self
    {
        $map = CborDecoder::decode($cborBytes);
        if (!is_array($map)) {
            throw new WebAuthnException('malformed_key', 'COSE key is not a CBOR map.');
        }
        $kty = $map[1] ?? null;
        $alg = $map[3] ?? null;

        if ($kty === 2 && $alg === self::ALG_ES256) {
            $crv = $map[-1] ?? null;
            $x = $map[-2] ?? null;
            $y = $map[-3] ?? null;
            if ($crv !== 1 || !is_string($x) || !is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) {
                throw new WebAuthnException('malformed_key', 'COSE EC2 key is not a valid P-256 point encoding.');
            }
            $pem = self::pem(self::EC_P256_SPKI_PREFIX . "\x04" . $x . $y);
            self::assertOpenSslAccepts($pem);
            return new self(self::ALG_ES256, $pem);
        }

        if ($kty === 3 && $alg === self::ALG_RS256) {
            $n = $map[-1] ?? null;
            $e = $map[-2] ?? null;
            if (!is_string($n) || !is_string($e)) {
                throw new WebAuthnException('malformed_key', 'COSE RSA key has an unsupported modulus or exponent size.');
            }
            $n = ltrim($n, "\0");
            $e = ltrim($e, "\0");
            if (strlen($n) < 256 || strlen($n) > 512 || strlen($e) < 1 || strlen($e) > 4) {
                throw new WebAuthnException('malformed_key', 'COSE RSA key has an unsupported modulus or exponent size.');
            }
            $exp = self::decodeSmallUint($e);
            if ($exp < 3 || ($exp & 1) === 0) {
                throw new WebAuthnException('malformed_key', 'COSE RSA key has an unusable public exponent.');
            }
            $rsa = self::derSequence(self::derUint($n) . self::derUint($e));
            $spki = self::derSequence(self::RSA_ALGORITHM_IDENTIFIER . self::derBitString($rsa));
            $pem = self::pem($spki);
            self::assertOpenSslAccepts($pem);
            return new self(self::ALG_RS256, $pem);
        }

        throw new WebAuthnException('unsupported_algorithm', 'Only ES256 and RS256 credentials are accepted.');
    }

    public function toPem(): string
    {
        return $this->pem;
    }

    public function verify(string $data, string $signature): bool
    {
        $key = openssl_pkey_get_public($this->pem);
        if ($key === false) {
            return false;
        }
        return openssl_verify($data, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /** SEQUENCE(SEQUENCE(OID id-ecPublicKey, OID prime256v1), BIT STRING <uncompressed point>) minus the point. */
    private const EC_P256_SPKI_PREFIX = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";

    /** SEQUENCE(OID rsaEncryption, NULL) */
    private const RSA_ALGORITHM_IDENTIFIER = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";

    private static function pem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function assertOpenSslAccepts(string $pem): void
    {
        if (openssl_pkey_get_public($pem) === false) {
            throw new WebAuthnException('malformed_key', 'COSE key does not decode to a usable OpenSSL public key.');
        }
    }

    private static function decodeSmallUint(string $bytes): int
    {
        $value = 0;
        foreach (unpack('C*', $bytes) as $byte) {
            $value = ($value << 8) | $byte;
        }
        return $value;
    }

    private static function derLength(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }
        $bytes = ltrim(pack('N', $len), "\0");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function derSequence(string $content): string
    {
        return "\x30" . self::derLength(strlen($content)) . $content;
    }

    private static function derBitString(string $content): string
    {
        return "\x03" . self::derLength(strlen($content) + 1) . "\x00" . $content;
    }

    private static function derUint(string $raw): string
    {
        $raw = ltrim($raw, "\0");
        if ($raw === '') {
            $raw = "\0";
        }
        if ((ord($raw[0]) & 0x80) !== 0) {
            $raw = "\0" . $raw;
        }
        return "\x02" . self::derLength(strlen($raw)) . $raw;
    }
}
```

Declare the dependency in `composer.json` — in `require`, after `"ext-mbstring": "*"`:

```json
        "ext-openssl": "*",
```

then refresh the lock hash:

```bash
composer update --lock && composer validate
```

Expected: `./composer.json is valid`, lock file updated without dependency changes.

- [ ] **Step 4: Run the test to verify it passes**

```bash
vendor/bin/phpunit tests/Unit/Security/WebAuthn/CoseKeyTest.php
```

Expected: PASS (3 tests). The RS256 PEM-equality assertion proves the hand-built DER matches openssl's own SPKI encoding byte-for-byte.

- [ ] **Step 5: Commit**

```bash
git add src/Security/WebAuthn/CoseKey.php tests/Unit/Security/WebAuthn/CoseKeyTest.php composer.json composer.lock
git commit -m "feat(webauthn): COSE_Key parsing (ES256/RS256 allowlist) with native DER assembly over ext-openssl

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: `AuthenticatorData` parser

Parses the fixed 37-byte header (rpIdHash, flags, signCount) plus the optional attested-credential block (AAGUID, credential id, COSE key — whose byte length is found by CBOR-decoding the first item) and optional extension data, refusing truncation and trailing bytes.

**Files:**
- Create: `src/Security/WebAuthn/AuthenticatorData.php`
- Test: `tests/Unit/Security/WebAuthn/AuthenticatorDataTest.php`

**Interfaces:**
- Consumes: `CborDecoder::decodeFirst`, `WebAuthnException`.
- Produces: `AuthenticatorData::parse(string $bytes): self`; readonly `$rpIdHash`, `$flags`, `$signCount`, `?$aaguid`, `?$credentialId`, `?$credentialPublicKey` (raw COSE CBOR bytes); `userPresent()`, `userVerified()`, `backupEligible()`, `backedUp()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security\WebAuthn;

use App\Security\WebAuthn\AuthenticatorData;
use App\Security\WebAuthn\WebAuthnException;
use PHPUnit\Framework\TestCase;

final class AuthenticatorDataTest extends TestCase
{
    private const COSE_STUB = "\xa1\x01\x02"; // {1:2} — decodable map; key validity is CoseKey's job

    private static function bytes(int $flags, int $signCount, ?string $credId = null, string $cose = self::COSE_STUB, string $tail = ''): string
    {
        $out = hash('sha256', 'localhost', true) . chr($flags) . pack('N', $signCount);
        if ($credId !== null) {
            $out .= str_repeat("\xAA", 16) . pack('n', strlen($credId)) . $credId . $cose;
        }
        return $out . $tail;
    }

    public function test_parses_assertion_shape_and_flags(): void
    {
        $a = AuthenticatorData::parse(self::bytes(0x01 | 0x04 | 0x08 | 0x10, 42));
        self::assertSame(hash('sha256', 'localhost', true), $a->rpIdHash);
        self::assertSame(42, $a->signCount);
        self::assertTrue($a->userPresent());
        self::assertTrue($a->userVerified());
        self::assertTrue($a->backupEligible());
        self::assertTrue($a->backedUp());
        self::assertNull($a->credentialId);
        self::assertNull($a->credentialPublicKey);
    }

    public function test_parses_attested_credential_data(): void
    {
        $id = random_bytes(32);
        $a = AuthenticatorData::parse(self::bytes(0x01 | 0x40, 0, $id));
        self::assertSame($id, $a->credentialId);
        self::assertSame(str_repeat("\xAA", 16), $a->aaguid);
        self::assertSame(self::COSE_STUB, $a->credentialPublicKey);
        self::assertFalse($a->userVerified());
    }

    public function test_tolerates_extension_data_when_flagged(): void
    {
        $a = AuthenticatorData::parse(self::bytes(0x01 | 0x80, 7, null, self::COSE_STUB, "\xa1\x63abc\x01"));
        self::assertSame(7, $a->signCount);
    }

    public function test_rejects_truncation_trailing_bytes_and_flag_shape_mismatches(): void
    {
        try {
            AuthenticatorData::parse(substr(self::bytes(0x01, 1), 0, 36));
            self::fail('Short input must throw');
        } catch (WebAuthnException $e) {
            self::assertSame('malformed_authenticator_data', $e->code);
        }
        try {
            AuthenticatorData::parse(self::bytes(0x01, 1, null, self::COSE_STUB, 'junk'));
            self::fail('Trailing bytes must throw');
        } catch (WebAuthnException $e) {
            self::assertSame('malformed_authenticator_data', $e->code);
        }
        // AT flag set but no attested block present.
        $this->expectException(WebAuthnException::class);
        AuthenticatorData::parse(hash('sha256', 'localhost', true) . chr(0x41) . pack('N', 1));
    }

    public function test_rejects_out_of_range_credential_id_length(): void
    {
        $raw = hash('sha256', 'localhost', true) . chr(0x41) . pack('N', 0)
            . str_repeat("\xAA", 16) . pack('n', 1024) . str_repeat('x', 1024) . self::COSE_STUB;
        $this->expectException(WebAuthnException::class);
        AuthenticatorData::parse($raw);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

```bash
vendor/bin/phpunit tests/Unit/Security/WebAuthn/AuthenticatorDataTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

`src/Security/WebAuthn/AuthenticatorData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

/**
 * WebAuthn authenticator-data structure (§6.1): 32-byte rpIdHash, 1 flag byte,
 * 4-byte big-endian signCount, optional attested-credential block, optional
 * extensions. The COSE key is captured as its raw CBOR bytes (the storable form).
 */
final class AuthenticatorData
{
    private const FLAG_UP = 0x01;
    private const FLAG_UV = 0x04;
    private const FLAG_BE = 0x08;
    private const FLAG_BS = 0x10;
    private const FLAG_AT = 0x40;
    private const FLAG_ED = 0x80;

    private function __construct(
        public readonly string $rpIdHash,
        public readonly int $flags,
        public readonly int $signCount,
        public readonly ?string $aaguid,
        public readonly ?string $credentialId,
        public readonly ?string $credentialPublicKey,
    ) {
    }

    public static function parse(string $bytes): self
    {
        if (strlen($bytes) < 37) {
            throw new WebAuthnException('malformed_authenticator_data', 'Authenticator data shorter than 37 bytes.');
        }
        $rpIdHash = substr($bytes, 0, 32);
        $flags = ord($bytes[32]);
        $signCount = unpack('N', substr($bytes, 33, 4))[1];
        $rest = substr($bytes, 37);

        $aaguid = null;
        $credentialId = null;
        $credentialPublicKey = null;

        if (($flags & self::FLAG_AT) !== 0) {
            if (strlen($rest) < 18) {
                throw new WebAuthnException('malformed_authenticator_data', 'Attested credential data truncated.');
            }
            $aaguid = substr($rest, 0, 16);
            $idLen = unpack('n', substr($rest, 16, 2))[1];
            // §6.5.2 caps credential ids at 1023 bytes and sets no floor; the 16-byte
            // minimum is a deliberate hardening choice (every real authenticator issues
            // >=16-byte random ids) that rejects trivially short / degenerate ids.
            if ($idLen < 16 || $idLen > 1023 || strlen($rest) < 18 + $idLen) {
                throw new WebAuthnException('malformed_authenticator_data', 'Credential id length out of range.');
            }
            $credentialId = substr($rest, 18, $idLen);
            [$coseMap, $after] = CborDecoder::decodeFirst(substr($rest, 18 + $idLen));
            if (!is_array($coseMap)) {
                throw new WebAuthnException('malformed_authenticator_data', 'Credential public key is not a COSE map.');
            }
            $coseLen = strlen($rest) - 18 - $idLen - strlen($after);
            $credentialPublicKey = substr($rest, 18 + $idLen, $coseLen);
            $rest = $after;
        }

        if (($flags & self::FLAG_ED) !== 0) {
            [, $rest] = CborDecoder::decodeFirst($rest);
        }
        if ($rest !== '') {
            throw new WebAuthnException('malformed_authenticator_data', 'Trailing bytes after authenticator data.');
        }

        return new self($rpIdHash, $flags, $signCount, $aaguid, $credentialId, $credentialPublicKey);
    }

    public function userPresent(): bool
    {
        return ($this->flags & self::FLAG_UP) !== 0;
    }

    public function userVerified(): bool
    {
        return ($this->flags & self::FLAG_UV) !== 0;
    }

    public function backupEligible(): bool
    {
        return ($this->flags & self::FLAG_BE) !== 0;
    }

    public function backedUp(): bool
    {
        return ($this->flags & self::FLAG_BS) !== 0;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
vendor/bin/phpunit tests/Unit/Security/WebAuthn/AuthenticatorDataTest.php
```

Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Security/WebAuthn/AuthenticatorData.php tests/Unit/Security/WebAuthn/AuthenticatorDataTest.php
git commit -m "feat(webauthn): authenticator-data parser with attested-credential and extension handling

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 6: `RelyingParty` — origin/RP-ID policy from `APP_URL` (A5/D6)

The policy seat: canonical origin and RP ID derive from config, never from the request. Implements decision-to-record #1 (env override, full-host default) and the A5 production hard-refuse. This starts the spec-pinned `tests/Unit/Auth/WebAuthnPolicyTest.php`, which Task 8 extends with ceremony policy.

**Files:**
- Create: `src/Security/WebAuthn/RelyingParty.php`
- Modify: `.env.example` (document `WEBAUTHN_RP_ID`, next to `APP_URL`)
- Test: `tests/Unit/Auth/WebAuthnPolicyTest.php` (new directory `tests/Unit/Auth/`)

**Interfaces:**
- Consumes: `WebAuthnException`.
- Produces: `new RelyingParty(string $appUrl, ?string $rpIdOverride, string $appEnv)`; `origin(): string` (normalized, default ports stripped), `rpId(): string`, `rpIdHash(): string` (raw 32 bytes), `assertUsable(): void`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Security\WebAuthn\RelyingParty;
use App\Security\WebAuthn\WebAuthnException;
use PHPUnit\Framework\TestCase;

final class WebAuthnPolicyTest extends TestCase
{
    public function test_origin_is_normalized_and_rp_id_defaults_to_the_full_host(): void
    {
        $rp = new RelyingParty('https://forum.example.com:443/', null, 'production');
        self::assertSame('https://forum.example.com', $rp->origin());
        self::assertSame('forum.example.com', $rp->rpId());
        self::assertSame(hash('sha256', 'forum.example.com', true), $rp->rpIdHash());

        $dev = new RelyingParty('http://localhost:8000', null, 'local');
        self::assertSame('http://localhost:8000', $dev->origin());
        self::assertSame('localhost', $dev->rpId());
    }

    public function test_rp_id_override_must_be_a_registrable_suffix_of_the_host(): void
    {
        $rp = new RelyingParty('https://forum.example.com', 'example.com', 'production');
        self::assertSame('example.com', $rp->rpId());

        try {
            new RelyingParty('https://forum.example.com', 'other.com', 'production');
            self::fail('Non-suffix override must refuse');
        } catch (WebAuthnException $e) {
            self::assertSame('invalid_rp_id', $e->code);
        }
        // "…ple.com" is a substring but not a dot-boundary suffix.
        $this->expectException(WebAuthnException::class);
        new RelyingParty('https://forum.example.com', 'ple.com', 'production');
    }

    public function test_production_over_plain_http_hard_refuses_ceremonies(): void
    {
        $rp = new RelyingParty('http://forum.example.com', null, 'production');
        try {
            $rp->assertUsable();
            self::fail('Insecure production origin must hard-refuse');
        } catch (WebAuthnException $e) {
            self::assertSame('insecure_origin', $e->code);
        }
        // The A5 exceptions: HTTPS production, localhost, and non-production envs.
        (new RelyingParty('https://forum.example.com', null, 'production'))->assertUsable();
        (new RelyingParty('http://localhost:8000', null, 'production'))->assertUsable();
        (new RelyingParty('http://forum.example.com', null, 'testing'))->assertUsable();
        $this->addToAssertionCount(3);
    }

    public function test_unusable_app_url_refuses(): void
    {
        $this->expectException(WebAuthnException::class);
        new RelyingParty('not-a-url', null, 'production');
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

```bash
vendor/bin/phpunit tests/Unit/Auth/WebAuthnPolicyTest.php
```

Expected: FAIL — `Class "App\Security\WebAuthn\RelyingParty" not found`.

- [ ] **Step 3: Implement**

`src/Security/WebAuthn/RelyingParty.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

/**
 * Canonical origin + RP ID per A5/D6: both derive from the configured APP_URL,
 * never from request headers. RP ID defaults to the full APP_URL host (always a
 * valid, strictly-narrower RP ID); operators wanting subdomain portability set
 * WEBAUTHN_RP_ID to their registrable domain (validated as a dot-suffix here;
 * the browser enforces the public-suffix boundary). Production over plain HTTP
 * hard-refuses (A5 §3 owner sign-off) except for localhost.
 */
final class RelyingParty
{
    private readonly string $scheme;
    private readonly string $host;
    private readonly ?int $port;
    private readonly string $rpId;

    public function __construct(string $appUrl, ?string $rpIdOverride, private readonly string $appEnv)
    {
        $parts = parse_url(trim($appUrl));
        $scheme = strtolower((string) (($parts ?: [])['scheme'] ?? ''));
        $host = strtolower((string) (($parts ?: [])['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new WebAuthnException('invalid_app_url', 'APP_URL must be an absolute http(s) URL to use passkeys.');
        }
        $this->scheme = $scheme;
        $this->host = $host;
        $port = ($parts ?: [])['port'] ?? null;
        $isDefault = $port === null || ($scheme === 'https' && (int) $port === 443) || ($scheme === 'http' && (int) $port === 80);
        $this->port = $isDefault ? null : (int) $port;

        $override = $rpIdOverride !== null ? strtolower(trim($rpIdOverride)) : '';
        if ($override !== '') {
            if ($override !== $host && !str_ends_with($host, '.' . $override)) {
                throw new WebAuthnException('invalid_rp_id', 'WEBAUTHN_RP_ID must equal the APP_URL host or be a parent domain of it.');
            }
            $this->rpId = $override;
        } else {
            $this->rpId = $host;
        }
    }

    public function origin(): string
    {
        return $this->scheme . '://' . $this->host . ($this->port !== null ? ':' . $this->port : '');
    }

    public function rpId(): string
    {
        return $this->rpId;
    }

    public function rpIdHash(): string
    {
        return hash('sha256', $this->rpId, true);
    }

    public function assertUsable(): void
    {
        $local = $this->host === 'localhost' || str_ends_with($this->host, '.localhost')
            || $this->host === '127.0.0.1' || $this->host === '::1';
        if ($this->appEnv === 'production' && $this->scheme !== 'https' && !$local) {
            throw new WebAuthnException('insecure_origin', 'Passkeys require an HTTPS APP_URL in production (A5 §3).');
        }
    }
}
```

In `.env.example`, directly under the `APP_URL` line, add:

```
# Optional WebAuthn relying-party id. Defaults to the APP_URL host. Set to your
# registrable domain (e.g. example.com for forum.example.com) if you want
# passkeys to survive subdomain moves. Changing it later invalidates passkeys —
# see docs/runbooks/passkeys.md before touching this.
WEBAUTHN_RP_ID=
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
vendor/bin/phpunit tests/Unit/Auth/WebAuthnPolicyTest.php
```

Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Security/WebAuthn/RelyingParty.php tests/Unit/Auth/WebAuthnPolicyTest.php .env.example
git commit -m "feat(webauthn): RelyingParty origin/RP-ID policy from APP_URL with production hard-refuse (A5/D6)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 7: `Tests\Support\Phase5\WebAuthnHarness` — real-crypto ceremony fixtures

The test-only counterpart of a browser authenticator: mints real P-256/RSA keypairs with openssl, hand-encodes their COSE form, builds authenticator data / clientDataJSON / attestation objects, and signs assertions. Every negative fixture (wrong origin, wrong RP, tampered signature, replayed challenge) derives from a valid one via `$overrides`. **The only private-key material in the increment lives here.**

**Files:**
- Create: `tests/Support/Phase5/WebAuthnHarness.php`
- Test: `tests/Unit/Security/WebAuthn/WebAuthnHarnessTest.php`

**Interfaces:**
- Consumes: `Base64Url`, openssl.
- Produces (consumed by Tasks 8, 11, 13, 14, 15, 17):
  - `new WebAuthnHarness(string $rpId = 'localhost', string $origin = 'http://localhost:8000')`
  - `createCredential(): array{privateKey:\OpenSSLAsymmetricKey, credentialId:string, coseKey:string, alg:int}`
  - `rs256Credential(): array` (same shape)
  - `registrationPayload(array $cred, string $challenge, array $overrides = []): string` — JSON; overrides: `type`, `origin`, `rpId`, `flags`, `signCount`
  - `assertionPayload(array $cred, string $challenge, int $signCount, array $overrides = []): string` — JSON; overrides: `type`, `origin`, `rpId`, `flags`, `challengeOverride`, `tamperSignature`
  - `authData(string $rpId, int $flags, int $signCount, ?string $credentialId, ?string $coseKey): string`

- [ ] **Step 1: Write the failing self-consistency test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security\WebAuthn;

use App\Security\WebAuthn\AuthenticatorData;
use App\Security\WebAuthn\CborDecoder;
use App\Security\WebAuthn\CoseKey;
use App\Support\Base64Url;
use PHPUnit\Framework\TestCase;
use Tests\Support\Phase5\WebAuthnHarness;

final class WebAuthnHarnessTest extends TestCase
{
    public function test_registration_payload_decomposes_into_valid_protocol_pieces(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->createCredential();
        $challenge = random_bytes(32);
        $payload = json_decode($h->registrationPayload($cred, $challenge), true);

        $clientData = json_decode((string) Base64Url::decode($payload['response']['clientDataJSON']), true);
        self::assertSame('webauthn.create', $clientData['type']);
        self::assertSame(Base64Url::encode($challenge), $clientData['challenge']);
        self::assertSame('http://localhost:8000', $clientData['origin']);

        $attObj = CborDecoder::decode((string) Base64Url::decode($payload['response']['attestationObject']));
        self::assertSame('none', $attObj['fmt']);
        self::assertSame([], $attObj['attStmt']);
        $auth = AuthenticatorData::parse($attObj['authData']);
        self::assertSame($cred['credentialId'], $auth->credentialId);
        self::assertSame($cred['coseKey'], $auth->credentialPublicKey);
        self::assertSame(CoseKey::ALG_ES256, CoseKey::fromCbor((string) $auth->credentialPublicKey)->alg);
    }

    public function test_assertion_signature_verifies_against_the_minted_cose_key_for_both_algorithms(): void
    {
        $h = new WebAuthnHarness();
        foreach ([$h->createCredential(), $h->rs256Credential()] as $cred) {
            $challenge = random_bytes(32);
            $payload = json_decode($h->assertionPayload($cred, $challenge, 5), true);
            $authData = (string) Base64Url::decode($payload['response']['authenticatorData']);
            $clientDataRaw = (string) Base64Url::decode($payload['response']['clientDataJSON']);
            $sig = (string) Base64Url::decode($payload['response']['signature']);
            $key = CoseKey::fromCbor($cred['coseKey']);
            self::assertTrue($key->verify($authData . hash('sha256', $clientDataRaw, true), $sig));
        }
    }

    public function test_tamper_override_breaks_the_signature(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->createCredential();
        $payload = json_decode($h->assertionPayload($cred, random_bytes(32), 1, ['tamperSignature' => true]), true);
        $authData = (string) Base64Url::decode($payload['response']['authenticatorData']);
        $clientDataRaw = (string) Base64Url::decode($payload['response']['clientDataJSON']);
        $sig = (string) Base64Url::decode($payload['response']['signature']);
        self::assertFalse(CoseKey::fromCbor($cred['coseKey'])->verify($authData . hash('sha256', $clientDataRaw, true), $sig));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

```bash
vendor/bin/phpunit tests/Unit/Security/WebAuthn/WebAuthnHarnessTest.php
```

Expected: FAIL — `Class "Tests\Support\Phase5\WebAuthnHarness" not found`.

- [ ] **Step 3: Implement**

`tests/Support/Phase5/WebAuthnHarness.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Support\Phase5;

use App\Support\Base64Url;

/**
 * Test-only WebAuthn "authenticator": real openssl keypairs, hand-encoded COSE,
 * signed assertions. Negative fixtures derive from valid ones via $overrides.
 * Private keys exist only in-process here — never in src/ (decision #28).
 */
final class WebAuthnHarness
{
    public function __construct(
        private readonly string $rpId = 'localhost',
        private readonly string $origin = 'http://localhost:8000',
    ) {
    }

    /** @return array{privateKey:\OpenSSLAsymmetricKey, credentialId:string, coseKey:string, alg:int} */
    public function createCredential(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        assert($key !== false);
        $d = openssl_pkey_get_details($key);
        $cose = self::coseMap([1 => 2, 3 => -7, -1 => 1, -2 => self::pad32($d['ec']['x']), -3 => self::pad32($d['ec']['y'])]);
        return ['privateKey' => $key, 'credentialId' => random_bytes(32), 'coseKey' => $cose, 'alg' => -7];
    }

    /** @return array{privateKey:\OpenSSLAsymmetricKey, credentialId:string, coseKey:string, alg:int} */
    public function rs256Credential(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        assert($key !== false);
        $d = openssl_pkey_get_details($key);
        $cose = self::coseMap([1 => 3, 3 => -257, -1 => $d['rsa']['n'], -2 => $d['rsa']['e']]);
        return ['privateKey' => $key, 'credentialId' => random_bytes(32), 'coseKey' => $cose, 'alg' => -257];
    }

    /** @param array{type?:string, origin?:string, rpId?:string, flags?:int, signCount?:int} $overrides */
    public function registrationPayload(array $cred, string $challenge, array $overrides = []): string
    {
        $clientData = $this->clientData($overrides['type'] ?? 'webauthn.create', $challenge, $overrides['origin'] ?? $this->origin);
        $authData = $this->authData(
            $overrides['rpId'] ?? $this->rpId,
            $overrides['flags'] ?? (0x01 | 0x04 | 0x40),
            $overrides['signCount'] ?? 0,
            $cred['credentialId'],
            $cred['coseKey'],
        );
        $attObj = "\xa3"
            . self::tstr('fmt') . self::tstr('none')
            . self::tstr('attStmt') . "\xa0"
            . self::tstr('authData') . self::bstr($authData);

        return json_encode([
            'id' => Base64Url::encode($cred['credentialId']),
            'rawId' => Base64Url::encode($cred['credentialId']),
            'type' => 'public-key',
            'transports' => ['internal'],
            'response' => [
                'clientDataJSON' => Base64Url::encode($clientData),
                'attestationObject' => Base64Url::encode($attObj),
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /** @param array{type?:string, origin?:string, rpId?:string, flags?:int, challengeOverride?:string, tamperSignature?:bool} $overrides */
    public function assertionPayload(array $cred, string $challenge, int $signCount, array $overrides = []): string
    {
        $clientData = $this->clientData(
            $overrides['type'] ?? 'webauthn.get',
            $overrides['challengeOverride'] ?? $challenge,
            $overrides['origin'] ?? $this->origin,
        );
        $authData = $this->authData($overrides['rpId'] ?? $this->rpId, $overrides['flags'] ?? (0x01 | 0x04), $signCount, null, null);
        openssl_sign($authData . hash('sha256', $clientData, true), $signature, $cred['privateKey'], OPENSSL_ALGO_SHA256);
        if (($overrides['tamperSignature'] ?? false) === true) {
            $signature[5] = chr(ord($signature[5]) ^ 0xff);
        }

        return json_encode([
            'id' => Base64Url::encode($cred['credentialId']),
            'rawId' => Base64Url::encode($cred['credentialId']),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64Url::encode($clientData),
                'authenticatorData' => Base64Url::encode($authData),
                'signature' => Base64Url::encode($signature),
            ],
        ], JSON_THROW_ON_ERROR);
    }

    public function authData(string $rpId, int $flags, int $signCount, ?string $credentialId, ?string $coseKey): string
    {
        $out = hash('sha256', $rpId, true) . chr($flags) . pack('N', $signCount);
        if ($credentialId !== null && $coseKey !== null) {
            $out .= str_repeat("\xAA", 16) . pack('n', strlen($credentialId)) . $credentialId . $coseKey;
        }
        return $out;
    }

    private function clientData(string $type, string $challenge, string $origin): string
    {
        return json_encode([
            'type' => $type,
            'challenge' => Base64Url::encode($challenge),
            'origin' => $origin,
            'crossOrigin' => false,
        ], JSON_THROW_ON_ERROR);
    }

    private static function pad32(string $bin): string
    {
        return str_pad(ltrim($bin, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    /** @param array<int, int|string> $entries */
    private static function coseMap(array $entries): string
    {
        $out = chr(0xa0 + count($entries));
        foreach ($entries as $k => $v) {
            $out .= self::int($k) . (is_int($v) ? self::int($v) : self::bstr($v));
        }
        return $out;
    }

    private static function int(int $v): string
    {
        return $v >= 0 ? self::head(0, $v) : self::head(1, -1 - $v);
    }

    private static function bstr(string $s): string
    {
        return self::head(2, strlen($s)) . $s;
    }

    private static function tstr(string $s): string
    {
        return self::head(3, strlen($s)) . $s;
    }

    private static function head(int $major, int $value): string
    {
        $m = $major << 5;
        if ($value < 24) {
            return chr($m | $value);
        }
        if ($value < 256) {
            return chr($m | 24) . chr($value);
        }
        if ($value < 65536) {
            return chr($m | 25) . pack('n', $value);
        }
        return chr($m | 26) . pack('N', $value);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
vendor/bin/phpunit tests/Unit/Security/WebAuthn/WebAuthnHarnessTest.php
```

Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add tests/Support/Phase5/WebAuthnHarness.php tests/Unit/Security/WebAuthn/WebAuthnHarnessTest.php
git commit -m "test(webauthn): real-crypto ceremony harness (openssl-signed fixtures, override-driven negatives)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 8: `WebAuthnVerifier` + result DTOs — the pure ceremony verifier

The stateless heart: given the JS-serialized credential JSON (as a decoded array), the expected challenge bytes, and (for assertions) the stored COSE key + counter, either return a result DTO or throw a coded `WebAuthnException`. TM-ID-07 (origin/rpIdHash) and the protocol-negative fixtures (wrong type, altered signature, algorithm confusion, missing UP/UV) are proven here at the unit layer; TM-ID-05/06/08/09 need state and land in the service tasks.

**Files:**
- Create: `src/Security/WebAuthn/RegisteredCredential.php`
- Create: `src/Security/WebAuthn/AssertionResult.php`
- Create: `src/Security/WebAuthn/WebAuthnVerifier.php`
- Test: `tests/Unit/Auth/WebAuthnPolicyTest.php` (extend Task 6's file)

**Interfaces:**
- Consumes: `Base64Url`, `CborDecoder`, `CoseKey`, `AuthenticatorData`, `RelyingParty`, `WebAuthnHarness` (tests).
- Produces: `new WebAuthnVerifier(RelyingParty $rp)`; `verifyRegistration(array $credential, string $expectedChallenge): RegisteredCredential`; `verifyAssertion(array $credential, string $expectedChallenge, string $publicKeyCbor, int $storedSignCount, bool $requireUv): AssertionResult`.

- [ ] **Step 1: Extend `WebAuthnPolicyTest` with failing ceremony tests**

Add to `tests/Unit/Auth/WebAuthnPolicyTest.php` (new `use` lines: `App\Security\WebAuthn\WebAuthnVerifier`, `Tests\Support\Phase5\WebAuthnHarness`; plus a small private helper):

```php
    private function verifier(): WebAuthnVerifier
    {
        return new WebAuthnVerifier(new RelyingParty('http://localhost:8000', null, 'testing'));
    }

    public function test_registration_happy_path_returns_the_storable_credential(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->createCredential();
        $challenge = random_bytes(32);
        $r = $this->verifier()->verifyRegistration(json_decode($h->registrationPayload($cred, $challenge), true), $challenge);
        self::assertSame($cred['credentialId'], $r->credentialId);
        self::assertSame($cred['coseKey'], $r->publicKey);
        self::assertSame(0, $r->signCount);
        self::assertSame(str_repeat("\xAA", 16), $r->aaguid);
        self::assertSame('internal', $r->transports);
        self::assertTrue($r->userVerified);
    }

    public function test_registration_rejects_wrong_origin_wrong_rp_wrong_type_and_stale_challenge(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->createCredential();
        $challenge = random_bytes(32);
        $cases = [
            ['overrides' => ['origin' => 'https://evil.test'], 'code' => 'origin_mismatch'],        // TM-ID-07
            ['overrides' => ['rpId' => 'evil.test'], 'code' => 'rp_id_mismatch'],                    // TM-ID-07
            ['overrides' => ['type' => 'webauthn.get'], 'code' => 'wrong_ceremony_type'],
            ['overrides' => ['flags' => 0x40], 'code' => 'user_presence_required'],                  // UP bit dropped
        ];
        foreach ($cases as $case) {
            try {
                $this->verifier()->verifyRegistration(
                    json_decode($h->registrationPayload($cred, $challenge, $case['overrides']), true),
                    $challenge,
                );
                self::fail('Expected refusal: ' . $case['code']);
            } catch (WebAuthnException $e) {
                self::assertSame($case['code'], $e->code);
            }
        }
        // Challenge mismatch: payload built for a different challenge.
        try {
            $this->verifier()->verifyRegistration(
                json_decode($h->registrationPayload($cred, random_bytes(32)), true),
                $challenge,
            );
            self::fail('Expected challenge_mismatch');
        } catch (WebAuthnException $e) {
            self::assertSame('challenge_mismatch', $e->code);
        }
    }

    public function test_registration_rejects_raw_id_spoofing(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->createCredential();
        $challenge = random_bytes(32);
        $payload = json_decode($h->registrationPayload($cred, $challenge), true);
        $payload['rawId'] = \App\Support\Base64Url::encode(random_bytes(32));
        try {
            $this->verifier()->verifyRegistration($payload, $challenge);
            self::fail('Expected credential_mismatch');
        } catch (WebAuthnException $e) {
            self::assertSame('credential_mismatch', $e->code);
        }
    }

    public function test_assertion_happy_path_counter_anomaly_and_signature_tamper(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->createCredential();
        $challenge = random_bytes(32);
        $v = $this->verifier();

        $ok = $v->verifyAssertion(json_decode($h->assertionPayload($cred, $challenge, 6), true), $challenge, $cred['coseKey'], 5, false);
        self::assertTrue($ok->userVerified);
        self::assertSame(6, $ok->signCount);
        self::assertFalse($ok->counterAnomaly);

        // Non-increasing counter: valid signature, anomaly flagged, NOT thrown (decision #30 / TM-ID-08).
        $anomaly = $v->verifyAssertion(json_decode($h->assertionPayload($cred, $challenge, 5), true), $challenge, $cred['coseKey'], 5, false);
        self::assertTrue($anomaly->counterAnomaly);

        // Zero counters (synced-passkey convention) are not anomalous.
        $zero = $v->verifyAssertion(json_decode($h->assertionPayload($cred, $challenge, 0), true), $challenge, $cred['coseKey'], 0, false);
        self::assertFalse($zero->counterAnomaly);

        try {
            $v->verifyAssertion(json_decode($h->assertionPayload($cred, $challenge, 7, ['tamperSignature' => true]), true), $challenge, $cred['coseKey'], 5, false);
            self::fail('Expected bad_signature');
        } catch (WebAuthnException $e) {
            self::assertSame('bad_signature', $e->code);
        }
    }

    public function test_assertion_enforces_uv_when_required_and_rejects_cross_key_signatures(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->createCredential();
        $challenge = random_bytes(32);
        $v = $this->verifier();

        try {
            $v->verifyAssertion(
                json_decode($h->assertionPayload($cred, $challenge, 3, ['flags' => 0x01]), true), // UP only
                $challenge, $cred['coseKey'], 1, true,
            );
            self::fail('Expected uv_required');
        } catch (WebAuthnException $e) {
            self::assertSame('uv_required', $e->code);
        }

        // Signature from a different key must not verify against the stored key.
        $other = $h->createCredential();
        try {
            $v->verifyAssertion(json_decode($h->assertionPayload($other, $challenge, 3), true), $challenge, $cred['coseKey'], 1, false);
            self::fail('Expected bad_signature');
        } catch (WebAuthnException $e) {
            self::assertSame('bad_signature', $e->code);
        }
    }

    public function test_rs256_assertion_verifies_end_to_end(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->rs256Credential();
        $challenge = random_bytes(32);
        $reg = $this->verifier()->verifyRegistration(json_decode($h->registrationPayload($cred, $challenge), true), $challenge);
        $challenge2 = random_bytes(32);
        $ok = $this->verifier()->verifyAssertion(json_decode($h->assertionPayload($cred, $challenge2, 1), true), $challenge2, $reg->publicKey, 0, false);
        self::assertSame(1, $ok->signCount);
    }
```

- [ ] **Step 2: Run to verify the new tests fail**

```bash
vendor/bin/phpunit tests/Unit/Auth/WebAuthnPolicyTest.php
```

Expected: the Task 6 tests still pass; the six new ones ERROR — `Class "App\Security\WebAuthn\WebAuthnVerifier" not found`.

- [ ] **Step 3: Implement**

`src/Security/WebAuthn/RegisteredCredential.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

/** Verified registration output — exactly the storable columns of webauthn_credentials. */
final class RegisteredCredential
{
    public function __construct(
        public readonly string $credentialId,
        public readonly string $publicKey,
        public readonly int $signCount,
        public readonly ?string $aaguid,
        public readonly string $transports,
        public readonly bool $userVerified,
        public readonly bool $backupEligible,
        public readonly bool $backedUp,
    ) {
    }
}
```

`src/Security/WebAuthn/AssertionResult.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

/** Verified assertion output. counterAnomaly is a risk signal, never a refusal (decision #30). */
final class AssertionResult
{
    public function __construct(
        public readonly bool $userVerified,
        public readonly int $signCount,
        public readonly bool $counterAnomaly,
    ) {
    }
}
```

`src/Security/WebAuthn/WebAuthnVerifier.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

use App\Support\Base64Url;

/**
 * Pure WebAuthn ceremony verifier (P5-11 SP1). Stateless: challenge issuance,
 * one-time consumption, credential lookup, and counter persistence live in
 * PasskeyService. Every failure throws a coded WebAuthnException (fail closed).
 * The attestation statement is shape-checked but never trusted (decision #29).
 */
final class WebAuthnVerifier
{
    public function __construct(private readonly RelyingParty $rp)
    {
    }

    /** @param array<string, mixed> $credential decoded ceremony JSON from the client */
    public function verifyRegistration(array $credential, string $expectedChallenge): RegisteredCredential
    {
        $this->rp->assertUsable();
        $response = $credential['response'] ?? null;
        if (($credential['type'] ?? '') !== 'public-key' || !is_array($response)) {
            throw new WebAuthnException('malformed_credential', 'Payload is not a public-key credential.');
        }
        $this->clientData((string) ($response['clientDataJSON'] ?? ''), 'webauthn.create', $expectedChallenge);

        $attBytes = Base64Url::decode((string) ($response['attestationObject'] ?? ''));
        if ($attBytes === null || $attBytes === '') {
            throw new WebAuthnException('malformed_credential', 'attestationObject is not valid base64url.');
        }
        $attObj = CborDecoder::decode($attBytes);
        if (!is_array($attObj) || !is_string($attObj['fmt'] ?? null) || !is_array($attObj['attStmt'] ?? null) || !is_string($attObj['authData'] ?? null)) {
            throw new WebAuthnException('malformed_credential', 'attestationObject shape is invalid.');
        }

        $auth = AuthenticatorData::parse($attObj['authData']);
        $this->checkAuthenticatorData($auth);
        if ($auth->credentialId === null || $auth->credentialPublicKey === null) {
            throw new WebAuthnException('malformed_credential', 'Attested credential data missing from registration.');
        }
        $rawId = Base64Url::decode((string) ($credential['rawId'] ?? ''));
        if ($rawId === null || $rawId === '' || !hash_equals($auth->credentialId, $rawId)) {
            throw new WebAuthnException('credential_mismatch', 'rawId does not match the attested credential id.');
        }
        CoseKey::fromCbor($auth->credentialPublicKey); // throws unsupported_algorithm / malformed_key

        $transports = '';
        if (is_array($credential['transports'] ?? null)) {
            $clean = array_values(array_filter(
                $credential['transports'],
                static fn ($t): bool => is_string($t) && preg_match('/^[a-z-]{1,20}$/', $t) === 1,
            ));
            $transports = substr(implode(',', array_slice($clean, 0, 8)), 0, 190);
        }

        return new RegisteredCredential(
            credentialId: $auth->credentialId,
            publicKey: $auth->credentialPublicKey,
            signCount: $auth->signCount,
            aaguid: $auth->aaguid,
            transports: $transports,
            userVerified: $auth->userVerified(),
            backupEligible: $auth->backupEligible(),
            backedUp: $auth->backedUp(),
        );
    }

    /** @param array<string, mixed> $credential decoded ceremony JSON from the client */
    public function verifyAssertion(array $credential, string $expectedChallenge, string $publicKeyCbor, int $storedSignCount, bool $requireUv): AssertionResult
    {
        $this->rp->assertUsable();
        $response = $credential['response'] ?? null;
        if (($credential['type'] ?? '') !== 'public-key' || !is_array($response)) {
            throw new WebAuthnException('malformed_credential', 'Payload is not a public-key credential.');
        }
        $clientDataRaw = $this->clientData((string) ($response['clientDataJSON'] ?? ''), 'webauthn.get', $expectedChallenge);

        $authBytes = Base64Url::decode((string) ($response['authenticatorData'] ?? ''));
        $signature = Base64Url::decode((string) ($response['signature'] ?? ''));
        if ($authBytes === null || $authBytes === '' || $signature === null || $signature === '') {
            throw new WebAuthnException('malformed_credential', 'authenticatorData/signature are not valid base64url.');
        }
        $auth = AuthenticatorData::parse($authBytes);
        $this->checkAuthenticatorData($auth);
        if ($requireUv && !$auth->userVerified()) {
            throw new WebAuthnException('uv_required', 'This action needs a passkey with user verification (PIN, fingerprint, or face unlock).');
        }

        $key = CoseKey::fromCbor($publicKeyCbor);
        if (!$key->verify($authBytes . hash('sha256', $clientDataRaw, true), $signature)) {
            throw new WebAuthnException('bad_signature', 'Assertion signature does not verify.');
        }

        $anomaly = $auth->signCount !== 0 && $storedSignCount !== 0 && $auth->signCount <= $storedSignCount;

        return new AssertionResult(
            userVerified: $auth->userVerified(),
            signCount: $auth->signCount,
            counterAnomaly: $anomaly,
        );
    }

    /** Validates clientDataJSON and returns its raw bytes (needed for the signature base). */
    private function clientData(string $b64u, string $expectedType, string $expectedChallenge): string
    {
        $raw = Base64Url::decode($b64u);
        if ($raw === null || $raw === '') {
            throw new WebAuthnException('malformed_client_data', 'clientDataJSON is not valid base64url.');
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new WebAuthnException('malformed_client_data', 'clientDataJSON is not valid JSON.');
        }
        if (($data['type'] ?? '') !== $expectedType) {
            throw new WebAuthnException('wrong_ceremony_type', 'clientData type mismatch.');
        }
        $challenge = Base64Url::decode((string) ($data['challenge'] ?? ''));
        if ($challenge === null || $challenge === '' || !hash_equals($expectedChallenge, $challenge)) {
            throw new WebAuthnException('challenge_mismatch', 'clientData challenge does not match the issued challenge.');
        }
        if (($data['origin'] ?? '') !== $this->rp->origin()) {
            throw new WebAuthnException('origin_mismatch', 'clientData origin does not match the canonical APP_URL origin.');
        }
        return $raw;
    }

    private function checkAuthenticatorData(AuthenticatorData $auth): void
    {
        if (!hash_equals($this->rp->rpIdHash(), $auth->rpIdHash)) {
            throw new WebAuthnException('rp_id_mismatch', 'rpIdHash does not match the configured RP ID.');
        }
        if (!$auth->userPresent()) {
            throw new WebAuthnException('user_presence_required', 'User-presence flag missing from authenticator data.');
        }
    }
}
```

- [ ] **Step 4: Run the whole WebAuthn unit surface**

```bash
vendor/bin/phpunit tests/Unit/Auth/WebAuthnPolicyTest.php tests/Unit/Security/WebAuthn/ tests/Unit/Support/Base64UrlTest.php
```

Expected: PASS (Task 6's 4 + 6 new + CBOR 8 + COSE 3 + authData 5 + harness 3).

- [ ] **Step 5: Commit**

```bash
git add src/Security/WebAuthn/WebAuthnVerifier.php src/Security/WebAuthn/RegisteredCredential.php src/Security/WebAuthn/AssertionResult.php tests/Unit/Auth/WebAuthnPolicyTest.php
git commit -m "feat(webauthn): pure ceremony verifier with fail-closed protocol-negative coverage (TM-ID-07 unit layer)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 9: Repositories over the `0051` tables + container binds

Thin single-table wrappers (`final`, prepared statements, assoc arrays) for credentials and one-time challenges, plus the DI wiring for them, `RelyingParty`, and `WebAuthnVerifier`. Challenge consumption is a single guarded `UPDATE` — the concurrency-safe one-time gate everything else builds on.

**Files:**
- Create: `src/Repository/WebAuthnCredentialRepository.php`
- Create: `src/Repository/WebAuthnChallengeRepository.php`
- Modify: `src/Core/App.php` (`buildContainer()` — add binds near the `MfaRepository` bind ~line 837)
- Modify: `config/config.php` (add `'webauthn_rp_id' => Env::get('WEBAUTHN_RP_ID', '')` to the `app` section, next to `app.url`; confirm the exact `Env` idiom used by neighboring keys and mirror it)
- Test: `tests/Integration/Repository/WebAuthnRepositoriesTest.php`

**Interfaces:**
- Consumes: `Database` (`run`/`fetch`/`fetchAll`/`fetchValue`/`insert`), the Task 6/8 classes.
- Produces: the two repository APIs from the Locked interfaces block; container-resolvable `RelyingParty` and `WebAuthnVerifier`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\WebAuthnChallengeRepository;
use App\Repository\WebAuthnCredentialRepository;
use Tests\Support\TestCase;

final class WebAuthnRepositoriesTest extends TestCase
{
    public function test_challenge_is_consumed_exactly_once_with_matching_binding(): void
    {
        $user = $this->makeUser();
        $repo = new WebAuthnChallengeRepository($this->db);
        $challenge = random_bytes(32);
        $binding = hash('sha256', 'session-a');

        $repo->mint((int) $user['id'], $binding, 'register', $challenge, 300);
        self::assertFalse($repo->consume($challenge, $binding, 'login', (int) $user['id']), 'purpose mismatch');
        self::assertFalse($repo->consume($challenge, hash('sha256', 'other'), 'register', (int) $user['id']), 'session mismatch');
        self::assertFalse($repo->consume($challenge, $binding, 'register', (int) $user['id'] + 1), 'user mismatch');
        self::assertTrue($repo->consume($challenge, $binding, 'register', (int) $user['id']));
        self::assertFalse($repo->consume($challenge, $binding, 'register', (int) $user['id']), 'replay must fail');
    }

    public function test_expired_challenges_never_consume_and_purge_deletes_stale_rows(): void
    {
        $user = $this->makeUser();
        $repo = new WebAuthnChallengeRepository($this->db);
        $challenge = random_bytes(32);
        $binding = hash('sha256', 'session-b');
        $repo->mint((int) $user['id'], $binding, 'login', $challenge, -90000); // expired a day+ ago
        self::assertFalse($repo->consume($challenge, $binding, 'login', (int) $user['id']));
        self::assertSame(1, $repo->purgeExpired());
    }

    public function test_credential_lifecycle_create_find_rename_use_revoke(): void
    {
        $user = $this->makeUser();
        $repo = new WebAuthnCredentialRepository($this->db);
        $rawId = random_bytes(32);
        $id = $repo->create([
            'user_id' => (int) $user['id'],
            'credential_id' => $rawId,
            'public_key' => "\xa1\x01\x02",
            'sign_count' => 0,
            'aaguid' => str_repeat("\xAA", 16),
            'transports' => 'internal',
            'is_discoverable' => 1,
            'is_backup_eligible' => 1,
            'is_backed_up' => 0,
            'nickname' => 'Laptop',
        ]);

        self::assertSame(1, $repo->countActiveForUser((int) $user['id']));
        self::assertSame($rawId, $repo->findActiveByCredentialId($rawId)['credential_id']);
        self::assertTrue($repo->rename((int) $user['id'], $id, 'Work laptop'));
        $repo->updateOnUse($id, 7);
        $row = $repo->findForUser((int) $user['id'], $id);
        self::assertSame('Work laptop', $row['nickname']);
        self::assertSame(7, (int) $row['sign_count']);
        self::assertNotNull($row['last_used_at']);
        $repo->updateOnUse($id, 3);
        $lowered = $repo->findForUser((int) $user['id'], $id);
        self::assertSame(7, (int) $lowered['sign_count'], 'counter anomalies must not lower the stored high-water mark');

        self::assertTrue($repo->revoke((int) $user['id'], $id));
        self::assertFalse($repo->revoke((int) $user['id'], $id), 'second revoke is a no-op');
        self::assertNull($repo->findActiveByCredentialId($rawId));
        self::assertSame([], $repo->activeForUser((int) $user['id']));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

```bash
vendor/bin/phpunit tests/Integration/Repository/WebAuthnRepositoriesTest.php
```

Expected: FAIL — repository classes not found.

- [ ] **Step 3: Implement**

`src/Repository/WebAuthnCredentialRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class WebAuthnCredentialRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function activeForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM webauthn_credentials WHERE user_id = ? AND revoked_at IS NULL ORDER BY id',
            [$userId],
        );
    }

    /** @return list<array<string,mixed>> row-locked for a removal transaction */
    public function activeForUserForUpdate(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM webauthn_credentials WHERE user_id = ? AND revoked_at IS NULL ORDER BY id FOR UPDATE',
            [$userId],
        );
    }

    public function countActiveForUser(int $userId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM webauthn_credentials WHERE user_id = ? AND revoked_at IS NULL',
            [$userId],
        );
    }

    public function findActiveByCredentialId(string $rawId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM webauthn_credentials WHERE credential_id = ? AND revoked_at IS NULL',
            [$rawId],
        );
    }

    public function findForUser(int $userId, int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM webauthn_credentials WHERE id = ? AND user_id = ?',
            [$id, $userId],
        );
    }

    /** @param array<string,mixed> $row */
    public function create(array $row): int
    {
        return $this->db->insert(
            'INSERT INTO webauthn_credentials
                (user_id, credential_id, public_key, sign_count, aaguid, transports,
                 is_discoverable, is_backup_eligible, is_backed_up, nickname, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [
                (int) $row['user_id'],
                (string) $row['credential_id'],
                (string) $row['public_key'],
                (int) $row['sign_count'],
                $row['aaguid'],
                (string) $row['transports'],
                (int) $row['is_discoverable'],
                (int) $row['is_backup_eligible'],
                (int) $row['is_backed_up'],
                $row['nickname'],
            ],
        );
    }

    public function rename(int $userId, int $id, string $nickname): bool
    {
        return $this->db->run(
            'UPDATE webauthn_credentials SET nickname = ? WHERE id = ? AND user_id = ? AND revoked_at IS NULL',
            [$nickname, $id, $userId],
        )->rowCount() === 1;
    }

    public function revoke(int $userId, int $id): bool
    {
        return $this->db->run(
            'UPDATE webauthn_credentials SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ? AND revoked_at IS NULL',
            [$id, $userId],
        )->rowCount() === 1;
    }

    public function updateOnUse(int $id, int $signCount): void
    {
        $this->db->run(
            'UPDATE webauthn_credentials SET sign_count = GREATEST(sign_count, ?), last_used_at = UTC_TIMESTAMP() WHERE id = ?',
            [$signCount, $id],
        );
    }
}
```

`src/Repository/WebAuthnChallengeRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** One-time, short-lived, purpose/session/user-bound WebAuthn challenges (§8.5). */
final class WebAuthnChallengeRepository
{
    public function mint(?int $userId, string $sessionHash, string $purpose, string $challenge, int $ttlSeconds): int
    {
        return $this->db->insert(
            'INSERT INTO webauthn_challenges (user_id, session_token_hash, purpose, challenge, created_at, expires_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))',
            [$userId, $sessionHash, $purpose, $challenge, $ttlSeconds],
        );
    }

    public function __construct(private Database $db)
    {
    }

    /**
     * The one-time gate: a single guarded UPDATE wins exactly once per challenge
     * and only under the exact (session, purpose, user) binding it was minted for.
     */
    public function consume(string $challenge, string $sessionHash, string $purpose, ?int $userId): bool
    {
        return $this->db->run(
            'UPDATE webauthn_challenges
             SET consumed_at = UTC_TIMESTAMP()
             WHERE challenge = ? AND session_token_hash = ? AND purpose = ? AND user_id <=> ?
               AND consumed_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            [$challenge, $sessionHash, $purpose, $userId],
        )->rowCount() === 1;
    }

    /** Opportunistic cleanup, called on mint; the D11 "challenge-store cleanup" hook. */
    public function purgeExpired(): int
    {
        return $this->db->run(
            'DELETE FROM webauthn_challenges WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)',
        )->rowCount();
    }
}
```

(Keep the constructor first when writing the real file — shown out of order here only to highlight `consume`.)

In `src/Core/App.php` `buildContainer()`, next to the `MfaRepository` bind:

```php
$c->bind(WebAuthnCredentialRepository::class, fn (Container $c) => new WebAuthnCredentialRepository($c->get(Database::class)));
$c->bind(WebAuthnChallengeRepository::class, fn (Container $c) => new WebAuthnChallengeRepository($c->get(Database::class)));
$c->bind(RelyingParty::class, function () use ($config) {
    $override = trim((string) $config->get('app.webauthn_rp_id', ''));
    return new RelyingParty(
        (string) $config->get('app.url', ''),
        $override !== '' ? $override : null,
        (string) $config->get('app.env', 'production'),
    );
});
$c->bind(WebAuthnVerifier::class, fn (Container $c) => new WebAuthnVerifier($c->get(RelyingParty::class)));
```

(Add the matching `use` imports; confirm the exact config key for the environment — the neighboring health/setup code reads it — and mirror whatever `config/config.php` names it.)

- [ ] **Step 4: Run the test to verify it passes**

```bash
vendor/bin/phpunit tests/Integration/Repository/WebAuthnRepositoriesTest.php
```

Expected: PASS (3 tests). Also run `vendor/bin/phpunit --testsuite integration` once — the container edits must not break kernel boot anywhere.

- [ ] **Step 5: Commit**

```bash
git add src/Repository/WebAuthnCredentialRepository.php src/Repository/WebAuthnChallengeRepository.php src/Core/App.php config/config.php tests/Integration/Repository/WebAuthnRepositoriesTest.php
git commit -m "feat(webauthn): credential/challenge repositories with one-time consume + DI wiring

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 10: `ReauthGate::requireFactor` — passkey joins the present-factor policy (F7)

The F7 gate grows the reserved second factor: a caller may now offer a passkey-assertion probe as an alternative to the password. Window-zero semantics stay — the factor is presented with the request itself. Existing `requirePassword` call sites are untouched.

**Files:**
- Modify: `src/Security/ReauthGate.php`
- Test: `tests/Integration/Security/ReauthGateFactorTest.php`

**Interfaces:**
- Consumes: existing `requirePassword`; the existing `ReauthGate::FACTOR_PASSWORD` constant (already declared on line 21 — do **not** re-declare it, PHP fatals with "Cannot redeclare").
- Produces: `ReauthGate::FACTOR_PASSKEY` (new constant, added beside the existing `FACTOR_PASSWORD`), `requireFactor(User $actor, ?string $currentPassword, ?\Closure $passkeyProbe = null, string $field = 'current_password'): string`. Contract: a non-null probe that returns `true` wins as `FACTOR_PASSKEY` (a probe that fails must **throw**, not return false); otherwise a non-empty password is verified via `requirePassword`; otherwise `ValidationException` on `$field`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use App\Core\ValidationException;
use App\Security\ReauthGate;
use App\Security\PasswordHasher;
use Tests\Support\TestCase;

final class ReauthGateFactorTest extends TestCase
{
    public function test_password_factor_verifies_and_wrong_password_throws(): void
    {
        $gate = new ReauthGate(new PasswordHasher());
        $user = $this->userEntity($this->makeUser(['password' => 'secret-pass-1']));

        self::assertSame(ReauthGate::FACTOR_PASSWORD, $gate->requireFactor($user, 'secret-pass-1'));

        $this->expectException(ValidationException::class);
        $gate->requireFactor($user, 'wrong');
    }

    public function test_passkey_probe_wins_when_it_returns_true(): void
    {
        $gate = new ReauthGate(new PasswordHasher());
        $user = $this->userEntity($this->makeUser());
        self::assertSame(ReauthGate::FACTOR_PASSKEY, $gate->requireFactor($user, null, static fn (): bool => true));
    }

    public function test_no_factor_at_all_throws_on_the_named_field(): void
    {
        $gate = new ReauthGate(new PasswordHasher());
        $user = $this->userEntity($this->makeUser());
        try {
            $gate->requireFactor($user, null, null);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }
    }
}
```

Check `makeUser`'s password knob in `tests/Support/TestCase.php` first — if seeding uses a different key (e.g. it always hashes a fixed password), adapt the first test to the helper's real contract rather than adding new seeding paths. Adjust `ValidationException`'s import to its real namespace (`App\Core\ValidationException` — verify).

- [ ] **Step 2: Run it to verify it fails**

```bash
vendor/bin/phpunit tests/Integration/Security/ReauthGateFactorTest.php
```

Expected: FAIL — unknown method `requireFactor` (the `FACTOR_PASSWORD` constant already exists on the class from Foundation, so only the method is missing).

- [ ] **Step 3: Implement**

Add to `src/Security/ReauthGate.php` the new `FACTOR_PASSKEY` constant beside the existing `FACTOR_PASSWORD` (**already declared on line 21 — do NOT re-add it, or PHP fatals with "Cannot redeclare App\Security\ReauthGate::FACTOR_PASSWORD"**), and the `requireFactor()` method after `requirePassword`:

```php
    // NOTE: `public const FACTOR_PASSWORD = 'password';` already exists (line 21) — leave it.
    // Add ONLY the new constant beside it:
    public const FACTOR_PASSKEY = 'passkey';

    /**
     * F7 present-factor step-up, Inc 7 form: the caller may offer a passkey
     * probe (a closure that verifies a fresh step_up assertion and returns true,
     * or throws) as an alternative to the account password. Window zero either
     * way — the factor rides the request being authorized.
     */
    public function requireFactor(User $actor, ?string $currentPassword, ?\Closure $passkeyProbe = null, string $field = 'current_password'): string
    {
        if ($passkeyProbe !== null && $passkeyProbe() === true) {
            return self::FACTOR_PASSKEY;
        }
        if ($currentPassword !== null && $currentPassword !== '') {
            $this->requirePassword($actor, $currentPassword, $field);
            return self::FACTOR_PASSWORD;
        }
        throw new ValidationException([$field => 'Confirm this change with your password or a passkey.']);
    }
```

Update the class docblock's "Inc 7 adds FACTOR_PASSKEY…" sentence to past tense.

- [ ] **Step 4: Run the test to verify it passes**

```bash
vendor/bin/phpunit tests/Integration/Security/ReauthGateFactorTest.php
```

Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Security/ReauthGate.php tests/Integration/Security/ReauthGateFactorTest.php
git commit -m "feat(security): ReauthGate::requireFactor — passkey probe beside the password (F7, window zero)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 11: Enrollment vertical — `PasskeyService` core + `PasskeyController` ceremony endpoints (SP2 part 1)

The registration and step-up state machine: reauth-gated challenge minting, one-time session/user/purpose-bound consumption, verification, duplicate refusal, audit, security email, telemetry — exposed over three JSON endpoints. Lands the spec-pinned `AppPasskeyRegistrationTest` proving TM-ID-05 (replay + cross-user), TM-ID-06 (no fresh factor ⇒ no challenge), and TM-ID-07 at the HTTP layer, plus the dark-404 flag regression for the new routes.

**Files:**
- Create: `src/Service/PasskeyService.php`
- Create: `src/Controller/PasskeyController.php`
- Modify: `src/Core/App.php` (routes beside the TOTP block ~line 1604; `PasskeyService` bind beside `MfaService` ~line 1408)
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php` (new dark-routes test)
- Test: `tests/Integration/Core/AppPasskeyRegistrationTest.php`

**Interfaces:**
- Consumes: everything from Tasks 2–10; `WriteGate::assertCanWrite`, `ModerationLogRepository::log`, `Mailer`, `Telemetry`, `RateLimitService::enforce('mfa_settings', …)`.
- Produces (for Tasks 12–17): the `PasskeyService` methods `sessionBinding`, `status`, `beginRegistration`, `completeRegistration`, `beginStepUp`, `verifyStepUp`, `assertFreshFactor`, plus routes `POST /settings/security/passkeys/challenge`, `POST /settings/security/passkeys/step-up-challenge`, `POST /settings/security/passkeys`. Challenge TTL constant `PasskeyService::CHALLENGE_TTL = 300`.

- [ ] **Step 1: Write the failing integration test**

`tests/Integration/Core/AppPasskeyRegistrationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use App\Support\Base64Url;
use Tests\Support\TestCase;
use Tests\Support\Phase5\WebAuthnHarness;

final class AppPasskeyRegistrationTest extends TestCase
{
    private WebAuthnHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();
        (new SettingRepository($this->db))->set('features', ['passkeys' => true]);
        $this->harness = new WebAuthnHarness();
    }

    /** @return array{options: array<string,mixed>} */
    private function mintChallenge(string $password = 'password123'): array
    {
        $res = $this->post('/settings/security/passkeys/challenge', ['current_password' => $password]);
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        self::assertTrue($json['ok']);
        return $json;
    }

    private function createExtraSessionFor(array $user): string
    {
        $id = hash('sha256', 'extra-session-' . bin2hex(random_bytes(16)));
        (new \App\Repository\SessionRepository($this->db))->create([
            'id' => $id,
            'user_id' => (int) $user['id'],
            'csrf_secret' => bin2hex(random_bytes(32)),
            'user_agent' => 'phpunit-other-session',
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
        ]);
        return $id;
    }

    public function test_user_enrolls_a_passkey_end_to_end_and_duplicates_are_refused(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $json = $this->mintChallenge();
        $options = $json['options'];
        self::assertSame('localhost', $options['rp']['id'], 'test APP_URL must derive rp.id=localhost — fix harness/env if this fails');
        self::assertSame([['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]], $options['pubKeyCredParams']);
        self::assertSame('none', $options['attestation']);

        $challenge = (string) Base64Url::decode($options['challenge']);
        $cred = $this->harness->createCredential();
        $store = $this->post('/settings/security/passkeys', [
            'credential' => $this->harness->registrationPayload($cred, $challenge),
            'nickname' => 'Laptop',
        ]);
        $this->assertStatus(200, $store);
        self::assertTrue(json_decode($store->body(), true)['ok']);

        $page = $this->get('/settings/security');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Laptop');

        // Same authenticator again: excludeCredentials advertises it, and a replayed insert refuses.
        $json2 = $this->mintChallenge();
        self::assertSame(Base64Url::encode($cred['credentialId']), $json2['options']['excludeCredentials'][0]['id']);
        $challenge2 = (string) Base64Url::decode($json2['options']['challenge']);
        $dup = $this->post('/settings/security/passkeys', [
            'credential' => $this->harness->registrationPayload($cred, $challenge2),
        ]);
        $this->assertStatus(422, $dup);
    }

    public function test_enrolling_a_passkey_revokes_other_sessions_but_keeps_current_session(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $otherSession = $this->createExtraSessionFor($user);
        self::assertNull($this->db->fetchValue('SELECT revoked_at FROM sessions WHERE id = ?', [$otherSession]));

        $challenge = (string) Base64Url::decode($this->mintChallenge()['options']['challenge']);
        $cred = $this->harness->createCredential();
        $this->assertStatus(200, $this->post('/settings/security/passkeys', [
            'credential' => $this->harness->registrationPayload($cred, $challenge),
        ]));

        self::assertNotNull($this->db->fetchValue('SELECT revoked_at FROM sessions WHERE id = ?', [$otherSession]));
        $current = $this->get('/settings/security');
        $this->assertStatus(200, $current); // current session survives the credential-change revocation
    }

    public function test_challenge_requires_a_fresh_factor(): void // TM-ID-06
    {
        $this->actingAs($this->makeUser());
        $res = $this->post('/settings/security/passkeys/challenge', []);
        $this->assertStatus(422, $res);
        self::assertArrayHasKey('current_password', json_decode($res->body(), true)['errors']);

        $wrong = $this->post('/settings/security/passkeys/challenge', ['current_password' => 'not-it']);
        $this->assertStatus(422, $wrong);
    }

    public function test_management_rate_limit_keeps_the_json_error_contract(): void
    {
        $this->actingAs($this->makeUser());
        for ($i = 0; $i < 10; $i++) {
            $this->assertStatus(422, $this->post('/settings/security/passkeys/challenge', []));
        }
        $limited = $this->post('/settings/security/passkeys/challenge', []);
        $this->assertStatus(429, $limited);
        $json = json_decode($limited->body(), true);
        self::assertFalse($json['ok']);
        self::assertArrayHasKey('rate_limit', $json['errors']);
    }

    public function test_challenge_is_single_use(): void // TM-ID-05 (replay)
    {
        $this->actingAs($this->makeUser());
        $challenge = (string) Base64Url::decode($this->mintChallenge()['options']['challenge']);
        $cred = $this->harness->createCredential();
        $payload = $this->harness->registrationPayload($cred, $challenge);

        $this->assertStatus(200, $this->post('/settings/security/passkeys', ['credential' => $payload]));
        $replay = $this->post('/settings/security/passkeys', ['credential' => $this->harness->registrationPayload($this->harness->createCredential(), $challenge)]);
        $this->assertStatus(422, $replay);
    }

    public function test_challenge_minted_for_one_user_cannot_complete_for_another(): void // TM-ID-05 (cross-user)
    {
        $alice = $this->makeUser(['username' => 'pk_alice']);
        $this->actingAs($alice);
        $challenge = (string) Base64Url::decode($this->mintChallenge()['options']['challenge']);

        $this->logoutClient();
        $this->actingAs($this->makeUser(['username' => 'pk_bob']));
        $res = $this->post('/settings/security/passkeys', [
            'credential' => $this->harness->registrationPayload($this->harness->createCredential(), $challenge),
        ]);
        $this->assertStatus(422, $res);
    }

    public function test_wrong_origin_and_wrong_rp_refuse_at_the_http_layer(): void // TM-ID-07
    {
        $this->actingAs($this->makeUser());
        foreach ([['origin' => 'https://evil.test'], ['rpId' => 'evil.test']] as $overrides) {
            $challenge = (string) Base64Url::decode($this->mintChallenge()['options']['challenge']);
            $res = $this->post('/settings/security/passkeys', [
                'credential' => $this->harness->registrationPayload($this->harness->createCredential(), $challenge, $overrides),
            ]);
            $this->assertStatus(422, $res);
        }
    }

    public function test_step_up_challenge_round_trip_verifies_a_registered_credential(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $challenge = (string) Base64Url::decode($this->mintChallenge()['options']['challenge']);
        $cred = $this->harness->createCredential();
        $this->assertStatus(200, $this->post('/settings/security/passkeys', ['credential' => $this->harness->registrationPayload($cred, $challenge)]));

        $step = $this->post('/settings/security/passkeys/step-up-challenge', []);
        $this->assertStatus(200, $step);
        $stepOptions = json_decode($step->body(), true)['options'];
        self::assertSame('required', $stepOptions['userVerification']);
        $stepChallenge = (string) Base64Url::decode($stepOptions['challenge']);

        // A fresh factor via passkey instead of password: mint the next registration challenge with it.
        $res = $this->post('/settings/security/passkeys/challenge', [
            'passkey_assertion' => $this->harness->assertionPayload($cred, $stepChallenge, 1),
        ]);
        $this->assertStatus(200, $res);
    }
}
```

Check `TestCase::makeUser`'s default password (`password123` is the browser-seed value; the PHPUnit helper may differ — read the helper and use its real default in `mintChallenge()`), and `Response::body()`'s real accessor name.

Add to `tests/Integration/Core/AppFeatureFlagTest.php`:

```php
    public function test_passkeys_flag_gates_ceremony_routes(): void
    {
        $this->actingAs($this->makeUser());
        $this->assertStatus(404, $this->post('/settings/security/passkeys/challenge', ['current_password' => 'x']));
        $this->assertStatus(404, $this->post('/settings/security/passkeys', ['credential' => '{}']));
        $this->assertStatus(404, $this->post('/settings/security/passkeys/step-up-challenge', []));

        $this->setFlags(['passkeys' => true]);
        self::assertNotSame(404, $this->post('/settings/security/passkeys/challenge', [])->status());

        $this->setFlags(['passkeys' => false]);
        $this->assertStatus(404, $this->post('/settings/security/passkeys/challenge', ['current_password' => 'x']));
    }
```

- [ ] **Step 2: Run to verify failure**

```bash
vendor/bin/phpunit tests/Integration/Core/AppPasskeyRegistrationTest.php
```

Expected: FAIL — 404s everywhere (routes unregistered).

- [ ] **Step 3: Implement the service**

`src/Service/PasskeyService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\Telemetry;
use App\Core\ValidationException;
use App\Domain\User;
use App\Mail\Mailer;
use App\Mail\MailException;
use App\Repository\ModerationLogRepository;
use App\Repository\OAuthIdentityRepository;
use App\Repository\UserRepository;
use App\Repository\WebAuthnChallengeRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Security\LastOwnerGuard;
use App\Security\ReauthGate;
use App\Security\Session;
use App\Security\WebAuthn\RelyingParty;
use App\Security\WebAuthn\WebAuthnException;
use App\Security\WebAuthn\WebAuthnVerifier;
use App\Security\WriteGate;
use App\Support\Base64Url;

/**
 * Stateful passkey rules (P5-11 SP2/SP3): challenge lifecycle (one-time,
 * session/user/purpose-bound, 300 s TTL), credential CRUD invariants,
 * counter-anomaly risk signals, last-method/final-owner blocks, audit +
 * security notifications. All ceremony cryptography lives in WebAuthnVerifier.
 */
final class PasskeyService
{
    public const CHALLENGE_TTL = 300;
    public const MAX_ACTIVE_CREDENTIALS = 8; // keeps email-first login allowCredentials fixed-shape without excluding real keys

    public function __construct(
        private readonly WebAuthnCredentialRepository $credentials,
        private readonly WebAuthnChallengeRepository $challenges,
        private readonly WebAuthnVerifier $verifier,
        private readonly RelyingParty $rp,
        private readonly UserRepository $users,
        private readonly OAuthIdentityRepository $oauthIdentities,
        private readonly MfaService $mfaService,
        private readonly ReauthGate $reauth,
        private readonly WriteGate $writeGate,
        private readonly LastOwnerGuard $lastOwnerGuard,
        private readonly ModerationLogRepository $log,
        private readonly Mailer $mailer,
        private readonly Config $config,
        private readonly Database $db,
        private readonly ?Telemetry $telemetry = null,
    ) {
    }

    /** Uniform challenge binding for guests and members: hash of the session's CSRF secret. */
    public static function sessionBinding(Session $session): string
    {
        return hash('sha256', $session->csrfSecret());
    }

    /** @return array{credentials: list<array<string,mixed>>, has_password: bool, has_provider: bool} */
    public function status(User $user): array
    {
        $rows = [];
        foreach ($this->credentials->activeForUser($user->id()) as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'nickname' => (string) ($row['nickname'] ?? ''),
                'created_at' => (string) $row['created_at'],
                'last_used_at' => $row['last_used_at'],
                'transports' => (string) $row['transports'],
                'backed_up' => (int) $row['is_backed_up'] === 1,
            ];
        }
        return [
            'credentials' => $rows,
            'has_password' => $user->passwordHash() !== null,
            'has_provider' => $this->oauthIdentities->countForUser($user->id()) > 0,
        ];
    }

    public function assertFreshFactor(User $user, ?string $currentPassword, ?string $assertionJson, string $sessionHash): string
    {
        $probe = null;
        if ($assertionJson !== null && $assertionJson !== '') {
            $probe = function () use ($user, $assertionJson, $sessionHash): bool {
                $this->verifyStepUp($user, $sessionHash, $assertionJson);
                return true;
            };
        }
        return $this->reauth->requireFactor($user, $currentPassword, $probe);
    }

    /** @return array<string,mixed> PublicKeyCredentialCreationOptions, JSON-ready */
    public function beginRegistration(User $user, string $sessionHash): array
    {
        $this->writeGate->assertCanWrite($user);
        $this->rp->assertUsable();
        $this->challenges->purgeExpired();

        $challenge = random_bytes(32);
        $this->challenges->mint($user->id(), $sessionHash, 'register', $challenge, self::CHALLENGE_TTL);

        $exclude = [];
        foreach ($this->credentials->activeForUser($user->id()) as $row) {
            $exclude[] = [
                'type' => 'public-key',
                'id' => Base64Url::encode((string) $row['credential_id']),
                'transports' => $row['transports'] !== '' ? explode(',', (string) $row['transports']) : [],
            ];
        }

        return [
            'rp' => ['id' => $this->rp->rpId(), 'name' => (string) $this->config->get('app.name', 'RetroBoards')],
            'user' => [
                'id' => Base64Url::encode(pack('J', $user->id())),
                'name' => $user->username(),
                'displayName' => $user->displayName() !== '' ? $user->displayName() : $user->username(),
            ],
            'challenge' => Base64Url::encode($challenge),
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257],
            ],
            'timeout' => self::CHALLENGE_TTL * 1000,
            'excludeCredentials' => $exclude,
            'authenticatorSelection' => ['residentKey' => 'preferred', 'userVerification' => 'preferred'],
            'attestation' => 'none',
        ];
    }

    /** @return array<string,mixed> the stored credential row */
    public function completeRegistration(User $user, string $sessionHash, string $credentialJson, ?string $nickname): array
    {
        $this->writeGate->assertCanWrite($user);
        $payload = $this->decodePayload($credentialJson);
        $challenge = $this->challengeFromPayload($payload);
        $nickname = $nickname !== null ? trim($nickname) : null;
        if ($nickname !== null && ($nickname === '' || mb_strlen($nickname) > 120)) {
            $nickname = $nickname === '' ? null : mb_substr($nickname, 0, 120);
        }

        $row = $this->db->transaction(function () use ($user, $sessionHash, $payload, $challenge, $nickname): array {
            if (!$this->challenges->consume($challenge, $sessionHash, 'register', $user->id())) {
                throw new ValidationException(['passkey' => 'This passkey request expired or was already used — try again.']);
            }
            if ($this->credentials->countActiveForUser($user->id()) >= self::MAX_ACTIVE_CREDENTIALS) {
                throw new ValidationException(['passkey' => 'Remove an old passkey before adding another one.']);
            }
            $verified = $this->verifier->verifyRegistration($payload, $challenge);
            if ($this->credentials->findActiveByCredentialId($verified->credentialId) !== null) {
                throw new ValidationException(['passkey' => 'This passkey is already registered.']);
            }
            $isDiscoverable = (bool) (($payload['credProps']['rk'] ?? false) === true);
            try {
                $id = $this->credentials->create([
                    'user_id' => $user->id(),
                    'credential_id' => $verified->credentialId,
                    'public_key' => $verified->publicKey,
                    'sign_count' => $verified->signCount,
                    'aaguid' => $verified->aaguid,
                    'transports' => $verified->transports,
                    'is_discoverable' => $isDiscoverable ? 1 : 0,
                    'is_backup_eligible' => $verified->backupEligible ? 1 : 0,
                    'is_backed_up' => $verified->backedUp ? 1 : 0,
                    'nickname' => $nickname,
                ]);
            } catch (\PDOException) {
                // uq_webauthn_credid race — same refusal as the pre-check.
                throw new ValidationException(['passkey' => 'This passkey is already registered.']);
            }
            $this->audit($user->id(), 'passkey_registered', ['credential' => $id, 'nickname' => $nickname]);
            return ['id' => $id, 'nickname' => $nickname];
        });

        $this->notify($user, 'A passkey was added to your account',
            'A new passkey' . ($row['nickname'] !== null ? ' ("' . $row['nickname'] . '")' : '') . ' was just added to your account.');
        $this->telemetry?->emit('passkey.registered', ['user' => $user->id()]);
        return $row;
    }

    /** @return array<string,mixed> PublicKeyCredentialRequestOptions for a step_up ceremony */
    public function beginStepUp(User $user, string $sessionHash): array
    {
        $this->rp->assertUsable();
        $this->challenges->purgeExpired();
        $challenge = random_bytes(32);
        $this->challenges->mint($user->id(), $sessionHash, 'step_up', $challenge, self::CHALLENGE_TTL);
        return $this->requestOptions($challenge, $this->credentials->activeForUser($user->id()), 'required');
    }

    public function verifyStepUp(User $user, string $sessionHash, string $credentialJson): void
    {
        $payload = $this->decodePayload($credentialJson);
        $challenge = $this->challengeFromPayload($payload);
        $rawId = Base64Url::decode((string) ($payload['rawId'] ?? ''));
        $row = ($rawId !== null && $rawId !== '') ? $this->credentials->findActiveByCredentialId($rawId) : null;
        if ($row === null || (int) $row['user_id'] !== $user->id()) {
            throw new ValidationException(['passkey' => 'That passkey is not registered to this account.']);
        }
        if (!$this->challenges->consume($challenge, $sessionHash, 'step_up', $user->id())) {
            throw new ValidationException(['passkey' => 'The passkey confirmation expired — try again.']);
        }
        $result = $this->verifier->verifyAssertion($payload, $challenge, (string) $row['public_key'], (int) $row['sign_count'], true);
        $this->credentials->updateOnUse((int) $row['id'], $result->signCount); // high-water sign_count only; last_used_at always refreshes
        if ($result->counterAnomaly) {
            $this->recordAnomaly($user->id(), (int) $row['id']);
        }
    }

    /** @param list<array<string,mixed>> $credentialRows */
    private function requestOptions(string $challenge, array $credentialRows, string $userVerification): array
    {
        $allow = [];
        foreach ($credentialRows as $row) {
            $allow[] = [
                'type' => 'public-key',
                'id' => Base64Url::encode((string) $row['credential_id']),
                'transports' => $row['transports'] !== '' ? explode(',', (string) $row['transports']) : [],
            ];
        }
        return [
            'challenge' => Base64Url::encode($challenge),
            'rpId' => $this->rp->rpId(),
            'timeout' => self::CHALLENGE_TTL * 1000,
            'allowCredentials' => $allow,
            'userVerification' => $userVerification,
        ];
    }

    /** @return array<string,mixed> */
    private function decodePayload(string $credentialJson): array
    {
        $payload = json_decode($credentialJson, true);
        if (!is_array($payload)) {
            throw new ValidationException(['passkey' => 'The passkey response could not be read.']);
        }
        return $payload;
    }

    private function challengeFromPayload(array $payload): string
    {
        $clientDataRaw = Base64Url::decode((string) ($payload['response']['clientDataJSON'] ?? ''));
        $clientData = $clientDataRaw !== null ? json_decode($clientDataRaw, true) : null;
        $challenge = is_array($clientData) ? Base64Url::decode((string) ($clientData['challenge'] ?? '')) : null;
        if ($challenge === null || $challenge === '') {
            throw new ValidationException(['passkey' => 'The passkey response could not be read.']);
        }
        return $challenge;
    }

    private function recordAnomaly(int $userId, int $credentialId): void
    {
        // Decision #30 / TM-ID-08: signal, never lockout.
        $this->audit($userId, 'passkey_counter_anomaly', ['credential' => $credentialId]);
        $this->telemetry?->emit('passkey.counter_anomaly', ['user' => $userId, 'credential' => $credentialId]);
    }

    private function audit(int $userId, string $action, array $context = []): void
    {
        $this->log->log([
            'actor_id' => $userId,
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $userId,
            'reason' => 'account security',
            'after' => $context,
        ]);
    }

    private function notify(User $user, string $subject, string $line): void
    {
        if (!$this->mailer->isConfigured()) {
            return; // Email fails closed; the moderation_log row is the durable record.
        }
        $appName = (string) $this->config->get('app.name', 'RetroBoards');
        $text = $line . "\n\nIf this wasn't you, sign in with your password, review Settings → Security, and remove anything you don't recognize.";
        try {
            $this->mailer->send($user->email(), '[' . $appName . '] ' . $subject, $text, null);
        } catch (MailException) {
            // Best-effort security notice.
        }
    }
}
```

Verify against the real code while writing: `User::username()/displayName()/passwordHash()/email()` accessor names (`src/Domain/User.php`), `ModerationLogRepository::log` field names, and the `Config::get` key for the site name (`app.name` — mirror what `EmailVerificationService::siteName()` reads). The `MfaService` collaborator is only consumed in Task 13's `completeLogin` — keep the constructor parameter now so the signature never changes.

- [ ] **Step 4: Implement the controller + routes + bind**

`src/Controller/PasskeyController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\HttpException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Security\WebAuthn\WebAuthnException;
use App\Service\PasskeyService;
use App\Service\RateLimitService;

final class PasskeyController extends Controller
{
    public function challenge(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $this->gate();
        $svc = $this->container->get(PasskeyService::class);
        $binding = PasskeyService::sessionBinding($this->session());
        try {
            $this->container->get(RateLimitService::class)->enforce('mfa_settings', $request, $user);
        } catch (HttpException $e) {
            return $this->jsonRateLimit($e);
        }
        try {
            $svc->assertFreshFactor($user, $this->str($request, 'current_password'), $this->str($request, 'passkey_assertion'), $binding);
            $options = $svc->beginRegistration($user, $binding);
        } catch (HttpException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => $e->getMessage()]], $e->statusCode());
        } catch (ValidationException $e) {
            return Response::json(['ok' => false, 'errors' => $e->errors], 422);
        } catch (WebAuthnException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => $e->getMessage()], 'code' => $e->code], 422);
        }
        return Response::json(['ok' => true, 'options' => $options]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $this->gate();
        try {
            $this->container->get(RateLimitService::class)->enforce('mfa_settings', $request, $user);
        } catch (HttpException $e) {
            return $this->jsonRateLimit($e);
        }
        try {
            $this->container->get(PasskeyService::class)->completeRegistration(
                $user,
                PasskeyService::sessionBinding($this->session()),
                (string) ($this->str($request, 'credential') ?? ''),
                $this->str($request, 'nickname'),
            );
        } catch (HttpException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => $e->getMessage()]], $e->statusCode());
        } catch (ValidationException $e) {
            return Response::json(['ok' => false, 'errors' => $e->errors], 422);
        } catch (WebAuthnException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => $e->getMessage()], 'code' => $e->code], 422);
        }
        $this->revokeOtherSessionsFor($user);
        return Response::json(['ok' => true]);
    }

    public function stepUpChallenge(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $this->gate();
        try {
            $this->container->get(RateLimitService::class)->enforce('mfa_settings', $request, $user);
        } catch (HttpException $e) {
            return $this->jsonRateLimit($e);
        }
        try {
            $options = $this->container->get(PasskeyService::class)->beginStepUp($user, PasskeyService::sessionBinding($this->session()));
        } catch (HttpException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => $e->getMessage()]], $e->statusCode());
        } catch (WebAuthnException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => $e->getMessage()], 'code' => $e->code], 422);
        }
        return Response::json(['ok' => true, 'options' => $options]);
    }

    private function str(Request $request, string $key): ?string
    {
        $value = $request->post($key);
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function jsonRateLimit(HttpException $e): Response
    {
        return Response::json(['ok' => false, 'errors' => ['rate_limit' => $e->getMessage()]], $e->statusCode());
    }

    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('passkeys')) {
            throw new NotFoundException('Not found.');
        }
    }
}
```

(Match the action signature — `(Request $request, array $params)` vs `(Request $request)` — to what the Router actually dispatches; copy from `ThreadWorkflowController`. Same for `Response::body()` accessor names in the test.)

Routes in `App::buildRouter()` beside the TOTP block:

```php
$r->post('/settings/security/passkeys/challenge', [PasskeyController::class, 'challenge']);
$r->post('/settings/security/passkeys/step-up-challenge', [PasskeyController::class, 'stepUpChallenge']);
$r->post('/settings/security/passkeys', [PasskeyController::class, 'store']);
```

(Mirror the file's actual route-registration idiom exactly.)

Bind in `buildContainer()` beside `MfaService`:

```php
$c->bind(PasskeyService::class, fn (Container $c) => new PasskeyService(
    $c->get(WebAuthnCredentialRepository::class),
    $c->get(WebAuthnChallengeRepository::class),
    $c->get(WebAuthnVerifier::class),
    $c->get(RelyingParty::class),
    $c->get(UserRepository::class),
    $c->get(OAuthIdentityRepository::class),
    $c->get(MfaService::class),
    $c->get(ReauthGate::class),
    $c->get(WriteGate::class),
    $c->get(LastOwnerGuard::class),
    $c->get(ModerationLogRepository::class),
    $c->get(Mailer::class),
    $config,
    $c->get(Database::class),
    $c->get(FeatureFlags::class)->enabled('telemetry') ? $c->get(Telemetry::class) : null,
));
```

(Confirm how existing binds pass `Telemetry` — copy the `ThemeStateService` bind's telemetry argument verbatim, including its flag/config key.)

- [ ] **Step 5: Run the tests**

```bash
vendor/bin/phpunit tests/Integration/Core/AppPasskeyRegistrationTest.php tests/Integration/Core/AppFeatureFlagTest.php
```

Expected: PASS (9 new + all existing flag tests).

- [ ] **Step 6: Commit**

```bash
git add src/Service/PasskeyService.php src/Controller/PasskeyController.php src/Core/App.php tests/Integration/Core/AppPasskeyRegistrationTest.php tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "feat(passkeys): enrollment + step-up vertical — reauth-gated one-time challenges, JSON ceremony endpoints (TM-ID-05/06/07 http layer)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 12: Credential management — security-page panel, rename/revoke, last-method + final-owner blocks (SP2 part 2)

The user-visible management surface: a Passkeys panel on `/settings/security` (server-rendered list; add-flow revealed by JS; rename/revoke are plain forms that work without JS), and the removal invariants — TM-ID-09's "last usable method" block for everyone, `LastOwnerGuard` wiring (the F5 Inc-7 path) for owners, plus the `OAuthIdentityRepository::soleMethodAccounts` detector that Inc 8's provider-disable UI will consume.

**Files:**
- Modify: `src/Service/PasskeyService.php` (add `rename`, `remove`)
- Modify: `src/Repository/OAuthIdentityRepository.php` (add `soleMethodAccounts`)
- Modify: `src/Controller/PasskeyController.php` (add `rename`, `revoke` form actions)
- Modify: `src/Controller/AccountController.php` (`securityView` passes `passkeys` data)
- Modify: `templates/account/security.php` (Passkeys panel after the 2FA section)
- Modify: `src/Core/App.php` (two routes)
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php` (extend the passkeys dark test with the new routes + panel absence)
- Test: extend `tests/Integration/Core/AppPasskeyRegistrationTest.php`

**Interfaces:**
- Consumes: `LastOwnerGuard::assertNotLastOwnerForUpdate(User, string $field)`, `OAuthIdentityRepository::countForUser`, `UserRepository::findEntity`.
- Produces: `PasskeyService::rename/remove`; `OAuthIdentityRepository::soleMethodAccounts`; routes `POST /settings/security/passkeys/{id}/rename`, `POST /settings/security/passkeys/{id}/revoke`; template hooks `data-passkey-panel`, `data-passkey-add-form` (hidden until JS reveals), `data-passkey-stepup-btn`.

- [ ] **Step 1: Write the failing tests** (append to `AppPasskeyRegistrationTest`)

```php
    /** Registers a credential for the current user over HTTP and returns [cred, id-from-page]. */
    private function enroll(string $nickname = 'Key'): array
    {
        $challenge = (string) Base64Url::decode($this->mintChallenge()['options']['challenge']);
        $cred = $this->harness->createCredential();
        $this->assertStatus(200, $this->post('/settings/security/passkeys', [
            'credential' => $this->harness->registrationPayload($cred, $challenge),
            'nickname' => $nickname,
        ]));
        $page = $this->get('/settings/security')->body();
        preg_match('#/settings/security/passkeys/(\d+)/revoke#', $page, $m);
        self::assertNotEmpty($m, 'panel must render a revoke form');
        return [$cred, (int) $m[1]];
    }

    public function test_rename_and_revoke_work_as_plain_forms(): void
    {
        $this->actingAs($this->makeUser());
        [, $id] = $this->enroll('Old name');

        $rename = $this->post("/settings/security/passkeys/{$id}/rename", ['nickname' => 'New name']);
        $this->assertRedirect($rename, '/settings/security');
        $this->assertSeeText($this->get('/settings/security'), 'New name');

        $revoke = $this->post("/settings/security/passkeys/{$id}/revoke", ['current_password' => 'password123']);
        $this->assertRedirect($revoke, '/settings/security');
        $this->assertDontSeeText($this->get('/settings/security'), 'New name');
    }

    public function test_revoke_requires_a_fresh_factor_and_supports_passkey_step_up(): void
    {
        $this->actingAs($this->makeUser());
        [$cred, $id] = $this->enroll();

        $this->assertStatus(422, $this->post("/settings/security/passkeys/{$id}/revoke", []));

        $stepOptions = json_decode($this->post('/settings/security/passkeys/step-up-challenge', [])->body(), true)['options'];
        $stepChallenge = (string) Base64Url::decode($stepOptions['challenge']);
        $revoke = $this->post("/settings/security/passkeys/{$id}/revoke", [
            'passkey_assertion' => $this->harness->assertionPayload($cred, $stepChallenge, 3),
        ]);
        $this->assertRedirect($revoke, '/settings/security');
    }

    public function test_revoking_a_passkey_revokes_other_sessions_but_keeps_current_session(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        [, $id] = $this->enroll('Session key');
        $otherSession = $this->createExtraSessionFor($user);
        self::assertNull($this->db->fetchValue('SELECT revoked_at FROM sessions WHERE id = ?', [$otherSession]));

        $this->assertRedirect(
            $this->post("/settings/security/passkeys/{$id}/revoke", ['current_password' => 'password123']),
            '/settings/security',
        );

        self::assertNotNull($this->db->fetchValue('SELECT revoked_at FROM sessions WHERE id = ?', [$otherSession]));
        $current = $this->get('/settings/security');
        $this->assertStatus(200, $current); // current session survives the credential-change revocation
    }

    public function test_removing_the_last_sign_in_method_is_blocked(): void // TM-ID-09
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        [$cred, $id] = $this->enroll('Only key');
        // Make the account passwordless after enrollment (OAuth-style account).
        $this->db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [(int) $user['id']]);

        $stepOptions = json_decode($this->post('/settings/security/passkeys/step-up-challenge', [])->body(), true)['options'];
        $blocked = $this->post("/settings/security/passkeys/{$id}/revoke", [
            'passkey_assertion' => $this->harness->assertionPayload($cred, (string) Base64Url::decode($stepOptions['challenge']), 2),
        ]);
        $this->assertStatus(422, $blocked);
        $this->assertSeeText($this->get('/settings/security'), 'Only key');

        // With a password restored, the same removal succeeds.
        $hash = (new \App\Security\PasswordHasher())->hash('password123');
        $this->db->run('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, (int) $user['id']]);
        $this->assertRedirect($this->post("/settings/security/passkeys/{$id}/revoke", ['current_password' => 'password123']), '/settings/security');
    }

    public function test_final_owner_last_method_removal_carries_the_owner_block(): void // F5 Inc-7 path
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        [$cred, $id] = $this->enroll('Owner key');
        $this->db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [(int) $admin['id']]);

        $stepOptions = json_decode($this->post('/settings/security/passkeys/step-up-challenge', [])->body(), true)['options'];
        $blocked = $this->post("/settings/security/passkeys/{$id}/revoke", [
            'passkey_assertion' => $this->harness->assertionPayload($cred, (string) Base64Url::decode($stepOptions['challenge']), 2),
        ]);
        $this->assertStatus(422, $blocked);
        // LastOwnerGuard's copy, not the generic block — read the guard's real message
        // in src/Security/LastOwnerGuard.php and pin a stable fragment of it here.
        $this->assertSeeText($blocked, 'owner');
    }
```

```php
    public function test_sole_provider_accounts_detector_reports_oauth_only_accounts(): void
    {
        $user = $this->makeUser();
        $this->db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [(int) $user['id']]);
        $this->db->run(
            "INSERT INTO oauth_identities (user_id, provider, provider_user_id, created_at) VALUES (?, 'github', 'gh-1', UTC_TIMESTAMP())",
            [(int) $user['id']],
        );
        $hits = (new \App\Repository\OAuthIdentityRepository($this->db))->soleMethodAccounts('github');
        self::assertContains((int) $user['id'], array_column($hits, 'id'));

        // A passkey (or a password, or a second provider) takes the account off the list.
        // Seed the detector row directly: OAuth-only accounts cannot start passkey
        // enrollment without first proving a password or existing passkey factor.
        $this->db->run(
            'INSERT INTO webauthn_credentials
                (user_id, credential_id, public_key, sign_count, aaguid, transports,
                 is_discoverable, is_backup_eligible, is_backed_up, nickname, created_at)
             VALUES (?, ?, ?, 0, ?, ?, 0, 0, 0, ?, UTC_TIMESTAMP())',
            [(int) $user['id'], random_bytes(32), "\xa1\x01\x02", str_repeat("\0", 16), 'internal', 'Rescue key'],
        );
        $again = (new \App\Repository\OAuthIdentityRepository($this->db))->soleMethodAccounts('github');
        self::assertNotContains((int) $user['id'], array_column($again, 'id'));
    }
```

(Check `oauth_identities`' real column list in its Phase 3 migration before writing the INSERT — adjust column names to the actual shape.)

- [ ] **Step 2: Run to verify the new tests fail**

```bash
vendor/bin/phpunit tests/Integration/Core/AppPasskeyRegistrationTest.php
```

Expected: failures — routes 404, panel missing.

- [ ] **Step 3: Implement service methods**

Append to `PasskeyService`:

```php
    public function rename(User $user, int $credentialId, string $nickname): void
    {
        $this->writeGate->assertCanWrite($user);
        $nickname = trim($nickname);
        if ($nickname === '' || mb_strlen($nickname) > 120) {
            throw new ValidationException(['nickname' => 'Pick a name between 1 and 120 characters.']);
        }
        if (!$this->credentials->rename($user->id(), $credentialId, $nickname)) {
            throw new ValidationException(['passkey' => 'That passkey was not found.']);
        }
        $this->audit($user->id(), 'passkey_renamed', ['credential' => $credentialId, 'nickname' => $nickname]);
    }

    public function remove(User $user, int $credentialId, ?string $currentPassword, ?string $assertionJson, string $sessionHash): void
    {
        $this->writeGate->assertCanWrite($user);
        $this->assertFreshFactor($user, $currentPassword, $assertionJson, $sessionHash);

        $removedNickname = $this->db->transaction(function () use ($user, $credentialId): ?string {
            $rows = $this->credentials->activeForUserForUpdate($user->id());
            $target = null;
            foreach ($rows as $row) {
                if ((int) $row['id'] === $credentialId) {
                    $target = $row;
                }
            }
            if ($target === null) {
                throw new ValidationException(['passkey' => 'That passkey was not found.']);
            }
            $fresh = $this->users->findEntity($user->id());
            $hasPassword = $fresh !== null && $fresh->passwordHash() !== null;
            $hasProvider = $this->oauthIdentities->countForUser($user->id()) > 0;
            if (count($rows) === 1 && !$hasPassword && !$hasProvider) {
                // §8.5 last-method/final-owner invariants (TM-ID-09). Owners get the
                // guard's message and audit trail (the F5 Inc-7 owner-loss path);
                // everyone else gets the generic block.
                $this->lastOwnerGuard->assertNotLastOwnerForUpdate($user, 'passkey');
                throw new ValidationException(['passkey' => 'Add a password or another passkey before removing your only way to sign in.']);
            }
            $this->credentials->revoke($user->id(), $credentialId);
            $this->audit($user->id(), 'passkey_revoked', ['credential' => $credentialId, 'nickname' => $target['nickname']]);
            return $target['nickname'] !== null ? (string) $target['nickname'] : null;
        });

        $this->notify($user, 'A passkey was removed from your account',
            'A passkey' . ($removedNickname !== null ? ' ("' . $removedNickname . '")' : '') . ' was removed from your account.');
        $this->telemetry?->emit('passkey.revoked', ['user' => $user->id()]);
    }
```

And in `src/Repository/OAuthIdentityRepository.php` (cross-table reads in a repository have precedent — `ReportRepository::openCount` joins posts/threads):

```php
    /**
     * Accounts whose ONLY sign-in method is the named OAuth provider (no password,
     * no passkey, no other provider). Inc 8's provider-disable surface consumes
     * this before an operator turns a provider off (TM-ID-09 clause 2).
     * @return list<array{id:int, username:string, email:string}>
     */
    public function soleMethodAccounts(string $provider): array
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.username, u.email
             FROM users u
             JOIN oauth_identities oi ON oi.user_id = u.id AND oi.provider = ?
             WHERE u.password_hash IS NULL
               AND u.status NOT IN ('deleted', 'banned')
               AND NOT EXISTS (SELECT 1 FROM oauth_identities o2 WHERE o2.user_id = u.id AND o2.provider <> ?)
               AND NOT EXISTS (SELECT 1 FROM webauthn_credentials wc WHERE wc.user_id = u.id AND wc.revoked_at IS NULL)
             ORDER BY u.id",
            [$provider, $provider],
        );
    }
```

(The inventory spans three tables and stays read-only. Note the two distinct positional placeholders carrying the same provider value — never reuse one named placeholder.)

- [ ] **Step 4: Implement controller actions + routes + view data + template**

`PasskeyController` additions:

```php
    public function rename(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $this->gate();
        try {
            $this->container->get(RateLimitService::class)->enforce('mfa_settings', $request, $user);
        } catch (HttpException $e) {
            return $this->securityPage($user, ['passkey_errors' => ['rate_limit' => $e->getMessage()]], $e->statusCode());
        }
        try {
            $this->container->get(PasskeyService::class)->rename($user, (int) $params['id'], (string) ($this->str($request, 'nickname') ?? ''));
        } catch (HttpException $e) {
            return $this->securityPage($user, ['passkey_errors' => ['passkey' => $e->getMessage()]], $e->statusCode());
        } catch (ValidationException $e) {
            return $this->securityPage($user, ['passkey_errors' => $e->errors], 422);
        }
        return $this->redirectWithFlash('/settings/security', 'Passkey renamed.');
    }

    public function revoke(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $this->gate();
        try {
            $this->container->get(RateLimitService::class)->enforce('mfa_settings', $request, $user);
        } catch (HttpException $e) {
            return $this->securityPage($user, ['passkey_errors' => ['rate_limit' => $e->getMessage()]], $e->statusCode());
        }
        try {
            $this->container->get(PasskeyService::class)->remove(
                $user,
                (int) $params['id'],
                $this->str($request, 'current_password'),
                $this->str($request, 'passkey_assertion'),
                PasskeyService::sessionBinding($this->session()),
            );
        } catch (HttpException $e) {
            return $this->securityPage($user, ['passkey_errors' => ['passkey' => $e->getMessage()]], $e->statusCode());
        } catch (ValidationException $e) {
            return $this->securityPage($user, ['passkey_errors' => $e->errors], 422);
        } catch (WebAuthnException $e) {
            return $this->securityPage($user, ['passkey_errors' => ['passkey' => $e->getMessage()]], 422);
        }
        $this->revokeOtherSessionsFor($user);
        return $this->redirectWithFlash('/settings/security', 'Passkey removed.');
    }

    private function securityPage(\App\Domain\User $user, array $extra, int $status): Response
    {
        return (new AccountController($this->container))->securityView($user, $extra, $status);
    }
```

Change `AccountController::securityView` from `private` to `public` in this same task. Controllers in this app are instantiated directly in `App::process()` and are not container-bound, so instantiate `AccountController` directly with the current container for this re-render path.

Routes:

```php
$r->post('/settings/security/passkeys/{id}/rename', [PasskeyController::class, 'rename']);
$r->post('/settings/security/passkeys/{id}/revoke', [PasskeyController::class, 'revoke']);
```

Register these **before** `POST /settings/security/passkeys` if the router would otherwise shadow them (first-match wins; `{id}` compiles to `\d+`, so `/passkeys/3/rename` cannot match `/passkeys` — but keep the specific-before-generic habit).

`AccountController::securityView` — add to the `$data` it builds:

```php
'passkeys' => $this->container->get(FeatureFlags::class)->enabled('passkeys')
    ? $this->container->get(PasskeyService::class)->status($user)
    : null,
```

`templates/account/security.php` — insert after the 2FA `</section>` (~line 123):

```php
<?php if (!empty($features['passkeys']) && is_array($passkeys ?? null)): ?>
<section class="scribe-panel" data-passkey-panel>
    <h2>Passkeys</h2>
    <?php if (!empty($passkey_errors)): ?>
        <p class="form-error"><?= $e(implode(' ', $passkey_errors)) ?></p>
    <?php endif; ?>
    <?php if ($passkeys['credentials'] === []): ?>
        <p>No passkeys yet. A passkey signs you in with your device's screen lock — fingerprint, face, or PIN — instead of your password.</p>
    <?php else: ?>
        <ul class="passkey-list">
            <?php foreach ($passkeys['credentials'] as $pk): ?>
                <li class="passkey-row">
                    <div>
                        <strong><?= $e($pk['nickname'] !== '' ? $pk['nickname'] : 'Unnamed passkey') ?></strong>
                        <small>Added <?= $e(human_datetime($pk['created_at'])) ?><?= $pk['last_used_at'] !== null ? ' · last used ' . $e(human_datetime((string) $pk['last_used_at'])) : '' ?><?= $pk['backed_up'] ? ' · synced' : '' ?></small>
                    </div>
                    <form method="post" action="/settings/security/passkeys/<?= (int) $pk['id'] ?>/rename" class="passkey-inline-form">
                        <?= $this->csrfField() ?>
                        <input type="text" name="nickname" value="<?= $e($pk['nickname']) ?>" maxlength="120" aria-label="Passkey name">
                        <button type="submit" class="btn btn-secondary">Rename</button>
                    </form>
                    <form method="post" action="/settings/security/passkeys/<?= (int) $pk['id'] ?>/revoke" class="passkey-inline-form" data-passkey-revoke-form>
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="passkey_assertion" value="">
                        <?php if ($passkeys['has_password']): ?>
                            <input type="password" name="current_password" placeholder="Current password" autocomplete="current-password" aria-label="Current password">
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary" data-passkey-stepup-btn hidden>Confirm with a passkey</button>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-danger"<?= !$passkeys['has_password'] ? ' data-passkey-needs-stepup' : '' ?>>Remove</button>
                        <p class="form-error" data-passkey-revoke-error hidden></p>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <form data-passkey-add-form hidden
          data-challenge-url="/settings/security/passkeys/challenge"
          data-store-url="/settings/security/passkeys"
          data-stepup-url="/settings/security/passkeys/step-up-challenge">
        <?= $this->csrfField() ?>
        <input type="hidden" name="passkey_assertion" value="">
        <?php if ($passkeys['has_password']): ?>
            <input type="password" name="current_password" placeholder="Current password" autocomplete="current-password" aria-label="Current password">
        <?php endif; ?>
        <input type="text" name="nickname" placeholder="Name this passkey (optional)" maxlength="120" aria-label="Passkey name">
        <button type="button" class="btn" data-passkey-add-btn>Add a passkey</button>
        <p class="form-error" data-passkey-add-error hidden></p>
    </form>
    <noscript>
        <p>Adding a passkey needs JavaScript and a supported browser. Password<?= !empty($totp['enabled']) ? ', authenticator code,' : '' ?> and recovery sign-in keep working without it.</p>
    </noscript>
</section>
<?php endif; ?>
```

(Adapt the `$totp['enabled']` probe and CSS class names to what the file actually uses; `human_datetime` is an autoloaded helper. The add form and step-up buttons ship `hidden` — Task 14's JS reveals them when `window.PublicKeyCredential` exists, so the no-JS page shows only working forms.)

Extend the flag test:

```php
        // inside test_passkeys_flag_gates_ceremony_routes, dark section:
        $this->assertStatus(404, $this->post('/settings/security/passkeys/1/rename', ['nickname' => 'x']));
        $this->assertStatus(404, $this->post('/settings/security/passkeys/1/revoke', []));
        $securityPage = $this->get('/settings/security');
        $this->assertStatus(200, $securityPage);
        self::assertStringNotContainsString('data-passkey-panel', $securityPage->body());
```

- [ ] **Step 5: Run the tests**

```bash
vendor/bin/phpunit tests/Integration/Core/AppPasskeyRegistrationTest.php tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppProtectedOwnerTest.php
```

Expected: PASS (including the untouched owner-guard suite).

- [ ] **Step 6: Commit**

```bash
git add src/Service/PasskeyService.php src/Repository/OAuthIdentityRepository.php src/Controller/PasskeyController.php src/Controller/AccountController.php templates/account/security.php src/Core/App.php tests/Integration/Core/AppPasskeyRegistrationTest.php tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "feat(passkeys): credential management panel with last-method + final-owner blocks (TM-ID-09, F5 Inc-7 path)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 13: Sign-in vertical — email-first passkey login integrated with `AuthController` (SP3)

Email-first challenge (`allowCredentials`), fixed-shape enumeration-safe decoys for unknown/passkeyless accounts, one-time consumption keyed to the credential's owner (TM-ID-05's cross-account guard on the login side), counter anomalies as pure risk signals (TM-ID-08), banned-account refusal with the house's generic copy, and the decision-#4 TOTP interaction: UV-verified assertions are multi-factor and sign straight in; TOTP-enrolled accounts refuse non-UV assertions with actionable guidance.

**Files:**
- Modify: `src/Service/PasskeyService.php` (add `beginLogin`, `completeLogin`, private `LOGIN_FAILED` const)
- Modify: `src/Controller/AuthController.php` (add `passkeyChallenge`, `passkeyLogin`, private `gatePasskeys()`)
- Modify: `src/Core/App.php` (two guest routes)
- Modify: `config/config.php` (rate policies `'passkey_login' => [10, 900]`, `'passkey_challenge' => [30, 900]` beside `mfa_login`)
- Modify: `templates/auth/login.php` (flag-gated, JS-revealed sign-in slot)
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php` (extend: guest routes 404 dark; login page carries no slot when dark)
- Test: `tests/Integration/Core/AppPasskeyLoginTest.php`

**Interfaces:**
- Consumes: Task 11/12 service internals; `MfaService::enabledForUser(int): bool`; `Session::login(User)`; `AuthController::safeNext(string)`; `RateLimitService::enforceSubject`.
- Produces: routes `POST /login/passkey/challenge` and `POST /login/passkey`; `PasskeyService::beginLogin(?string $email, string $sessionHash): array` and `completeLogin(string $credentialJson, string $sessionHash): array{user:User, used_uv:bool}`; template hook `data-passkey-signin` on the login page.

- [ ] **Step 1: Write the failing test**

`tests/Integration/Core/AppPasskeyLoginTest.php` — the enrollment helper mirrors `AppPasskeyRegistrationTest` (self-contained per house rule: tasks may be read out of order):

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use App\Security\Totp;
use App\Support\Base64Url;
use Tests\Support\TestCase;
use Tests\Support\Phase5\WebAuthnHarness;

final class AppPasskeyLoginTest extends TestCase
{
    private WebAuthnHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();
        (new SettingRepository($this->db))->set('features', ['passkeys' => true]);
        $this->harness = new WebAuthnHarness();
    }

    /** Enrolls a fresh credential for $user (who must be acting) and returns it. */
    private function enroll(array $user, string $password = 'password123'): array
    {
        $res = $this->post('/settings/security/passkeys/challenge', ['current_password' => $password]);
        $this->assertStatus(200, $res);
        $challenge = (string) Base64Url::decode(json_decode($res->body(), true)['options']['challenge']);
        $cred = $this->harness->createCredential();
        $this->assertStatus(200, $this->post('/settings/security/passkeys', [
            'credential' => $this->harness->registrationPayload($cred, $challenge),
        ]));
        return $cred;
    }

    /** @return array{challenge: string, options: array<string,mixed>} */
    private function loginChallenge(string $email): array
    {
        $res = $this->post('/login/passkey/challenge', ['email' => $email]);
        $this->assertStatus(200, $res);
        $options = json_decode($res->body(), true)['options'];
        return ['challenge' => (string) Base64Url::decode($options['challenge']), 'options' => $options];
    }

    public function test_registered_credential_signs_in_the_right_account(): void
    {
        $user = $this->makeUser(['username' => 'pk_login']);
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $this->logoutClient();

        $c = $this->loginChallenge((string) $user['email']);
        self::assertContains(Base64Url::encode($cred['credentialId']), array_column($c['options']['allowCredentials'], 'id'));
        self::assertCount(8, $c['options']['allowCredentials'], 'real and decoy responses use the same fixed slot count');

        $res = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c['challenge'], 1),
            'next' => '/settings/security',
        ]);
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        self::assertTrue($json['ok']);
        self::assertSame('/settings/security', $json['redirect']);
        $this->assertStatus(200, $this->get('/settings/security')); // authenticated, no login redirect
    }

    public function test_unknown_or_passkeyless_email_gets_fixed_shape_decoys_and_never_signs_in(): void
    {
        $c = $this->loginChallenge('nobody@retro.test');
        $secondUnknown = json_decode($this->post('/login/passkey/challenge', ['email' => 'nobody@retro.test'])->body(), true);
        self::assertTrue($secondUnknown['ok']);
        self::assertCount(8, $c['options']['allowCredentials']); // fixed decoy slots, same count as a real account
        self::assertSame(
            array_column($c['options']['allowCredentials'], 'id'),
            array_column($secondUnknown['options']['allowCredentials'], 'id'),
            'decoys must be stable across challenge requests so real IDs are not identifiable by set-diffing',
        );
        foreach ($c['options']['allowCredentials'] as $entry) {
            self::assertSame(['internal', 'hybrid', 'usb', 'nfc', 'ble'], $entry['transports']);
        }
        $knownNoPasskey = $this->makeUser(['username' => 'known_no_pk']);
        $knownNoPk = $this->loginChallenge((string) $knownNoPasskey['email']);
        self::assertCount(8, $knownNoPk['options']['allowCredentials']);
        $cred = $this->harness->createCredential();
        $res = $this->post('/login/passkey', [
            'email' => 'nobody@retro.test',
            'credential' => $this->harness->assertionPayload($cred, $c['challenge'], 1),
        ]);
        $this->assertStatus(422, $res);
        self::assertStringNotContainsString('nobody', $res->body()); // no account information leaks (§9)
    }

    public function test_login_challenge_rate_limit_keeps_the_json_error_contract(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->assertStatus(200, $this->post('/login/passkey/challenge', ['email' => 'rate-limit@example.test']));
        }
        $limited = $this->post('/login/passkey/challenge', ['email' => 'rate-limit@example.test']);
        $this->assertStatus(429, $limited);
        $json = json_decode($limited->body(), true);
        self::assertFalse($json['ok']);
        self::assertArrayHasKey('rate_limit', $json['errors']);
    }

    public function test_passkey_login_rate_limit_is_subject_keyed_and_cleared_after_success(): void
    {
        $user = $this->makeUser(['username' => 'pk_rate_clear']);
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $this->logoutClient();

        for ($i = 0; $i < 9; $i++) {
            $c = $this->loginChallenge((string) $user['email']);
            $bad = $this->harness->createCredential();
            $this->assertStatus(422, $this->post('/login/passkey', [
                'email' => (string) $user['email'],
                'credential' => $this->harness->assertionPayload($bad, $c['challenge'], $i + 1),
            ]));
        }

        $c = $this->loginChallenge((string) $user['email']);
        $success = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c['challenge'], 10),
        ]);
        $this->assertStatus(200, $success); // the tenth subject-keyed attempt succeeds and must clear the window
        $this->post('/logout', []);

        $next = $this->loginChallenge((string) $user['email']);
        $badAgain = $this->harness->createCredential();
        $afterClear = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($badAgain, $next['challenge'], 11),
        ]);
        self::assertSame(422, $afterClear->status(), 'without clearSubject this would be a 429 after the successful tenth attempt');
    }

    public function test_assertion_replay_is_rejected(): void // TM-ID-05
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $this->logoutClient();

        $c = $this->loginChallenge((string) $user['email']);
        $payload = $this->harness->assertionPayload($cred, $c['challenge'], 1);
        $this->assertStatus(200, $this->post('/login/passkey', ['email' => (string) $user['email'], 'credential' => $payload]));
        $this->post('/logout', []);
        $this->assertStatus(422, $this->post('/login/passkey', ['email' => (string) $user['email'], 'credential' => $payload]));
    }

    public function test_challenge_minted_for_one_account_rejects_another_accounts_credential(): void // TM-ID-05
    {
        $alice = $this->makeUser(['username' => 'pk_alice2']);
        $this->actingAs($alice);
        $this->enroll($alice);
        $this->logoutClient();

        $bob = $this->makeUser(['username' => 'pk_bob2']);
        $this->actingAs($bob);
        $bobCred = $this->enroll($bob);
        $this->logoutClient();

        $c = $this->loginChallenge((string) $alice['email']); // challenge bound to alice
        $res = $this->post('/login/passkey', [
            'email' => (string) $alice['email'],
            'credential' => $this->harness->assertionPayload($bobCred, $c['challenge'], 1),
        ]);
        $this->assertStatus(422, $res);
    }

    public function test_altered_signature_fails_generically(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $this->logoutClient();
        $c = $this->loginChallenge((string) $user['email']);
        $res = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c['challenge'], 1, ['tamperSignature' => true]),
        ]);
        $this->assertStatus(422, $res);
    }

    public function test_banned_account_cannot_passkey_sign_in(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $this->logoutClient();
        $c = $this->loginChallenge((string) $user['email']);
        $this->db->run("UPDATE users SET status = 'banned' WHERE id = ?", [(int) $user['id']]);
        $res = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c['challenge'], 1),
        ]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('not permitted', $res->body());
    }

    public function test_non_increasing_counter_signs_in_and_writes_a_risk_audit_row(): void // TM-ID-08
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $this->logoutClient();

        $c1 = $this->loginChallenge((string) $user['email']);
        $this->assertStatus(200, $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c1['challenge'], 5),
        ]));
        $this->post('/logout', []);

        $c2 = $this->loginChallenge((string) $user['email']);
        $res = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c2['challenge'], 5),
        ]);
        self::assertSame(200, $res->status(), 'anomaly must NOT block sign-in (decision #30)');
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'passkey_counter_anomaly' AND target_id = ?",
            [(int) $user['id']],
        ));
        self::assertSame(5, (int) $this->db->fetchValue(
            'SELECT sign_count FROM webauthn_credentials WHERE user_id = ?',
            [(int) $user['id']],
        ), 'stored sign_count remains a high-water mark after an anomaly');
    }

    public function test_totp_enrolled_account_requires_a_uv_assertion(): void // decision #4
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $secret = $this->enrollTotp();
        $this->logoutClient();

        $c1 = $this->loginChallenge((string) $user['email']);
        $noUv = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c1['challenge'], 1, ['flags' => 0x01]), // UP only
        ]);
        $this->assertStatus(422, $noUv);
        self::assertStringContainsString('two-factor', strtolower($noUv->body()));

        $c2 = $this->loginChallenge((string) $user['email']);
        $withUv = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c2['challenge'], 2),
        ]);
        self::assertSame(200, $withUv->status(), 'UV assertion is multi-factor and bypasses the TOTP interstitial');
        self::assertNotEmpty($secret);
    }

    /** Enrolls TOTP for the acting user over HTTP (AppMfaTest pattern) and returns the secret. */
    private function enrollTotp(string $password = 'password123'): string
    {
        $enroll = $this->post('/settings/security/totp/enroll', ['current_password' => $password]);
        self::assertContains($enroll->status(), [200, 302], 'enrollment must start');
        $page = $this->get('/settings/security')->body();
        preg_match('/([A-Z2-7]{32})/', $page, $m); // pin to AppMfaTest::extractAuthenticatorSecret's exact regex
        self::assertNotEmpty($m, 'enrolment secret must render — copy the extraction from AppMfaTest verbatim');
        $secret = $m[1];
        $confirm = $this->post('/settings/security/totp/confirm', [
            'current_password' => $password,
            'totp_code' => (new Totp())->code($secret),
        ]);
        self::assertContains($confirm->status(), [200, 302], 'confirm must succeed');
        return $secret;
    }
}
```

Before running: align `enrollTotp()` with the real TOTP flow in `tests/Integration/Core/AppMfaTest.php` — copy its secret-extraction regex and confirm-step assertions verbatim (the sketch above simplifies; the AppMfaTest helpers are authoritative). Confirm `Totp::code(string $secret)`'s real signature there too.

Extend `test_passkeys_flag_gates_ceremony_routes` in `AppFeatureFlagTest`:

```php
        // dark section additions:
        $this->assertStatus(404, $this->post('/login/passkey/challenge', ['email' => 'x@example.test']));
        $this->assertStatus(404, $this->post('/login/passkey', ['email' => 'x@example.test', 'credential' => '{}']));
        $login = $this->get('/login');
        $this->assertStatus(200, $login);
        self::assertStringNotContainsString('data-passkey-signin', $login->body());
```

- [ ] **Step 2: Run to verify failure**

```bash
vendor/bin/phpunit tests/Integration/Core/AppPasskeyLoginTest.php
```

Expected: FAIL — 404 on `/login/passkey/challenge`.

- [ ] **Step 3: Implement**

Append to `PasskeyService`:

```php
    private const LOGIN_FAILED = 'That passkey could not be used to sign in.';
    private const LOGIN_ALLOW_CREDENTIAL_SLOTS = self::MAX_ACTIVE_CREDENTIALS;
    private const LOGIN_TRANSPORT_HINTS = ['internal', 'hybrid', 'usb', 'nfc', 'ble'];

    /**
     * Email-first login options. Unknown/passkey-less accounts get an identically
     * shaped response: exactly LOGIN_ALLOW_CREDENTIAL_SLOTS allowCredentials entries,
     * normalized transport hints, deterministic decoys, and an unstored challenge.
     * Real accounts get the same count/transport shape (real ids padded with decoys).
     * Completion can only succeed for a real stored challenge + real credential.
     * @return array<string,mixed>
     */
    public function beginLogin(?string $email, string $sessionHash): array
    {
        $this->rp->assertUsable();
        $this->challenges->purgeExpired();
        $email = strtolower(trim((string) $email));
        $challenge = random_bytes(32);

        $userRow = $email !== '' ? $this->users->findByEmail($email) : null;
        $credentials = $userRow !== null ? $this->credentials->activeForUser((int) $userRow['id']) : [];

        if ($userRow === null || $credentials === []) {
            return $this->loginRequestOptions($challenge, [], $email);
        }

        $this->challenges->mint((int) $userRow['id'], $sessionHash, 'login', $challenge, self::CHALLENGE_TTL);
        return $this->loginRequestOptions($challenge, $credentials, $email);
    }

    /** @param list<array<string,mixed>> $credentialRows */
    private function loginRequestOptions(string $challenge, array $credentialRows, string $email): array
    {
        $key = (string) $this->config->get('app.key', '');
        if ($key === '') {
            throw new WebAuthnException('missing_app_key', 'APP_KEY is required for enumeration-safe passkey login decoys.');
        }
        $allow = [];
        foreach (array_slice($credentialRows, 0, self::LOGIN_ALLOW_CREDENTIAL_SLOTS) as $row) {
            $allow[] = [
                'type' => 'public-key',
                'id' => Base64Url::encode((string) $row['credential_id']),
                'transports' => self::LOGIN_TRANSPORT_HINTS,
            ];
        }
        for ($i = count($allow); $i < self::LOGIN_ALLOW_CREDENTIAL_SLOTS; $i++) {
            $allow[] = [
                'type' => 'public-key',
                'id' => Base64Url::encode(hash_hmac('sha256', 'passkey-decoy:' . $email . ':' . $i, $key, true)),
                'transports' => self::LOGIN_TRANSPORT_HINTS,
            ];
        }
        usort($allow, static function (array $a, array $b) use ($key, $email): int {
            $ha = hash_hmac('sha256', 'passkey-slot:' . $email . ':' . (string) $a['id'], $key);
            $hb = hash_hmac('sha256', 'passkey-slot:' . $email . ':' . (string) $b['id'], $key);
            return $ha <=> $hb;
        });

        return [
            'challenge' => Base64Url::encode($challenge),
            'rpId' => $this->rp->rpId(),
            'timeout' => self::CHALLENGE_TTL * 1000,
            'allowCredentials' => $allow,
            'userVerification' => 'preferred',
        ];
    }

    /** @return array{user: User, used_uv: bool} */
    public function completeLogin(string $credentialJson, string $sessionHash): array
    {
        $payload = $this->decodePayload($credentialJson);
        $challenge = $this->challengeFromPayload($payload);
        $rawId = Base64Url::decode((string) ($payload['rawId'] ?? ''));
        $row = ($rawId !== null && $rawId !== '') ? $this->credentials->findActiveByCredentialId($rawId) : null;
        if ($row === null) {
            throw new ValidationException(['passkey' => self::LOGIN_FAILED]); // unknown credential: generic (§9)
        }
        $userId = (int) $row['user_id'];
        if (!$this->challenges->consume($challenge, $sessionHash, 'login', $userId)) {
            // stale, replayed, other-session, or minted for a different account (TM-ID-05)
            throw new ValidationException(['passkey' => self::LOGIN_FAILED]);
        }
        $user = $this->users->findEntity($userId);
        if ($user === null || $user->isBanned()) {
            throw new ValidationException(['passkey' => 'This account is not permitted to sign in.']);
        }

        $requireUv = $this->mfaService->enabledForUser($userId); // decision #4: TOTP accounts demand UV
        try {
            $result = $this->verifier->verifyAssertion($payload, $challenge, (string) $row['public_key'], (int) $row['sign_count'], $requireUv);
        } catch (\App\Security\WebAuthn\WebAuthnException $e) {
            if ($e->code === 'uv_required') {
                throw new ValidationException(['passkey' => 'This account uses two-factor authentication: use a passkey with a screen lock, or sign in with your password and code.']);
            }
            throw new ValidationException(['passkey' => self::LOGIN_FAILED]); // no protocol detail leaks
        }

        $this->credentials->updateOnUse((int) $row['id'], $result->signCount); // high-water sign_count only; last_used_at always refreshes
        if ($result->counterAnomaly) {
            $this->recordAnomaly($userId, (int) $row['id']); // TM-ID-08: signal, never block
        }
        $this->audit($userId, 'passkey_login', ['credential' => (int) $row['id'], 'uv' => $result->userVerified]);
        $this->telemetry?->emit('passkey.login', ['user' => $userId]);

        return ['user' => $user, 'used_uv' => $result->userVerified];
    }
```

(`$this->mfaService` — the constructor collaborator; see the Locked interfaces. `UserRepository::findByEmail` — confirm the exact name from `AuthService::attempt`.)

`AuthController` additions (add imports for `FeatureFlags`, `HttpException`, `NotFoundException`, `PasskeyService`, and `WebAuthnException` at the top of the existing class):

```php
    public function passkeyChallenge(Request $request, array $params = []): Response
    {
        $this->gatePasskeys();
        if ($this->session()->user() !== null) {
            return Response::json(['ok' => false, 'errors' => ['email' => 'Already signed in.']], 422);
        }
        $email = strtolower(trim((string) ($request->post('email') ?? '')));
        try {
            $this->container->get(RateLimitService::class)
                ->enforceSubject('passkey_challenge', $request, $email !== '' ? $email : 'anonymous');
        } catch (HttpException $e) {
            return Response::json(['ok' => false, 'errors' => ['rate_limit' => $e->getMessage()]], $e->statusCode());
        }
        try {
            $options = $this->container->get(PasskeyService::class)
                ->beginLogin($email, PasskeyService::sessionBinding($this->session()));
        } catch (WebAuthnException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => $e->getMessage()], 'code' => $e->code], 422);
        }
        return Response::json(['ok' => true, 'options' => $options]);
    }

    public function passkeyLogin(Request $request, array $params = []): Response
    {
        $this->gatePasskeys();
        if ($this->session()->user() !== null) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => 'Already signed in.']], 422);
        }
        $email = strtolower(trim((string) ($request->post('email') ?? '')));
        $subject = $email !== '' ? $email : 'anonymous';
        $limiter = $this->container->get(RateLimitService::class);
        try {
            $limiter->enforceSubject('passkey_login', $request, $subject);
        } catch (HttpException $e) {
            return Response::json(['ok' => false, 'errors' => ['rate_limit' => $e->getMessage()]], $e->statusCode());
        }
        try {
            $result = $this->container->get(PasskeyService::class)->completeLogin(
                (string) ($request->post('credential') ?? ''),
                PasskeyService::sessionBinding($this->session()),
            );
        } catch (ValidationException $e) {
            return Response::json(['ok' => false, 'errors' => $e->errors], 422);
        } catch (WebAuthnException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => 'That passkey could not be used to sign in.']], 422);
        }
        $limiter->clearSubject('passkey_login', $request, $subject);
        $this->session()->login($result['user']);
        return Response::json(['ok' => true, 'redirect' => $this->safeNext((string) ($request->post('next') ?? '/'))]);
    }

    private function gatePasskeys(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('passkeys')) {
            throw new NotFoundException('Not found.');
        }
    }
```

Routes (before the generic `/login` POST registration if adjacency matters; paths are distinct):

```php
$r->post('/login/passkey/challenge', [AuthController::class, 'passkeyChallenge']);
$r->post('/login/passkey', [AuthController::class, 'passkeyLogin']);
```

`templates/auth/login.php` — after the login form's submit row:

```php
<?php if (!empty($features['passkeys'])): ?>
    <div class="passkey-signin" data-passkey-signin
         data-challenge-url="/login/passkey/challenge"
         data-login-url="/login/passkey" hidden>
        <button type="button" class="btn btn-secondary" data-passkey-signin-btn>Sign in with a passkey</button>
        <p class="form-error" data-passkey-signin-error hidden></p>
    </div>
<?php endif; ?>
```

`config/config.php` rate policies (beside `mfa_login`):

```php
'passkey_challenge' => [30, 900],
'passkey_login' => [10, 900],
```

- [ ] **Step 4: Run the tests**

```bash
vendor/bin/phpunit tests/Integration/Core/AppPasskeyLoginTest.php tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Controller/AuthControllerTest.php
```

Expected: PASS — 11 new login tests, extended flag test, and the untouched password-login suite.

- [ ] **Step 5: Commit**

```bash
git add src/Service/PasskeyService.php src/Controller/AuthController.php src/Core/App.php config/config.php templates/auth/login.php tests/Integration/Core/AppPasskeyLoginTest.php tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "feat(passkeys): email-first passkey sign-in with enumeration-safe decoys, counter risk-signals, TOTP-UV policy (TM-ID-05/08)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 14: `public/assets/passkeys.js` — the progressive-enhancement ceremony driver

Vanilla, CSP-clean (external file, no inline anything), decorates the three `data-passkey-*` surfaces rendered hidden by Tasks 12–13, reads `_token` from hidden inputs exactly like `composer.js`, and degrades to nothing when `PublicKeyCredential` is missing (the panels' `<noscript>`/hidden states already tell that story).

**Files:**
- Create: `public/assets/passkeys.js`
- Modify: `templates/layout.php` (flag-gated `<script>` beside the other conditional includes, ~line 79-82)
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php` (script-tag gating assertions)

**Interfaces:**
- Consumes: the wire contract; hooks `data-passkey-add-form`/`data-passkey-add-btn`/`data-passkey-add-error` plus hidden `input[name="passkey_assertion"]` for passwordless add-step-up, `data-passkey-revoke-form`/`data-passkey-stepup-btn`/`data-passkey-needs-stepup`, `data-passkey-signin`/`data-passkey-signin-btn`/`data-passkey-signin-error`; URLs from `data-challenge-url`/`data-store-url`/`data-stepup-url`/`data-login-url`.
- Produces: nothing for later tasks except the working browser flow Task 16 records.

- [ ] **Step 1: Write the failing gating test** (extend `AppFeatureFlagTest`)

```php
        // dark section addition:
        self::assertStringNotContainsString('/assets/passkeys.js', $this->get('/')->body());
        // enabled section addition (while setFlags(['passkeys' => true]) is active):
        self::assertStringContainsString('/assets/passkeys.js', $this->get('/')->body());
        self::assertStringContainsString('data-passkey-signin', $this->get('/login')->body());
```

Run: `vendor/bin/phpunit --filter test_passkeys_flag_gates_ceremony_routes tests/Integration/Core/AppFeatureFlagTest.php` — FAIL (script tag never rendered).

- [ ] **Step 2: Implement the layout include**

In `templates/layout.php`, beside the other flag-conditional scripts:

```php
<?php if (!empty($features['passkeys'])): ?><script src="/assets/passkeys.js" defer></script><?php endif; ?>
```

- [ ] **Step 3: Implement `public/assets/passkeys.js`**

```js
/* Passkey ceremonies (P5-11). Progressive enhancement only: every surface this
   file touches is rendered hidden/no-op by the server; without JS (or without
   WebAuthn support) password/TOTP/recovery flows remain the working baseline. */
(function () {
    'use strict';
    if (!('PublicKeyCredential' in window) || !window.fetch || !window.FormData) {
        return;
    }

    function b64uToBuf(s) {
        s = String(s).replace(/-/g, '+').replace(/_/g, '/');
        while (s.length % 4) { s += '='; }
        var bin = atob(s);
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) { bytes[i] = bin.charCodeAt(i); }
        return bytes.buffer;
    }

    function bufToB64u(buf) {
        var bytes = new Uint8Array(buf);
        var bin = '';
        for (var i = 0; i < bytes.length; i++) { bin += String.fromCharCode(bytes[i]); }
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function tokenNear(el) {
        var input = (el && el.querySelector && el.querySelector('input[name="_token"]'))
            || document.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }

    function post(url, fields, tokenScope) {
        var data = new FormData();
        Object.keys(fields).forEach(function (k) {
            if (fields[k] !== null && fields[k] !== undefined) { data.append(k, fields[k]); }
        });
        if (!data.get('_token')) { data.append('_token', tokenNear(tokenScope)); }
        return fetch(url, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.json().then(function (j) { j.__status = r.status; return j; });
        });
    }

    function firstError(json, fallback) {
        if (json && json.errors) {
            var keys = Object.keys(json.errors);
            if (keys.length) { return json.errors[keys[0]]; }
        }
        return fallback;
    }

    function show(el, message) {
        if (!el) { return; }
        el.textContent = message;
        el.hidden = false;
    }

    function prepCreateOptions(options) {
        options.challenge = b64uToBuf(options.challenge);
        options.user.id = b64uToBuf(options.user.id);
        (options.excludeCredentials || []).forEach(function (c) { c.id = b64uToBuf(c.id); });
        return options;
    }

    function prepGetOptions(options) {
        options.challenge = b64uToBuf(options.challenge);
        (options.allowCredentials || []).forEach(function (c) { c.id = b64uToBuf(c.id); });
        return options;
    }

    function serializeCreated(cred) {
        var ext = cred.getClientExtensionResults ? cred.getClientExtensionResults() : {};
        return JSON.stringify({
            id: cred.id,
            rawId: bufToB64u(cred.rawId),
            type: cred.type,
            transports: (cred.response.getTransports ? cred.response.getTransports() : []),
            credProps: ext.credProps || null,
            response: {
                clientDataJSON: bufToB64u(cred.response.clientDataJSON),
                attestationObject: bufToB64u(cred.response.attestationObject)
            }
        });
    }

    function serializeAssertion(cred) {
        return JSON.stringify({
            id: cred.id,
            rawId: bufToB64u(cred.rawId),
            type: cred.type,
            response: {
                clientDataJSON: bufToB64u(cred.response.clientDataJSON),
                authenticatorData: bufToB64u(cred.response.authenticatorData),
                signature: bufToB64u(cred.response.signature),
                userHandle: cred.response.userHandle ? bufToB64u(cred.response.userHandle) : null
            }
        });
    }

    var CANCELLED = 'Passkey step was cancelled or unavailable — your other sign-in methods still work.';

    // ── Add a passkey (settings/security) ─────────────────────────────
    var addForm = document.querySelector('[data-passkey-add-form]');
    if (addForm) {
        addForm.hidden = false;
        var addBtn = addForm.querySelector('[data-passkey-add-btn]');
        var addErr = addForm.querySelector('[data-passkey-add-error]');
        function beginRegistrationChallenge() {
            var pw = addForm.querySelector('input[name="current_password"]');
            var assertion = addForm.querySelector('input[name="passkey_assertion"]');
            if (!pw && assertion && !assertion.value) {
                return post(addForm.getAttribute('data-stepup-url'), {}, addForm)
                    .then(function (json) {
                        if (!json.ok) { throw new Error(firstError(json, 'Confirm with an existing passkey before adding another one.')); }
                        return navigator.credentials.get({ publicKey: prepGetOptions(json.options) });
                    })
                    .then(function (cred) {
                        if (!cred) { throw new Error(CANCELLED); }
                        assertion.value = serializeAssertion(cred);
                        return beginRegistrationChallenge();
                    });
            }
            return post(addForm.getAttribute('data-challenge-url'), {
                current_password: pw ? pw.value : null,
                passkey_assertion: assertion ? assertion.value : null
            }, addForm);
        }
        addBtn.addEventListener('click', function () {
            if (addErr) { addErr.hidden = true; }
            beginRegistrationChallenge()
                .then(function (json) {
                    if (!json.ok) { throw new Error(firstError(json, 'Could not start the passkey setup.')); }
                    return navigator.credentials.create({ publicKey: prepCreateOptions(json.options) });
                })
                .then(function (cred) {
                    if (!cred) { throw new Error(CANCELLED); }
                    var nick = addForm.querySelector('input[name="nickname"]');
                    return post(addForm.getAttribute('data-store-url'), {
                        credential: serializeCreated(cred),
                        nickname: nick ? nick.value : null
                    }, addForm);
                })
                .then(function (json) {
                    if (!json.ok) { throw new Error(firstError(json, 'The passkey could not be saved.')); }
                    window.location.reload();
                })
                .catch(function (err) {
                    show(addErr, err && err.message ? err.message : CANCELLED);
                });
        });
    }

    // ── Step-up confirm for passwordless revoke ────────────────────────
    document.querySelectorAll('[data-passkey-revoke-form]').forEach(function (form) {
        var stepBtn = form.querySelector('[data-passkey-stepup-btn]');
        var needsStepUp = form.querySelector('[data-passkey-needs-stepup]');
        if (!stepBtn) { return; }
        stepBtn.hidden = false;
        if (needsStepUp) { needsStepUp.disabled = true; }
        stepBtn.addEventListener('click', function () {
            var panel = document.querySelector('[data-passkey-add-form]');
            post(panel.getAttribute('data-stepup-url'), {}, form)
                .then(function (json) {
                    if (!json.ok) { throw new Error(firstError(json, CANCELLED)); }
                    return navigator.credentials.get({ publicKey: prepGetOptions(json.options) });
                })
                .then(function (cred) {
                    if (!cred) { throw new Error(CANCELLED); }
                    form.querySelector('input[name="passkey_assertion"]').value = serializeAssertion(cred);
                    if (needsStepUp) { needsStepUp.disabled = false; }
                    form.submit();
                })
                .catch(function (err) {
                    show(form.querySelector('[data-passkey-revoke-error]'), err && err.message ? err.message : CANCELLED);
                });
        });
    });

    // ── Sign in with a passkey (login page) ───────────────────────────
    var signin = document.querySelector('[data-passkey-signin]');
    if (signin) {
        signin.hidden = false;
        var signinBtn = signin.querySelector('[data-passkey-signin-btn]');
        var signinErr = signin.querySelector('[data-passkey-signin-error]');
        signinBtn.addEventListener('click', function () {
            if (signinErr) { signinErr.hidden = true; }
            var emailInput = document.querySelector('form input[name="email"]');
            var nextInput = document.querySelector('form input[name="next"]');
            post(signin.getAttribute('data-challenge-url'), { email: emailInput ? emailInput.value : '' }, document)
                .then(function (json) {
                    if (!json.ok) { throw new Error(firstError(json, 'Could not start passkey sign-in.')); }
                    return navigator.credentials.get({ publicKey: prepGetOptions(json.options) });
                })
                .then(function (cred) {
                    if (!cred) { throw new Error(CANCELLED); }
                    return post(signin.getAttribute('data-login-url'), {
                        email: emailInput ? emailInput.value : '',
                        credential: serializeAssertion(cred),
                        next: nextInput ? nextInput.value : null
                    }, document);
                })
                .then(function (json) {
                    if (!json.ok) { throw new Error(firstError(json, 'That passkey could not be used to sign in.')); }
                    window.location.assign(json.redirect || '/');
                })
                .catch(function (err) {
                    show(signinErr, err && err.message ? err.message : CANCELLED);
                });
        });
    }
})();
```

- [ ] **Step 4: Run the gating test + eyeball the flow**

```bash
vendor/bin/phpunit --filter test_passkeys_flag_gates_ceremony_routes tests/Integration/Core/AppFeatureFlagTest.php
```

Expected: PASS. Full behavioral proof arrives with Task 16's virtual-authenticator spec (don't hand-verify in a real browser as evidence — the spec is the record).

- [ ] **Step 5: Commit**

```bash
git add public/assets/passkeys.js templates/layout.php templates/account/security.php tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "feat(passkeys): CSP-clean progressive-enhancement ceremony driver (add / step-up / sign-in)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 15: Recovery matrix + lifecycle export (`AppPasskeyRecoveryTest`)

The §9 "Passkey fallback / removal / recovery" scenarios as one spec-pinned suite: every non-passkey path keeps working with passkeys enrolled, the lost-authenticator journey (fallback sign-in → revoke → re-enroll) is proven end-to-end, suspended users can't enroll (state beats role), and account export includes passkey metadata (program-plan Inc 7 scope).

**Files:**
- Modify: `src/Service/AccountLifecycleService.php` (export section; find the exact assembly method by reading the class — it is the one `AccountController`'s export route calls)
- Test: `tests/Integration/Core/AppPasskeyRecoveryTest.php`

**Interfaces:**
- Consumes: everything landed so far; the TOTP flow (AppMfaTest patterns); the account-export HTTP route (locate via `grep -n "export" src/Core/App.php`).
- Produces: export payload gains a `passkeys` list: `[{nickname, created_at, last_used_at, transports, backed_up}]` — metadata only, never key material or raw credential ids.

- [ ] **Step 1: Write the failing test**

`tests/Integration/Core/AppPasskeyRecoveryTest.php` (helpers copied in; tasks stay self-contained; pin the two TOTP extraction regexes to `AppMfaTest`'s helpers before running):

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use App\Security\Totp;
use App\Support\Base64Url;
use Tests\Support\TestCase;
use Tests\Support\Phase5\WebAuthnHarness;

final class AppPasskeyRecoveryTest extends TestCase
{
    private WebAuthnHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();
        (new SettingRepository($this->db))->set('features', ['passkeys' => true]);
        $this->harness = new WebAuthnHarness();
    }

    private function enroll(string $password = 'password123', ?string $nickname = null): array
    {
        $res = $this->post('/settings/security/passkeys/challenge', ['current_password' => $password]);
        $this->assertStatus(200, $res);
        $challenge = (string) Base64Url::decode(json_decode($res->body(), true)['options']['challenge']);
        $cred = $this->harness->createCredential();
        $this->assertStatus(200, $this->post('/settings/security/passkeys', [
            'credential' => $this->harness->registrationPayload($cred, $challenge),
            'nickname' => $nickname,
        ]));
        return $cred;
    }

    private function passkeyLogin(string $email, array $cred, int $signCount)
    {
        $res = $this->post('/login/passkey/challenge', ['email' => $email]);
        $this->assertStatus(200, $res);
        $challenge = (string) Base64Url::decode(json_decode($res->body(), true)['options']['challenge']);
        return $this->post('/login/passkey', [
            'email' => $email,
            'credential' => $this->harness->assertionPayload($cred, $challenge, $signCount),
        ]);
    }

    public function test_password_totp_and_recovery_paths_survive_passkey_enrollment(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->enroll();

        $this->post('/settings/security/totp/enroll', ['current_password' => 'password123']);
        preg_match('/([A-Z2-7]{32})/', $this->get('/settings/security')->body(), $sm);
        self::assertNotEmpty($sm, 'pin this extraction to AppMfaTest::extractAuthenticatorSecret');
        $secret = $sm[1];
        $confirm = $this->post('/settings/security/totp/confirm', [
            'current_password' => 'password123',
            'totp_code' => (new Totp())->code($secret),
        ]);
        preg_match_all('#<code>([A-F0-9-]{11})</code>#', $confirm->body() . $this->get('/settings/security')->body(), $rm);
        self::assertNotEmpty($rm[1], 'recovery codes are shown once — pin to AppMfaTest::extractRecoveryCodes');
        $recovery = $rm[1][0];
        $this->logoutClient();

        // Password + TOTP interstitial still works with a passkey enrolled.
        $mfaPage = $this->post('/login', ['email' => (string) $user['email'], 'password' => 'password123']);
        $this->assertStatus(200, $mfaPage);
        preg_match('/name="mfa_token" value="([a-f0-9]+)"/', $mfaPage->body(), $tm);
        self::assertNotEmpty($tm);
        $this->assertRedirect($this->post('/login/mfa', ['mfa_token' => $tm[1], 'code' => (new Totp())->code($secret)]));
        $this->assertStatus(200, $this->get('/settings/security'));
        $this->post('/logout', []);

        // Recovery-code path too.
        $mfaPage2 = $this->post('/login', ['email' => (string) $user['email'], 'password' => 'password123']);
        preg_match('/name="mfa_token" value="([a-f0-9]+)"/', $mfaPage2->body(), $tm2);
        self::assertNotEmpty($tm2);
        $this->assertRedirect($this->post('/login/mfa', ['mfa_token' => $tm2[1], 'code' => $recovery]));
        $this->assertStatus(200, $this->get('/settings/security'));
    }

    public function test_lost_authenticator_journey_fallback_revoke_reenroll(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $lost = $this->enroll(nickname: 'Lost key');
        $this->logoutClient();

        // The "lost" path: password sign-in, then revoke the stranded credential.
        $this->assertRedirect($this->post('/login', ['email' => (string) $user['email'], 'password' => 'password123']));
        preg_match('#/settings/security/passkeys/(\d+)/revoke#', $this->get('/settings/security')->body(), $m);
        self::assertNotEmpty($m);
        $this->assertRedirect(
            $this->post("/settings/security/passkeys/{$m[1]}/revoke", ['current_password' => 'password123']),
            '/settings/security',
        );

        // Re-enroll a replacement and sign in with it.
        $replacement = $this->enroll(nickname: 'New key');
        $this->post('/logout', []);
        $ok = $this->passkeyLogin((string) $user['email'], $replacement, 1);
        $this->assertStatus(200, $ok);
        self::assertTrue(json_decode($ok->body(), true)['ok']);
        $this->post('/logout', []);

        // The revoked credential can no longer sign in.
        $this->assertStatus(422, $this->passkeyLogin((string) $user['email'], $lost, 9));
    }

    public function test_suspended_user_cannot_mint_an_enrollment_challenge(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->db->run(
            "UPDATE users SET status = 'suspended', suspended_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE id = ?",
            [(int) $user['id']],
        );
        $res = $this->post('/settings/security/passkeys/challenge', ['current_password' => 'password123']);
        // WriteGate refusal ("state beats role") — pin the exact status after reading
        // WriteGate::assertCanWrite's exception type; 403 expected.
        $this->assertStatus(403, $res);
    }

    public function test_one_registration_challenge_admits_exactly_one_credential(): void
    {
        $this->actingAs($this->makeUser());
        $res = $this->post('/settings/security/passkeys/challenge', ['current_password' => 'password123']);
        $challenge = (string) Base64Url::decode(json_decode($res->body(), true)['options']['challenge']);
        $first = $this->harness->registrationPayload($this->harness->createCredential(), $challenge);
        $second = $this->harness->registrationPayload($this->harness->createCredential(), $challenge);
        $this->assertStatus(200, $this->post('/settings/security/passkeys', ['credential' => $first]));
        $this->assertStatus(422, $this->post('/settings/security/passkeys', ['credential' => $second]));
    }

    public function test_export_includes_passkey_metadata_only(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $cred = $this->enroll(nickname: 'Export me');
        // Pin the export route first: grep -n "export" src/Core/App.php (Phase 3 account export).
        $export = $this->post('/settings/account/export', []);
        self::assertContains($export->status(), [200, 302]);
        $body = $export->status() === 200 ? $export->body() : $this->get('/settings/account/export')->body();
        self::assertStringContainsString('Export me', $body);
        self::assertStringContainsString('internal', $body);
        self::assertStringNotContainsString(Base64Url::encode($cred['credentialId']), $body, 'raw credential ids never leave');
        self::assertStringNotContainsString(Base64Url::encode($cred['coseKey']), $body, 'key material never leaves');
    }
}
```

- [ ] **Step 2: Run to verify the export test fails** (the first four should pass already if Tasks 11–13 are correct — they are regression armor; the export assertions fail until Step 3)

```bash
vendor/bin/phpunit tests/Integration/Core/AppPasskeyRecoveryTest.php
```

- [ ] **Step 3: Implement the export section**

In `AccountLifecycleService`'s export assembly (alongside its existing sections), add:

```php
        $passkeys = [];
        foreach ($this->webauthnCredentials->activeForUser($user->id()) as $row) {
            $passkeys[] = [
                'nickname' => $row['nickname'],
                'created_at' => $row['created_at'],
                'last_used_at' => $row['last_used_at'],
                'transports' => $row['transports'],
                'backed_up' => (int) $row['is_backed_up'] === 1,
            ];
        }
        $export['passkeys'] = $passkeys;
```

with `WebAuthnCredentialRepository` added to the service's constructor and container bind (nullable-when-dark is unnecessary — the repository is harmless with zero rows; bind it unconditionally). Match the surrounding export-section naming style. Account deletion needs no code: `0051`'s `ON DELETE CASCADE` removes credential rows with the user; deactivation deliberately preserves them (documented in the runbook).

- [ ] **Step 4: Run the suite**

```bash
vendor/bin/phpunit tests/Integration/Core/AppPasskeyRecoveryTest.php && composer test 2>&1 | tail -3
```

Expected: recovery suite PASS and the full suite green.

- [ ] **Step 5: Commit**

```bash
git add src/Service/AccountLifecycleService.php src/Core/App.php tests/Integration/Core/AppPasskeyRecoveryTest.php
git commit -m "test(passkeys): recovery/fallback/lockout matrix + passkey metadata in account export

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 16: Browser evidence — CDP virtual authenticator + axe (SP-browser)

The evidence rule is explicit: *"Passkey completion requires real supported-browser/authenticator evidence plus protocol-negative fixtures; mocked cryptography alone is insufficient."* This spec drives the real Chromium WebAuthn stack against the real app through a CDP virtual authenticator (the harness's first — none exists yet), on desktop + mobile, with axe on the passkey panel, producing the §10.3 "supported-browser WebAuthn evidence" PNGs.

**Files:**
- Create: `tests/browser/passkeys.spec.ts`
- Modify: `tests/browser/package.json` (add script `"evidence:passkeys": "playwright test passkeys.spec.ts totp.spec.ts"`)

**Interfaces:**
- Consumes: seeded `bob@retro.test` / `password123`; the `runPhp()` flag-toggling pattern from `wysiwyg-composer.spec.ts` (copy it verbatim, adapted to `passkeys`).
- Produces: `docs/evidence/browser/<project>/passkeys-0N-*.png` + green desktop/mobile runs, cited by Task 18.

- [ ] **Step 1: Write the spec**

```ts
import { expect, test, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// Empty string lets Playwright resolve relative paths against use.baseURL.
// E2E_BASE_URL matches tests/browser/playwright.config.ts; RB_BASE_URL is a
// legacy/manual override for ad-hoc runs. Do not hard-code the server port.
const BASE = process.env.E2E_BASE_URL ?? process.env.RB_BASE_URL ?? '';

// Copy runPhp()/flag-setter helpers from wysiwyg-composer.spec.ts, adapted:
// setPasskeys(true|false|null) merges/unsets features.passkeys via SettingRepository.

function shot(name: string, projectName: string): string {
    return `../../docs/evidence/browser/${projectName}/${name}.png`;
}

async function login(page: Page, email: string): Promise<void> {
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    await expect(page).not.toHaveURL(/\/login/);
}

async function addVirtualAuthenticator(page: Page) {
    const cdp = await page.context().newCDPSession(page);
    await cdp.send('WebAuthn.enable');
    const { authenticatorId } = await cdp.send('WebAuthn.addVirtualAuthenticator', {
        options: {
            protocol: 'ctap2',
            transport: 'internal',
            hasResidentKey: true,
            hasUserVerification: true,
            isUserVerified: true,
            automaticPresenceSimulation: true,
        },
    });
    return { cdp, authenticatorId };
}

test.describe('passkeys (P5-11 Gate A browser evidence)', () => {
    test.beforeAll(async () => { await setPasskeys(true); });
    test.afterAll(async () => { await setPasskeys(null); }); // restore the DEFAULTS-dark state

    test('enroll, sign out, passkey sign-in, revoke — with a real Chromium authenticator', async ({ page }, testInfo) => {
        await addVirtualAuthenticator(page);
        await login(page, 'bob@retro.test');

        await page.goto(`${BASE}/settings/security`);
        const addForm = page.locator('[data-passkey-add-form]');
        await expect(addForm).toBeVisible(); // JS revealed it
        await page.screenshot({ path: shot('passkeys-01-panel', testInfo.project.name), fullPage: true });

        await addForm.locator('input[name="current_password"]').fill('password123');
        await addForm.locator('input[name="nickname"]').fill('Evidence key');
        await addForm.locator('[data-passkey-add-btn]').click();
        await expect(page.locator('.passkey-list')).toContainText('Evidence key');
        await page.screenshot({ path: shot('passkeys-02-enrolled', testInfo.project.name), fullPage: true });

        // Axe on the enrolled panel state.
        const axe = await new AxeBuilder({ page }).include('[data-passkey-panel]').analyze();
        expect(axe.violations).toEqual([]);

        // Sign out, then in with the passkey alone.
        await page.click('form[action="/logout"] button[type="submit"]');
        await page.goto(`${BASE}/login`);
        await page.fill('input[name="email"]', 'bob@retro.test');
        const signin = page.locator('[data-passkey-signin]');
        await expect(signin).toBeVisible();
        await page.screenshot({ path: shot('passkeys-03-login', testInfo.project.name) });
        await signin.locator('[data-passkey-signin-btn]').click();
        await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 });
        await page.screenshot({ path: shot('passkeys-04-signed-in', testInfo.project.name) });

        // Revoke with the password factor; the row disappears.
        await page.goto(`${BASE}/settings/security`);
        const revokeForm = page.locator('[data-passkey-revoke-form]').first();
        await revokeForm.locator('input[name="current_password"]').fill('password123');
        await revokeForm.locator('button[type="submit"]').click();
        await expect(page.locator('body')).not.toContainText('Evidence key');
        await page.screenshot({ path: shot('passkeys-05-revoked', testInfo.project.name), fullPage: true });
    });

    test('login page shows no passkey affordance while the flag is dark', async ({ page }, testInfo) => {
        await setPasskeys(false);
        await page.goto(`${BASE}/login`);
        await expect(page.locator('[data-passkey-signin]')).toHaveCount(0);
        await page.screenshot({ path: shot('passkeys-06-dark-login', testInfo.project.name) });
        await setPasskeys(true);
    });
});
```

Copy `setPasskeys`/`runPhp` from `wysiwyg-composer.spec.ts` **verbatim** (it already handles merging the `features` JSON and unsetting overrides); align `login()`/selectors with that file's working helpers. Serial-run the two tests (`test.describe.configure({ mode: 'serial' })`) if flag flips race the other test.

- [ ] **Step 2: Run both projects**

```bash
cd tests/browser && npx playwright test passkeys.spec.ts
```

Expected: 4 passed (2 tests × desktop+mobile), PNGs written. Then run the untouched baselines — the flag restore in `afterAll` must leave them green:

```bash
npm run evidence && npm run a11y
```

Expected: existing counts (53 passed / 1 skipped; 14 passed) — unchanged.

- [ ] **Step 3: Commit**

```bash
git add tests/browser/passkeys.spec.ts tests/browser/package.json docs/evidence/browser/desktop/passkeys-*.png docs/evidence/browser/mobile/passkeys-*.png
git commit -m "test(browser): passkey enroll/sign-in/revoke via CDP virtual authenticator + axe, desktop+mobile

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 17: D11 budget — measure `webauthn.ceremony_p95`

ADR 0004 D11 approved **2000 ms p95 server time (excluding authenticator UX)** for WebAuthn/TOTP ceremonies; the row sits `PENDING (inc7)` in `docs/evidence/phase5/performance-budgets.md`. Wire a measurement probe into the existing budget plumbing and flip the row to MEASURED.

**Files:**
- Create: `docs/evidence/phase5/webauthn-budget-fixture.json` (public-only committed fixture: COSE public key + challenges + signed payloads; no private key bytes)
- Modify: the measurement service behind `bin/console verify:phase5-budgets` — read `src/Service/Phase5BudgetReportService.php` and `src/Service/BaselineMetricsService.php` first and mirror **exactly** how `resolver.p95` was measured and reported in Inc 1 (same plumbing, one new probe).
- Regenerate: `docs/evidence/phase5/performance-budgets.md` (via the console command, never by hand).

**Interfaces:**
- Consumes: `WebAuthnVerifier`, `RelyingParty`, `CoseKey`, public-only fixture JSON — the probe measures the full server-side assertion verification (base64url decode → CBOR/COSE → openssl verify) without generating private keys in `src/`.
- Produces: `webauthn.ceremony_p95` → `MEASURED (PASS)`.

- [ ] **Step 1: Generate the public-only budget fixture**

Run this from the repo root. The private key exists only inside `Tests\Support\Phase5\WebAuthnHarness` during fixture generation; the committed JSON contains only the COSE public key, challenges, authenticator/client data, signatures, and stored counter inputs:

```bash
php <<'PHP'
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Support\Base64Url;
use Tests\Support\Phase5\WebAuthnHarness;

$h = new WebAuthnHarness();
$cred = $h->createCredential();
$samples = [];
for ($i = 0; $i < 200; $i++) {
    $challenge = random_bytes(32);
    $samples[] = [
        'challenge' => Base64Url::encode($challenge),
        'payload' => json_decode($h->assertionPayload($cred, $challenge, $i + 1), true, flags: JSON_THROW_ON_ERROR),
        'stored_sign_count' => $i,
    ];
}

file_put_contents(
    __DIR__ . '/docs/evidence/phase5/webauthn-budget-fixture.json',
    json_encode([
        'rp_id' => 'localhost',
        'origin' => 'http://localhost:8000',
        'public_key_cose' => Base64Url::encode($cred['coseKey']),
        'samples' => $samples,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
);
PHP
grep '"public_key_cose"' docs/evidence/phase5/webauthn-budget-fixture.json
! grep -Ei 'private|BEGIN|END|privateKey|private_key' docs/evidence/phase5/webauthn-budget-fixture.json
```

Expected: the first `grep` prints the public-key field and the second prints nothing; the fixture must not contain PEM blocks, private-key fields, or `BEGIN`/`END` markers.

- [ ] **Step 2: Add the probe** (method body for wherever the Inc 1 resolver probe lives; the probe is self-contained from a public-only fixture so production/runtime `src/` code never generates private keys):

```php
    /** p95 of 200 full assertion verifications (server side only), in ms. */
    private function measureWebauthnCeremony(): float
    {
        $rp = new RelyingParty('http://localhost:8000', null, 'testing');
        $verifier = new WebAuthnVerifier($rp);

        $path = dirname(__DIR__, 2) . '/docs/evidence/phase5/webauthn-budget-fixture.json';
        $fixture = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $cose = Base64Url::decode((string) ($fixture['public_key_cose'] ?? ''));
        if ($cose === null || $cose === '') {
            throw new \RuntimeException('Invalid public_key_cose in WebAuthn budget fixture.');
        }

        $samples = [];
        foreach ((array) ($fixture['samples'] ?? []) as $sample) {
            $challenge = Base64Url::decode((string) ($sample['challenge'] ?? ''));
            $payload = $sample['payload'] ?? null;
            if ($challenge === null || $challenge === '' || !is_array($payload)) {
                throw new \RuntimeException('Invalid WebAuthn budget sample.');
            }
            $t = hrtime(true);
            $verifier->verifyAssertion($payload, $challenge, $cose, (int) ($sample['stored_sign_count'] ?? 0), false);
            $samples[] = (hrtime(true) - $t) / 1e6;
        }
        sort($samples);
        return $samples[(int) floor(count($samples) * 0.95)];
    }
```

Hook the probe into the report exactly like the resolver row — including how PASS/FAIL is decided against `Phase5Budgets`. Do not call `openssl_pkey_new`, `openssl_sign`, or any private-key generation/signing API from the measurement service.

- [ ] **Step 3: Regenerate and verify**

```bash
APP_ENV=testing php bin/console verify:phase5-budgets
grep 'webauthn.ceremony_p95' docs/evidence/phase5/performance-budgets.md
! grep -rn "openssl_pkey_new\|openssl_sign\|privateKey\|private_key" src/Service/Phase5BudgetReportService.php src/Service/BaselineMetricsService.php
```

Expected: the row now reads `… ms MEASURED (PASS)` (single-digit ms is typical for ES256 verify; budget is 2000 ms). The final `grep` prints no lines from `src/Service`.

- [ ] **Step 4: Commit**

```bash
git add docs/evidence/phase5/webauthn-budget-fixture.json src/Service/Phase5BudgetReportService.php docs/evidence/phase5/performance-budgets.md
git commit -m "perf(passkeys): measure webauthn.ceremony_p95 against the D11 2s budget (PASS)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

(Stage the exact service file you edited if the report probe lives elsewhere; never `git add` a directory containing another session's files.)

---

### Task 18: Flip the threat-model fixtures + advance the ledger + evidence index

Evidence bookkeeping, enforced by tests: `ThreatModelIndexTest` requires every `implemented` fixture to name an existing test file; `Phase5EvidenceMapTest` guards the ledger. States are bumped in the commit that carries their evidence — everything cited below landed in Tasks 1–17.

**Files:**
- Modify: `docs/phase5/threat-models/fixtures.json` (five status flips)
- Modify: `docs/superpowers/plans/2026-07-01-phase5-foundation-remainder.md` (`GA-DOD-13` row; sync the embedded TM-ID mirror rows if that file repeats the fixture JSON — `grep -n "TM-ID-05" docs/superpowers/plans/2026-07-01-phase5-foundation-remainder.md`)
- Create: `docs/evidence/phase5/passkeys.md`

- [ ] **Step 1: Flip the five fixtures** in `docs/phase5/threat-models/fixtures.json`:

```json
{ "id": "TM-ID-05", "model": "identity-account-takeover.md", "fixture": "reused and cross-user WebAuthn challenges rejected", "owner": "Inc7", "status": "implemented", "test": "tests/Integration/Core/AppPasskeyLoginTest.php" },
{ "id": "TM-ID-06", "model": "identity-account-takeover.md", "fixture": "credential add without fresh reauth factor rejected", "owner": "Inc7", "status": "implemented", "test": "tests/Integration/Core/AppPasskeyRegistrationTest.php" },
{ "id": "TM-ID-07", "model": "identity-account-takeover.md", "fixture": "mismatched origin/rpIdHash rejected in ceremony", "owner": "Inc7", "status": "implemented", "test": "tests/Unit/Auth/WebAuthnPolicyTest.php" },
{ "id": "TM-ID-08", "model": "identity-account-takeover.md", "fixture": "non-increasing counter logs risk event without auto-lockout", "owner": "Inc7", "status": "implemented", "test": "tests/Integration/Core/AppPasskeyLoginTest.php" },
{ "id": "TM-ID-09", "model": "identity-account-takeover.md", "fixture": "last-usable-method removal blocked; provider disable lists sole-method accounts", "owner": "Inc7", "status": "implemented", "test": "tests/Integration/Core/AppPasskeyRegistrationTest.php" }
```

(Preserve the file's exact existing key order/format — edit only `status` and add `test`. TM-ID-09's second clause is capability-tested via `OAuthIdentityRepository::soleMethodAccounts`; the Inc 8 UI handoff is recorded in Task 19's status update.)

- [ ] **Step 2: Advance `GA-DOD-13`** in the foundation-remainder ledger — replace the `"state": "R1", "evidence": []` row with:

```json
{ "id": "GA-DOD-13", "gate": "A", "workstream": "P5-11", "title": "Passkey registration/login/list/remove/step-up/fallback/recovery scenarios on supported browsers", "state": "R4", "evidence": ["tests/Unit/Auth/WebAuthnPolicyTest.php", "tests/Integration/Core/AppPasskeyRegistrationTest.php", "tests/Integration/Core/AppPasskeyLoginTest.php", "tests/Integration/Core/AppPasskeyRecoveryTest.php", "tests/browser/passkeys.spec.ts", "docs/evidence/phase5/passkeys.md", "docs/runbooks/passkeys.md"], "notes": "R5 at staged enablement (§13.1 step 9); privileged-MFA enforcement and usernameless remain Gate B" }
```

(The runbook lands in Task 19 — Tasks 18+19 may share one commit if `Phase5EvidenceMapTest` insists evidence paths exist; check its assertions and, if it verifies file existence, land Task 19's runbook first or merge the commits.)

- [ ] **Step 3: Write `docs/evidence/phase5/passkeys.md`** — the P5-11 requirement→evidence index:

```markdown
# P5-11 Passkeys — Gate A evidence index (Increment 7)

Recorded <date of landing>. Flag `passkeys` remains **default OFF**; enablement follows §13.1 step 9.

| §9 scenario / requirement | Evidence |
| --- | --- |
| Registration validates challenge/origin/RP/signature/UP/UV; replay, cross-account, wrong origin/RP, duplicate, stale rejected | `WebAuthnPolicyTest` (protocol negatives), `AppPasskeyRegistrationTest` (TM-ID-05/06/07 over HTTP) |
| Sign-in: correct account; unknown credential/altered signature fail without account info | `AppPasskeyLoginTest` (fixed 8-slot decoy shape, stable decoys, generic errors) |
| Synced counter → risk signal, no auto-ban (TM-ID-08, decision #30) | `AppPasskeyLoginTest::test_non_increasing_counter_signs_in_and_writes_a_risk_audit_row` |
| Removal: last-usable-method + final-owner blocked (TM-ID-09, F5 Inc-7 path) | `AppPasskeyRegistrationTest` last-method/owner tests |
| Fallback: password/TOTP/recovery journeys with passkeys enrolled; lost-authenticator recovery | `AppPasskeyRecoveryTest`; no-JS TOTP journey `tests/browser/totp.spec.ts` |
| Supported-browser + authenticator evidence | `tests/browser/passkeys.spec.ts` — Chromium CDP virtual authenticator, desktop+mobile, PNGs `docs/evidence/browser/*/passkeys-0*.png` |
| Accessibility | axe scan of the passkey panel inside `passkeys.spec.ts` |
| D11 budget `webauthn.ceremony_p95` ≤ 2000 ms | `docs/evidence/phase5/performance-budgets.md` (MEASURED PASS) |
| Audit + security notification on add/remove/login/anomaly | `moderation_log` assertions across the three App suites; fail-closed Mailer notices |
| RP-ID/origin policy (A5/D6) incl. production hard-refuse | `WebAuthnPolicyTest` RelyingParty cases |

Suite counts at landing: <fill from the Task 20 runs>.
```

- [ ] **Step 4: Run the guards, then commit**

```bash
vendor/bin/phpunit tests/Unit/Core/ThreatModelIndexTest.php tests/Unit/Core/Phase5EvidenceMapTest.php
git add docs/phase5/threat-models/fixtures.json docs/superpowers/plans/2026-07-01-phase5-foundation-remainder.md docs/evidence/phase5/passkeys.md
git commit -m "docs(passkeys): TM-ID-05..09 implemented with test paths; GA-DOD-13 -> R4; evidence index

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 19: Runbook + SCHEMA/STATUS reconciliation + owner-decision surfacing

**Files:**
- Create: `docs/runbooks/passkeys.md`
- Modify: `SCHEMA.md` (§5A row for `0051`: "inert, no app reads/writes" → active as of Inc 7; add a §9 changelog line; bump the version header one minor — read the current value first)
- Modify: `PHASE_5_STATUS.md` (status paragraph, suite numbers from Task 20, Inc 8 handoff, decisions-to-record)

- [ ] **Step 1: Write `docs/runbooks/passkeys.md`** (mirroring `docs/runbooks/topic_workflow.md`'s heading shape):

```markdown
# Runbook — Passkeys (WebAuthn, P5-11)

## What the flag gates
`passkeys` (default **OFF**) gates: the Passkeys panel on /settings/security, the
JSON ceremony endpoints (/settings/security/passkeys/*, /login/passkey/*), the
login-page "Sign in with a passkey" affordance, and /assets/passkeys.js. Password,
OAuth, TOTP, and recovery sign-in are independent of this flag (decision #31).

## Roll back (disable)
Merge `"passkeys": false` into the `features` settings JSON (do not clobber other
keys). Ceremonies stop with 404s; **credential rows are preserved** for later
re-enablement (PHASE_5_PLAN §13.2). No schema action. Sessions created via
passkey sign-in remain valid ordinary sessions.

## Re-enable
Merge `"passkeys": true`. Existing credentials work again immediately — they are
bound to the RP ID, not to the flag.

## Staged rollout (§13.1 step 9 — staff pilot)
1. Enable on a staging copy; run `tests/browser/passkeys.spec.ts` against it.
2. Enable in production; announce to owners/staff only; watch `moderation_log`
   actions `passkey_registered`/`passkey_login` and the error rate.
3. Announce to privileged users, then all members. Do NOT enable any privileged-MFA
   enforcement — that is Gate B (A7 stays off).

## Lost authenticator / account recovery
Passkeys augment, never replace (D7): the member signs in with password (or
password + TOTP/recovery code), removes the lost passkey on /settings/security,
and enrolls a new one. Operators never need to touch the database; if a member
lost every factor, the standard password-reset path applies. Removal of the last
usable sign-in method is blocked server-side; the final owner additionally sits
behind LastOwnerGuard — a stranded-owner state cannot be reached via this surface.

## Counter-anomaly review policy (decision #30)
A non-increasing signCount writes `moderation_log` action `passkey_counter_anomaly`
and telemetry `passkey.counter_anomaly`, and the sign-in still succeeds — synced
passkeys (iCloud/Google) legitimately report zero or non-monotonic counters.
Review: check the account's recent sessions and audit rows; if compromise is
suspected, advise the member to remove the credential and rotate their password.
Never auto-revoke on the counter alone.

## RP ID / domain changes
RP ID = the `APP_URL` host by default, or `WEBAUTHN_RP_ID` when set (must be the
host or a parent domain). Changing the registrable domain invalidates every
passkey — follow `docs/phase5/canonical-origin-and-rp-id.md` §5 (pre-announce,
freeze enrollment, cut over, members re-enroll via fallback sign-in). A
subdomain-only move keeps passkeys working **only if** `WEBAUTHN_RP_ID` was set
to the registrable domain before enrollment began. Production requires HTTPS:
ceremonies hard-refuse a non-localhost `http://` APP_URL.

## Monitoring & known limits
- Audit actions: passkey_registered / passkey_renamed / passkey_revoked /
  passkey_login / passkey_counter_anomaly (moderation_log, target_type user).
- Rate limits: passkey_challenge 30/15 min (per email), passkey_login 10/15 min
  (per email subject, cleared on successful passkey login), management via
  mfa_settings 10/15 min.
- Accounts with TOTP enrolled require user-verified (screen-lock) assertions.
- Usernameless/discoverable sign-in and privileged-MFA enforcement are Gate B.
- Inc 8 handoff: before disabling an OAuth provider, list sole-method accounts
  via OAuthIdentityRepository::soleMethodAccounts() (UI arrives with provider registry).

## Acceptance evidence
See docs/evidence/phase5/passkeys.md (tests, browser PNGs, budget row).
```

- [ ] **Step 2: Update `SCHEMA.md` + `PHASE_5_STATUS.md`**

- SCHEMA §5A `0051` note: append "Animated by Increment 7 (2026-07-XX): PasskeyService + WebAuthn* repositories read/write these tables; RP ID/origins remain config-derived (A5)." Changelog: "vX.Y — 0051 tables activated by Inc 7 passkeys (no schema change)". Bump the header version by one minor.
- PHASE_5_STATUS: extend the top status line ("Increment 7 (P5-11 passkeys) landed <date> behind the dark `passkeys` flag — enrollment/sign-in/step-up/recovery with CDP browser evidence; GA-DOD-13 at R4; SLICE-TOTP retrofit paid"), add the suite/budget numbers from Task 20, and record under a "Decisions recorded with Inc 7" heading the eight decisions-to-record from this plan's header verbatim — flagging **RP-ID resolution (env override, full-host default)** for explicit owner acknowledgment as an A5 implementation refinement.

- [ ] **Step 3: Commit**

```bash
git add docs/runbooks/passkeys.md SCHEMA.md PHASE_5_STATUS.md
git commit -m "docs(passkeys): operator runbook, SCHEMA 0051 activation note, status + owner-decision record

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 20: Verification gates (nothing ships on assertion — DESIGN §13)

- [ ] **Step 1: Full PHPUnit, fresh + reused schema**

```bash
RB_TEST_FRESH=1 vendor/bin/phpunit --no-progress 2>&1 | tail -3
vendor/bin/phpunit --no-progress 2>&1 | tail -3
```

Expected: green twice (no seed migrations were added, but the two-run habit stands). Record the counts for PHASE_5_STATUS.

- [ ] **Step 2: Browser + a11y + budgets + upgrade rehearsal**

```bash
cd tests/browser && npm run evidence && npm run a11y && npx playwright test passkeys.spec.ts totp.spec.ts && cd ../..
APP_ENV=testing php bin/console verify:phase5-budgets
APP_ENV=testing DB_DATABASE=retroboards_e2e php bin/console verify:upgrade --force
```

Expected: baselines unchanged (53/1, 14), new specs green on both projects, `webauthn.ceremony_p95` MEASURED PASS, `verify:upgrade` 17/17 (no new migration).

- [ ] **Step 3: Invariant spot-checks**

```bash
grep -rn "private" src/Security/WebAuthn/ | grep -i "key" | grep -v "public" | head   # no private-key handling in src/
grep -rn "onclick\|<script>" templates/ public/assets/passkeys.js | grep -v "src="    # CSP: nothing inline
git log --oneline main..HEAD | head -30                                               # every commit scoped + footered
```

- [ ] **Step 4: Update `PHASE_5_STATUS.md` with the recorded numbers (amend Task 19's section), then hand off**

Follow `superpowers:finishing-a-development-branch` for the merge decision. The branch is deploy-dark end-to-end; merging does not change any default behavior.

---

## Plan self-review (spec coverage)

| P5-11 Gate A requirement (PHASE_5_PLAN §142-145, §9, program plan Inc 7) | Task |
| --- | --- |
| WebAuthn protocol core: CBOR/COSE/clientData/authenticatorData, ES256 mandatory | 3, 4, 5, 8 |
| RP-ID/origin checks from canonical APP_URL; production HTTPS hard-refuse (A5/D6) | 6 |
| Registration + credential naming/list/removal | 11, 12 |
| Fresh-reauth before credential add/remove (TM-ID-06, #26) | 10, 11, 12 |
| Sign-in (email-first, allowCredentials) integrated with AuthController; no enumeration | 13 |
| Step-up: FACTOR_PASSKEY behind the ReauthGate API (F7) | 10, 11 |
| Recovery on the TOTP/recovery path; fallback matrix; lost-authenticator journey | 1 (B1 predecessor), 15 |
| LastOwnerGuard final-method block (F5 Inc-7 path) + last-usable-method (TM-ID-09) | 12 |
| Synced-counter anomaly = risk signal, never lockout (TM-ID-08, #30) | 8, 13 |
| Attestation accepted but never trusted (#29) | 8 |
| Rate limits + audit + security notifications; revoke other sessions after add/remove credential changes | 11, 12, 13 |
| Deploy-dark flag + route 404 regressions + JS asset gating | 11, 12, 13, 14 |
| CDP virtual-authenticator browser evidence + axe (desktop+mobile) | 16 |
| D11 `webauthn.ceremony_p95` budget | 17 |
| TM-ID-05…09 fixtures flipped with real test paths; GA-DOD-13 advanced | 18 |
| Runbooks (pause/recovery/counter-review/domain-change) + SCHEMA/STATUS reconciliation | 19 |
| Account export/delete passkey metadata | 15 |
| Out (Gate B, asserted absent): usernameless, passkey-first, privileged-MFA enforcement, migration 0074 | header |

Known intentional deviations are the eight "Decisions to record" items in the header; the executor surfaces them via Task 19.
