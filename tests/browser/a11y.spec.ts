import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const repoRoot = path.resolve(__dirname, '..', '..');
const CUSTOM_EMOJI_SVG =
  '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><rect width="32" height="32" rx="6" fill="#d94848"/><circle cx="11" cy="12" r="2" fill="#fff7df"/><circle cx="21" cy="12" r="2" fill="#fff7df"/><path d="M10 21c3.5 3 8.5 3 12 0" fill="none" stroke="#fff7df" stroke-width="2.5" stroke-linecap="round"/></svg>';

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

function seedAdminAppearanceA11yStates(): void {
  runPhp(`
$settings->set('theme_safe_mode', '');
$admin = (new \\App\\Repository\\UserRepository($db))->findByUsername('admin');
$installs = array_values(array_filter(
    (new \\App\\Repository\\PackageThemeRepository($db))->themeInstalls(),
    static fn (array $install): bool => (string) $install['state'] === 'enabled',
));
if ($admin === null || count($installs) < 2) { throw new \\RuntimeException('Appearance axe fixtures are incomplete.'); }
$themes = new \\App\\Repository\\PackageThemeRepository($db);
$buildIds = [];
foreach (array_slice($installs, 0, 2) as $install) {
    $sourceDigest = hash('sha256', 'appearance-axe-source-' . $install['id']);
    $build = $themes->findBuildFor((int) $install['id'], $sourceDigest);
    if ($build === null) {
        $buildId = $themes->createBuild([
            'installed_package_id' => (int) $install['id'],
            'package_id' => (int) $install['package_id'],
            'release_id' => (int) $install['release_id'],
            'source_digest' => $sourceDigest,
            'token_schema_version' => 1,
            'tokens_json' => '{}',
            'validation_json' => '{"fixture":"appearance-axe"}',
            'css' => '',
            'css_digest' => hash('sha256', ''),
            'built_by' => (int) $admin['id'],
        ]);
    } else {
        $buildId = (int) $build['id'];
    }
    $buildIds[] = $buildId;
}
$themes->setState($buildIds[1], $buildIds[0], (int) $admin['id']);
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

/**
 * Open the board's New topic composer. With JS on, app.js promotes the board
 * identity button (desktop) / FAB (mobile) to the real opener and hides the
 * native <details> summary via .has-js .js-native-topic-trigger (app.css) — the
 * summary is only the no-JS path, so clicking it here waits forever on a
 * display:none element. Mirrors openNewTopicComposer() in wysiwyg-composer.spec.ts.
 * Callers have already navigated to the board.
 */
async function openTopicComposer(page: Page): Promise<void> {
  const details = page.locator('details.composer-details#new-topic');
  const promoted = page.locator('[data-open-topic-composer]');
  const fab = page.locator('a.fab[href="#new-topic"]');
  const summary = details.locator(':scope > summary');
  const opener = (await promoted.isVisible())
    ? promoted
    : ((await fab.isVisible()) ? fab : summary);
  await opener.click();
  await expect(details).toHaveJSProperty('open', true);
}

async function openTopicTools(page: Page, section: 'watch' | 'standing' | 'tags' | 'memory' | 'management') {
  const trigger = page.getByRole('button', { name: 'Topic tools', exact: true });
  await trigger.click();
  const tools = page.locator('[data-topic-tools]');
  await expect(tools).toBeVisible();
  await tools.evaluate(async (element) => Promise.all(element.getAnimations().map((animation) => animation.finished)));
  const details = tools.locator(`[data-topic-tools-section="${section}"]`);
  if (!(await details.evaluate((element) => (element as HTMLDetailsElement).open))) await details.locator(':scope > summary').click();
  return { tools, details };
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
  // Clear any prior session before (re-)login: an authenticated GET /login
  // 302-redirects to '/', hiding the email field. No-op on a fresh context;
  // required for same-page user switches (e.g. the staff appeals-queue scan).
  await page.context().clearCookies();
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
  seedAdminAppearanceA11yStates();
  await login(page, 'admin@retro.test');

  await visit(page, '/admin');
  await expect(page.getByRole('heading', { level: 1, name: 'Admin console' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Dashboard');
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/settings');
  await expect(page.getByRole('heading', { level: 1, name: 'General & intelligence' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('General & registration');
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/users');
  await expect(page.getByRole('heading', { level: 1, name: 'Members & invitations' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Directory');
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/invitations');
  await expect(page.getByRole('heading', { level: 1, name: 'Members & invitations' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Invitations');
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/email');
  await expect(page.getByRole('heading', { level: 1, name: 'Email & announcements' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Email');
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/announcements');
  await expect(page.getByRole('heading', { level: 1, name: 'Email & announcements' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Announcements');
  await expect(page.locator('[data-announcement-count]')).toHaveText('0 / 500');
  await page.locator('input[name="broadcast_email"]').check();
  await expect(page.locator('[data-announcement-broadcast-warning]')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/extensions');
  await expect(page.getByRole('heading', { level: 1, name: 'Packages & registries' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Extensions');
  await expect(page.getByText('browser-evidence')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/roles');
  await expect(page.getByRole('heading', { level: 1, name: 'Roles & capabilities' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Roles');
  await expect(page.getByRole('heading', { level: 2, name: 'Roles', exact: true })).toHaveCount(0);
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/packages');
  await expect(page.getByRole('heading', { level: 1, name: 'Packages & registries' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Packages');
  await expectNoSeriousA11yViolations(page, info);

  // Appearance is the Slice 8 boundary. Resolve its `system` setting through
  // the OS-dark media query explicitly; the remaining legacy admin scans keep
  // their established light baseline so unrelated surfaces do not masquerade
  // as Slice 8 acceptance failures.
  await page.emulateMedia({ colorScheme: 'dark' });
  await visit(page, '/admin/themes');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'system');
  expect(await page.evaluate(() => matchMedia('(prefers-color-scheme: dark)').matches)).toBe(true);
  await expect(page.getByRole('heading', { level: 1, name: 'Branding & themes' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Themes');
  await expect(page.locator('.admin-appearance-themes > .pane-intro')).toContainText('Safe mode returns the site to the built-in chrome');
  await expect(page.locator('.theme-enable-link')).toBeVisible();
  await expect(page.locator('.theme-rollback-button')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/themes/safe-mode');
  await expect(page.getByRole('heading', { level: 1, name: 'Theme safe mode' })).toBeVisible();
  await expect(page.locator('[data-admin-tier]')).toHaveCount(0);
  await expect(page.locator('.pill.pill-admin')).toHaveText('Recovery');
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/branding');
  await expect(page.getByRole('heading', { level: 1, name: 'Branding & themes' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Branding');
  await expect(page.locator('.branding-marks input[type="file"]')).toHaveCount(4);
  await page.locator('[data-brand-accent]').fill('#a33300');
  await expect(page.locator('.brand-preview-accent')).toHaveCSS('color', 'rgb(255, 255, 255)');
  await expectNoSeriousA11yViolations(page, info);

  await page.emulateMedia({ colorScheme: 'light' });

  await visit(page, '/admin/registries');
  await expect(page.getByRole('heading', { level: 1, name: 'Packages & registries' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Registry trust');
  await expectNoSeriousA11yViolations(page, info);

  const packageDetailPath = await openPackageDetailByUid(page, 'acme/consent-demo');
  await expect(page.getByRole('heading', { level: 1, name: 'Packages & registries' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Packages');
  await expect(page.getByRole('heading', { level: 2, name: 'Consent Demo Theme' })).toBeVisible();
  // Slice 14 pluralises this notice; the consent-demo fixture seeds exactly one
  // pending permission, so it now reads "1 permission awaits consent."
  await expect(page.getByText(/permissions? awaits? consent/)).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, `${packageDetailPath}/consent`);
  await expect(page.getByRole('heading', { level: 1, name: 'Packages & registries' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Packages');
  await expect(page.getByRole('heading', { level: 2, name: 'Consent to permissions' })).toBeVisible();
  await expect(page.getByText('Store its own settings and data', { exact: false })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/roles/simulator');
  await expect(page.getByRole('heading', { level: 1, name: 'Roles & capabilities' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Permission simulator');
  await expect(page.getByRole('heading', { level: 2, name: 'Simulate' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  // Direct query submission bypasses native required-field blocking and
  // proves the server-owned empty-capability error has the design's card.
  await visit(page, '/admin/roles/simulator?actor=guest&capability=');
  await expect(page.locator('.role-simulator-error').getByRole('heading', { level: 2, name: 'The simulator could not answer' })).toBeVisible();
  await expect(page.locator('.role-simulator-error')).toContainText('Pick a capability to test.');
  await expectNoSeriousA11yViolations(page, info);

  // badge_rules graduated to default-on (GA 2026-07-02): the admin create-rule
  // form + rule list is the operator surface.
  await visit(page, '/admin/badge-rules');
  await expect(page.getByRole('heading', { level: 1, name: 'Features & badges' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Badge rules');
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

test('staff appeals queue has no serious axe violations (resolve form)', async ({ page }, info) => {
  // appeals graduated to default-on (GA 2026-07-02, ADR 0007). The member
  // /appeals form is scanned above; the incremental staff surface is the
  // board-scoped resolution queue's resolve form (outcome select + note
  // textarea). Ensure an open appeal exists (bob appeals his seeded, removed
  // reply — tolerating a prior serial pass that already opened one), then scan
  // the populated queue as alice, the seeded moderator of #general.
  await login(page, 'bob@retro.test');
  await visit(page, '/appeals');
  const appealForm = page.locator('form.appeal-form').first();
  if (await appealForm.isVisible({ timeout: 1000 }).catch(() => false)) {
    await appealForm.locator('textarea[name="reason"]').fill('Requesting review for the a11y appeals-queue scan.');
    await appealForm.getByRole('button', { name: 'Submit appeal' }).click();
    await page.waitForURL(/\/appeals$/);
  }

  await login(page, 'alice@retro.test');
  await visit(page, '/mod/appeals');
  await expect(page.getByRole('heading', { name: 'Appeals queue' })).toBeVisible();
  const resolveForm = page.locator('form.appeal-resolve').first();
  await expect(resolveForm).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.appeal-resolve');

  // Self-clean so the serial mobile pass (and the member /appeals scan above)
  // finds bob's post appealable again: dismiss does not reverse the removal, and
  // a non-open appeal no longer blocks re-appealing (eligibility keys on 'open').
  await resolveForm.locator('select[name="outcome"]').selectOption('dismissed');
  await resolveForm.getByRole('button', { name: 'Resolve appeal' }).click();
  await page.waitForURL(/\/mod\/appeals$/);
});

test('group DM surfaces have no serious axe violations (compose, rail, report)', async ({ page }, info) => {
  // group_dms graduated to default-on (GA 2026-07-18, ADR 0022) and the seed
  // deliberately does NOT override the flag, so these scans certify the
  // GA-default surface: the compose fields (recipients + group title), the
  // details rail with the owner tools, and the reading room with the report
  // form open.
  await login(page, 'alice@retro.test');
  await visit(page, '/messages/new');
  await expect(page.locator('.dm-compose input[name="title"]')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.dm-compose');

  await page.fill('input[name="to"]', 'bob, carol');
  await page.fill('.dm-compose input[name="title"]', 'A11y counsel');
  await page.fill('.dm-form textarea[name="body"]', 'Scanning the group reading room.');
  await page.locator('.dm-form button[type="submit"]').click();
  await page.waitForURL(/\/messages\/\d+/);
  const convPath = new URL(page.url()).pathname;

  // Below 1400px (both projects) the rail is the :target drawer; open it.
  await page.locator('[data-rail-toggle]').click();
  await expect(page.locator('.dm-inforail')).toBeInViewport();
  await expect(page.locator('.dm-inforail .dm-owner-tool')).toHaveCount(2);
  await expectNoSeriousA11yViolations(page, info, '.dm-inforail');

  // A recipient's view: the ··· report control is a native <details>; scan the
  // reading room with the reason form open.
  await login(page, 'bob@retro.test');
  await visit(page, convPath);
  const line = page.locator('.dm-group:not(.mine) .dm-line').first();
  await line.locator('.dm-line-menu summary').click();
  await expect(line.locator('.dm-report-form')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.dm-threadpane');
});

test('server-draft conflict panel has no serious axe violations', async ({ page }, info) => {
  // server_drafts graduated to default-on (GA 2026-07-02, ADR 0010). The
  // feature's interactive surface is the composer's conflict panel (keep local /
  // keep server / save local as next revision); scope the scan to it so this
  // gate isn't blocked by unrelated pre-existing composer issues, mirroring the
  // polls (.poll-panel) and topic_workflow ([data-topic-tools]) axe precedent.
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await openTopicComposer(page);
  const body = page.locator('form.composer textarea.composer-input').first();
  await expect(body).toBeVisible();

  const key = serverDraftKey('/threads');
  await discardServerDraft(page, key);
  await visit(page, '/c/general');
  await openTopicComposer(page);
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

test('phase 4 topic workflow tools have no serious axe violations (actions and history)', async ({ page }, info) => {
  // topic_workflow graduated to default-on (GA 2026-07-01). Scan the Study
  // tools as alice (moderator of #general → the full status/snooze/assign
  // control set renders). Scope to the tools so this gate
  // isn't blocked by unrelated pre-existing thread-page issues.
  await login(page, 'alice@retro.test');

  await visit(page, '/c/general');
  await page.getByRole('link', { name: 'Share your favourite keyboard shortcuts' }).click();
  await page.waitForURL(/\/t\//);

  let standing = await openTopicTools(page, 'standing');
  await expectNoSeriousA11yViolations(page, info, '[data-topic-tools]');

  // Status history is surfaced as a collapsible audit list. Generate a change so
  // the list exists regardless of run order vs the gate-a flow, then expand and
  // scan it (a no-op change simply reuses the existing history row).
  await standing.details.locator('select[name="status"]').selectOption('needs_answer');
  await standing.details.getByRole('button', { name: 'Update status' }).click();
  standing = await openTopicTools(page, 'standing');
  const history = standing.details.locator('[data-thread-status-history]');
  await expect(history).toBeVisible();
  if (!(await history.evaluate((element) => (element as HTMLDetailsElement).open))) await history.locator(':scope > summary').click();
  await expect(history.locator('.thread-status-history-list')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '[data-topic-tools]');
});

test('wysiwyg composer toolbar and reference picker have no serious axe violations', async ({ page }, info) => {
  setWysiwygComposer(true);
  try {
    await login(page, 'bob@retro.test');
    await visit(page, '/c/general');
    await openTopicComposer(page);
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

test('phase 4 content reference cards have no serious axe violations', async ({ page }, info) => {
  // content_references graduated to default-on (GA 2026-07-02). The card panel
  // is rendered server-side from persisted internal links; scope the scan to it
  // so this gate is not blocked by unrelated thread-page issues.
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await openTopicComposer(page);
  await expect(page.locator('form.composer textarea.composer-input').first()).toBeVisible();
  await page.locator('form.composer input[name="title"]').first().fill('A11y reference source');
  await page.locator('form.composer textarea.composer-input').first().fill(
    'See [the general board](/c/general) for context.',
  );
  await page.locator('form.composer button[type="submit"]').first().click();
  await page.waitForURL(/\/t\/\d+-/);

  await expect(page.locator('.reference-cards')).toBeVisible();
  await expect(page.locator('.reference-cards')).toContainText('general');
  await expectNoSeriousA11yViolations(page, info, '.reference-cards');
});

test('phase 4 profile media panels have no serious axe violations', async ({ page }, info) => {
  await login(page, 'bob@retro.test');
  await visit(page, '/settings/account');
  const avatarPanel = page.locator('.profile-media-panel');
  await expect(avatarPanel).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.profile-media-panel');

  await avatarPanel.locator('input[name="avatar"]').setInputFiles({
    name: 'avatar.png',
    mimeType: 'image/png',
    buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWP4z8AAAAMBAQCc479ZAAAAAElFTkSuQmCC', 'base64'),
  });
  await avatarPanel.locator('form[action="/settings/avatar"] button[type="submit"]').click();
  await page.waitForURL(/\/settings\/account$/);
  await page.locator('textarea[name="signature"]').fill(`Profile media a11y (${info.project.name})`);
  await page.getByRole('button', { name: 'Save changes' }).click();
  await page.waitForURL(/\/settings\/account$/);

  await login(page, 'admin@retro.test');
  await visit(page, '/admin/users');
  await page.getByRole('link', { name: 'bob', exact: true }).click();
  await page.waitForURL(/\/admin\/users\/\d+$/);
  await expect(page.locator('.member-record-profile-media')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.member-record-profile-media');
});

test('phase 4 custom emoji surfaces have no serious axe violations', async ({ page }, info) => {
  const suffix = info.project.name.replace(/[^a-z0-9_-]/gi, '').toLowerCase();
  const shortcode = `a11y_${suffix}`;
  const token = `:${shortcode}:`;
  const imagePath = `/emoji/${shortcode}.png`;

  await page.route(`**${imagePath}`, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'image/svg+xml',
      body: CUSTOM_EMOJI_SVG,
    });
  });

  await login(page, 'admin@retro.test');
  await visit(page, '/admin/custom-emoji');
  const panel = page.locator('.custom-emoji-panel');
  await expect(panel).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.custom-emoji-panel');

  await panel.locator('input[name="shortcode"]').fill(shortcode);
  await panel.locator('input[name="name"]').fill(`A11y ${suffix}`);
  await panel.locator('input[name="image_path"]').fill(imagePath);
  await panel.locator('select[name="mime"]').selectOption('image/png');
  await panel.locator('input[name="allow_reactions"]').check();
  await panel.getByRole('button', { name: 'Save emoji' }).click();
  await page.waitForURL(/\/admin\/custom-emoji$/);
  await expect(page.getByRole('status').getByText('Custom emoji saved.')).toBeVisible();
  await expect(page.locator('.custom-emoji-panel')).toContainText(token);
  await expectNoSeriousA11yViolations(page, info, '.custom-emoji-panel');

  await page.context().clearCookies();
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await openTopicComposer(page);
  await expect(page.locator('form.composer textarea.composer-input').first()).toBeVisible();
  await page.locator('form.composer input[name="title"]').first().fill(`Custom emoji a11y ${suffix}`);
  await page.locator('form.composer textarea.composer-input').first().fill(`Hello ${token}`);
  await page.locator('form.composer button[type="submit"]').first().click();
  await page.waitForURL(/\/t\/\d+-/);

  await expect(page.locator(`.post-body img[src="${imagePath}"][alt="${token}"]`)).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.post-body');

  const post = page.locator('article[data-post]').first();
  await post.hover();
  const toolbar = post.locator('[data-post-toolbar]');
  await expect(toolbar).toBeVisible();
  await toolbar.locator('.reaction-add > summary').click();
  await expect(toolbar.getByRole('button', { name: token })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '[data-post-toolbar]');
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
  await openTopicComposer(page);
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

test('phase 4 split/merge moderator panel has no serious axe violations', async ({ page }, info) => {
  // split_merge default-on: the Study restructure dialog renders for alice (board
  // moderator of #general). Read-only scan — nothing is submitted, so the shared
  // seed thread is untouched. Scope to the dialog so an unrelated pre-existing
  // thread-page issue can't block this feature's gate.
  await login(page, 'alice@retro.test');
  await visit(page, '/c/general');
  await page.getByRole('link', { name: 'Share your favourite keyboard shortcuts' }).click();
  await page.waitForURL(/\/t\//);
  const management = await openTopicTools(page, 'management');
  await management.details.locator('[data-thread-restructure-open]').click();
  await expect(page.locator('.thread-restructure-dialog')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.thread-restructure-dialog');
});

test('phase 4 custom profile fields settings panel has no serious axe violations', async ({ page }, info) => {
  // custom_profile_fields default-on: the bounded label/value fieldset renders on
  // /settings/account whenever the flag is on (no seeded values required).
  await login(page, 'carol@retro.test');
  await visit(page, '/settings/account');
  await expect(page.locator('.custom-profile-fields')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.custom-profile-fields');
});
