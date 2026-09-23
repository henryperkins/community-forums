# CLAUDE.md

The canonical agent guidance for this repository is **`AGENTS.md`** — every tool rule file points there and none carries a second source of truth.

@AGENTS.md

## Directory-scoped addenda

Claude Code auto-loads these when working under each path (other agents: read them when touching those areas):

- `database/CLAUDE.md` — writing migrations (next-number rule, nowdoc DDL, Vitess-safe `ALTER` guards, seeds, `SCHEMA.md` upkeep).
- `tests/CLAUDE.md` — writing and debugging tests (transaction isolation semantics, strict PHPUnit).
