# Complete composer draft recovery — historical qualification

This is a curated publication of the **2026-10-24 local evidence**, prepared on
2026-10-31. Original diagnostics and ledgers remain unchanged in the private
recovery archive. Browser reports below are explicitly derived summaries that
exclude inherited environments and raw diagnostic content. Their original
SHA-256 identities and every included/omitted file are recorded in
[publication-manifest.json](publication-manifest.json).

The current combined release is qualified separately in
[the 2026-10-31 release evidence](../draft-mobile-release-2026-10-31/README.md).
These historical results are not relabeled as a test of the new release.

## Original audit

The original Mobile Web UX Runner audit on 2026-10-24, based on commit
`960f7f0f27b15ca7def0e815ce19912077e9d249`, confirmed three P2 defects:
new-topic recovery lost title/anonymity, conflict choices hid both copies,
and Drafts Resume did not reliably open the actual composer. The audit used
Chromium 155.0.8059.12 and desktop Playwright WebKit 26.6 with disposable
MariaDB fixtures. It established proxy/browser evidence only; no physical
phone result was claimed. The exact original audit identity is recorded in
this capsule's publication manifest; the full original audit, ledger, diagnostic
history and failed-before-fix captures remain privately preserved.

The authorized remediation retains complete body/wrapper snapshots, presents
both conflict copies and field differences, checks destination/ownership on
Resume, and protects newer work through checked draft identity/revision and
completion receipts. [ADR 0052](../../adr/0052-complete-composer-draft-recovery.md)
and the [server drafts runbook](../../runbooks/server_drafts.md) specify the
behavior.

## Historical results

| Check | Recorded result | Proof |
| --- | --- | --- |
| Full PHP suite after the final retry correction | 3,371 tests, 29,834 assertions, one existing dedicated migration skip; pass | [Original log](php-final-retry.log) |
| Browser matrix | 108 passed, four explicit WebKit harness skips, zero failures/flaky results; 64 new recovery cases passed | [Sanitized result summary](browser-final.summary.json) |
| Direct unchanged rich retry-token assertions | Two passed, one per engine | [Sanitized result summary](browser-identity.summary.json) |
| Two-connection conflicts and bounded retry probes | Passed; controlled errors and repeatable-read setup | [Receipt](concurrency-final.json), [reproduction](../../../tests/browser/draft-concurrency.php) |
| Generated assets | Current for that historical candidate; three served assets matched | [Check](assets-identity-check.log), [served hashes](served-assets.json) |
| Asset/Worker tests | 35 passed | [Original log](worker-assets-identity.log) |
| Independent source review | No remaining critical/important issue in the reviewed scope | [Review identity and limits](review.json) |

[Candidate source/asset hashes](candidate.json) and
[actual browser versions](browser-versions.json) preserve the original identity.
The historical source/base and some generated assets predate later mobile and
theming work; missing superseded bundles are retained in recovery archives.

## Representative accepted captures

| Behavior | Chromium | WebKit |
| --- | --- | --- |
| Complete recovered fields | [Capture](browser-accepted/chromium/source-restored-fields.png) | [Capture](browser-accepted/webkit/source-restored-fields.png) |
| Named copies and field differences | [Capture](browser-accepted/chromium/conflict-title-and-anonymous.png) | [Capture](browser-accepted/webkit/conflict-title-and-anonymous.png) |
| Server Resume without JavaScript | [Capture](browser-accepted/chromium/source-nojs-resume.png) | [Capture](browser-accepted/webkit/source-nojs-resume.png) |

## Limits and preservation

Ordinary saves, conflicts, Resume and sends used the real PHP/backend against
synthetic local fixtures. Delayed enhancement, offline navigation, lost responses,
and injected concurrency errors were controlled tests. Native mobile keyboard,
autofill, browser chrome, storage eviction and assistive technology were not
qualified. Four WebKit harness skips remain explicit, and no skip or failed
intermediate candidate is counted as a pass. No deployment occurred during this
historical task.

The raw original reports contain inherited process environments. They are
excluded from publication; the derived summaries retain only test outcomes and
source identities. Every omitted intermediate log, duplicate screenshot and
full runner ledger remains in the verified private archive. No original file
was edited or deleted while preparing this capsule.
