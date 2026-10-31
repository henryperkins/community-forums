# Mobile UX remediation — historical qualification, 2026-10-25

This curated capsule preserves the accepted **2026-10-25 local results** while
keeping original diagnostics and ledgers unchanged in the private recovery
archive. Browser summaries are derived records with process environments,
configuration and raw diagnostic output omitted. See
[the publication manifest](publication-manifest.json) for original source hashes,
transformation details and the complete omitted-file inventory.

The current combined candidate has separate
[2026-10-31 release evidence](../draft-mobile-release-2026-10-31/README.md).
This capsule does not claim that its historical run tested the later release.

## Scope and candidate identity

The follow-up preserved complete draft snapshots, conflict comparisons and usable
Resume from the [earlier remediation](../draft-recovery-2026-10-23/README.md), and
added bounded scrolling account actions, reliable WebKit pointer-to-keyboard
entry, exact resumed-draft completion cleanup, and a 44px Create topic target.
It refreshed only design-context metadata, not the visual system.

The [tested mobile candidate](candidate.json) was based on `960f7f0f`.
The [integrated candidate](candidate-integrated.json) was based on `4162a90a`.
The incoming subscription change was separately checked, and the relevant mobile
inputs were unchanged across that integration. Neither historical task committed
or deployed this working-tree candidate. The implementation contracts are in
[ADR 0052](../../adr/0052-complete-composer-draft-recovery.md),
[COMPOSER](../../../COMPOSER.md), and
[the runbook](../../runbooks/server_drafts.md).

## Historical results

| Check | Recorded result | Proof |
| --- | --- | --- |
| Final integrated PHP suite | 3,383 tests, 29,951 assertions, one dedicated migration-rehearsal skip; pass | [Original log](php-integrated.log) |
| Affected browser matrix | 132 passed, four explicit WebKit skips, zero failures/flaky results; one uninterrupted 136-case run | [Sanitized summary](browser-final.summary.json) |
| Incoming subscription browser check | One passed | [Sanitized summary](browser-integrated.summary.json) |
| Original audit browser builds/roles | Three matching probes passed | [Receipt](matching-browser-probes.json) |
| Generated assets and Worker tests | Current for that candidate; 35 tests passed | [Asset check](check-assets.log), [test log](test-assets.log) |
| WebKit focus investigation | Pointer controls identify the decorative-summary hit target as the observed cause | [Control receipt](webkit-focus-pointer-control.json) |
| Design metadata | Narrative unchanged; valid Messages target | [Receipt](design-context.json) |

The matrix includes 64 core recovery, 10 server-draft, 38 thread history/recovery,
14 resumed-completion and 10 account-menu cases. Chromium passed 68; WebKit
passed 64 and skipped four Chromium CDP/error-history harness cases. Browser
versions were Chromium 149.0.7827.55 and Playwright WebKit 26.5 on Linux. The
matching-audit probes separately used Chromium 155.0.8059.12 and WebKit 26.6.

## Representative accepted captures

| Behavior | Chromium | WebKit |
| --- | --- | --- |
| Admin actions at doubled text | [Capture](final-attachments/chromium/admin-account-actions-fit-and-scroll-at-narrow-and-short-viewports/account-32px.png) | [Capture](final-attachments/webkit/admin-account-actions-fit-and-scroll-at-narrow-and-short-viewports/account-32px.png) |
| Native no-JavaScript menu entry | [Capture](final-attachments/chromium/native-account-menu-remains-keyboard-reachable-after-pointer-opening-without-JavaScript/native-menu.png) | [Capture](final-attachments/webkit/native-account-menu-remains-keyboard-reachable-after-pointer-opening-without-JavaScript/native-menu.png) |
| Rich server Resume completion | [Capture](final-attachments/chromium/rich-unchanged-server-Resume-sends-once-and-removes-the-completed-copy/completed-composer.png) | [Capture](final-attachments/webkit/rich-unchanged-server-Resume-sends-once-and-removes-the-completed-copy/completed-composer.png) |

Raw geometric measurements for member, moderator and admin in both engines are
retained alongside these captures and indexed in the publication manifest.

## Performance and physical-device limits

The controlled local experiment isolated transport compression: median LCP was
6,852ms uncompressed versus 1,460ms compressed across three alternating pairs.
This is a lab transport result, not production LCP or physical-phone performance.
It used the immediate predecessor CSS; its final 44px target change added 45 bytes.
[Summary and boundaries](performance-summary.json), [raw pairs](performance-paired.json),
and [historical live compression receipt](live-compression.json) preserve that
separation. Editor readiness remained variable and was not accepted as a pass.

All ten reserved physical-device checks remain **NOT READY / not run** in the
[device handoff](device-checks.md). Native software keyboards, safe areas,
VoiceOver/TalkBack, browser chrome and storage lifecycle are not established by
browser emulation. The [ledger check](runner-check.json) is schema-valid but
incomplete and retains historical failures under its target-level supersession
rules. The matching browser probes do not rewrite those historical failures.

## Preservation and reproduction

Original recovery records, full runner history, failed/intermediate runs and
redundant captures remain privately archived. All source originals were kept
unchanged; only allowlisted historical results and selected accepted proof are
published here. Browser configuration/environment snapshots are excluded.

To repeat the behavior checks, prepare an isolated database matching the draft
fixture guard, run the standard seed plus `thread-view-fixture.php`, and use
`playwright.mobile-remediation.config.ts`. The original local server and temporary
databases were cleaned up; their former loopback URL is not a usable preview.
