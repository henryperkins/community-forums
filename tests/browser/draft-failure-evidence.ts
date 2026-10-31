import type { Page, TestInfo } from '@playwright/test';

/** Synthetic draft content only; omit cookies, CSRF fields and credentials. */
export async function draftFailureEvidence(page: Page, info: TestInfo): Promise<void> {
  if (info.status === info.expectedStatus || page.isClosed()) return;
  try {
    const client = await page.evaluate(() => {
      const user = document.body.dataset.user || 'anon';
      const local: Record<string, string | null> = {};
      for (const key of Object.keys(localStorage)) {
        if (key.startsWith('rb-draft:' + user + ':') || key.startsWith('rb-draft-state:' + user + ':')) local[key] = localStorage.getItem(key);
      }
      return {
        user, url: location.href, local,
        pending: JSON.parse(sessionStorage.getItem('rb-draft:pending-submits') || '[]'),
        layout: { innerWidth, clientWidth: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth, rootFont: getComputedStyle(document.documentElement).fontSize },
        overflow: Array.from(document.querySelectorAll('body *')).map(node => ({
          node: node.tagName + '#' + node.id + '.' + String(node.className), right: node.getBoundingClientRect().right, left: node.getBoundingClientRect().left,
        })).filter(box => box.right > innerWidth + 1 || box.left < -1).slice(0, 30),
        forms: Array.from(document.querySelectorAll<HTMLFormElement>('form.composer')).map(form => ({
          action: form.getAttribute('action') || '', busy: form.getAttribute('aria-busy'),
          submitting: form.classList.contains('is-submitting'),
          body: form.querySelector<HTMLTextAreaElement>('textarea[name=body]')?.value,
          rich: form.querySelector<HTMLElement>('.ProseMirror')?.innerText,
          submission: form.querySelector<HTMLInputElement>('[name=idempotency_key]')?.value,
        })),
      };
    });
    const server: Record<string, unknown> = {};
    for (const context of new Set(client.forms.map(form => form.action))) {
      if (!/^\/(threads|messages(?:\/\d+)?|t\/\d+\/reply)$/.test(context)) continue;
      const key = encodeURIComponent(context).replace(/%/g, '~');
      const response = await page.request.get('/api/drafts/' + key);
      server[context] = { status: response.status(), data: await response.json() };
    }
    await info.attach('draft-failure-state', { body: JSON.stringify({ client, server }, null, 2), contentType: 'application/json' });
  } catch (error) {
    await info.attach('draft-failure-diagnostic-error', { body: String(error), contentType: 'text/plain' });
  }
}
