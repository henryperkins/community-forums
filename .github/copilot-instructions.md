# RetroBoards Copilot Guidelines

The canonical guidance for this repository is **[`AGENTS.md`](../AGENTS.md)** — read it in full before making changes. It owns the spec-doc precedence chain, the command reference, and the architecture/security/testing invariants (pure kernel, hand-wired DI, write-path layering, CSRF/session/rate-limit/CSP rules, feature-flag policy, migration rules, anti-draft-loss form pattern, strict PHPUnit culture).

There is no separate Copilot rule set: `CLAUDE.md` imports the same `AGENTS.md`, and `database/CLAUDE.md` / `tests/CLAUDE.md` are directory-scoped addenda to it.
