# Thread Content Presentation Remediation Implementation Plan

> **For agentic workers:** Execute these tasks in order with test-first checkpoints. No delegation is required for this focused shared-stylesheet change.

**Goal:** Repair every confirmed cascade, measure, contrast, rhythm, and coverage defect in the shared user-authored-content presentation.

**Architecture:** Preserve `.formatted-content` as the only prose contract. Add observable PHPUnit and Playwright regressions first, then make the smallest final-layer CSS changes and refresh only relevant deterministic evidence.

**Tech Stack:** PHP 8.2, server-rendered templates, unlayered CSS, vanilla progressive-enhancement JavaScript, PHPUnit, Playwright, Axe.

### Task 1: Add regression coverage and prove RED

**Files:**

- Modify: `tests/Unit/Core/FormattedContentContractTest.php`
- Modify: `tests/Integration/Core/AppUserSettingsTest.php`
- Modify: `tests/browser/rich-content.spec.ts`
- Modify: `tests/browser/profile-surface-fixture.php`
- Modify: `tests/browser/profile-surface.spec.ts`
- Create: `tests/browser/thread-content-presentation-fixture.php`
- Create: `tests/browser/thread-content-presentation.spec.ts`

- [ ] Extend the shared-surface contract to include the profile bio.
- [ ] Prove the profile route emits sanitized rich Markdown inside `.formatted-content`.
- [ ] Seed deterministic consecutive-author and deleted-reply states in the isolated E2E database.
- [ ] Assert computed grouped padding, deleted framing, blockquote rhythm, preview measure/containment, and cross-theme code boundaries.
- [ ] Run the focused tests and retain the expected pre-fix failures.

### Task 2: Implement the final-layer CSS repair

**Files:**

- Modify: `public/assets/app.css`

- [ ] Remove the last child margin inside formatted blockquotes.
- [ ] Strengthen shared inline and block code borders with the existing strong-border token.
- [ ] Give the composer preview a border-box maximum equal to `66ch` plus its border/padding and a non-sunken outer surface.
- [ ] Restore the deleted-post frame after the final `.post` rule.
- [ ] Reassert grouped top padding after the mobile `.post` shorthand.
- [ ] Run the focused PHPUnit and Playwright tests and require GREEN.

### Task 3: Refresh and inspect completion evidence

**Files:**

- Modify relevant files under `docs/evidence/browser/`
- Modify relevant files under `docs/evidence/imladris-profile-production/`
- Modify: `config/imladris-runtime-baseline.json`

- [ ] Capture the deterministic thread-edge, rich-content, composer-preview, and profile states in desktop/mobile evidence.
- [ ] Inspect light/dark screenshots for spacing, boundary visibility, wrapping, and overflow.
- [ ] Refresh the application-surface digest without changing unrelated baseline fields.
- [ ] Run `php vendor/bin/phpunit`, `composer verify:imladris`, `npm run check:wysiwyg`, the relevant Playwright suites, and `git diff --check`.
- [ ] Review the final diff and preserve all unrelated dirty and untracked files.
