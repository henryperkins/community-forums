import { test, expect, type Page, type BrowserContext } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(__dirname, '../..');
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/browser/compose-destination');
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });

type Destination = { id: number; slug: string; name: string };
let destination: Destination;
let session: Awaited<ReturnType<BrowserContext['cookies']>> | undefined;

test.beforeAll(() => {
  destination = JSON.parse(execFileSync('php', ['tests/browser/compose-destination-fixture.php'], {
    cwd: ROOT,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString());
});

async function signIn(page: Page) {
  if (session) {
    await page.context().addCookies(session);
    return;
  }
  await page.goto('/login');
  await page.fill('input[name="email"]', 'compose-destination-reader@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.startsWith('/login'));
  session = await page.context().cookies();
}

for (const javaScriptEnabled of [true, false]) {
  test.describe(javaScriptEnabled ? 'with JavaScript' : 'without JavaScript', () => {
    test.use({ javaScriptEnabled });

    test('the header keeps a numeric board slug as the posting destination', async ({ page }, info) => {
      await signIn(page);
      const boardResponse = await page.goto('/c/' + destination.slug);
      expect(boardResponse?.status()).toBe(200);
      const newTopic = page.locator('.forum-bar-compose a');
      await expect(newTopic).toHaveAttribute('href', '/compose?board=' + destination.slug);
      await expect(newTopic).toHaveAccessibleName('New topic');

      await newTopic.click();

      await expect(page).toHaveURL(new RegExp('/compose\\?board=' + destination.slug + '$'));
      await expect(page.locator('select[name="board_id"]')).toHaveValue(String(destination.id));
      await expect(page.locator('select[name="board_id"] option:checked')).toHaveText(destination.name);
      await expect(page.locator('[data-compose-board-name]')).toHaveText('Posting to ' + destination.name);
      const dir = path.join(OUT, info.project.name);
      fs.mkdirSync(dir, { recursive: true });
      await page.screenshot({
        path: path.join(dir, javaScriptEnabled ? 'numeric-board-js.png' : 'numeric-board-no-js.png'),
        fullPage: true,
        animations: 'disabled',
      });
    });
  });
}
