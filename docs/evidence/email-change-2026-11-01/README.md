# Verified email changes — 2026-11-01

Verified email changes complete the email portion of ADR 0014 behind the
default-off `email_change` flag. Account settings exposes a separate form when
enabled. Existing password, session-bound passkey or exact connected-provider
proof precedes new-inbox verification; the old contact remains active until the
signed-in owner submits confirmation. Completion revokes other sessions and old
recovery links, queues an old-address security notice, suppresses ordinary queued
mail for the old contact and writes a privacy-preserving audit event.

Scope and operator boundaries: [ADR 0055](../../adr/0055-verified-email-changes.md)
and [runbook](../../runbooks/email_changes.md). No migration or dependency change
is required. Other notification, username and branding carryovers stay separate.

## Qualification

| Check | Result |
| --- | --- |
| Final full PHP suite | [3,564 tests, 33,898 assertions, zero failures, one intentional migration-rehearsal skip](checks/php-full.log) |
| Domain, worker and recovery regression | [95 tests / 586 assertions passed](checks/domain-recovery-green.log) |
| Native PDO prepares | [36 tests / 233 assertions passed](checks/domain-recovery-native-green.log) |
| OAuth/HTTP regression including retained-intent throttle | [42 tests / 241 assertions passed](checks/oauth-rate-green.log) |
| Updated feature inventories | [39 tests / 367 assertions passed](checks/inventory-green.log); final full suite covers the rest |
| Browser matrix | 29 distinct passing configurations and three deliberate virtual-authenticator exclusions; table below |
| Generated assets | [Build](checks/asset-build.log) and [freshness](checks/assets-check.log) pass; Imladris flag-classification [manifest regenerated](checks/imladris-build.log) without stylesheet changes |
| Asset / Worker tests | [35 passed](checks/assets-tests.log) |
| Worker dry run | [Wrangler 4.153.0 succeeds without upload](checks/wrangler-dry-run.log) |
| Review | [Findings and corrections](review.md); follow-up code review found no remaining concrete blocker |

The first full run found the old admin search expectation and missing imported
flag classification. Updating their bookkeeping and generated classification
hash fixed both; rendered stylesheet bytes did not change. A subsequent full
suite passed before the final OAuth throttle refinement; the final receipt above
includes that refinement. Repeated qualification was limited to changed inputs
and identified failures.

## Browser evidence

| Run | Result | Receipt |
| --- | --- | --- |
| Chromium core, desktop/mobile | 11 passed, one deliberate mobile virtual-WebAuthn exclusion | [Log](checks/browser-chromium.log) |
| WebKit core, desktop/mobile | 10 passed, two deliberate virtual-WebAuthn exclusions | [Log](checks/browser-webkit.log) |
| Chromium provider/operator additions | Four passed | [Log](checks/browser-chromium-additions.log) |
| WebKit provider/operator additions | Four passed | [Log](checks/browser-webkit-additions.log) |
| Final affected provider case, both viewports | Two Chromium and two WebKit repeats passed after throttle refinement; included in the 29 unique configurations | [Chromium](checks/browser-chromium-provider-final.log), [WebKit](checks/browser-webkit-provider-final.log) |

The 43 captures under `chromium/` and `webkit/` cover desktop 1280×800 and mobile
390×844, with an additional 320px no-JavaScript confirmation view. Native forms
work with JS disabled. The passwordless passkey case uses Chromium credential
APIs and a virtual authenticator, including required-field validation before
the ceremony. Axe checks found no serious/critical violations on the affected
member and operator screens; overflow assertions pass. Representative desktop,
mobile and narrow captures were visually inspected.

Tests cover safe validation, old-contact preservation, pending/cancellation,
replacement/expiry/foreign links, read-only GET, owner-only POST, session refresh,
other-session revocation and feature rollback. Provider navigation is intercepted
locally; no real external provider call occurs. Browser tests stop at the native
return form, while HTTP tests perform the fake-provider exchange and exact
identity/session checks, including Apple's missing-member-cookie return.

Fixtures refuse production/shared databases and use only private
`retroboards_e2e_emailchange_20261101`; PHP uses private test databases. Mail is
synthetic ArrayMailer capture and outbox state, not SMTP or mailbox receipt.
Screens are emulated, not physical-device or physical-authenticator evidence.
Synthetic query tokens and CSRF values are redacted in copied browser logs.

## Security and source identity

[Review notes](review.md) link the initial RED receipts and the tested fixes for
provider cookie behavior, old-inbox recovery races, cross-account destination
limits and retained-intent replay. Real second-connection/subprocess tests prove
the recovery interleavings and unique-index rollback. Existing NULL-address
recovery/registration links retain compatibility until invalidation or expiry.

[Qualified file hashes](qualified-files.json) identify application, test, config
and documentation inputs relative to base `f269c9a0`. Browser proof for unchanged
rendering is reused; affected provider captures were refreshed after its final
rate-policy change. The generated asset version is `67ba8434286ff7e6`, staging
57 files while retaining the three previously verified deployment pins.

The separate [dependency diagnostic](dependencies/diagnostic.md) confirms that
the published lock and GitHub graph contain patched Undici/Zod versions, while
alerts 60/63 still name their old versions. It documents GitHub's supported
manual refresh and the lack of a supported refresh API. No alert was dismissed
and no dependency/security setting was changed.

## Publication and live proof

Publish the qualified application once under ADR 0051. Post-publication source,
automatic Worker/Cloud Run builds, service/job image parity, health, asset bytes
and effective default-dark routing receipts belong to the release artifacts at
`/home/ubuntu/.local/state/community-forums/email-change-20261101/production/`.
Recording these receipts does not require another bookkeeping publication.
Live passive checks do not change a production member's contact or activate the
default-off subsystem, and cannot establish real inbox receipt.
