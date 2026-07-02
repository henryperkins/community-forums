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

test('server draft validation errors replace stale success status', async ({ page }) => {
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();

  const title = page.locator('form.composer input[name="title"]').first();
  const body = page.locator('form.composer textarea.composer-input').first();
  const key = serverDraftKey('/threads');
  await discardServerDraft(page, key);
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();

  await title.fill('Validation status topic');
  await body.fill('Saved before validation error');
  await expect(page.getByText('Saved to server drafts.')).toBeVisible({ timeout: 5000 });

  await body.evaluate((node) => {
    const textarea = node as HTMLTextAreaElement;
    textarea.value = 'x'.repeat(20001);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  });

  await expect(page.getByText('Draft body must be 20000 characters or fewer.')).toBeVisible({ timeout: 5000 });
});

test('drafts page keeps browser-local drafts visible while server drafts are enabled', async ({ page }) => {
  await login(page, 'bob@retro.test');
  await page.evaluate(() => {
    localStorage.setItem('rb-draft:bob:/threads', 'Browser-only draft that has not synced');
  });

  await visit(page, '/drafts');

  const localDrafts = page.locator('[data-local-drafts-list]');
  await expect(page.locator('[data-drafts-list][data-server-drafts]')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Saved in this browser' })).toBeVisible();
  await expect(localDrafts).toContainText('Browser-only draft that has not synced');
  await expect(localDrafts.getByRole('link', { name: 'Resume' })).toHaveAttribute('href', '/');

  await localDrafts.getByRole('button', { name: 'Remove local copy' }).click();
  await expect(localDrafts).toContainText('No browser-local drafts in this browser.');
  const remaining = await page.evaluate(() => localStorage.getItem('rb-draft:bob:/threads'));
  expect(remaining).toBeNull();
});

test('failed submits keep server drafts and successful submits clear them after navigation', async ({ page }) => {
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();

  const title = page.locator('form.composer input[name="title"]').first();
  const body = page.locator('form.composer textarea.composer-input').first();
  const key = serverDraftKey('/threads');
  await discardServerDraft(page, key);
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();

  await title.fill('Draft retention topic');
  await body.fill('Keep this shared draft on failed submit');
  await expect(page.getByText('Saved to server drafts.')).toBeVisible({ timeout: 5000 });

  await body.evaluate((node) => {
    const textarea = node as HTMLTextAreaElement;
    textarea.value = 'x'.repeat(20001);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  });

  await page.getByRole('button', { name: 'Create topic' }).click();
  await expect(page.getByText('Your post is too long.')).toBeVisible({ timeout: 5000 });

  const afterFailedSubmit = await page.evaluate(async ({ key }) => {
    const response = await fetch(`/api/drafts/${key}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    return response.json();
  }, { key });
  expect(afterFailedSubmit.draft.body).toBe('Keep this shared draft on failed submit');

  await body.fill('Now submit successfully');
  await expect(page.getByText('Saved to server drafts.')).toBeVisible({ timeout: 5000 });

  await page.getByRole('button', { name: 'Create topic' }).click();
  await page.waitForURL(/\/t\/\d+-/);

  const afterSuccess = await page.evaluate(async ({ key }) => {
    const response = await fetch(`/api/drafts/${key}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    return response.json();
  }, { key });
  expect(afterSuccess.draft).toBeNull();
});

test('composer discard button immediately removes the matching server draft', async ({ page }) => {
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();

  const title = page.locator('form.composer input[name="title"]').first();
  const body = page.locator('form.composer textarea.composer-input').first();
  const key = serverDraftKey('/threads');
  await discardServerDraft(page, key);
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();

  await title.fill('Discard synced draft');
  await body.fill('This draft should disappear from both stores');
  await expect(page.getByText('Saved to server drafts.')).toBeVisible({ timeout: 5000 });

  await page.getByRole('button', { name: 'Discard draft' }).click();

  const afterDiscard = await page.evaluate(async ({ key }) => {
    const response = await fetch(`/api/drafts/${key}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    return response.json();
  }, { key });
  expect(afterDiscard.draft).toBeNull();
  expect(await body.inputValue()).toBe('');
});
