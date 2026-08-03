# Imladris Board Identity Prototype Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restore the approved dark Imladris board-identity band on `#/c/the-archive` while leaving the canonical thread header parchment and reading-focused.

**Architecture:** Keep the existing React component structure and route behavior unchanged. Apply the selected Direction A treatment through the existing `.board-hero` selectors, document the durable prototype rule, then verify the board and thread as separate rendered surfaces in the in-app browser.

**Tech Stack:** React 19, Vite 6, CSS, Phosphor Icons, Node's built-in test runner, Product Design browser QA.

## Global Constraints

- The board identity field is exactly evergreen `#2E4A3A` with parchment `#FAF6EC` primary text and a `3px solid #C29A44` bottom rule.
- The field sits below the breadcrumb and contains the board name, description, facts, Follow action, and New topic action.
- The treatment applies only to `#/c/the-archive`; it does not extend to the Forum Index, Forum Inbox, Messages, or the canonical thread.
- The thread header remains parchment and follows `docs/design-system/imladris/templates/thread-view/.thumbnail`.
- Preserve all existing prototype interactions, responsive behavior, focus handling, routes, and realistic mock data.
- Do not add board sort controls, a reading pane, a new route, or production PHP changes.
- Preserve unrelated dirty and untracked workspace files.

---

### Task 1: Restore the board-only identity band

**Files:**
- Modify: `docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/AGENTS.md`
- Modify: `docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/src/styles.css`
- Create: `docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/board-identity-direction-a-reference.html`
- Create: `docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/board-identity-direction-a-reference.png`
- Create: `docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/board-identity-desktop.png`
- Create: `docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/board-identity-mobile.png`
- Create: `docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/thread-identity-desktop.png`
- Create: `docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/board-identity-comparison.png`
- Modify: `docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/design-qa.md`

**Interfaces:**
- Consumes: the existing `BoardView` `.board-hero`, `.board-hero-copy`, `.board-facts`, and `.board-actions` markup.
- Produces: a board-only rendered identity band plus same-state visual evidence and a passing design-QA result; no component API or route changes.

- [ ] **Step 1: Establish the failing rendered-state check**

Run the prototype and inspect `#/c/the-archive` in the in-app browser. Evaluate the first `.board-hero` computed style against these literal expectations:

```js
const style = getComputedStyle(document.querySelector('.board-hero'));
({
  background: style.backgroundColor,
  color: style.color,
  borderBottom: style.borderBottom,
});
```

Expected before implementation: the check fails because the background is transparent and the bottom rule is the existing hairline rather than `3px solid rgb(194, 154, 68)`.

- [ ] **Step 2: Record the durable prototype decision**

Append this item under `## Approved prototype decisions` in `AGENTS.md`:

```markdown
- `/c/{slug}` uses the selected Direction A board-identity band: evergreen `#2E4A3A`, parchment `#FAF6EC` text, and a `3px` mallorn-gold `#C29A44` bottom rule beneath the breadcrumb. This treatment is board-only; canonical thread headers remain parchment.
```

- [ ] **Step 3: Apply the minimal board-only CSS**

Update the existing board selectors in `src/styles.css` with this treatment, preserving the existing flex layout:

```css
.board-hero {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  padding: 22px 24px 20px;
  border-bottom: 3px solid #c29a44;
  background: #2e4a3a;
  color: #faf6ec;
  box-shadow: var(--shadow-sm);
}

.board-hero .eyebrow,
.board-hero-copy > p,
.board-facts {
  color: #dce8dd;
}

.board-hero h1 {
  color: #faf6ec;
}

.board-hero .button-primary {
  border-color: #c29a44;
  background: #c29a44;
  color: #1b231d;
}

.board-hero .button-primary:hover {
  border-color: #d3ad5b;
  background: #d3ad5b;
}
```

At `max-width: 680px`, add `padding: 20px 18px 18px` to `.board-hero` while preserving the existing stacked action layout.

- [ ] **Step 4: Verify the rendered-state check turns green**

Re-run the computed-style check. Expected values:

```text
background: rgb(46, 74, 58)
color: rgb(250, 246, 236)
borderBottom: 3px solid rgb(194, 154, 68)
```

Inspect `.thread-hero` separately and confirm it does not receive the evergreen background or gold rule.

- [ ] **Step 5: Verify the board's existing interactions**

Exercise Follow, New topic open/close, invalid submit notice, valid submit notice, and the exemplar topic link. Confirm the dark band does not obscure focus rings or change action order at desktop and mobile widths.

#### QA and evidence

- [ ] **Step 6: Restore the exact Direction A reference fixture**

Create `evidence/board-identity-direction-a-reference.html` from the selected companion's board mini-screen using its original declarations:

```html
<!doctype html>
<meta charset="utf-8">
<style>
  * { box-sizing: border-box; }
  html, body { margin: 0; background: #f5efe1; }
  .board-hero {
    width: 440px;
    padding: 14px;
    border-bottom: 3px solid #c29a44;
    background: #2e4a3a;
    color: #faf6ec;
  }
  .eyebrow { margin: 0 0 5px; color: #dce8dd; font: 600 8px/1.2 system-ui; letter-spacing: .16em; text-transform: uppercase; }
  h1 { margin: 0; color: #faf6ec; font: 500 28px/1.15 Georgia, serif; }
  .lede { margin: 4px 0 10px; color: #dce8dd; font: 15px/1.35 Georgia, serif; }
  .button { display: inline-block; padding: 8px 11px; border-radius: 6px; background: #c29a44; color: #1b231d; font: 700 10px system-ui; letter-spacing: .08em; text-transform: uppercase; }
</style>
<header class="board-hero">
  <p class="eyebrow">Board</p>
  <h1>#counsel</h1>
  <p class="lede">Long-lived questions, decisions, and shared direction.</p>
  <span class="button">New topic</span>
</header>
```

Open it in the in-app browser and capture the 440px-wide header as `evidence/board-identity-direction-a-reference.png`.

- [ ] **Step 7: Capture matched rendered evidence**

Capture the board at `1266 × 854` and `390 × 844`, then capture the canonical thread at `1266 × 854`. Use the same parchment theme, route state, and mock data as the existing QA evidence.

- [ ] **Step 8: Create the focused comparison**

Place the recovered Direction A board-header reference and the new board-header capture into one comparison image. Normalize the crops to the same displayed width and record the source and implementation dimensions in `design-qa.md`.

- [ ] **Step 9: Run the design-QA gate**

Review the combined comparison and focused board/thread captures. Fix every P0, P1, or P2 mismatch before proceeding. Leave `final result: blocked` if a required source or implementation capture cannot be opened and compared.

- [ ] **Step 10: Run build and hosting checks**

Run:

```powershell
npm run build
npm run test:sites
```

Expected: both commands exit `0`; `test:sites` reports 4 passing tests and 0 failures.

- [ ] **Step 11: Run repository hygiene checks**

Run:

```powershell
git diff --check
git status --short
```

Expected: no whitespace errors. Confirm only the prototype, this plan, and the already-approved design spec/commit belong to this work; preserve every unrelated dirty or untracked file.

- [ ] **Step 12: Record the final QA result**

Update `design-qa.md` with the new evidence paths, interaction checks, console result, comparison history, and exactly one terminal line:

```text
final result: passed
```

Use `passed` only when no actionable P0, P1, or P2 finding remains.

- [ ] **Step 13: Commit the verified prototype atomically**

Stage only this plan and the self-contained prototype directory, inspect the staged scope, and commit:

```powershell
git add -- docs/superpowers/plans/2026-08-02-imladris-board-identity-prototype.md docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces
git diff --cached --check
git diff --cached --name-only
git commit -m "docs: add verified Imladris forum surface prototype"
```

Do not stage `docs/superpowers/plans/2026-08-02-imladris-screen-alignment.md` or any design-system import and handoff files.
