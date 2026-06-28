# ADR 0002: Phase 3 Gate B deferrals

**Date:** 2026-06-28

**Status:** engineering deferral record; requires product-owner acceptance for
formal phase close.

## Context

`PHASE_3_PLAN.md` allows Phase 3 to close only when Gate A and Gate B are accepted
or every omitted Gate B item has an explicit deferral with owner, rationale, risk,
and destination. The current codebase implements the Gate A polish/hardening
slice. The larger Gate B trust, platform, and account surfaces are not present
except for inert schema scaffolding such as `users.avatar_path`.

## Decision

The following Gate B items are deferred out of Phase 3. They remain obligations
for their destination phases and must be re-scoped again if those phases choose
not to ship them.

| Item | Owner | Destination | Rationale | Risk / control |
|---|---|---|---|---|
| Moderation appeals | Product + Engineering | Phase 4 Milestone 0 carryover | Needs policy decisions around eligible actions, restoration, notifications, and staff queue UX. | Do not expose appeal links or tables until the full authorization/audit model ships. |
| Server-side draft sync | Product + Engineering | Phase 7 P7-05, or earlier only by signed carryover | Gate A local drafts satisfy reload/retry needs without storing draft bodies server-side. Cross-device/offline conflict handling belongs with later offline work. | Keep drafts browser-local and context/user isolated; do not add partial sync endpoints. |
| Restricted non-image attachments | Engineering + Security | Phase 4 P4-05 | Requires allowlist, scanner/quarantine policy, safe download headers, and scanner-outage behavior beyond image re-encoding. | Image pipeline remains accepted; non-image upload UI stays absent. |
| Retro skin, guarded custom CSS, logo variants | Product + Engineering | Phase 5 theme/package work, unless pulled into Phase 4 by signed carryover | Advanced theming needs safe-mode, validation, anti-phishing review, and rollback beyond Gate A color/name/logo controls. | Core branding supports safe colors/name/logo reset; do not expose arbitrary CSS. |
| TOTP, recovery codes, reauthentication, security notifications | Product + Security | Phase 5 identity work | Strong-auth flows need recovery/lockout policy and privileged-action integration. | Password/email/OAuth/session controls remain Phase 2 behavior; no inert 2FA UI. |
| Account deactivation, avatar upload/Gravatar, bookmark folders, custom profile fields | Product + Engineering | Phase 4 P4-13 for profile/personal organization; Phase 5 if tied to identity/governance | These are profile/account polish, not required for Gate A composer/media/branding hardening. | Existing monogram/OAuth avatar behavior remains; `users.avatar_path` is inert until a full media-backed avatar flow exists. |
| Internal hooks/plugins, webhooks, admin API tokens | Product + Platform/Security | Phase 5 ecosystem foundation | Requires capability model, secret handling, event delivery, disable-on-error, compatibility checks, and audit. | No extension/API/webhook admin UI or endpoints are exposed. |

## Consequences

- Phase 4 and Phase 5 entry gates must treat these as explicit carryovers, not as
  accepted Phase 3 foundations.
- Schema rows or columns that merely foreshadow deferred work are not evidence of
  shipped behavior.
- Gate A rollback and evidence remain scoped to implemented features:
  preferences, composer/local drafts, image uploads, anti-abuse, branding, SEO,
  product tour, and operations.
