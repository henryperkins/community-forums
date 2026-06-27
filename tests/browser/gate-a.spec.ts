import { test, expect, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Gate A browser evidence: drive the real, server-rendered app in Chromium at
 * two viewports and capture each key surface. These are evidence-generating
 * journeys, but each navigation also asserts a non-error status (and a stable,
 * viewport-visible anchor where useful) so a broken page fails the run instead
 * of silently capturing an error screen.
 *
 * Seeded credentials (see seed.php): admin@retro.test, bob@retro.test — both
 * password123. Login uses the no-JS form post, so these double as proof the
 * server-rendered POST→redirect auth path works in a real browser.
 */

const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.screenshot({
    path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`),
    fullPage: true,
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
  await page.waitForURL((u) => !u.pathname.endsWith('/login')); // PRG redirect off the login page
}

test('public pages render and capture', async ({ page }, info) => {
  await visit(page, '/');
  await expect(page.locator('a[href^="/c/"]').first()).toBeVisible();
  await shot(page, info, '01-home');

  await visit(page, '/c/general');
  await expect(page.locator('a[href^="/t/"]').first()).toBeVisible();
  await shot(page, info, '02-board-general');

  // Open the first thread from the board (id-agnostic).
  await page.locator('a[href^="/t/"]').first().click();
  await page.waitForURL(/\/t\//);
  await shot(page, info, '03-thread');

  await visit(page, '/leaderboard');
  await shot(page, info, '04-leaderboard');

  await visit(page, '/login');
  await expect(page.locator('button[type="submit"]')).toBeVisible();
  await shot(page, info, '05-login');

  await visit(page, '/register');
  await expect(page.locator('input[name="username"]')).toBeVisible();
  await shot(page, info, '06-register');
});

test('admin + member pages render and capture', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await visit(page, '/admin');
  await shot(page, info, '07-admin-dashboard');

  await visit(page, '/admin/structure');
  await expect(page.locator('a[href^="/c/"]').first()).toBeVisible();
  await shot(page, info, '08-admin-structure');

  // The board-roster UI (board moderators + members) — open #General's edit page.
  await page
    .locator('li.admin-board-row', { hasText: 'General' })
    .getByRole('link', { name: 'Edit' })
    .click();
  await page.waitForURL(/\/admin\/boards\/\d+\/edit/);
  await expect(page.getByRole('heading', { name: 'Moderators' })).toBeVisible();
  await expect(page.getByText('@alice')).toBeVisible(); // seeded board moderator
  await shot(page, info, '09-admin-board-roster');

  await visit(page, '/inbox');
  await shot(page, info, '10-inbox');

  await visit(page, '/notifications');
  await shot(page, info, '11-notifications');

  await visit(page, '/settings');
  await shot(page, info, '12-settings');

  await visit(page, '/search?q=keyboard');
  await shot(page, info, '13-search');
});

test('private board access for a member', async ({ page }, info) => {
  await login(page, 'bob@retro.test');
  // Membership grants read access — visit() asserts 200 (a non-member would 404).
  await visit(page, '/c/staff-room');
  await expect(page.getByText('Staff Room').first()).toBeVisible();
  await shot(page, info, '14-private-board-member');
});
