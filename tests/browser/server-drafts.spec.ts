import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

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
      data.append('metadata', JSON.stringify({ context: '/threads', source: 'playwright' }));
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

test('server drafts expose conflict choices and save local as next revision', async ({ page }, info) => {
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  await expect(page.locator('form.composer textarea.composer-input').first()).toBeVisible();

  const title = page.locator('form.composer input[name="title"]').first();
  const body = page.locator('form.composer textarea.composer-input').first();
  const key = serverDraftKey('/threads');
  await discardServerDraft(page, key);
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  await expect(page.locator('form.composer textarea.composer-input').first()).toBeVisible();

  await title.fill('Local conflict title');
  await body.fill('Local first draft');
  await expect(page.getByText('Saved to server drafts.')).toBeVisible({ timeout: 5000 });

  await postServerDraft(page, key, 1, 'Remote draft from another device');

  await body.fill('Local second draft');
  await expect(page.getByText('Draft conflict detected.')).toBeVisible({ timeout: 5000 });
  await expect(page.getByRole('button', { name: 'Keep local' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Keep server' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Save local as next revision' })).toBeVisible();
  await shot(page, info, '28-server-draft-conflict');

  await page.getByRole('button', { name: 'Save local as next revision' }).click();
  await expect(page.getByText('Saved to server drafts.')).toBeVisible({ timeout: 5000 });

  const saved = await page.evaluate(async ({ key }) => {
    const response = await fetch(`/api/drafts/${key}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    return response.json();
  }, { key });
  expect(saved.draft.body).toBe('Local second draft');
  expect(saved.draft.revision).toBe(3);
});
