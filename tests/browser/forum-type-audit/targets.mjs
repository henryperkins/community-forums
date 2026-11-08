// WCAG 2.5.8 check on the audit's 22 routes at both viewports: every
// non-inline target smaller than 24 x 24 CSS pixels must satisfy the
// spacing exception (its 24px circle meets no other target or circle).
// Prints each failure; prints only "target check done" when there are none.
//   E2E_BASE_URL=http://localhost:8011 node forum-type-audit/targets.mjs
import { chromium } from 'playwright';
const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:8011';
const browser = await chromium.launch(process.env.CHROME ? { executablePath: process.env.CHROME }
  : process.env.E2E_BROWSER_CHANNEL ? { channel: process.env.E2E_BROWSER_CHANNEL } : {});
async function login(page, email) {
  await page.goto(BASE + '/login');
  await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await page.waitForURL(u => u.pathname !== '/login');
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible().catch(() => false)) await skip.click();
}
const check = () => {
  const sel = 'a[href], button, input:not([type=hidden]), select, textarea, summary, [role=button], [tabindex]:not([tabindex="-1"])';
  const vis = el => { const r = el.getBoundingClientRect(); const cs = getComputedStyle(el); if (!(r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.pointerEvents !== 'none' && !el.closest('[hidden], [inert]'))) return false; for (let n = el; n; n = n.parentElement) { const s = getComputedStyle(n); if (+s.opacity === 0 || s.display === 'none') return false; if (n.tagName === 'DETAILS' && !n.open && n !== el && !el.matches('summary')) return false; } return true; };
  const els = [...document.querySelectorAll(sel)].filter(vis);
  // A target's hit area includes an enclosing label for form controls.
  const box = el => { const l = el.closest('label'); const r = (l && (el.matches('input'))) ? l.getBoundingClientRect() : el.getBoundingClientRect(); return { x: r.left, y: r.top, w: r.width, h: r.height }; };
  const items = els.map(el => ({ el, b: box(el), inline: el.tagName === 'A' && getComputedStyle(el).display === 'inline' && !!el.closest('p, li, td, dd, .formatted-content, .post-body') }));
  const small = items.filter(i => !i.inline && (i.b.w < 24 || i.b.h < 24));
  const circ = i => ({ cx: i.b.x + i.b.w / 2, cy: i.b.y + i.b.h / 2, r: 12 });
  const rectHitsCircle = (b, c) => { const nx = Math.max(b.x, Math.min(c.cx, b.x + b.w)); const ny = Math.max(b.y, Math.min(c.cy, b.y + b.h)); return (nx - c.cx) ** 2 + (ny - c.cy) ** 2 < c.r * c.r; };
  const fails = [];
  for (const s of small) {
    const c = circ(s);
    for (const o of items) {
      if (o === s || o.el.contains(s.el) || s.el.contains(o.el)) continue;
      const otherSmall = small.includes(o);
      const hit = otherSmall ? Math.hypot(circ(o).cx - c.cx, circ(o).cy - c.cy) < 24 : rectHitsCircle(o.b, c);
      if (hit) { fails.push({ target: (s.el.getAttribute('aria-label') || s.el.textContent || s.el.name || s.el.className).replace(/\s+/g, ' ').trim().slice(0, 40), size: `${s.b.w.toFixed(0)}x${s.b.h.toFixed(0)}`, near: (o.el.getAttribute('aria-label') || o.el.textContent || o.el.className).replace(/\s+/g, ' ').trim().slice(0, 30) }); break; }
    }
  }
  return fails;
};
const routes = {
  guest: ['/', '/c/general', '/t/1-welcome-to-retroboards', '/login', '/register', '/search?q=welcome', '/u/alice', '/tags', '/leaderboard'],
  'alice@retro.test': ['/inbox', '/messages', '/notifications', '/compose', '/settings', '/settings/appearance', '/drafts', '/t/1-welcome-to-retroboards'],
  'admin@retro.test': ['/admin', '/admin/branding', '/admin/users', '/admin/structure', '/mod/reports'],
};
let failures = 0;
for (const [vpName, vp] of [['desktop', { viewport: { width: 1280, height: 800 } }], ['mobile', { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 }]]) {
  for (const [who, list] of Object.entries(routes)) {
    const ctx = await browser.newContext(vp); const page = await ctx.newPage();
    if (who !== 'guest') await login(page, who);
    for (const r of list) { await page.goto(BASE + r, { waitUntil: 'networkidle' }); const f = await page.evaluate(check); failures += f.length; if (f.length) console.log(vpName, r, JSON.stringify(f.slice(0, 6))); }
    await ctx.close();
  }
}
await browser.close();
console.log('target check done', failures === 0 ? 'with no spacing failures' : `with ${failures} failures`);
process.exitCode = failures === 0 ? 0 : 1;
