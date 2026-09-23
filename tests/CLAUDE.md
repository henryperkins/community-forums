# tests/ — writing and debugging tests

Loaded when working under `tests/`. Commands and the test-DB setup live in `AGENTS.md` §Commands (imported by the root `CLAUDE.md`).

Integration tests extend `Tests\Support\TestCase`, which drives the real kernel in-process via `App::handle()` as a cookie-jar HTTP client (`get`/`post`/`postFile`/`actingAs`), with seeding helpers (`makeUser`/`makeAdmin`/`makeBoard`/`makeThread`) and CSRF handled automatically. **Per-test isolation is one DB transaction rolled back in tearDown — there are no savepoints, so code that "rolls back" inside its own transaction does NOT undo rows in tests; assert observable HTTP behavior, not row counts.** PHPUnit is **strict** (`failOnWarning`, `failOnRisky`, `beStrictAboutOutputDuringTests`): every test needs ≥1 assertion, no stray `echo`/`var_dump`, no PHP warnings — any of these turns a green run red. Repositories are `final`; exercise the real test DB rather than mocking.
