# Boards: layout, typeset and distill

This is the original local review on d06a303a. The later integration with the
four accessibility commits and publication preparation is recorded in
[integration evidence](integration/README.md).

**Date:** 2026-10-12
**Scope:** Local Boards directory refinement requested with two owner screenshots.
**Base:** d06a303ae2723d7da303d62069f79a877a3f1f77 on main.

## Review

- **Layout:** Shorten the introductory stack and give the six orders and three Peek
  choices deliberate groups. Supporting density and ranking text share one row.
- **Typeset:** Retain the four self-hosted Imladris families. Use Display/Title/Body/
  Small/Fine/Meta/caps roles, connected title leading and tabular metadata. Topic
  previews and descriptions wrap; the phone summary uses supplied regular Marcellus.
- **Distill:** Show totals once, remove the repeated category-order explanation and
  redundant wide Viewing caption, and replace the separate Peek capsule with the
  existing gold underline grammar. Every order, Peek choice and native action remains.

The independent layout and type assessments agreed on the introduction's excessive
weight, redundant category prose, whole toolbar groups and wrapping text. The
implementation follows ADR 0048 and stays outside the authored compatibility transfer.
Hero and introductory copy, board data, ranking, preference persistence, routes,
compact/Peek independence and the mobile sheet's JavaScript are unchanged.

## Final previews

- [Desktop, light](final/member-desktop-light.png) · [Desktop, dark](final/member-desktop-dark.png)
- [Phone, light](final/member-phone-light.png) · [Phone, dark](final/member-phone-dark.png)
- [Viewing sheet](final/member-phone-viewing-sheet.png)
- [861px, enlarged text](final/member-861-large-text.png) · [320px, enlarged text](final/member-320-large-text.png)

Two visual rounds were used. Round one identified a low-contrast day underline and
intermediate-width wrapping to correct. The final treatment uses semantic gold ink,
whole-group flex wrapping and an explicit density-link focus halo. A capture helper
initially waited for the zero-height details wrapper; its fixed panel was already
visible. The helper now waits for the panel. The failed log is retained.

Final measurements in [measurements.json](final/measurements.json) cover widths
320, 390, 760, 861, 1024 and 1440, both themes, and enlarged text at 320/861.
All ten cases have zero main overflow, zero topic-preview truncation and no targets
below 44px. Separate hardening suites exercise long and unbroken board names.
Enlarged text is a root-font-size simulation, not physical-device or native-zoom proof.

## Checks

| Check | Result | Evidence |
| --- | --- | --- |
| Directory Chromium | 15 passed | verification/chromium.log.gz |
| Directory WebKit on Windows | 6 passed, 1 failed | verification/webkit.log.gz |
| Asset tests after fixture repairs | 35 passed | verification/assets-tests.log.gz |
| Asset and Imladris checks | Passed | verification/assets-check.log.gz, verification/imladris-check.log.gz |
| Boards and Imladris PHP cases in final suite | 72 passed | verification/phpunit-final.xml.gz |
| Final full PHP suite | 3232 tests; 9 failures, 1 warning, 2 skips | verification/phpunit-final.log.gz, verification/phpunit-final.xml.gz |
| Manual Impeccable scan | 0 findings in home.php or the added CSS; 228 in unchanged CSS | verification/impeccable-review.json |

The browser suites cover guest/member behavior, both themes, native no-JavaScript
submission, compact rows with explicit Peek, sheet dismissal/containment, viewport
handoff, landscape and enlarged text, other directory panes and unused script requests.
All eight captured sheet axe scans are empty. The WebKit failure occurs when Tab
should move between guest links. A source/DOM diagnostic reproduces it with both
the candidate and the original compiled stylesheet; both lose focus to body.
The app.js file and mobile controls are unchanged. This remains a Windows WebKit
keyboard-coverage limitation; no Safari or physical-device pass is claimed.
See [the diagnostic](verification/webkit-focus-diagnostic.json) and
[Playwright's tab-navigation discussion](https://github.com/microsoft/playwright/issues/2114).

## Environment and failed evidence

PHP 8.3.33, PHPUnit 11.5.56, Node 24.15.0, Vite 8.3.1 and Playwright 1.61.1
ran against two private, disposable databases on loopback MariaDB 11.4.8: one
for PHP tests and one for browser fixtures. Mail uses an array transport and AI
configuration uses a dummy credential. No production database was involved.

The first full PHP attempt reached the configured 128MiB limit during an image
fixture. A 512MiB child-process setting allowed the complete run. The earlier
complete run recorded 3232 tests, 14 failures, 1 warning and 2 skips; it predates
the deliberate assertion update and canonical-byte corrections. Its logs and XML
remain available as phpunit.log.gz and phpunit.xml.gz. Current full results are recorded
separately as phpunit-final.*: 3232 tests, 25574 assertions, 9 failures,
1 warning and 2 skips. All 72 Boards/Imladris cases pass within that run.
The earlier and final runs are reported separately.

Windows autocrlf changed hash-addressed assets and source mirrors during checkout.
Unmodified reviewed bytes were restored from Git after checking normalized content;
no imported design changes were made. windows-line-endings.json lists the canonical
mirror/config corrections. The asset fixtures additionally needed fileURLToPath,
a Windows junction and normalized injection paths; otherwise they failed before
Vite or silently bypassed the simulated publication failures. Both failed stages
are preserved before the final 35-test pass.

The Cloud Run package/uploader tests assume a Linux filesystem and execution
environment; native Windows cannot supply Python os.O_NOFOLLOW or symlink privileges
and some fixtures miss their fake Docker commands. An unchanged reaction batching
test also fails. Eight remaining failures are Cloud Run fixtures and one is
reaction batching. Exact cases are in summary.json. A whole-suite green claim
is not supported.

## Delivery and handoff

Current asset version: **0f13ca29e198cb3d**. Application surface digest:
f1ba0b3b84b7a76058e42ff3210ca08f2a25a2dfd5f4091e4053e8c889c99a2a.
The manifest publishes 62 verified files; all three deployment pins remain intact.
The rebuilt stylesheet replaces only an unpinned candidate stylesheet.

Implementation and evidence were prepared in an isolated managed worktree, then
handed to the main checkout through an explicit file allowlist. Handoff records
confirm source/delivery hashes and preservation of the owner's four modified and
eight untracked files. The primary checkout's existing dependencies are preserved;
the declared toolchain checks ran in the isolated checkout. Changes remain
uncommitted. This is local evidence;
publication, automatic production deployment and live asset verification are
separate work.

The owned browser, PHP preview and private MariaDB process were stopped after
handoff. The temporary managed worktree remains attached. Automatic approval
review rejected removal of its dependency junction, with only "blocked by policy"
as the reported reason, so the junction and ignored dependencies were retained.

Raw text diagnostics are stored as lossless gzip archives. Their original byte
counts and SHA-256 hashes are recorded in verification/diagnostic-archives.json.
