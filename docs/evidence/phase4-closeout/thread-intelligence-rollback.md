# Thread Intelligence Rollback Evidence

**Scope:** independent feature rollback, global generation brake, credential
removal, restoration, and the fixture-free migration rehearsal.

**Status:** sequence implemented; focused command result pending.

## Data-Preserving Runtime Order

1. Pause provider egress with the canonical
   `thread_intelligence_generation_paused` setting.
2. Read-modify-write the `features` object and pin
   `automated_context=false`; preserve every unrelated override.
3. Read-modify-write the same object and pin `community_memory=false`; preserve
   every unrelated override.
4. Remove the environment-only provider credential and restart the worker
   runtime. Do not delete summaries, citations, relationships, jobs,
   generations, or budget/evidence state.
5. For restoration, replace the credential, clear only a matching provider
   health latch, remove only the two Thread Intelligence feature pins, and
   resume generation. A retained published version can be read and rendered
   immediately; do not run a due worker merely to prove restoration.

The focused integration rehearsal starts from a published AI brief and its job
and generation IDs. At each pause/flag/credential boundary it asserts zero fake
provider calls and unchanged row counts and IDs. It retains an unrelated
feature override as a sentinel, removes only the two Thread Intelligence pins,
then renders the retained version through the real view/template path without
a replacement provider call.

## Migration Limitation

Migration `0077_thread_intelligence.php::down()` narrows AI-aware schema and
cannot losslessly preserve populated nullable authorship. It is therefore
rehearsed only on the dedicated fixture-free
`retroboards_thread_intelligence_clean` schema. The test invokes direct
`down()`/`up()`, asserts old and new shapes at each boundary, and restores
`0077` in `finally` because MariaDB DDL implicitly commits the PHPUnit harness
transaction.

Production rollback is runtime-only and data-preserving. The full
`migrate:rollback` command is not a Thread Intelligence rollback mechanism.

## Verification

Focused runtime and migration command results will be added after their fresh
runs. Operator syntax and recovery decisions are in
`docs/runbooks/thread_intelligence.md`.
