// Computed-style diff of the production pages under two Imladris layers.
//
// One server, one DOM per page: the page loads with layer A and every element's
// computed style (plus ::before/::after/::marker/::placeholder, plus the hover
// and focus states of the chrome's controls) is recorded IN THE PAGE. The layer
// <link> is then pointed at B, A again and B again, recording each time. Only
// properties where A == A' and B == B' and A != B are reported, so DOM churn
// (polls, timers, transitions) cannot masquerade as a layer change. Webfonts sit
// in a separate, permanent stylesheet so swapping the layer never reloads them.
//
// usage: node layer-diff.cjs <config.json>
'use strict';
const fs = require('fs');
const path = require('path');

const cfg = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const { chromium, webkit } = require(path.join(cfg.repo, 'tests/browser/node_modules/playwright'));

const fixFonts = (css) => css.replace(/url\((['"]?)fonts\/imladris\//g, 'url($1/assets/fonts/imladris/');
const FONT_FACE = /@font-face\s*\{[^}]*\}/g;
const rawA = fixFonts(fs.readFileSync(cfg.layerA, 'utf8'));
const rawB = fixFonts(fs.readFileSync(cfg.layerB, 'utf8'));
const facesA = (rawA.match(FONT_FACE) || []).join('\n');
if (facesA !== (rawB.match(FONT_FACE) || []).join('\n')) throw new Error('The layers declare different @font-face rules.');
const LAYERS = { a: rawA.replace(FONT_FACE, ''), b: rawB.replace(FONT_FACE, ''), fonts: facesA };
const customProps = [...new Set(
  [rawA, rawB, fs.readFileSync(path.join(cfg.repo, 'public/assets/app.css'), 'utf8')]
    .flatMap((css) => [...css.matchAll(/(--[a-zA-Z0-9_-]+)\s*:/g)].map((m) => m[1])),
)].sort();

const HOVER_TARGETS = cfg.hoverTargets ?? [
  '.forum-bar-surface:not(.is-active)', '.forum-bar-surface.is-active', '.forum-bar-search', '.forum-bar-railtoggle',
  '.forum-bar-bell', '.forum-bar-user', '.forum-bar-signin', '.forum-bar-signup', '.forum-bar-brand',
  '.forum-bar-compose .btn', '.board-rail-item:not(.is-active)', '.board-rail-item.is-active', '.presence-person',
  '.presence-all', '.presence-title a', '.admin-tier-item:not(.is-active)', '.admin-bar-exit', '.admin-bar-user',
  '.admin-bar [data-bell]', '.admin-bar form.inline .linkbtn', '.admin-tab:not(.is-active)', '.btn:not(.btn-secondary)',
  '.btn-secondary', '.choice-card', '.thread-row', '.star-toggle', '.compose-board-picker', '.input-engraved',
];
const FOCUS_TARGETS = cfg.focusTargets ?? [
  '.forum-bar-surface.is-active', '.forum-bar-bell', '.board-rail-item.is-active', '.admin-tier-item:not(.is-active)',
  '.admin-bar-search .input', '.input-engraved', '.choice-card input', '.btn', '.admin-bar [data-bell]',
];

async function login(browser, email) {
  const context = await browser.newContext({ baseURL: cfg.base });
  const page = await context.newPage();
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill('password123');
  await Promise.all([
    page.waitForURL((u) => !u.pathname.startsWith('/login')),
    page.locator('form[action="/login"] button[type="submit"]').first().click(),
  ]);
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible().catch(() => false)) await skip.click();
  const state = await context.storageState();
  await context.close();
  return state;
}

// ── In-page helpers (installed once per page) ────────────────────────────────
function installHelpers() {
  if (window.__rb) return;
  const keyOf = (el) => {
    const parts = [];
    for (let n = el; n && n.nodeType === 1 && n !== document.documentElement; n = n.parentElement) {
      let i = 1;
      for (let s = n.previousElementSibling; s; s = s.previousElementSibling) if (s.tagName === n.tagName) i++;
      const cls = typeof n.className === 'string' && n.className.trim() ? '.' + n.className.trim().split(/\s+/).join('.') : '';
      parts.unshift(n.tagName.toLowerCase() + (n.id ? '#' + n.id : '') + cls + ':' + i);
    }
    return parts.join('>');
  };
  const read = (cs) => {
    const o = {};
    for (let i = 0; i < cs.length; i++) {
      const p = cs.item(i);
      if (!p.startsWith('--')) o[p] = cs.getPropertyValue(p);
    }
    return o;
  };
  const SKIP = new Set(['SCRIPT', 'STYLE', 'LINK', 'META', 'TEMPLATE', 'NOSCRIPT']);
  const snapEl = (el, out, prefix) => {
    if (SKIP.has(el.tagName)) return;
    const key = prefix + keyOf(el);
    const cs = getComputedStyle(el);
    out[key] = read(cs);
    for (const pseudo of ['::before', '::after']) {
      const ps = getComputedStyle(el, pseudo);
      const content = ps.getPropertyValue('content');
      if (content !== 'none' && content !== 'normal') out[key + pseudo] = read(ps);
    }
    if (cs.display === 'list-item') out[key + '::marker'] = read(getComputedStyle(el, '::marker'));
    if (el.matches('input[placeholder], textarea[placeholder]')) out[key + '::placeholder'] = read(getComputedStyle(el, '::placeholder'));
  };
  window.__rb = {
    snaps: {},
    full(name, customProps) {
      const out = {};
      for (const el of document.querySelectorAll('body, body *')) snapEl(el, out, '');
      const root = getComputedStyle(document.documentElement);
      const vars = {};
      for (const p of customProps) vars[p] = root.getPropertyValue(p).trim();
      out[':root(vars)'] = vars;
      this.snaps[name] = out;
      return Object.keys(out).length;
    },
    state(name, label, el) {
      const out = this.snaps[name] || (this.snaps[name] = {});
      snapEl(el, out, label + ' | ');
    },
    diff(a, a2, b, b2) {
      const A = this.snaps[a], A2 = this.snaps[a2], B = this.snaps[b], B2 = this.snaps[b2];
      const found = [];
      if (!A || !A2 || !B || !B2) return found;
      for (const key of Object.keys(A)) {
        if (!B[key] || !A2[key] || !B2[key]) continue;
        for (const prop of Object.keys(A[key])) {
          const va = A[key][prop], vb = B[key][prop];
          if (va !== A2[key][prop] || vb !== B2[key][prop]) continue;
          if (vb !== undefined && vb !== va) found.push({ key, prop, a: va, b: vb });
        }
      }
      return found;
    },
    clear() { this.snaps = {}; },
  };
}

async function settle(page) {
  await page.evaluate(async () => {
    document.body.offsetHeight;
    await document.fonts.ready;
    await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
    for (const a of document.getAnimations()) { try { a.finish(); } catch (e) { /* infinite */ } }
    document.body.offsetHeight;
  });
}

async function setLayer(page, which) {
  await page.evaluate(async (url) => {
    const link = document.querySelector('link[rel="stylesheet"][data-rb-layer]')
      || document.querySelector('link[rel="stylesheet"][href*="imladris"]');
    link.setAttribute('data-rb-layer', '1');
    if (link.getAttribute('href') === url) return;
    await new Promise((resolve, reject) => {
      link.addEventListener('load', resolve, { once: true });
      link.addEventListener('error', reject, { once: true });
      link.setAttribute('href', url);
    });
  }, `/__layer/${which}.css`);
  await settle(page);
}

async function recordStates(page, name, targets, kind) {
  for (const selector of targets) {
    const handles = await page.locator(selector).all();
    for (const h of handles) {
      if (!(await h.isVisible().catch(() => false))) continue;
      const label = `${kind} ${selector}`;
      try {
        if (kind === 'hover') {
          await h.evaluate((el) => el.scrollIntoView({ block: 'center', inline: 'nearest' }));
          const box = await h.boundingBox();
          if (!box || box.width < 1 || box.height < 1) continue;
          await page.mouse.move(1, 1);
          await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
        } else {
          await page.keyboard.press('Shift');
          await h.evaluate((el) => el.focus({ preventScroll: true }));
        }
        await settle(page);
        await h.evaluate((el, args) => window.__rb.state(args.name, args.label, el), { name, label });
      } catch (e) {
        // Not hoverable/focusable at this width: skipped in every pass alike.
      }
      break; // first visible match only
    }
  }
  if (kind === 'focus') await page.evaluate(() => document.activeElement && document.activeElement.blur && document.activeElement.blur());
  await page.mouse.move(1, 1);
}

async function runCase(browser, states, c) {
  const context = await browser.newContext({
    baseURL: cfg.base,
    viewport: { width: c.width, height: c.height },
    storageState: c.persona === 'guest' ? undefined : states[c.persona],
    colorScheme: 'light',
  });
  await context.addInitScript(() => {
    const add = () => {
      if (document.querySelector('link[data-rb-fonts]')) return;
      const l = document.createElement('link');
      l.rel = 'stylesheet'; l.href = '/__layer/fonts.css'; l.setAttribute('data-rb-fonts', '1');
      (document.head || document.documentElement).appendChild(l);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', add); else add();
  });
  await context.route(/\/assets\/dist\/imladris-style-[^/]*\.css(\?.*)?$/, (r) => r.fulfill({ status: 200, contentType: 'text/css', body: LAYERS.a }));
  await context.route(/\/__layer\/(a|b|fonts)\.css$/, (r) => {
    const which = r.request().url().match(/\/__layer\/(a|b|fonts)\.css$/)[1];
    r.fulfill({ status: 200, contentType: 'text/css', body: LAYERS[which] });
  });
  const page = await context.newPage();
  const findings = [];
  try {
    const resp = await page.goto(c.route, { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    const skip = page.getByRole('button', { name: 'Skip', exact: true });
    if (await skip.isVisible().catch(() => false)) { await skip.click(); await page.waitForTimeout(150); }
    await page.evaluate(installHelpers);
    for (const theme of (cfg.themes ?? ['light', 'dark', 'system-dark'])) {
      await page.emulateMedia({ colorScheme: theme === 'system-dark' ? 'dark' : 'light' });
      await page.evaluate((t) => document.documentElement.setAttribute('data-theme', t), theme === 'system-dark' ? 'system' : theme);
      const withStates = theme !== 'system-dark';
      const pass = async (which, tag) => {
        await setLayer(page, which);
        await page.evaluate((args) => window.__rb.full(args.tag, args.customProps), { tag, customProps });
        if (withStates) {
          await recordStates(page, tag + ':s', HOVER_TARGETS, 'hover');
          await recordStates(page, tag + ':s', FOCUS_TARGETS, 'focus');
        }
      };
      await pass('a', 'A'); await pass('b', 'B'); await pass('a', 'A2'); await pass('b', 'B2');
      const found = await page.evaluate(() => [
        ...window.__rb.diff('A', 'A2', 'B', 'B2'),
        ...window.__rb.diff('A:s', 'A2:s', 'B:s', 'B2:s'),
      ]);
      await page.evaluate(() => window.__rb.clear());
      for (const f of found) findings.push({ persona: c.persona, route: c.route, width: c.width, theme, status: resp && resp.status(), ...f });
    }
  } catch (e) {
    findings.push({ persona: c.persona, route: c.route, width: c.width, error: String(e).slice(0, 300) });
  }
  await context.close();
  return findings;
}

(async () => {
  const engine = cfg.engine === 'webkit' ? webkit : chromium;
  const browser = await engine.launch();
  const states = {};
  for (const [persona, email] of Object.entries(cfg.personas)) states[persona] = await login(browser, email);
  const cases = [];
  for (const group of cfg.groups) {
    for (const [persona, routes] of Object.entries(group.routes)) {
      for (const route of routes) for (const [width, height] of group.viewports) cases.push({ persona, route, width, height });
    }
  }
  const total = cases.length;
  const all = [];
  let done = 0;
  const started = Date.now();
  const workers = Array.from({ length: cfg.workers ?? 4 }, async () => {
    while (cases.length) {
      const c = cases.shift();
      all.push(...(await runCase(browser, states, c)));
      done++;
      if (done % 10 === 0 || done === total) {
        process.stderr.write(`${done}/${total} cases, ${all.length} findings, ${Math.round((Date.now() - started) / 1000)}s\n`);
      }
    }
  });
  await Promise.all(workers);
  await browser.close();
  fs.writeFileSync(cfg.out, JSON.stringify(all, null, 1));
  console.log(`cases done: ${done}/${total}; findings: ${all.length}; written ${cfg.out}`);
})();
