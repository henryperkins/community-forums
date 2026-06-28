# RetroBoards Copilot Guidelines

This project is a spec-driven, hand-rolled vanilla PHP 8.2+ + MySQL/MariaDB forum application, server-rendered with progressive-enhancement JavaScript. There is **no application framework** (no Laravel, Symfony, etc.). Please strictly adhere to these architectural standards and safety constraints on every instruction.

## Architectural Boundaries

1. **Dependency Injection**: Hand-wired, lazy-singleton container in `src/Core/Container.php` (`App::buildContainer()`). There is **no autowiring**. When creating a new service, repository, or mailer, you **must register its binding manually** in `buildContainer()`.
2. **Write Path Rules**:
   - **Controllers**: Thin. Marshall the `Request` object and call exactly **one** service method.
   - **Services**: Own all business logic (validation, anti-abuse, transformations), and **must** encapsulate all multi-table changes in a `$db->transaction(fn)` closure.
   - **Repositories**: Direct SQL single-table wrappers returning **associative arrays** (not objects), with individual prepared queries. The only exception is `User` model objects via `UserRepository::findEntity()`.
3. **Denormalized Counters & Reputation**:
   - Every modified count (like `threads.reply_count` or `users.reputation`) must be transactionally double-booked or updated.
   - You **must** also add a matching recompute routine in `RepairService` with identical `WHERE` clauses to keep them reconcilable.

## Critical Invariants & Security

1. **Form Validation & Anti-Draft-Loss**:
   - Controllers **must catch `ValidationException` and `DuplicateSubmissionException` itself**. The kernel does NOT handle them.
   - Always re-render the form carrying `->errors` and `->old` inputs on failure (`422` status) instead of redirecting so users do not lose their drafted text.
2. **Idempotency**: Submissions utilize `idempotency_key` with unique constraint checks in the transaction to reject double-submits.
3. **Database Constraints (PDO)**:
   - PDO runs with `EMULATE_PREPARES=false`.
   - **Never bind `LIMIT` or `OFFSET` parameters**. (Explicitly cast them to integer and format directly into the SQL string after clamping).
   - **Never reuse a named placeholder twice** in the same query.
4. **Strict CSP & Front-End**:
   - Highly restrictive CSP: `script-src 'self'; style-src 'self'`. **No `'unsafe-inline'` is allowed**.
   - Progressive-enhancement JS must reside in external files under `public/assets/`. Never output inline `<script>` or `<style>` tags anywhere in templates.
5. **Session Safety**: Session ids and CSRF secrets are rotated in the database on login. Validate CSRF utilizing `hash_equals()`.

## CSS & UX Regressions

- To avoid unintended frontend or visual regressions, **always propose CSS rules/declarations in chat first** to allow for review before writing them directly into assets or stylesheets unless explicitly instructed to edit instantly.

## Testing & Quality Assurance

- Always run `composer test` locally to verify changes, as there is no PHPUnit CI in GitHub (only Playwright browser evidence).
- Tests must be extremely strict: zero warnings, no stray printouts (`echo`, `var_dump`), and a minimum of one assertion per test.
- Every database modification under test runs inside a transaction rolled back in `tearDown()`.
