# Hosted browser orchestration qualification — 2026-10-23

The 2026-10-23 release exposed a capacity deadlock: two Cloud Build controllers
waited for queued child builds while occupying the available running slots. The
production build was also queued. Prior cancellation receipts remain outside the
repository in the completed PR91 publication record.

The replacement runs the full matrix explicitly against exact source/control
commits. `submit.py` waits on the operator host, records resumable build IDs, and
submits the trusted verifier only after the restricted test build is terminal.
The verifier reads that child once, fails on an active child, and preserves all
provider-status, step-status, identity, bounded artifact and receipt checks.
The browser webhook/trigger are disabled by configuration; production deployment
remains automatic. The verifier loses build-create and child ActAs privileges.

The active-child regression failed before the change (`child-wait-timeout`) and
passes with immediate `child-not-complete` rejection. The local PHPUnit boundary
suite passes (18 tests, 145 assertions); its log is adjacent. It invokes the
Python verifier (14), coordinator (7), and configuration (1) contracts as well as
archive/uploader checks. Tests use synthetic data without cloud requests.

An independent read-only reviewer found two issues, both fixed before cloud
configuration: success was persisted before receipt validation, and successful
builds with expired artifacts could not be replaced. Interrupted receipt reads
now leave `passed=false`; `--fresh` with a new state file replaces completed runs
while reusing matching active work. The reviewer independently passed all 22
Python contracts and found no remaining P1/P2 findings.

Run resumption reuses the exact recorded identities. Same-host coordinators use
a per-source/control lock; cross-host simultaneous submission is not an
exactly-once guarantee. Discovery examines the latest 100 regional builds. These
limits and explicit retry commands are documented in the deployment runbook.

This record covers local qualification. Live configuration, the complete hosted
matrix, and final production deployment receive separate receipts after their
actual execution.
