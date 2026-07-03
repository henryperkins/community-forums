# ADR 0012: Phase 5 Gate A entry-gate artifacts (A1/A4/A5 recorded; A8 product-demand review)

**Date:** 2026-06-30
**Status:** **Accepted 2026-07-01.** Owner sign-offs for A4/A5/A8 were received 2026-06-30 (see
*Owner sign-off* below) and the two A1 corrections (`manage_workflow` dual-path;
`core.board.manage` → `category` scope) are landed. The owner's final acceptance
pass returned a **go** on 2026-07-01; this ADR flips from Proposed to **Accepted**
and `PHASE_5_STATUS.md` is updated to match. Acceptance covers the §2 **entry-gate
artifacts only** — it does **not** accept any Gate A *behavior* (that remains
**ADR 0015**, Increment 10).
**Supersedes a numbering reservation:** the Gate A program plan originally
reserved ADR `0012` for the *acceptance* record; that acceptance ADR is
renumbered **`0015`** (ADR `0013`/`0014` were subsequently consumed by the wysiwyg-composer and member-notifications ADRs). This ADR (`0012`) is entry-gate artifacts.

## Context

`PHASE_5_PLAN.md` §2 forbids Gate A implementation until the Phase 5 trust model
is recorded: package classes, trust tiers, registry ownership, **signing-key
custody**, review criteria, vulnerability handling, **permission taxonomy**,
extension data classes, host isolation, and unsupported-host behavior
(`PHASE_5_PLAN.md:48`).

ADR 0004 locked the *policy defaults* (D1–D12) but explicitly left a handful of
artifacts to be **authored/decided** before Foundation code begins. The Gate A
program plan (`docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md`,
§A) tracks them as A1/A4/A5/A8:

| # | Artifact | Was | Now |
|---|---|---|---|
| A1 | Capability taxonomy (instantiates D4) | approved in principle, not authored | **Recorded** → `docs/phase5/capability-taxonomy.md` (54 keys) |
| A4 | Registry signing-key custody (operationalizes D2) | policy approved, ceremony outstanding | **Recorded** → `docs/phase5/registry-signing-key-custody.md` |
| A5 | Canonical origin / WebAuthn RP ID (operationalizes D6) | policy approved, concrete origin outstanding | **Recorded** → `docs/phase5/canonical-origin-and-rp-id.md` (rule + DR runbook; self-host framing) |
| A8 | §2 product-demand review for opening a public ecosystem | open (owner judgment) | **Drafted below** for sign-off |

A2 (first named additional OIDC provider) is **not** an entry-gate item — it is
required before P5-12 *acceptance*. A3 (numeric budgets measured) is produced by
the Foundation budget harness (F9). A6/A7 default to the safe setting
(admin-only invitations; no privileged-MFA enforcement) unless the owner opts in.

## A8 — Product-demand review (adopted by product owner 2026-06-30)

> **Adopted 2026-06-30.** The product owner accepts the position below: the value is
> **operator customization/integration without forks**, and the Imladris/theming
> pressure is **sufficient evidence** to justify a **declarative-first** Gate A
> ecosystem — conditioned on public packages remaining **non-critical** and Gate A
> continuing to **exclude untrusted PHP**.

**Question (§2):** Is there sufficient product demand to justify opening a public
package ecosystem, given the supply-chain and maintenance surface it adds?

**Recommended position: yes, for a *declarative-first* ecosystem in Gate A.**

1. **The demand is extension without forking.** Operators of a self-hostable
   forum repeatedly need to change look-and-feel and wire in outside services
   without patching core. Today that means forking or hand-editing — fragile and
   un-upgradable. The Phase-5 carryover ledger already records this pressure:
   themes/branding (the Imladris reskin shipped as a bespoke fork), avatar/profile
   media, expanded attachments, polls, custom emoji (ADR 0004 C4/C6). A registry
   turns these into versioned, provenance-tracked, upgradable packages.
2. **Declarative-first keeps risk bounded.** Gate A ships **themes** +
   **declarative/remote integrations** only. There is **no untrusted PHP**:
   `server_extension` packages are rejected at install in Gate A and the sandbox
   runtime is Gate B (ADR 0004 D5). So the ecosystem's blast radius in Gate A is
   declarative tokens + scoped, consented API/webhook access — not arbitrary code.
3. **The registry is the safety feature, not just distribution.** Signed
   releases, an offline trust root (A4), exact-digest review binding, advisories
   with `warn→block_new→force_disable→revoke`, and a registry-independent local
   blocklist give operators provenance and a kill switch that an ad-hoc plugin
   folder never could. Opening the ecosystem *through* this machinery is safer
   than the status quo of bespoke patches.
4. **Core reliability is structurally protected.** Public packages are
   **non-critical** (decision #10): never a synchronous dependency of core
   login/read/post/moderation/recovery, with a flag-independent global safe mode
   that loads no package code. Every subsystem ships deploy-dark behind a flag and
   is enabled only under staged rollout. So adopting the ecosystem cannot, by
   construction, degrade the core forum.
5. **Maintenance surface is acknowledged and staged.** The cost (registry
   operation, review/advisory workflow, key custody) is real; Gate A starts with
   **one first-party registry** (D2) and an admin-only review console, deferring
   governance maturity, service principals, and verified links to Gate B.

**Owner judgment (2026-06-30):** demand is judged **sufficient** — operator
customization/integration-without-forks plus the Imladris theming precedent — so the
program **proceeds past Foundation**, conditioned on the non-critical guarantee
(decision #10) and Gate A excluding untrusted PHP (D5). Had demand been judged
insufficient, the program would stop at Foundation with the dark schema inert (no
behavior lost).

## Owner sign-off (received 2026-06-30)

- **A4 (signing-key custody)** — **approved.** Rotation **annual + immediate-on-compromise**; **deployment-local operator custody with a named successor per deployment**; default mechanism **air-gapped offline media with two backups**, with **hardware-backed non-exportable storage a stronger acceptable variant**. (`docs/phase5/registry-signing-key-custody.md` §6–§7.)
- **A5 (canonical origin / RP ID)** — **approved as written.** Self-host **rule + DR runbook** framing kept; passkey flows **hard-refuse** a non-secure non-localhost production origin. (`docs/phase5/canonical-origin-and-rp-id.md` §6.)
- **A8 (product-demand)** — **approved** (see above).
- **A1 corrections (from owner review):** `core.thread.manage_workflow` re-modeled as a **dual-path** key (author/self + staff) so non-staff workflow authority is represented; `core.board.manage` rescoped **`board → category`**. Both landed in `docs/phase5/capability-taxonomy.md`.
- **Parity-first stance confirmed:** keep the documented quirks for Gate A — no `edit_any`, `core.user.warn` stays **staff-any**, the vestigial global-moderator behavior is **preserved** (not "fixed" mid-parity).

## Decision

1. A1, A4, A5 are **recorded** as the linked artifacts above; A8 is **drafted**
   above for sign-off. With these in place (and the A4/A5 sign-off boxes
   completed), the remaining `PHASE_5_PLAN.md` §2 entry-gate artifacts are
   satisfied and the **Foundation increment (program-plan §B, F1–F11) may become
   an executable TDD plan.**
2. This ADR does **not** accept any Gate A *behavior*. Gate A acceptance remains a
   separate, evidence-gated record (**ADR 0015**, Increment 10).
3. A1 is the source of truth the Foundation **F3** generates `CapabilityCatalog.php`
   and the `0066` seed from; the F3 coverage test is the enforcement of A1, not
   this ADR.
4. A2 must be named before P5-12 acceptance; A6/A7 stay at their safe defaults
   unless a later owner record opts in.

## Consequences

- Clears the last §2 entry-gate artifacts; ADR 0004's Milestone-0 policy plus this
  ADR's instantiation together make the trust model "recorded before code begins."
- The Gate A program plan's acceptance-ADR reference moves `0012 → 0015` (`0013`/`0014` since consumed).
- A1's parity-first quirks (capability-taxonomy.md §7) are recorded as *current
  authority to reproduce*; correcting any of them is a separate post-parity owner
  decision, not Gate A scope.
- A4/A5 operator decisions (rotation cadence + key custodian; self-host framing +
  HTTPS enforcement) are **signed off** (see *Owner sign-off*); the code those
  artifacts gate (verifier, WebAuthn origin derivation) is built regardless.
