# ADR 0052: Complete composer draft recovery

**Date:** 2026-10-24
**Status:** Accepted; release integration authorized 2026-10-31

## Context

The Mobile Web UX Runner audit confirmed three P2 defects: a topic draft could
lose its title and anonymity choice on reload; a revision conflict offered
choices without showing the copies; and Drafts Resume did not reliably open
the original composer. The owner authorized all three fixes, regression coverage
and local Chromium/WebKit verification, with unrelated work preserved and no
deployment.

## Decision

Keep the existing plain Markdown localStorage keys for compatibility with older
tabs. Store a versioned companion snapshot with the matching body and an explicit
whitelist of wrapper fields: title, board, anonymity and message recipients.
Mirror the permitted state in existing server-draft metadata; no migration or
new subsystem is required. Explicit empty and false values remain distinct from
missing fields. Only controls enabled in the actual form can be restored.
Recover older body-only drafts conservatively and never override typing,
clearing or unchecking performed while enhancement/hydration is pending.

Serialize and coalesce saves. Current clients compare both row identity and
revision, protecting against a deleted draft being recreated at revision 1.
Revision updates remain conditional; conflict responses use current reads and
successful acknowledgements are collected in the write transaction. Submitted
draft cleanup refers to the same checked row ID/revision and complete recorded
state, extending ADR 0050's receipt protection to same-body wrapper edits.
Only save-owned transactions may retry driver 1020/1213 after rollback, at most
twice, with the original CAS inputs; nested callers cannot replay wider work.
The latest unresolved complete local snapshot can recover its unchanged
submission identity across a reload that loses history state. A later completed
or different attempt blocks this fallback. A stale predecessor autosave is
recognized only through its exact state and an actual completed successor.

Display named browser/server copies with identifiable differences and bounded,
scrollable body text. Keep the existing three explicit choices. Keep server
uses the latest compared copy, and Save local reads current form state at
activation; repeated conflicts refresh the comparison without replacing controls.

Derive Resume destinations from canonical contexts, never arbitrary metadata
URLs. Server Resume checks ownership, expiry, matching context and existing
destination/feature gates and prefills ordinary GET forms without JavaScript.
Legacy topics with no board require an explicit destination choice. A lost,
closed or merged destination returns an unavailable result while retaining the
draft, instead of silently moving it elsewhere. Explicit server Resume still
compares a separate browser copy before replacing it.

The 2026-10-25 follow-up also binds the resumed form to the exact saved row and
revision with `draft_source`, without reusing the older form's submission key.
Successful-send cleanup still checks ownership, context, exact body and every
recorded wrapper field, then conditionally deletes that row/revision. The title
of a versioned draft without a recorded title field is only a list label.
Legacy drafts retain the body/title compatibility boundary. After permitted
server prefill, enhancement removes only the one-use `draft` query from the
current history entry. This preserves an uncertain retry's usable composer URL
even when the original send committed and deleted the server copy.

## Consequences and evidence

Existing local bodies, legacy server metadata and old clients remain readable.
Old clients that omit row identity retain their earlier revision-only boundary.
Quota, retention, permissions and the design system remain existing contracts.
New browser assets and PHP agree on the versioned metadata without requiring a
database migration.

The original audit is summarized in
[the historical evidence record](../evidence/draft-recovery-2026-10-23/README.md#original-audit).
Its full ledger and raw reports remain in the private recovery archive.
Local regression evidence and its limits are recorded in
`docs/evidence/draft-recovery-2026-10-23/README.md`. Browser emulation remains
proxy evidence; physical keyboard, autofill, assistive-technology and browser
chrome checks are not claimed. The follow-up evidence is in
`docs/evidence/mobile-ux-remediation-2026-10-25/README.md`.
The original October 24–25 work was limited to local implementation and proof.
The owner authorized release integration on October 31. That follow-up adds
`WriteGate` to server-draft save and both discard handlers, preserving list/load
access while suspended or banned accounts cannot mutate drafts. Current release
qualification is in `docs/evidence/draft-mobile-release-2026-10-31/README.md`;
deployment receipts remain separate linked release artifacts (ADR 0051).
