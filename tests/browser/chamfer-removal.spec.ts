import { expect, test, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Browser evidence for ADR 0033 / ADR 0034 — the chamfered frames are removed.
 *
 * PHPUnit can only assert that a string is present in a stylesheet. What the
 * cascade actually resolves to on a painted page is what this captures, and
 * three of these facts could not be asserted any other way:
 *
 *  - no repainted frame still clips (`clip-path` resolves to `none`), and each
 *    has a real border width and a real corner radius,
 *  - the outer focus ring RENDERS. Under the old octagon `clip-path` cut
 *    everything outside it away, so the `0 0 0 3px var(--focus-ring)` that
 *    `.input-engraved`, `.choice-card` and `.search-query-well` each declared
 *    had never once painted,
 *  - `/compose`'s title field no longer floods solid gold on focus — the
 *    `background:` shorthand used to reset the eight layers' geometry, which
 *    `.input-engraved:focus` then repainted at `auto` size over the whole box.
 */

const REPO_ROOT = path.resolve(__dirname, '..', '..');
const EVIDENCE_DIR = path.resolve(
  REPO_ROOT,
  process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/chamfer-removal',
);

/** Everything a frame's corner is made of. */
const FRAME = (el: Element) => {
  const s = getComputedStyle(el);
  return {
    clip: s.clipPath,
    radius: Number.parseFloat(s.borderTopLeftRadius) || 0,
    width: Number.parseFloat(s.borderTopWidth) || 0,
    color: s.borderTopColor,
    image: s.backgroundImage,
    shadow: s.boxShadow,
    outline: `${s.outlineStyle} ${s.outlineWidth}`,
  };
};

async function login(page: Page, email = 'bob@retro.test'): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await Promise.all([
    page.waitForURL((url) => !url.pathname.endsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) await skip.click();
}

/** A repainted frame: no clip, a real border, a real radius, no gradient edge. */
async function expectPlainFrame(page: Page, selector: string, label: string): Promise<void> {
  const frame = await page.locator(selector).first().evaluate(FRAME);
  expect(frame.clip, `${label}: must not clip`).toBe('none');
  expect(frame.image, `${label}: edge must not be gradient layers`).not.toContain('linear-gradient');
  expect(frame.radius, `${label}: must have a corner radius`).toBeGreaterThan(0);
  // DESIGN.md: nothing holding content is rounded past 12px.
  expect(frame.radius, `${label}: radius must stay within the system scale`).toBeLessThanOrEqual(12);
}

for (const theme of ['light', 'dark'] as const) {
  test.describe(`ADR 0033 / 0034 — ${theme}`, () => {
    // RB_BROWSER_DARK_SURFACES enables feature fixtures; it does not set a theme.
    // Stamp each destination before reading styles, then verify the painted ground.
    async function visit(page: Page, route: string): Promise<void> {
      const response = await page.goto(route);
      expect(response?.status(), route).toBe(200);
      await page.locator('html').evaluate((el, value) => el.setAttribute('data-theme', value), theme);
      await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
      await expect(page.locator('main')).toBeVisible();
      // Auth has an intentionally dark stage in both registers; its card flips.
      const ground = route === '/login' ? '.auth-card' : 'body';
      const color = route === '/login'
        ? (theme === 'dark' ? 'rgb(30, 39, 48)' : 'rgb(250, 246, 236)')
        : (theme === 'dark' ? 'rgb(22, 29, 36)' : 'rgb(245, 239, 225)');
      await expect(page.locator(ground)).toHaveCSS('background-color', color);
    }

    async function shot(page: Page, name: string): Promise<void> {
      await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
      const directory = path.join(EVIDENCE_DIR, theme === 'dark' ? 'twilight' : '', test.info().project.name);
      fs.mkdirSync(directory, { recursive: true });
      // Focusing a field scrolls it into view. Reset scroll so sticky chrome
      // stays at the top of the full-page capture without clearing focus.
      await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
      await page.screenshot({
        path: path.join(directory, `${name}.png`),
        fullPage: true,
        animations: 'disabled',
      });
    }

    const browserErrors: string[] = [];
    test.beforeEach(async ({ page }) => {
      browserErrors.length = 0;
      page.on('pageerror', (error) => browserErrors.push(error.message));
      page.on('console', (message) => {
        if (message.type() !== 'error') return;
        // Pre-existing malformed eye SVG in partials/icon.php; tracked in notes.
        // Keep this narrow so other SVG errors and all JS failures still fail.
        if (message.text() === 'Error: <path> attribute d: Expected number, "… 7-3 7-10 7-10-7z".') return;
        browserErrors.push(message.text());
      });
    });
    test.afterEach(() => expect(browserErrors).toEqual([]));
    test('the auth card is a bordered card, not an octagon', async ({ page }) => {
      await visit(page, '/login');
      await expectPlainFrame(page, '.auth-card', '.auth-card');

      const card = await page.locator('.auth-card').first().evaluate(FRAME);
      expect(card.width, 'auth card carries a real gold rule').toBeGreaterThan(0);
      // The doubled inner octagon went with the outer one.
      const inner = await page.locator('.auth-card').first()
        .evaluate((el) => getComputedStyle(el, '::before').backgroundImage);
      expect(inner).not.toContain('linear-gradient');

      await expectPlainFrame(page, '.input-engraved', '.input-engraved (auth)');
      await shot(page, 'auth-card');
    });

    test('the engraved field shows the outer focus ring the clip-path used to eat', async ({ page }) => {
      await visit(page, '/login');
      const email = page.locator('input[name="email"]');

      // The template autofocuses this field, so a naive first read IS the focused
      // state. Blur it to get the resting frame.
      await email.evaluate((el: HTMLElement) => el.blur());
      const rest = await email.evaluate(FRAME);
      await email.focus();
      const focused = await email.evaluate(FRAME);

      expect(focused.color, 'focus gilds the rule').not.toBe(rest.color);
      // This is the assertion that could not have passed before ADR 0033.
      expect(focused.outline, 'focus renders a real outer outline').not.toContain('none');
      expect(Number.parseFloat(focused.outline.split(' ')[1]), 'and it has width').toBeGreaterThan(0);
      // An OUTER halo: a shadow spread with no `inset` keyword. Asserting merely
      // that the string contains "rgb" would be true of every box-shadow there is.
      const halo = focused.shadow.split(/,(?![^(]*\))/).filter((part) => !part.includes('inset'));
      expect(halo.length, `focus paints an outer halo — got ${focused.shadow}`).toBeGreaterThan(0);
      expect(focused.shadow).not.toBe(rest.shadow);

      await shot(page, 'engraved-field-focus');
    });

    test('the engraved field paints an honest danger border when invalid', async ({ page }) => {
      await visit(page, '/login');
      const email = page.locator('input[name="email"]');
      await email.evaluate((el: HTMLElement) => el.blur());
      const before = await email.evaluate(FRAME);

      const danger = await email.evaluate((el) => {
        el.setAttribute('aria-invalid', 'true');
        const probe = document.createElement('span');
        probe.style.color = 'var(--danger)';
        document.body.appendChild(probe);
        const value = getComputedStyle(probe).color;
        probe.remove();
        return value;
      });
      const after = await email.evaluate(FRAME);

      expect(after.color.replace(/\s/g, '')).toBe(danger.replace(/\s/g, ''));
      expect(after.width, 'a colour is only a frame if the edge has width').toBeGreaterThan(0);
      expect(after.shadow, 'the danger halo is a second, non-colour signal').not.toBe(before.shadow);

      await shot(page, 'engraved-field-invalid');
    });

    test('the account panels, rows and fields are all bordered', async ({ page }) => {
      await login(page);
      await visit(page, '/settings/account');

      await expectPlainFrame(page, '.scribe-panel', '.scribe-panel');
      await expectPlainFrame(page, '.input-engraved', '.input-engraved (account)');

      // .scribe-panel is a panel at rest: a hairline, not an elevation.
      const panel = await page.locator('.scribe-panel').first().evaluate(FRAME);
      expect(panel.width).toBeGreaterThan(0);
      expect(panel.shadow, 'a panel at rest is not elevated').toBe('none');

      const row = page.locator('.field-row').first();
      await expect(row, '.field-row must render here — a silent skip would hide a regression')
        .toBeVisible();
      await expectPlainFrame(page, '.field-row', '.field-row');
      const rest = await row.evaluate(FRAME);
      expect(rest.width, 'the row has a real edge').toBeGreaterThan(0);
      await row.locator('.row-input').first().focus();
      const focused = await row.evaluate(FRAME);
      expect(focused.color, 'the row gilds on focus').not.toBe(rest.color);
      expect(focused.width, 'and does not thicken, which would reflow it').toBe(rest.width);
      expect(focused.shadow, 'and takes the halo').not.toBe(rest.shadow);

      await shot(page, 'account-settings');
    });

    test('the choice cards are bordered and take a real focus outline', async ({ page }) => {
      await login(page);
      await visit(page, '/settings/appearance');
      await expectPlainFrame(page, '.choice-card', '.choice-card');

      const card = page.locator('.choice-card').first();
      await card.locator('input').first().focus();
      const focused = await card.evaluate(FRAME);
      expect(focused.outline, 'focus-within renders the accent outline').not.toContain('none');

      await shot(page, 'appearance-choice-cards');
    });

    // This trio is the one frame that does NOT take a real border: it draws its
    // edge as `inset 0 0 0 1.5px var(--gold-200)` over `border: 0`, and always
    // did. Only the clip-path changed to a radius. Inset rings follow
    // border-radius, so the ring now turns the corner instead of being sliced.
    test('the search well keeps its inset ring, now on a radius', async ({ page }) => {
      await login(page);
      await visit(page, '/search');
      await expectPlainFrame(page, '.search-query-well', '.search-query-well');

      const well = page.locator('.search-query-well');
      // Autofocused, as on /login.
      await well.evaluate((el: HTMLElement) => el.blur());
      const rest = await well.evaluate(FRAME);
      expect(rest.width, 'this frame deliberately has no border').toBe(0);
      expect(rest.shadow, 'the inset ring survives the radius').toContain('inset');
      await well.focus();
      const focused = await well.evaluate(FRAME);
      expect(focused.shadow).not.toBe(rest.shadow);

      await shot(page, 'search-well');
    });

    test('the compose title no longer floods gold on focus', async ({ page }) => {
      await login(page);
      await visit(page, '/compose');

      const title = page.locator('.compose-title-input');
      await expectPlainFrame(page, '.compose-title-input', '.compose-title-input');

      const rest = await title.evaluate(FRAME);
      await title.focus();
      const focused = await title.evaluate(FRAME);

      // The defect: eight gradient layers repainted at `auto` size over the box.
      expect(focused.image, 'focus must not paint a background over the field').not.toContain('linear-gradient');
      expect(focused.image).toBe(rest.image);
      expect(focused.shadow, 'and the focus ring must render').not.toBe(rest.shadow);

      await expectPlainFrame(page, '.compose-board-select', '.compose-board-select');
      await shot(page, 'compose-fields');
    });

    // Regression guard for a specificity trap the repaint walked into once. While
    // the frame was drawn with clip-path + background-image, a bare
    // `.textarea-engraved` was enough to win, because nothing else set those
    // properties. A real `border` competes with `.composer-input, textarea.input`
    // (0,1,1), which outranks a bare class — so on /appeals, whose markup is
    // `<textarea class="input textarea-engraved">`, the engraved textarea silently
    // went --border-soft while the engraved inputs beside it stayed gold.
    test('an engraved textarea is the same register as an engraved input', async ({ page }) => {
      await login(page);
      for (const route of ['/settings/account', '/appeals']) {
        await visit(page, route);
        const frames = await page.evaluate(() => {
          // Synthesise both markup shapes so the check does not depend on the seed
          // happening to have an open appeal.
          const host = document.querySelector('main') ?? document.body;
          const make = (cls: string, tag: 'input' | 'textarea') => {
            const el = document.createElement(tag);
            el.className = cls;
            host.appendChild(el);
            const s = getComputedStyle(el);
            const out = { border: s.borderTopColor, bg: s.backgroundColor, radius: s.borderTopLeftRadius };
            el.remove();
            return out;
          };
          return {
            input: make('input input-engraved', 'input'),
            textareaInput: make('input textarea-engraved', 'textarea'),
            textareaComposer: make('composer-input textarea-engraved', 'textarea'),
          };
        });
        for (const [name, frame] of Object.entries(frames)) {
          if (name === 'input') continue;
          expect(frame.border, `${route}: ${name} border must match the engraved input`)
            .toBe(frames.input.border);
          expect(frame.bg, `${route}: ${name} ground must match the engraved input`)
            .toBe(frames.input.bg);
        }
      }
    });

    test('nothing anywhere on a member surface still clips a corner', async ({ page }) => {
      await login(page);
      for (const route of ['/settings/account', '/settings/account/lifecycle', '/settings/appearance', '/settings/notifications', '/search', '/compose']) {
        await visit(page, route);
        // ::before/::after too — two of the removed octagons were pseudo-elements
        // (the "doubled gold rule" on .auth-card and .scribe-panel), so a sweep
        // over elements alone would not have seen them come back.
        const clipped = await page.evaluate(() => {
          const hits: string[] = [];
          for (const el of document.querySelectorAll<HTMLElement>('body *')) {
            for (const pseudo of [null, '::before', '::after']) {
              const clip = getComputedStyle(el, pseudo).clipPath;
              if (clip !== 'none' && clip.startsWith('polygon')) {
                hits.push(`${el.tagName.toLowerCase()}.${el.className}${pseudo ?? ''}`);
              }
            }
          }
          return hits;
        });
        expect(clipped, `${route} must have no clip-path polygons left`).toEqual([]);
      }
    });
    test('the lifecycle danger panel keeps its red leading rule on rounded corners', async ({ page }) => {
      await login(page);
      await visit(page, '/settings/account/lifecycle');
      await expectPlainFrame(page, '.scribe-panel.danger-zone', 'delete account');
      const frame = await page.locator('.scribe-panel.danger-zone').evaluate((el) => {
        const style = getComputedStyle(el);
        return {
          leadingWidth: parseFloat(style.borderLeftWidth),
          otherWidth: parseFloat(style.borderTopWidth),
          leadingColor: style.borderLeftColor,
          otherColor: style.borderTopColor,
          headingColor: getComputedStyle(el.querySelector('h2')!).color,
        };
      });
      expect(frame.leadingWidth).toBe(3);
      expect(frame.otherWidth).toBeGreaterThan(0);
      expect(frame.leadingColor).toBe(frame.headingColor);
      expect(frame.leadingColor).not.toBe(frame.otherColor);
      await shot(page, 'account-lifecycle');
    });

    test('member notification selects keep their raised ground and inset through focus', async ({ page }) => {
      await login(page);
      await visit(page, '/settings/notifications');
      const select = page.locator('select[name="timezone"]');
      const rest = await select.evaluate(FRAME);
      await expect(select).toHaveCSS('background-color',
        theme === 'dark' ? 'rgb(30, 39, 48)' : 'rgb(250, 246, 236)');
      expect(rest.width).toBeGreaterThan(0);
      expect(rest.shadow).toContain('inset');
      await select.focus();
      const focused = await select.evaluate(FRAME);
      expect(focused.shadow).toContain(rest.shadow);
      expect(focused.shadow).not.toBe(rest.shadow);
      await shot(page, 'notification-select-focus');
    });

    for (const [name, route, selector] of [
      ['content', '/admin/structure', '.admin-content .input'],
      ['roles', '/admin/roles', '.role-form-label .input'],
      ['features', '/admin/custom-emoji', '.admin-features .input'],
      ['audit', '/admin/audit', '.admin-overview-audit .input'],
    ]) {
      test(`admin ${name} fields stay flat through focus and blur`, async ({ page }) => {
        await login(page, 'admin@retro.test');
        await visit(page, route);
        const fields = page.locator(`${selector}:visible`);
        expect(await fields.count(), `${route} must render fields`).toBeGreaterThan(0);
        for (const field of await fields.all()) {
          await field.evaluate((el: HTMLElement) => el.blur());
          await expect(field).toHaveCSS('box-shadow', 'none');
          await field.focus();
          await expect(field).toBeFocused();
          const focused = await field.evaluate(FRAME);
          expect(focused.shadow).not.toBe('none');
          expect(focused.shadow).not.toContain('inset');
          await field.evaluate((el: HTMLElement) => el.blur());
          await expect(field).toHaveCSS('box-shadow', 'none');
        }
        await fields.first().focus();
        await shot(page, `admin-${name}-focus`);
      });
    }
  });
}
