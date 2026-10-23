# Admin safeguards and readable audit — 2026-10-23

Approved scope: close the two deferred P2 findings appended to ADR 0024 on 2026-10-18.
Base: `d9d56fba67f1f40dd56dc197a04c4cf4cc384325`; isolated branch
`codex/admin-safeguards-20261023`. The original main worktree and its review records are untouched.
Authority: DECISIONS, PRODUCT_DESIGN §13, ADMIN §§3.4/3.6/3.8/9.4 and ADR 0024.

## Design and implementation record

1. Keep the owning settings routes and service. Restrictive saves return an HTML impact review
   without writing. Explicit Confirm performs the existing transactional settings/audit write;
   Back to editing preserves the draft and Cancel reads saved state. No new CSRF exemption, GET
   mutation, client-only guard, password factor or navigation redesign.
2. Share a native expiry partial between member-record and bulk suspension forms. Label UTC and
   custom-only semantics directly; offer 24 hours, 7 days and 30 days. The service resolves a batch
   preset once, accepts strict native/legacy UTC values, and preserves stronger restrictions. Invalid
   and legacy draft dates render as text on 422 to prevent native-input erasure.
3. Resolve current user names through the existing batched audit query, only for the admin full log.
   Present known actions/fields as words, changed fields as before → after, and unknown actions via
   bounded escaped raw disclosure. No historical identity inference, PII expansion, masked-author
   reveal or audit mutation. Null actor ambiguity is explicit.
4. Reuse existing classes and native HTML. No CSS/JS/assets or design/runtime baseline edits.
5. Add behavioral integration tests and bounded presenter tests; update changed legacy expectations
   and existing browser date fills. Add desktop/390px Playwright journeys with explicit no-JS
   confirmation/bulk coverage. Record local proof and its limits separately from release evidence.

## Acceptance and evidence

- Review/edit/cancel do not mutate settings or audit; confirmation performs the intended save.
- Admin/feature/account-state/CSRF guards remain enforced on confirmed requests.
- Native minutes/seconds and legacy timestamps store UTC; impossible dates and malformed shapes
  reject without changing a member. Failed single/bulk forms retain drafts and selection.
- Presets use the submission instant and every member in a batch receives the same expiry.
- Actor/target names are escaped, missing identities are explicit, unknown payloads remain available,
  and summaries/raw output are bounded. Filters, pagination and moderator access keep existing tests.
- Focused PHPUnit: 137 tests, 869 assertions; browser: 10 passed at desktop and 390px, including
  JavaScript-disabled flows. See the [evidence index](../../evidence/admin-safeguards-2026-10-23/README.md).
- Full integrated suite/assets, publication and deployment are owned by the parent release workflow.
  Local render evidence is not physical-device, screen-reader or production proof.
