# Implementation decisions

These decisions resolve execution details within the approved existing-feature
scope. They do not authorize deployment or close the separately tracked product
carryovers. They are recorded in the order they were made.

1. **Use an isolated worktree.** All implementation and verification lives on
   `codex/unified-notifications-settings`, leaving the original checkout intact.
   The user's implementation request authorizes this reversible preparation.
   If the location is unsuitable, the commits can be moved without changing main.
2. **Use one implementation agent at a time with independent reviews.** The two
   approved plans share services, templates and CSS, so parallel runtime writers
   would create avoidable conflicts. Read-only reviews and parent-owned evidence
   preparation can proceed alongside implementation. The tradeoff is sequential
   source work; the requirements remain unchanged.
3. **Isolate and serialize database verification.** PHPUnit schema resets share
   a lock; browser, worker and concurrency evidence use separate disposable
   schemas and captured mail. This prevents test interference at the cost of
   serializing tests that reset the same schema.
4. **Refuse ambiguous legacy purges.** A legacy active account with a pending
   deletion request is not automatically purged or reactivated. The lifecycle
   runbook requires targeted reconciliation. Valid pending requests remain
   purgeable across later moderation states. The cost is delayed purge for
   ambiguous legacy rows until an operator resolves the inconsistency.
5. **Refresh the reviewed Imladris baseline on this branch.** The approved plan
   requires a coherent, verifiable build before delivery, overriding the older
   merger-only procedure for this repair. The pinned `reconciled_through_commit`
   is preserved. The merger may need to recompute the digest after integration.
6. **Distinguish All boards from explicit saved-feed selections.** An originally
   empty filter retains Latest discovery. Explicit IDs use current canonical
   read permission within those IDs, including readable private/hidden boards
   already offered by settings. Original and current scopes intersect on retry.
   This repairs the exposed selected-board workflow without changing ordinary
   Latest. If the product later wants narrower discovery even for explicit
   selections, that would intentionally remove currently authorized results.

Final verification and review disposition are recorded in the [evidence index](README.md).
