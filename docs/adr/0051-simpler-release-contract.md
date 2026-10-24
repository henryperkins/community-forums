# ADR 0051: A bounded ordinary release contract

Date: 2026-10-23. Status: accepted by owner instruction.

## Decision

Preserve unrelated work and review the final diff. For application changes run
one full PHP suite on the final candidate, affected generated-asset/Worker checks,
and focused browser evidence for UI changes. Re-run only when inputs change,
a check fails or a concrete concern remains. Docs-only changes need appropriate
document review. Specialized prototype, container-upload and recovery checks
apply when their inputs or risks change.

The broad hosted browser matrix is explicit optional regression coverage and
never an automatic merge/publication hold after sufficient completed local proof.
It runs once on the restricted test identity. Retire the separate verifier,
source/control coordination and receipt-acceptance service. Keep disposable data,
production-credential isolation, accurate test exit status and sanitized outputs.
Report diagnostic-publication problems separately from test outcome.

Remove the blanket application-surface Imladris digest and its baseline file.
Generated CSS/font reproducibility, imported-design review and concrete behavioral
contracts remain. This supersedes ADR 0024 delivery obligation 4 and derivative
per-merge baseline/follow-up-commit requirements in ADRs 0027/0038 and historical
adoption plans. Template edits, surface-spec prose and old bundle retention do not
require manual design re-approval by hash.

Deduplicate browser scheduling while preserving registered test/project cases.
The account/settings matrix must not repeat capture's chamfer, shared-chrome or
Gate A cases. Maintain isolated fixtures where they are needed.

Publish once, verify the exact independent Worker and Cloud Run deployments,
readiness/traffic and job image parity, public health, affected behavior and changed
asset bytes. Retain deployed asset versions for open-page/lazy-import compatibility;
advance pins after live proof. Store deployment receipts as linked CI/PR/release
artifacts and group necessary bookkeeping with planned changes instead of issuing
a proof-only publication.

## Consequences

Ordinary releases depend on relevant completed evidence. Optional CI infrastructure
and archive-format problems cannot add an unrelated publication barrier. Retiring
the verifier deliberately drops its isolated report-validation claim; it does not
change production privilege boundaries. The original owner-required stash and
local review records are preserved. Failed/canceled broad runs remain failures or
cancellations, with no claim of full hosted acceptance.

The operational commands and conditions live in AGENTS.md and the Cloud Run
runbook §5a. Historical plans/evidence remain historical rather than being
rewritten to imply a new result.
