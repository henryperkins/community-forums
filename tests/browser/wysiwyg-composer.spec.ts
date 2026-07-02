import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const repoRoot = path.resolve(__dirname, '..', '..');

function setWysiwygComposer(enabled: boolean): void {
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$settings = new \\App\\Repository\\SettingRepository(new \\App\\Core\\Database($config->get('db')));
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
$features['wysiwyg_composer'] = ${enabled ? 'true' : 'false'};
$settings->set('features', $features);
`;
  execFileSync('php', ['-r', php], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  });
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
  const loadedAssets: string[] = [];
  page.on('console', (msg) => {
    const text = msg.text();
    if (/Content Security Policy|Refused to apply inline style|Refused to execute inline script/i.test(text)) {
      violations.push(text);
    }
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
  expect(violations).toEqual([]);
});
