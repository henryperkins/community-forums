# Phase 3 Security And Privacy Review

**Date:** 2026-06-30
**Status:** Pending formal review signoff

## Required Review Areas

| Area | Status | Notes |
|---|---|---|
| Uploads/media ACLs | Pending | Must cover image uploads, expanded-file quarantine, private board/DM media, deleted/held post media, and cache headers. |
| Anti-abuse/rate limits | Pending | Must cover login, register, post, DM, upload, composer preview, webhook/email tests, and announcement limits. |
| TOTP/recovery | Pending | Must cover enrollment, challenge, recovery-code storage/use, lockout/rate limits, and dark/default posture. |
| OAuth | Pending | Must cover state, PKCE, nonce, provider collision handling, banned/refused accounts, and last-login-method unlink guard. |
| API tokens | Pending | Must cover mint/show-once, hash-only storage, scope checks, revoke, audit, and dark kill switch. |
| Webhooks/secrets | Pending | Must cover service-secret storage, signing, rotation, egress controls, retries, dead letters, and circuit breaker. |
| Email suppression/unsubscribe | Pending | Must cover login-free signed unsubscribe, resubscribe, suppression cascade, digest/instant/system mail behavior, and domain send-blocking. |
| Server drafts retention | Pending | Must cover owner-only access, 90-day retention, quota, purge worker, export, delete, and conflict handling. |
| Private board/DM search boundaries | Pending | Must cover read gates, hidden/deleted/pending exclusions, snippets, notifications, and archive behavior. |
| Dark public-runtime boundary | Pending | Must cover ADR 0011, `server_extensions` dark default, sandbox fail-closed behavior, worker-only execution, and no web-request execution. |

## Acceptance Rule

Phase 3 cannot close with any open high or critical finding. Medium or lower
findings require an owner, disposition, and target follow-up.

## Signoff

| Signoff | Name | Date | Signature |
|---|---|---|---|
| Security/privacy reviewer | Pending | Pending | Pending |
