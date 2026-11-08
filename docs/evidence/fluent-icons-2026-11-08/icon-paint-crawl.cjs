// GET-only crawl that lists every partial icon (svg.icon) painted as an outline:
// computed fill `none` or any stroke, on the svg or its paths.
// Usage (from the repository root, against a seeded browser server):
//   node docs/evidence/fluent-icons-2026-11-08/icon-paint-crawl.cjs http://localhost:8011 out.json [maxPagesPerAccount]
const path = require('path');
const { chromium } = require(require.resolve('@playwright/test', { paths: [path.resolve(__dirname, '../../../tests/browser')] }));
const fs = require('fs');

const base = process.argv[2].replace(/\/$/, '');
const out = process.argv[3];
const maxPages = Number(process.argv[4] || 70);
const SEEDS = ['/', '/inbox', '/boards', '/messages', '/notifications', '/settings', '/settings/appearance',
  '/settings/preferences', '/settings/boards', '/u/erestor', '/u/galadriel', '/search?q=the', '/drafts',
  '/admin', '/mod', '/leaderboard', '/members', '/users/online', '/tags'];
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
  const visited = [];
  let checked = 0;
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
      const seenRoutes = new Set();
      let count = 0;
      const limit = vp.name === 'mobile' ? Math.ceil(maxPages / 2) : maxPages;
      while (queue.length && count < limit) {
        const href = queue.shift();
        const key = routeKey(href);
        if (seenRoutes.has(key)) continue;
        seenRoutes.add(key);
        let status = 0;
        try {
          const resp = await page.goto(base + href, { waitUntil: 'domcontentloaded', timeout: 20000 });
          status = resp ? resp.status() : 0;
        } catch (e) { continue; }
        count++;
        visited.push(`${vp.name}/${acct.name} ${status} ${href}`);
        if (new URL(page.url()).pathname.startsWith('/login') && acct.email) continue;
        const res = await page.evaluate(() => {
          const icons = [...document.querySelectorAll('svg.icon')];
          const bad = [];
          for (const svg of icons) {
            const cs = getComputedStyle(svg);
            const paths = [...svg.querySelectorAll('path')].map((p) => getComputedStyle(p));
            const outlined = cs.fill === 'none' || cs.stroke !== 'none'
              || paths.some((p) => p.fill === 'none' || p.stroke !== 'none');
            if (outlined) {
              const parent = svg.parentElement;
              bad.push({
                cls: svg.getAttribute('class'),
                fill: cs.fill, stroke: cs.stroke, strokeWidth: cs.strokeWidth,
                ctx: parent ? `${parent.tagName.toLowerCase()}.${String(parent.className).trim().replace(/\s+/g, '.')}` : '',
                rendered: svg.getBoundingClientRect().width > 0,
              });
            }
          }
          const links = [...document.querySelectorAll('a[href^="/"]')].map((a) => a.getAttribute('href'));
          return { total: icons.length, bad, links };
        });
        checked += res.total;
        for (const b of res.bad) {
          const k = `${b.cls} | ${b.ctx}`;
          if (!findings.has(k)) findings.set(k, { ...b, example: `${vp.name}/${acct.name} ${href}`, pages: 0 });
          findings.get(k).pages++;
        }
        for (const l of res.links) {
          if (!l || SKIP.test(l) || l.startsWith('//')) continue;
          const clean = l.split('#')[0];
          if (clean && !seenRoutes.has(routeKey(clean))) queue.push(clean);
        }
      }
      await context.close();
    }
  }
  await browser.close();
  const result = { base, pages: visited.length, iconsChecked: checked, outlined: [...findings.values()], visited };
  fs.writeFileSync(out, JSON.stringify(result, null, 2));
  console.log(`pages=${visited.length} icons=${checked} outlined-kinds=${findings.size}`);
  for (const f of findings.values()) console.log(`  ${f.cls} in ${f.ctx} fill=${f.fill} stroke=${f.stroke} sw=${f.strokeWidth} rendered=${f.rendered} pages=${f.pages} e.g. ${f.example}`);
})().catch((e) => { console.error(e); process.exit(1); });
