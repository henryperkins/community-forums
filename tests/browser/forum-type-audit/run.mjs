// ADR 0057 typography audit: measures every visible text element, target and
// button label on 22 routes (guest, member, operator) in light and dark at a
// desktop and a phone viewport (88 states), with an axe WCAG 2.2 AA scan.
// Point it at a prepared browser fixture (prepare.sh), then summarise:
//   E2E_BASE_URL=http://localhost:8011 OUT=/tmp/audit-after node forum-type-audit/run.mjs
//   node forum-type-audit/metrics.mjs /tmp/audit-after
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:8011';
const OUT = process.env.OUT ?? path.join(os.tmpdir(), 'forum-type-audit');
const measure = fs.readFileSync(new URL('./measure.js', import.meta.url), 'utf8');
const tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const guest = [['/', 'boards'], ['/c/general', 'board'], ['/t/1-welcome-to-retroboards', 'topic'], ['/login', 'login'], ['/register', 'register'], ['/search?q=welcome', 'search'], ['/u/alice', 'profile'], ['/tags', 'tags'], ['/leaderboard', 'leaderboard']];
const member = [['/inbox', 'inbox'], ['/messages', 'messages'], ['/notifications', 'notifications'], ['/compose', 'compose'], ['/settings', 'settings'], ['/settings/appearance', 'appearance'], ['/drafts', 'drafts'], ['/t/1-welcome-to-retroboards', 'topic-member']];
const admin = [['/admin', 'admin'], ['/admin/branding', 'branding'], ['/admin/users', 'admin-users'], ['/admin/structure', 'admin-structure'], ['/mod/reports', 'mod-reports']];
const only = process.env.ONLY ? new Set(process.env.ONLY.split(',')) : null;
const viewports = {
  desktop: { viewport: { width: 1280, height: 800 } },
  mobile: { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true },
};

async function login(page, email) {
  await page.goto(BASE + '/login');
  await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await page.waitForURL(u => u.pathname !== '/login');
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible().catch(() => false)) await skip.click();
}

async function audit(page, vp, route, label) {
  if (only && !only.has(label)) return;
  const resp = await page.goto(BASE + route, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  for (const theme of ['light', 'dark']) {
    await page.evaluate(async t => {
      document.documentElement.dataset.theme = t;
      await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
      const finite = document.getAnimations().filter(a => Number.isFinite(Number(a.effect?.getComputedTiming().endTime)));
      await Promise.race([Promise.all(finite.map(a => a.finished.catch(() => undefined))), new Promise(r => setTimeout(r, 2000))]);
    }, theme);
    const data = await page.evaluate(`(${measure})()`);
    const axe = await new AxeBuilder({ page }).withTags(tags).analyze();
    data.status = resp?.status();
    data.axe = { violations: axe.violations.map(v => ({ id: v.id, impact: v.impact, nodes: v.nodes.map(n => ({ target: n.target, summary: n.failureSummary })) })), incomplete: axe.incomplete.map(v => ({ id: v.id, count: v.nodes.length })) };
    const dir = path.join(OUT, vp);
    fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(path.join(dir, `${label}-${theme}.json`), JSON.stringify(data, null, 1));
    await page.screenshot({ path: path.join(dir, `${label}-${theme}.png`), animations: 'disabled' });
  }
  await page.evaluate(() => { document.documentElement.dataset.theme = 'light'; });
}

const browser = await chromium.launch(process.env.CHROME ? { executablePath: process.env.CHROME }
  : process.env.E2E_BROWSER_CHANNEL ? { channel: process.env.E2E_BROWSER_CHANNEL } : {});
for (const [vp, opts] of Object.entries(viewports)) {
  if (process.env.VP && process.env.VP !== vp) continue;
  const ctx = await browser.newContext({ ...opts, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  for (const [r, l] of guest) await audit(page, vp, r, l);
  await ctx.close();
  const mctx = await browser.newContext({ ...opts, reducedMotion: 'reduce' });
  const mpage = await mctx.newPage();
  await login(mpage, 'alice@retro.test');
  for (const [r, l] of member) await audit(mpage, vp, r, l);
  await mctx.close();
  const actx = await browser.newContext({ ...opts, reducedMotion: 'reduce' });
  const apage = await actx.newPage();
  await login(apage, 'admin@retro.test');
  for (const [r, l] of admin) await audit(apage, vp, r, l);
  await actx.close();
}
await browser.close();
console.log('done', OUT);
