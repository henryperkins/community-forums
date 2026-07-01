import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';

async function visit(page: Page, url: string): Promise<void> {
  const resp = await page.goto(url);
  expect(resp, `no response for ${url}`).not.toBeNull();
  expect(resp!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
}

async function login(page: Page, email: string): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
  await dismissTour(page);
}

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click();
    await expect(page.locator('.tour-popover')).toHaveCount(0);
  }
}

async function expectNoSeriousA11yViolations(page: Page, info: TestInfo, include?: string): Promise<void> {
  let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
  // Scope the scan to one surface when given, so a feature's acceptance gate
  // isn't blocked by an unrelated pre-existing violation elsewhere on the page.
  if (include !== undefined) {
    builder = builder.include(include);
  }
  const results = await builder.analyze();
  const violations = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
  const scope = include !== undefined ? ` (${include})` : '';
  expect(violations, `${info.project.name} ${page.url()}${scope} serious/critical axe violations`).toEqual([]);
}

test('admin dark-surface pages have no serious axe violations', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await visit(page, '/admin/email');
  await expect(page.getByRole('heading', { name: 'Email delivery' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/extensions');
  await expect(page.getByRole('heading', { name: 'Server extensions' })).toBeVisible();
  await expect(page.getByText('browser-evidence')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);
});

test('member appeal and server-draft pages have no serious axe violations', async ({ page }, info) => {
  await login(page, 'bob@retro.test');

  await visit(page, '/appeals');
  await expect(page.getByRole('button', { name: 'Submit appeal' }).first()).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/drafts');
  await expect(page.getByText('Saved reply draft')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);
});

test('phase 4 poll panel has no serious axe violations (vote form and results)', async ({ page }, info) => {
  // Polls is the activate-now flag; the no-JS poll panel must clear a11y in both
  // its vote-form (pre-vote) and results (post-vote) states. Robust to whichever
  // order this runs vs the gate-a vote test (votes are idempotent per user).
  const voter = info.project.name === 'mobile' ? 'carol@retro.test' : 'bob@retro.test';
  await login(page, voter);

  await visit(page, '/c/general');
  await page.getByRole('link', { name: 'Share your favourite keyboard shortcuts' }).click();
  await page.waitForURL(/\/t\//);
  await expect(page.locator('.poll-panel')).toBeVisible();
  // Scope to the poll panel: this gates the polls feature, not unrelated
  // pre-existing thread-page issues (e.g. Imladris avatar-monogram contrast).
  // Scan whichever state this viewer is in (vote form if not yet voted, else results).
  await expectNoSeriousA11yViolations(page, info, '.poll-panel');

  const voteButton = page.getByRole('button', { name: 'Vote' });
  if (await voteButton.isVisible({ timeout: 1000 }).catch(() => false)) {
    await page.locator('form[action^="/polls/"] input[name="option_ids[]"]').first().check();
    await voteButton.click();
    await expect(page.locator('.poll-panel .link-list')).toBeVisible();
    // Results state (post-vote) must also be free of serious/critical violations.
    await expectNoSeriousA11yViolations(page, info, '.poll-panel');
  }
});

test('phase 4 topic workflow bar has no serious axe violations (actions and summary)', async ({ page }, info) => {
  // topic_workflow graduated to default-on (GA 2026-07-01). Scan the no-JS
  // workflow surface as alice (moderator of #general → the full status/snooze/
  // assign control set renders). Scope to the workflow selectors so this gate
  // isn't blocked by unrelated pre-existing thread-page issues.
  await login(page, 'alice@retro.test');

  await visit(page, '/c/general');
  await page.getByRole('link', { name: 'Share your favourite keyboard shortcuts' }).click();
  await page.waitForURL(/\/t\//);

  await expect(page.locator('.wf-actions')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.wf-actions');

  await expect(page.locator('.wf-bar')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.wf-bar');

  // Status history is surfaced as a collapsible audit list. Generate a change so
  // the list exists regardless of run order vs the gate-a flow, then expand and
  // scan it (a no-op change simply reuses the existing history row).
  await page.locator('#thread-status').selectOption('needs_answer');
  await page.getByRole('button', { name: 'Update status' }).click();
  await expect(page.locator('.wf-history')).toBeVisible();
  await page.locator('.wf-history > summary').click();
  await expect(page.locator('.wf-history-list')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.wf-history');
});
