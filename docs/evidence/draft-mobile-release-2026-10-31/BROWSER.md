# Focused browser qualification — 2026-10-31

The isolated combined draft/mobile/theming candidate passed all **54 selected
cases**, with no failures, skips or flaky results. No application or test source
was modified during this qualification. [Input hashes](browser-inputs.json)
identify the application and test inputs; publication is a separate step.

| Run | Actual result | Scope |
| --- | --- | --- |
| Draft/mobile integration | 50 passed, 190.2 seconds | 25 Chromium and 25 WebKit cases |
| Theming shared-asset regression, Chromium | 2 passed, 6.6 seconds | Mobile palette preview and independent asset removal |
| Theming shared-asset regression, WebKit | 2 passed, 8.8 seconds | Same two mobile cases |

Actual browser builds were Chromium 149.0.7827.55 and Playwright WebKit 26.5 on
Linux. The ordinary viewport was 390×844; account-menu checks also exercised
320×740 and 844×390 with normal/doubled root text, all three roles, native
keyboard traversal and no-JavaScript behavior. Create-topic target checks used
320/360/390/430 CSS-pixel widths. This is browser emulation, not physical-phone,
native OS text scaling, VoiceOver/TalkBack or production performance proof.

## Selection and retained coverage

[Exact arguments](selection.json) and the [50 draft/mobile titles](selected-tests.txt)
record the bounded selection. It covers source/rich complete wrapper and
title-only recovery; combined and repeated conflicts; divergent Resume;
explicit clears/unchecks during hydration; editing during rich import; board
rail and recipient synchronization; newer-tab preservation; all seven resumed
completion scenarios per engine, including lost committed responses and no-JS;
and all five account-menu/target scenarios per engine.

The prior October 25 matrix passed 132 cases and explicitly skipped four. Its
64 core recovery cases remain historical evidence. The [input comparison](prior-input-comparison.json)
confirms 20 of 23 relevant application/spec/config files remain byte-identical
to that integrated candidate. The three changed files are shared app JS/CSS
(the new theming integration) and DraftController (the account-state gate).
The previous 132 results are not relabeled as new runs. The current 54 cases
cover the integration boundary; the final full PHP suite covers the new gate.

## Results and representative captures

[Sanitized structured results](browser-results.json) include all selected case
statuses, durations and 30 extracted screenshot/measurement attachments.
Representative captures inspected after execution:

- [WebKit moderator menu at doubled root text](captures/webkit/alice-account-actions-fit-and-scroll-at-narrow-and-short-viewports/account-32px.png)
- [WebKit complete conflict comparison](draft-mobile/webkit/conflict-title-and-anonymous.png)
- [WebKit theming sample after individual removals](theming-webkit/webkit/mobile/individual-marks-removed.png)

The account action remains reachable in the short viewport; local/server copies
and the recovery choices remain readable and contained on the narrow layout.
The theming cases assert the preview stays isolated from the admin page and
individual removals retain the other branding settings.

## Isolation and report handling

Tests used the task-owned localhost:8037 PHP server and disposable
retroboards_draftfix_browser_20261031 database, shared deterministic synthetic
keys, isolated upload/package/rate stores, and seeded synthetic accounts. The
standard seed and thread fixture were prepared before execution. No production
settings or provider workers were exercised.

Raw logs and JSON reports are retained only in the private release directory:
`/home/ubuntu/.local/state/community-forums/draft-mobile-release-20261031/`.
Committed results are built from whitelisted case fields and known screenshot/
measurement attachments; the raw configuration and inherited environment are
excluded. The three raw-report hashes are retained in browser-results.json for
traceability without copying their configuration.
