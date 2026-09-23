import { expect, type Locator, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

export const root = path.resolve(__dirname, '../..');
export const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWP4z8AAAAMBAQCc479ZAAAAAElFTkSuQmCC', 'base64');
export const imageFile = (name = 'photo.png') => ({ name, mimeType: 'image/png', buffer: png });
export function fixture(mode = 'rich', ...args: string[]): any {
  return JSON.parse(execFileSync('php', ['tests/browser/upload-fixture.php', mode, ...args], { cwd: root, env: process.env, encoding: 'utf8' }));
}
export async function login(page: Page, email = 'alice@retro.test') {
  await page.goto('/login');
  await page.locator('input[name=email]').fill(email);
  await page.locator('input[name=password]').fill('password123');
  await page.locator('button[type=submit]').click();
  await page.waitForURL(url => !url.pathname.endsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}
export async function reply(page: Page, mode = 'rich') {
  const info = fixture(mode);
  await login(page);
  await page.goto(info.thread);
  const form = page.locator('form.reply-composer.composer-shell');
  await expect(form.locator('[data-composer-upload-input]')).toBeAttached();
  if (mode === 'rich') {
    await form.locator('textarea[name=body]').focus();
    await expect(form.locator('.wysiwyg-source-toggle')).toBeVisible();
    if (await form.locator('.wysiwyg-source-toggle').textContent() === 'Rich text') await form.locator('.wysiwyg-source-toggle').click();
    await expect(form.locator('.ProseMirror')).toBeVisible();
  }
  return form;
}
export async function setBody(form: Locator, text: string) {
  await form.evaluate((node: HTMLFormElement, value) => (node as any)._rbComposerAdapter.setMarkdown(value), text);
  await expect.poll(() => body(form)).toContain(text);
}
export async function body(form: Locator): Promise<string> {
  return form.evaluate((node: HTMLFormElement) => (node as any)._rbComposerAdapter.getMarkdown());
}
export async function choose(form: Locator, name = 'photo.png') {
  await form.locator('[data-composer-upload-input]').setInputFiles(imageFile(name));
}
export async function media(page: Page) {
  await page.route(/\/media\/9\d+$/, route => route.fulfill({ status: 200, contentType: 'image/png', body: png }));
}
export const accepted = (id = 9001) => ({ ok: true, id, url: `/media/${id}`, markdown: `![](/media/${id})`, width: 1, height: 1 });
