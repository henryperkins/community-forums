import { expect, test, type Page } from '@playwright/test';
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

async function openNewTopicComposer(page: Page) {
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  const form = page.locator('form.composer').first();
  await expect(form.locator('textarea.composer-input')).toBeAttached();
  return form;
}

function postByTitle(title: string): { id: number; body: string } {
  const title64 = Buffer.from(title, 'utf8').toString('base64');
  return JSON.parse(runPhp(`
$title = base64_decode('${title64}');
$row = $db->fetch(
    'SELECT p.id, p.body
       FROM posts p
       JOIN threads t ON t.id = p.thread_id
      WHERE t.title = ? AND p.is_op = 1
      ORDER BY p.id DESC
      LIMIT 1',
    [$title],
);
echo json_encode($row ?: null);
`));
}

test('textarea composer inserts @ mention from keyboard picker', async ({ page }) => {
  setWysiwygComposer(false);
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  const body = page.locator('form.composer textarea.composer-input').first();
  await body.fill('@ali');
  await expect(page.locator('.composer-reference-menu[role="listbox"]')).toBeVisible();
  await expect(body).toHaveAttribute('aria-expanded', 'true');
  await page.keyboard.press('Enter');
  await expect(body).toHaveValue('@alice');
});

test('textarea # picker inserts board reference and does not steal headings', async ({ page }) => {
  setWysiwygComposer(false);
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  const body = page.locator('form.composer textarea.composer-input').first();
  await body.fill('# ');
  await expect(page.locator('.composer-reference-menu[role="listbox"]')).toBeHidden();
  await body.fill('#gen');
  await expect(page.locator('.composer-reference-menu[role="listbox"]')).toBeVisible();
  await page.keyboard.press('Enter');
  await expect(body).toHaveValue('[#general](/c/general)');
});

test('wysiwyg assets load under strict CSP without violations', async ({ page }) => {
  setWysiwygComposer(true);
  const violations: string[] = [];
  const pageErrors: string[] = [];
  const loadedAssets: string[] = [];
  page.on('console', (msg) => {
    const text = msg.text();
    if (/Content Security Policy|Refused to apply inline style|Refused to execute inline script/i.test(text)) {
      violations.push(text);
    }
  });
  page.on('pageerror', (error) => {
    pageErrors.push(error.message);
  });
  page.on('response', (response) => {
    const pathname = new URL(response.url()).pathname;
    if (pathname === '/assets/wysiwyg-composer.js' || pathname === '/assets/wysiwyg-composer.css') {
      loadedAssets.push(pathname);
    }
  });

  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();

  await expect(page.locator('body')).toHaveAttribute('data-wysiwyg-composer', '1');
  await expect(page.locator('form.composer textarea.composer-input').first()).toBeVisible();
  expect(loadedAssets).toContain('/assets/wysiwyg-composer.js');
  expect(loadedAssets).toContain('/assets/wysiwyg-composer.css');
  expect(pageErrors).toEqual([]);
  expect(violations).toEqual([]);
});

test('new topic WYSIWYG compose and submit', async ({ page }) => {
  setWysiwygComposer(true);
  await login(page, 'bob@retro.test');
  const form = await openNewTopicComposer(page);
  const editor = form.locator('.wysiwyg-composer .ProseMirror');
  await expect(editor).toBeVisible();
  await expect(form.locator('textarea.composer-input')).toHaveClass(/is-wysiwyg-source-hidden/);

  const title = `WYSIWYG topic ${Date.now()}`;
  await form.locator('input[name="title"]').fill(title);
  await editor.fill('WYSIWYG topic body');
  await form.locator('button[type="submit"]').click();
  await page.waitForURL(/\/t\/\d+-/);

  await expect(page.getByRole('heading', { name: title })).toBeVisible();
  await expect(page.locator('.post-op .post-body')).toContainText('WYSIWYG topic body');
});

test('source mode edits canonical Markdown and switches back', async ({ page }) => {
  setWysiwygComposer(true);
  await login(page, 'bob@retro.test');
  const form = await openNewTopicComposer(page);
  const editor = form.locator('.wysiwyg-composer .ProseMirror');
  const textarea = form.locator('textarea.composer-input');
  await expect(editor).toBeVisible();

  await form.getByRole('button', { name: 'Source' }).click();
  await expect(textarea).not.toHaveClass(/is-wysiwyg-source-hidden/);
  await textarea.fill('## Source Heading\n\n- [x] done');
  await form.getByRole('button', { name: 'Rich text' }).click();
  await expect(editor).toBeVisible();
  await expect(editor).toContainText('Source Heading');

  const title = `Source mode ${Date.now()}`;
  await form.locator('input[name="title"]').fill(title);
  await form.locator('button[type="submit"]').click();
  await page.waitForURL(/\/t\/\d+-/);
  await expect(page.locator('.post-op .post-body h2')).toContainText('Source Heading');
  await expect(page.locator('.post-op .post-body input[type="checkbox"]')).toBeChecked();
});

test('wysiwyg reference selections become chips and serialize to markdown', async ({ page }) => {
  setWysiwygComposer(true);
  await login(page, 'bob@retro.test');
  const form = await openNewTopicComposer(page);
  const editor = form.locator('.wysiwyg-composer .ProseMirror');
  await expect(editor).toBeVisible();

  await editor.fill('#gen');
  await expect(page.locator('.composer-reference-menu[role="listbox"]')).toBeVisible();
  await page.keyboard.press('Enter');
  await expect(form.locator('.composer-chip')).toContainText('#general');

  await form.getByRole('button', { name: 'Source' }).click();
  await expect(form.locator('textarea.composer-input')).toHaveValue('[#general](/c/general)');
});

test('pasted internal topic url becomes canonical markdown chip', async ({ page }) => {
  setWysiwygComposer(true);
  await login(page, 'bob@retro.test');
  const form = await openNewTopicComposer(page);
  const firstTopic = await page.locator('a[href^="/t/"]').first().getAttribute('href');
  expect(firstTopic).not.toBeNull();
  const editor = form.locator('.wysiwyg-composer .ProseMirror');
  await expect(editor).toBeVisible();

  await editor.click();
  await page.context().grantPermissions(['clipboard-read', 'clipboard-write'], { origin: new URL(page.url()).origin });
  await page.evaluate(async (text) => navigator.clipboard.writeText(`${location.origin}${text}`), firstTopic);
  await page.keyboard.press(process.platform === 'darwin' ? 'Meta+V' : 'Control+V');
  await expect(form.locator('.composer-chip')).toBeVisible();

  await form.getByRole('button', { name: 'Source' }).click();
  await expect(form.locator('textarea.composer-input')).toHaveValue(/\/t\/\d+-/);
});

test('no-op edit does not rewrite body', async ({ page }) => {
  setWysiwygComposer(false);
  await login(page, 'bob@retro.test');
  const form = await openNewTopicComposer(page);
  const title = `No op WYSIWYG ${Date.now()}`;
  const originalBody = 'Legacy [link](https://example.com)\n\n- item';
  await form.locator('input[name="title"]').fill(title);
  await form.locator('textarea.composer-input').fill(originalBody);
  await form.locator('button[type="submit"]').click();
  await page.waitForURL(/\/t\/\d+-/);
  const before = postByTitle(title);
  expect(before.body.replace(/\r\n/g, '\n')).toBe(originalBody);

  setWysiwygComposer(true);
  await page.reload();
  await expect(page.locator('body')).toHaveAttribute('data-wysiwyg-composer', '1');
  const post = page.locator(`#p${before.id}`);
  await post.locator('details.post-edit > summary').click();
  await expect(post.locator('.wysiwyg-composer .ProseMirror')).toBeVisible();
  await post.locator(`form[action="/posts/${before.id}/edit"] button[type="submit"]`).click();
  await page.waitForLoadState('domcontentloaded');

  expect(postByTitle(title).body).toBe(before.body);
});

test('server preview matches final rendered post for supported syntax', async ({ page }) => {
  setWysiwygComposer(true);
  await login(page, 'bob@retro.test');
  const form = await openNewTopicComposer(page);
  const markdown = [
    '## Preview Heading',
    '**bold**',
    '| A | B |\n| - | - |\n| 1 | 2 |',
    '||secret||',
  ].join('\n\n');

  await form.getByRole('button', { name: 'Source' }).click();
  await form.locator('textarea.composer-input').fill(markdown);
  const preview = form.locator('.composer-preview');
  await expect(preview.locator('h2')).toContainText('Preview Heading', { timeout: 5000 });
  await expect(preview.locator('table')).toBeVisible();
  await expect(preview.locator('.spoiler')).toContainText('secret');

  const title = `Preview parity ${Date.now()}`;
  await form.locator('input[name="title"]').fill(title);
  await form.locator('button[type="submit"]').click();
  await page.waitForURL(/\/t\/\d+-/);
  const post = page.locator('.post-op .post-body');
  await expect(post.locator('h2')).toContainText('Preview Heading');
  await expect(post.locator('table')).toBeVisible();
  await expect(post.locator('.spoiler')).toContainText('secret');
});

test('WYSIWYG mobile viewport smoke', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile-specific smoke');
  setWysiwygComposer(true);
  await login(page, 'bob@retro.test');
  const form = await openNewTopicComposer(page);
  await expect(form.locator('.wysiwyg-composer .ProseMirror')).toBeVisible();
  await form.getByRole('button', { name: 'Source' }).click();
  await expect(form.locator('textarea.composer-input')).not.toHaveClass(/is-wysiwyg-source-hidden/);
});
