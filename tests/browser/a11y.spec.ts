import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const repoRoot = path.resolve(__dirname, '..', '..');

function runPhp(code: string): string {
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
$settings = new \\App\\Repository\\SettingRepository($db);
${code}
`;
  return execFileSync('php', ['-r', php], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString();
}

function setWysiwygComposer(enabled: boolean): void {
  runPhp(`
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
$features['wysiwyg_composer'] = ${enabled ? 'true' : 'false'};
$settings->set('features', $features);
`);
}

test.beforeEach(() => {
  setWysiwygComposer(false);
});

async function visit(page: Page, url: string): Promise<void> {
  const resp = await page.goto(url);
  expect(resp, `no response for ${url}`).not.toBeNull();
  expect(resp!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
}

async function openPackageDetailByUid(page: Page, uid: string): Promise<string> {
  await visit(page, '/admin/packages');
  const row = page.locator('table.audit tbody tr').filter({ hasText: uid }).first();
  await expect(row).toBeVisible();
  await row.getByRole('link', { name: 'Details' }).click();
  await page.waitForURL(/\/admin\/packages\/\d+$/);

  return new URL(page.url()).pathname;
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

function serverDraftKey(context: string): string {
  return encodeURIComponent(context).replace(/%/g, '~');
}

async function postServerDraft(page: Page, key: string, revision: number, body: string): Promise<void> {
  const token = await page.locator('input[name="_token"]').first().inputValue();
  const status = await page.evaluate(
    async ({ key, revision, body, token }) => {
      const data = new FormData();
      data.append('_token', token);
      data.append('revision', String(revision));
      data.append('title', 'Remote draft');
      data.append('body', body);
      data.append('metadata', JSON.stringify({ context: '/threads', source: 'playwright-a11y' }));
      const response = await fetch(`/api/drafts/${key}`, {
        method: 'POST',
        body: data,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      return response.status;
    },
    { key, revision, body, token },
  );
  expect(status).toBe(200);
}

async function discardServerDraft(page: Page, key: string): Promise<void> {
  const token = await page.locator('input[name="_token"]').first().inputValue();
  await page.evaluate(
    async ({ key, token }) => {
      const data = new FormData();
      data.append('_token', token);
      await fetch(`/api/drafts/${key}/discard`, {
        method: 'POST',
        body: data,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      localStorage.removeItem('rb-draft:bob:/threads');
    },
    { key, token },
  );
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

  await visit(page, '/admin');
  await expect(page.getByRole('heading', { name: 'Admin console' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/email');
  await expect(page.getByRole('heading', { name: 'Email delivery' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/extensions');
  await expect(page.getByRole('heading', { name: 'Server extensions' })).toBeVisible();
  await expect(page.getByText('browser-evidence')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/roles');
  await expect(page.getByRole('heading', { name: 'Roles & capabilities' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/packages');
  await expect(page.getByRole('heading', { name: 'Package catalogue' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/themes');
  await expect(page.getByRole('heading', { name: 'Themes' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/themes/safe-mode');
  await expect(page.getByRole('heading', { name: 'Theme safe mode' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/registries');
  await expect(page.getByRole('heading', { name: 'Registry trust & security response' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  const packageDetailPath = await openPackageDetailByUid(page, 'acme/consent-demo');
  await expect(page.getByRole('heading', { name: 'Consent Demo Theme' })).toBeVisible();
  await expect(page.getByText('permissions await consent')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, `${packageDetailPath}/consent`);
  await expect(page.getByRole('heading', { name: 'Consent to permissions' })).toBeVisible();
  await expect(page.getByText('Store its own settings and data', { exact: false })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/roles/simulator');
  await expect(page.getByRole('heading', { name: 'Permission simulator' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  // badge_rules graduated to default-on (GA 2026-07-02): the admin create-rule
  // form + rule list is the operator surface.
  await visit(page, '/admin/badge-rules');
  await expect(page.getByRole('heading', { name: 'Badge rules' })).toBeVisible();
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

  // account_lifecycle graduated to default-on (GA 2026-07-02, ADR 0006): the
  // member self-serve export/deactivate/delete surface must clear a11y. Scan the
  // rendered page read-only (no destructive action) — the shell is already clean
  // for the appeal/draft member pages above, so this is an unscoped full-page scan.
  await visit(page, '/settings/account/lifecycle');
  await expect(page.getByRole('heading', { name: 'Delete account' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);
});

test('server-draft conflict panel has no serious axe violations', async ({ page }, info) => {
  // server_drafts graduated to default-on (GA 2026-07-02, ADR 0010). The
  // feature's interactive surface is the composer's conflict panel (keep local /
  // keep server / save local as next revision); scope the scan to it so this
  // gate isn't blocked by unrelated pre-existing composer issues, mirroring the
  // polls (.poll-panel) and topic_workflow (.wf-actions) axe precedent.
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  const body = page.locator('form.composer textarea.composer-input').first();
  await expect(body).toBeVisible();

  const key = serverDraftKey('/threads');
  await discardServerDraft(page, key);
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  await expect(body).toBeVisible();

  await body.fill('Local first draft for the a11y conflict scan');
  await expect(page.getByText('Saved to server drafts.')).toBeVisible({ timeout: 5000 });
  await postServerDraft(page, key, 1, 'Remote draft from another device');
  await body.fill('Local second draft that collides');

  await expect(page.locator('.composer-draft-sync.is-conflict')).toBeVisible({ timeout: 5000 });
  await expect(page.getByRole('button', { name: 'Save local as next revision' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.composer-draft-sync');
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

test('wysiwyg composer toolbar and reference picker have no serious axe violations', async ({ page }, info) => {
  setWysiwygComposer(true);
  try {
    await login(page, 'bob@retro.test');
    await visit(page, '/c/general');
    await page.locator('details.composer-details > summary').click();
    const form = page.locator('form.composer').first();
    const editor = form.locator('.wysiwyg-composer .ProseMirror');
    await expect(editor).toBeVisible();
    await expect(editor).toHaveAttribute('aria-label', 'Composer body');
    await expect(editor).toHaveAttribute('aria-multiline', 'true');
    await expect(form.locator('.composer-toolbar')).toBeVisible();

    await expectNoSeriousA11yViolations(page, info, '.composer-toolbar');
    await expectNoSeriousA11yViolations(page, info, '.wysiwyg-composer');

    await editor.fill('#gen');
    await expect(page.locator('.composer-reference-menu[role="listbox"]')).toBeVisible();
    await expectNoSeriousA11yViolations(page, info, '.composer-reference-menu');

    await page.keyboard.press('Escape');
    await form.getByRole('button', { name: 'Source' }).click();
    await expect(form.locator('textarea.composer-input')).not.toHaveClass(/is-wysiwyg-source-hidden/);
    await expectNoSeriousA11yViolations(page, info, 'form.composer');
  } finally {
    setWysiwygComposer(false);
  }
});

test('phase 4 slash combobox has no serious axe violations and is keyboard operable', async ({ page }, info) => {
  // slash_giphy graduated to default-on (GA 2026-07-02). The composer slash
  // inserts + GIPHY picker are an APG combobox (textarea = combobox, popup =
  // role=listbox of role=option). Scope the axe scan to the listbox surface per
  // the scoped-scan precedent, and assert the keyboard contract that graduation
  // required: default active option, arrow navigation via aria-activedescendant,
  // and Escape-to-close.
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  const body = page.locator('form.composer textarea.composer-input').first();
  await expect(body).toBeVisible();

  await body.fill('/');
  await expect(page.locator('.composer-slash-menu[role="listbox"]').first()).toBeVisible();
  await expect(body).toHaveAttribute('aria-expanded', 'true');

  const firstOption = page.getByRole('option', { name: 'Insert table' });
  await expect(firstOption).toHaveAttribute('aria-selected', 'true');
  const activeId = await firstOption.getAttribute('id');
  expect(activeId, 'active option should have an id for aria-activedescendant').toBeTruthy();
  await expect(body).toHaveAttribute('aria-activedescendant', activeId!);

  // Certify the listbox + options are free of serious/critical violations.
  await expectNoSeriousA11yViolations(page, info, '.composer-slash-menu');

  await body.press('ArrowDown');
  await expect(page.getByRole('option', { name: 'Insert task list' })).toHaveAttribute('aria-selected', 'true');
  await expect(firstOption).toHaveAttribute('aria-selected', 'false');

  await body.press('Escape');
  await expect(body).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('.composer-slash-menu')).toBeHidden();
});
