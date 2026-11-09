// GET-only crawl that lists every icon drawn by hand or painted as an outline.
// A rendered <svg> is either a Fluent icon (class `icon`) painted with a fill
// and no stroke, or listed artwork that is not an icon; anything else is
// reported, with the page it was found on.
// Usage (from the repository root, against a seeded browser server):
//   node docs/evidence/fluent-icons-complete-2026-11-09/icon-crawl.cjs http://localhost:8011 out.json [maxPagesPerAccount]
const path = require('path');
const fs = require('fs');
const { chromium } = require(require.resolve('@playwright/test', { paths: [path.resolve(__dirname, '../../../tests/browser')] }));

const base = process.argv[2].replace(/\/$/, '');
const out = process.argv[3];
const maxPages = Number(process.argv[4] || 70);
// The community's default mark, a poll result's bar, a branding swatch and the
// retired profile ornament (hidden by the Forum theme).
const ARTWORK = ['forum-bar-mark', 'auth-brand-star', 'admin-bar-star', 'brand-mark', 'poll-result-progress', 'branding-revision-swatch', 'profile-cover-star'];
const SEEDS = ['/', '/inbox', '/boards', '/messages', '/notifications', '/compose', '/settings', '/settings/appearance',
  '/settings/preferences', '/settings/boards', '/u/erestor', '/u/galadriel', '/u/admin?tab=threads', '/search?q=the',
  '/drafts', '/users-online', '/verify?token=not-a-token', '/admin', '/admin/structure', '/admin/tags', '/admin/roles',
  '/mod', '/leaderboard', '/members', '/tags'];
const SKIP = /\/logout|\/auth\/|\/api\/|\.json|\/feed|\/rss|sitemap|healthz|export|download|\/brand\.css|\/assets\//;
const ACCOUNTS = [
  { name: 'guest' },
  { name: 'member', email: 'erestor@retro.test' },
  { name: 'admin', email: 'admin@retro.test' },
];
const VIEWPORTS = [{ name: 'desktop', width: 1280, height: 800 }, { name: 'mobile', width: 390, height: 844 }];

function routeKey(href) {
  const u = new URL(href, base);
  const keys = [...u.searchParams.keys()].sort().join(',');
  return u.pathname.replace(/\d+(-[^/]*)?/g, 'N') + (keys ? '?' + keys : '');
}

(async () => {
  const browser = await chromium.launch();
  const findings = new Map();
  const names = new Map();
  const visited = [];
  let icons = 0;
  for (const vp of VIEWPORTS) {
    for (const acct of ACCOUNTS) {
      if (vp.name === 'mobile' && acct.name === 'guest') continue;
      const context = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
      const page = await context.newPage();
      if (acct.email) {
        await page.goto(base + '/login');
        await page.fill('input[name="email"]', acct.email);
        await page.fill('input[name="password"]', 'password123');
        await page.click('form.auth-form button[type="submit"]');
        await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 15000 }).catch(() => {});
      }
      const queue = [...SEEDS];
      const seen = new Set();
      let count = 0;
      const limit = vp.name === 'mobile' ? Math.ceil(maxPages / 2) : maxPages;
      while (queue.length && count < limit) {
        const href = queue.shift();
        const key = routeKey(href);
        if (seen.has(key)) continue;
        seen.add(key);
        let status = 0;
        try {
          const resp = await page.goto(base + href, { waitUntil: 'domcontentloaded', timeout: 20000 });
          status = resp ? resp.status() : 0;
        } catch (e) { continue; }
        count++;
        visited.push(`${vp.name}/${acct.name} ${status} ${href}`);
        if (new URL(page.url()).pathname.startsWith('/login') && acct.email) continue;
        const res = await page.evaluate((artwork) => {
          const found = [];
          const drawn = [];
          for (const svg of document.querySelectorAll('svg')) {
            if (svg.getClientRects().length === 0) continue;
            if (artwork.some((name) => svg.classList.contains(name))) continue;
            const parent = svg.parentElement;
            const ctx = parent ? `${parent.tagName.toLowerCase()}.${String(parent.className).trim().replace(/\s+/g, '.')}` : '';
            if (!svg.classList.contains('icon')) {
              found.push({ kind: 'hand-made', cls: svg.getAttribute('class') || '(none)', ctx, viewBox: svg.getAttribute('viewBox') });
              continue;
            }
            drawn.push([...svg.classList].find((c) => c.startsWith('icon-')) || 'icon');
            const styles = [svg, ...svg.querySelectorAll('path')].map((el) => getComputedStyle(el));
            if (styles.some((s) => s.fill === 'none' || s.stroke !== 'none')) {
              found.push({ kind: 'outlined', cls: svg.getAttribute('class'), ctx, viewBox: svg.getAttribute('viewBox') });
            }
          }
          const links = [...document.querySelectorAll('a[href^="/"]')].map((a) => a.getAttribute('href'));
          return { found, drawn, links };
        }, ARTWORK);
        icons += res.drawn.length;
        for (const name of res.drawn) names.set(name, (names.get(name) || 0) + 1);
        for (const f of res.found) {
          const k = `${f.kind} | ${f.cls} | ${f.ctx}`;
          if (!findings.has(k)) findings.set(k, { ...f, example: `${vp.name}/${acct.name} ${href}`, pages: 0 });
          findings.get(k).pages++;
        }
        for (const l of res.links) {
          if (!l || SKIP.test(l) || l.startsWith('//')) continue;
          const clean = l.split('#')[0];
          if (clean && !seen.has(routeKey(clean))) queue.push(clean);
        }
      }
      await context.close();
    }
  }
  await browser.close();
  const result = {
    base, pages: visited.length, iconsChecked: icons,
    iconNames: Object.fromEntries([...names.entries()].sort()),
    findings: [...findings.values()], visited,
  };
  fs.writeFileSync(out, JSON.stringify(result, null, 2));
  const kinds = (kind) => [...findings.values()].filter((f) => f.kind === kind).length;
  console.log(`pages=${visited.length} icons=${icons} names=${names.size} hand-made-kinds=${kinds('hand-made')} outlined-kinds=${kinds('outlined')}`);
  for (const f of findings.values()) console.log(`  ${f.kind}: ${f.cls} in ${f.ctx} (${f.pages} pages, e.g. ${f.example})`);
})().catch((e) => { console.error(e); process.exit(1); });
