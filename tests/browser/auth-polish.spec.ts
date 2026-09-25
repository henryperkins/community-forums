import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Browser evidence for the login/logout polish pass (ADR 0039, DESIGN §13).
 *
 * `tests/Unit/Core/AuthGateStylesContractTest.php` pins the stylesheet; this
 * pins what it renders, in both colour schemes, because every defect here was
 * register-specific:
 *
 *  - the auth stage keeps its twilight in both registers, and focus on it is
 *    the stage's gold (the page's evergreen --accent measured 1.75:1 there),
 *  - an engraved field's edge holds 3:1 against the card and its own fill
 *    (gold-200 measured 1.30:1 by day),
 *  - the auth links read on the twilight card (2.86:1 under --brand),
 *  - a primary button hovers within its own hue (it turned green by night),
 *  - the index's guest note marks its link with more than colour.
 */

const REPO_ROOT = path.resolve(__dirname, '..', '..');
const EVIDENCE_DIR = path.resolve(
  REPO_ROOT,
  process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/auth-login-logout-polish',
);

/** Operator branding is a setting, so the branded case writes it and restores it. */
function setBrandPrimary(hex: string): void {
  execFileSync('php', ['-r', `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$settings = new \\App\\Repository\\SettingRepository(new \\App\\Core\\Database($config->get('db')));
$settings->set('brand_color_primary', ${JSON.stringify(hex)});
$settings->set('brand_version', ${JSON.stringify(hex === '' ? '' : `polish-${Date.now()}`)});
`], { cwd: REPO_ROOT, env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' } });
}

type Rgb = [number, number, number];

/** Computed colours come back as rgb()/rgba() or, from color-mix(), color(srgb …). */
function rgb(value: string): Rgb {
  const legacy = value.match(/rgba?\(([^)]+)\)/);
  if (legacy) {
    const [r, g, b] = legacy[1].split(/[ ,/]+/).filter(Boolean).map(Number);
    return [r, g, b];
  }
  const modern = value.match(/color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)/);
  if (modern) return [Number(modern[1]) * 255, Number(modern[2]) * 255, Number(modern[3]) * 255];
  throw new Error(`Unparsed colour: ${value}`);
}

function contrast(a: string, b: string): number {
  const lum = ([r, g, b]: Rgb) => {
    const f = (v: number) => { const s = v / 255; return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  };
  const [x, y] = [lum(rgb(a)), lum(rgb(b))];
  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
}

function hue(value: string): number {
  const [r, g, b] = rgb(value).map((v) => v / 255);
  const max = Math.max(r, g, b), min = Math.min(r, g, b), d = max - min;
  if (d === 0) return 0;
  const h = max === r ? ((g - b) / d) % 6 : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
  return (h * 60 + 360) % 360;
}

async function shot(page: Page, name: string, locator?: string): Promise<void> {
  const info = test.info();
  const directory = path.join(EVIDENCE_DIR, info.project.name);
  fs.mkdirSync(directory, { recursive: true });
  const file = path.join(directory, `${name}.png`);
  if (locator) await page.locator(locator).first().screenshot({ path: file, animations: 'disabled' });
  else await page.screenshot({ path: file, animations: 'disabled' });
}

async function settle(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);
}

async function signIn(page: Page): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'bob@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await Promise.all([
    page.waitForURL((url) => !url.pathname.endsWith('/login')),
    page.locator('.auth-form button[type="submit"]').click(),
  ]);
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 800 }).catch(() => false)) await skip.click();
}

/** The largest visible match's edge, fill, and the surface it sits on. */
async function frameOf(page: Page, selector: string) {
  return page.evaluate((s) => {
    const all = [...document.querySelectorAll(s)].filter((n) => n.getBoundingClientRect().width > 60);
    all.sort((a, b) => b.getBoundingClientRect().width - a.getBoundingClientRect().width);
    const el = all[0];
    if (!el) return null;
    (document.activeElement as HTMLElement | null)?.blur();
    const opaque = (n: Element | null): string => {
      while (n) {
        const c = getComputedStyle(n).backgroundColor;
        const alpha = c.startsWith('rgba') ? parseFloat(c.split(',')[3]) : 1;
        if (c !== 'rgba(0, 0, 0, 0)' && alpha > 0.95) return c;
        n = n.parentElement;
      }
      return getComputedStyle(document.body).backgroundColor;
    };
    const cs = getComputedStyle(el);
    el.setAttribute('data-evidence-frame', '1');
    el.scrollIntoView({ block: 'center' });
    return { edge: cs.borderTopColor, width: parseFloat(cs.borderTopWidth), fill: cs.backgroundColor, host: opaque(el.parentElement) };
  }, selector);
}

for (const scheme of ['light', 'dark'] as const) {
  test.describe(`login and logout polish, ${scheme} scheme`, () => {
    test.use({ colorScheme: scheme, reducedMotion: 'reduce' });

    test('the stage keeps its twilight, and focus on it is the stage gold', async ({ page }) => {
      await page.goto('/login');
      await settle(page);
      const stage = await page.evaluate(() => ({
        body: getComputedStyle(document.body).backgroundColor,
        ground: getComputedStyle(document.querySelector('.auth-stage')!).backgroundColor,
        brand: getComputedStyle(document.querySelector('.auth-brand')!).color,
        wordmark: getComputedStyle(document.querySelector('.auth-brand-name')!).color,
        star: getComputedStyle(document.querySelector('.auth-stage-star')!).color,
        cardRule: getComputedStyle(document.querySelector('.auth-card')!).borderTopColor,
      }));
      // The same five values in either scheme: the stage does not flip.
      expect(stage).toEqual({
        body: 'rgb(22, 29, 36)',
        ground: 'rgb(22, 29, 36)',
        brand: 'rgb(210, 176, 98)',
        wordmark: 'rgb(250, 246, 236)',
        star: 'rgb(194, 154, 68)',
        cardRule: 'rgb(210, 176, 98)',
      });

      // Shift+Tab from the autofocused email reaches the home link, then the skip link.
      await page.locator('input[name="email"]').focus();
      for (const target of ['.auth-brand', '.skip-link']) {
        await page.keyboard.press('Shift+Tab');
        const ring = await page.evaluate(() => {
          const cs = getComputedStyle(document.activeElement!);
          return { cls: (document.activeElement as HTMLElement).className, style: cs.outlineStyle, width: parseFloat(cs.outlineWidth), color: cs.outlineColor };
        });
        expect(ring.cls).toContain(target.slice(1));
        expect(ring.style).toBe('solid');
        expect(ring.width).toBeGreaterThanOrEqual(2);
        expect(contrast(ring.color, stage.ground), `${target} focus ring on the stage`).toBeGreaterThanOrEqual(3);
        if (target === '.auth-brand') await shot(page, `${scheme}-home-link-focus`, '.auth-brand');
      }
    });

    test('an engraved field keeps a 3:1 edge on every surface that uses one', async ({ page }) => {
      await page.goto('/login');
      await settle(page);
      const login = await frameOf(page, 'input[name="password"]');
      await shot(page, `${scheme}-login-card`, '.auth-card');

      await signIn(page);
      const frames: Record<string, Awaited<ReturnType<typeof frameOf>>> = { login };
      for (const [name, url, selector] of [
        ['settings', '/settings/account', 'input[name="display_name"]'],
        ['appeals', '/appeals', 'textarea.textarea-engraved'],
        ['messages', '/messages/new', '.dm-to-field.input-engraved'],
      ] as const) {
        await page.goto(url);
        await settle(page);
        frames[name] = await frameOf(page, selector);
        await shot(page, `${scheme}-${name}-field`, '[data-evidence-frame]');
      }

      for (const [name, frame] of Object.entries(frames)) {
        expect(frame, name).not.toBeNull();
        expect(frame!.width, `${name}: the edge has width`).toBeGreaterThan(0);
        expect(contrast(frame!.edge, frame!.host), `${name}: edge against its surface`).toBeGreaterThanOrEqual(3);
        expect(contrast(frame!.edge, frame!.fill), `${name}: edge against its own fill`).toBeGreaterThanOrEqual(3);
      }
    });

    test('the auth links read on the card, and the refused-sign-in card stays clean for axe', async ({ page }) => {
      await page.goto('/login');
      await settle(page);
      const links = await page.evaluate(() => [...document.querySelectorAll('.auth-links a')].map((a) => ({
        color: getComputedStyle(a).color,
        card: getComputedStyle(document.querySelector('.auth-card')!).backgroundColor,
        underline: getComputedStyle(a).textDecorationLine,
      })));
      expect(links.length).toBeGreaterThanOrEqual(2);
      for (const link of links) {
        expect(contrast(link.color, link.card)).toBeGreaterThanOrEqual(4.5);
        expect(link.underline).toContain('underline');
      }
      const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
      expect(axe.violations.map((v) => v.id)).toEqual([]);
    });

    test('a primary button hovers within its own hue', async ({ page }, info) => {
      test.skip(info.project.name === 'mobile', 'hover is a pointer state; the phone project emulates touch');
      await page.goto('/login');
      await settle(page);
      const button = page.locator('.auth-form .btn');
      await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
      const rest = await button.evaluate((b) => getComputedStyle(b).backgroundColor);
      await button.hover();
      // Mid-transition Chromium serializes the interpolated fill as oklab();
      // wait for the settled value.
      await expect.poll(async () => {
        const fill = await button.evaluate((b) => getComputedStyle(b).backgroundColor);
        return fill !== rest && !fill.startsWith('oklab');
      }).toBe(true);
      const hover = await button.evaluate((b) => ({ fill: getComputedStyle(b).backgroundColor, ink: getComputedStyle(b).color }));

      const drift = Math.abs(hue(hover.fill) - hue(rest));
      expect(Math.min(drift, 360 - drift), `hover ${hover.fill} leaves the hue of ${rest}`).toBeLessThan(12);
      expect(contrast(hover.ink, hover.fill)).toBeGreaterThanOrEqual(4.5);
      await shot(page, `${scheme}-button-hover`, '.auth-form .btn');
    });

    test('the guest note marks its link with more than colour', async ({ page }) => {
      await page.goto('/');
      await settle(page);
      const link = page.locator('.directory-guest-note a');
      await expect(link).toHaveCSS('text-decoration-line', 'underline');
      const axe = await new AxeBuilder({ page }).include('.directory-guest-note').withTags(['wcag2a', 'wcag2aa']).analyze();
      expect(axe.violations.map((v) => v.id)).toEqual([]);
      await shot(page, `${scheme}-guest-note`, '.directory-guest-note');
    });
  });
}

test.describe('login and logout polish, operator branding', () => {
  test.use({ reducedMotion: 'reduce' });

  // /brand.css overrides --accent (and never --brand-hover), so the old hover
  // turned every branded button evergreen, in both registers.
  test('a branded primary button hovers within its own hue', async ({ page }, info) => {
    test.skip(info.project.name === 'mobile', 'hover is a pointer state; the phone project emulates touch');
    setBrandPrimary('#7c3aed');
    try {
      await page.goto('/login');
      await settle(page);
      await expect(page.locator('link[href^="/brand.css"]')).toHaveCount(1);
      const button = page.locator('.auth-form .btn');
      await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
      const rest = await button.evaluate((b) => getComputedStyle(b).backgroundColor);
      expect(rest).toBe('rgb(124, 58, 237)');
      await button.hover();
      await expect.poll(async () => {
        const fill = await button.evaluate((b) => getComputedStyle(b).backgroundColor);
        return fill !== rest && !fill.startsWith('oklab');
      }).toBe(true);
      const fill = await button.evaluate((b) => getComputedStyle(b).backgroundColor);
      const drift = Math.abs(hue(fill) - hue(rest));
      expect(Math.min(drift, 360 - drift), `branded hover ${fill}`).toBeLessThan(12);
      await shot(page, 'branded-button-hover', '.auth-form .btn');
    } finally {
      setBrandPrimary('');
    }
  });
});
