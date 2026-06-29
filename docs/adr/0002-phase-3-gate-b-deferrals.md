# ADR 0002: Phase 3 Gate B deferrals

**Date:** 2026-06-28

**Status:** engineering deferral record; requires product-owner acceptance for
formal phase close.

**2026-06-29 reconciliation:** later release-train slices resolved several
items in their destination phases. This ADR remains the historical Phase 3
deferral record, but those resolved items must not be double-counted as still
open Phase 3 work.

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
| Restricted non-image attachments | Engineering + Security | Phase 4 P4-05 | **Resolved in destination slice:** PDF/text-family uploads now have deploy-dark scanner/quarantine, access, download-only, and cleanup coverage behind `expanded_files`. | Keep the feature dark until browser/no-JS, scanner-outage, and runbook evidence are attached. |
| Retro skin, guarded custom CSS, logo variants | Product + Engineering | Phase 5 theme/package work, unless pulled into Phase 4 by signed carryover | Advanced theming needs safe-mode, validation, anti-phishing review, and rollback beyond Gate A color/name/logo controls. | Core branding supports safe colors/name/logo reset; do not expose arbitrary CSS. |
| TOTP, recovery codes, reauthentication, security notifications | Product + Security | Phase 5 identity work | **Partially resolved in destination slice:** opt-in TOTP and hash-only recovery codes now exist as the passkey fallback prerequisite. Broader privileged reauth/security-notification policy remains Phase 5 identity work. | Do not require ordinary users to enroll by default; passkey enforcement must keep the fallback evidence green. |
| Account deactivation, avatar upload/Gravatar, bookmark folders, custom profile fields | Product + Engineering | Phase 4 P4-13 for profile/personal organization; Phase 5 if tied to identity/governance | **Partially resolved in destination slices:** local avatar upload/removal and signature moderation are implemented behind `profile_media`; board folders and saved feed filters are implemented behind their own flags. Deactivation/reactivation/export/delete, bookmark folders, and custom profile fields remain open. | Keep shipped portions dark until browser/a11y/runbook evidence is attached; do not expose the remaining account/profile surfaces without policy and privacy review. |
| Internal hooks/plugins, webhooks, admin API tokens | Product + Platform/Security | Phase 5 ecosystem foundation | **Trusted prerequisites resolved in destination slice:** service secrets, read-only API tokens, webhook delivery, and first-party hook producers are deploy-dark Phase 5 B2 prerequisites. Public plugin runtime, sandbox, SDK, and third-party PHP execution remain Gate B. | Keep public/untrusted PHP execution disabled until the sandbox runtime is implemented and adversarially verified. |

## Consequences

- Phase 4 and Phase 5 entry gates must treat unresolved rows as explicit
  carryovers, not as accepted Phase 3 foundations.
- Rows marked resolved or partially resolved above are destination-slice evidence,
  not a retroactive Phase 3 acceptance claim.
- Schema rows or columns that merely foreshadow deferred work are not evidence of
  shipped behavior.
- Gate A rollback and evidence remain scoped to implemented features:
  preferences, composer/local drafts, image uploads, anti-abuse, branding, SEO,
  product tour, and operations.
