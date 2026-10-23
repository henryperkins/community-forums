import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/settings-audit-remediation-2026-10-22');

function fixture(command = 'reset', extension = false) {
  return JSON.parse(execFileSync('php', [
    `tests/browser/${extension ? 'settings-audit-remediation' : 'account-settings-repairs'}-fixture.php`, command,
  ], { cwd: root, env: { ...process.env, APP_ENV: 'test', MAIL_DRIVER: 'array' }, encoding: 'utf8' }));
}

async function login(page: Page) {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('settings-repair@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await expect(page).not.toHaveURL(/\/login/);
}

async function render(page: Page, theme: string, textSize = 16) {
  await page.mouse.move(0, 0);
  await page.locator('html').evaluate((html, state) => {
    html.setAttribute('data-theme', state.theme);
    html.style.fontSize = `${state.textSize}px`;
  }, { theme, textSize });
  await page.evaluate(async () => {
    await document.fonts.ready;
    await Promise.all(document.getAnimations().map(animation => animation.finished.catch(() => {})));
  });
}

async function capture(page: Page, info: TestInfo, name: string, metrics?: unknown) {
  const dir = path.join(evidence, info.project.name);
  fs.mkdirSync(dir, { recursive: true });
  if (metrics !== undefined) fs.writeFileSync(path.join(dir, `${name}.json`), JSON.stringify(metrics, null, 2) + '\n');
  await page.screenshot({ path: path.join(dir, `${name}.png`), fullPage: true });
}

async function fits(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
  const outside = await page.locator('.settings-pane input:visible, .settings-pane select:visible, .settings-pane button:visible').evaluateAll(nodes => nodes.filter(node => {
    const box = node.getBoundingClientRect();
    return box.left < -1 || box.right > document.documentElement.clientWidth + 1;
  }).map(node => node.outerHTML.slice(0, 160)));
  expect(outside).toEqual([]);
}

async function boundaryContrast(page: Page) {
  return page.locator('.settings-pane input:visible').evaluateAll(inputs => {
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 1;
    const context = canvas.getContext('2d')!;
    const rgba = (color: string) => {
      context.clearRect(0, 0, 1, 1);
      context.fillStyle = color;
      context.fillRect(0, 0, 1, 1);
      return Array.from(context.getImageData(0, 0, 1, 1).data).map((value, i) => i === 3 ? value / 255 : value);
    };
    const over = (front: number[], back: number[]) => [
      ...front.slice(0, 3).map((value, i) => value * front[3] + back[i] * (1 - front[3])), 1,
    ];
    const background = (node: Element | null): number[] => node
      ? over(rgba(getComputedStyle(node).backgroundColor), background(node.parentElement)) : [255, 255, 255, 1];
    const luminance = (color: number[]) => color.slice(0, 3).map(value => {
      const normalized = value / 255;
      return normalized <= 0.04045 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
    }).reduce((sum, value, i) => sum + value * [0.2126, 0.7152, 0.0722][i], 0);
    const contrast = (a: number[], b: number[]) => {
      const values = [luminance(a), luminance(b)].sort((x, y) => y - x);
      return (values[0] + 0.05) / (values[1] + 0.05);
    };
    return inputs.map(input => {
      const css = getComputedStyle(input);
      const fill = background(input);
      const surrounding = background(input.parentElement);
      const border = over(rgba(css.borderTopColor), fill);
      return {
        name: (input as HTMLInputElement).name, value: (input as HTMLInputElement).value,
        focused: document.activeElement === input, border: css.borderTopColor,
        borderWidth: parseFloat(css.borderTopWidth), borderStyle: css.borderTopStyle,
        fill: css.backgroundColor, insideContrast: contrast(border, fill), outsideContrast: contrast(border, surrounding),
      };
    });
  });
}

test.beforeEach(({ }, info) => {
  test.skip(info.project.name !== 'desktop', 'the desktop project drives the explicit viewport and text-resize matrix');
  fixture();
  fixture('reset', true);
});

test.describe('settings audit native rendering', () => {
  test.use({ javaScriptEnabled: false });

  test('subscription controls retain readable widths and stacked actions at 320px with doubled text', async ({ page }, info) => {
    fixture('delivery-on');
    await login(page);
    await page.setViewportSize({ width: 320, height: 900 });
    await page.goto('/settings/notifications');
    for (const theme of ['light', 'dark']) {
      await render(page, theme, 32);
      const measurements = await page.locator('.account-subscription-row').first().evaluate(row => {
        const form = row.querySelector('form')!;
        const save = form.querySelector('button')!;
        const saveStyle = getComputedStyle(save);
        const text = document.createRange();
        text.selectNodeContents(save);
        const textBounds = text.getBoundingClientRect();
        const lineTops = Array.from(text.getClientRects()).filter(rect => rect.width > 0)
          .map(rect => Math.round(rect.top));
        const box = (node: Element) => { const r = node.getBoundingClientRect(); return { width: r.width, height: r.height, top: r.top, bottom: r.bottom }; };
        return {
          row: box(row), form: box(form), select: box(form.querySelector('select')!), save: box(save),
          off: box(row.querySelector('form:last-child')!), saveTextLines: new Set(lineTops).size,
          saveTextHeight: textBounds.height, saveFontSize: parseFloat(saveStyle.fontSize),
          saveVerticalEdges: ['paddingTop', 'paddingBottom', 'borderTopWidth', 'borderBottomWidth']
            .reduce((sum, property) => sum + parseFloat(saveStyle[property as keyof CSSStyleDeclaration] as string), 0),
        };
      });
      await capture(page, info, `subscription-320-double-${theme}`, measurements);
      await fits(page);
      expect(measurements.form.width).toBeGreaterThanOrEqual(measurements.row.width * 0.9);
      expect(measurements.select.width).toBeGreaterThanOrEqual(measurements.form.width * 0.9);
      expect(measurements.select.width).toBeGreaterThan(120);
      expect(measurements.save.width).toBeGreaterThanOrEqual(measurements.form.width * 0.9);
      for (const control of [measurements.form, measurements.select, measurements.save]) {
        expect(control.width).toBeLessThanOrEqual(measurements.row.width + 1);
      }
      // Computed line-height can legitimately be "normal". Count the actual
      // rendered text fragments so a tall, one-letter-per-line button fails.
      expect(measurements.saveTextLines).toBeGreaterThan(0);
      expect(measurements.saveTextLines).toBeLessThanOrEqual(3);
      expect(measurements.save.height).toBeLessThanOrEqual(
        measurements.saveTextHeight + measurements.saveVerticalEdges + measurements.saveFontSize,
      );
      expect(measurements.off.top).toBeGreaterThanOrEqual(measurements.form.bottom - 1);
    }
    const form = page.locator('.account-subscription-row > form').first();
    await form.locator('select[name="frequency"]').selectOption('instant');
    await form.getByRole('button', { name: /^Save subscription for / }).click();
    await expect(form.locator('select[name="frequency"]')).toHaveValue('instant');
    expect(fixture('inspect').subscription.frequency).toBe('instant');
  });

  test('connected providers and long sessions keep text columns readable as chips and actions wrap', async ({ page }, info) => {
    test.setTimeout(90_000);
    fixture('linked', true);
    await login(page);
    fixture('long-sessions', true);
    for (const [width, textSize] of [[390, 16], [320, 32]]) {
      await page.setViewportSize({ width, height: 900 });
      for (const route of ['connections', 'sessions']) {
        await page.goto(`/settings/${route}`);
        if (route === 'sessions') {
          await expect(page.getByText('Samsung Internet on Android', { exact: true })).toBeVisible();
          for (const summary of await page.locator('.session-agent-details > summary').all()) await summary.click();
        }
        for (const theme of ['light', 'dark']) {
          await render(page, theme, textSize);
          const rows = await page.locator('.account-ruled-row').evaluateAll(nodes => nodes.map(row => {
            const main = row.querySelector('.account-row-main')!;
            const rect = main.getBoundingClientRect();
            const name = main.querySelector('.account-row-name')!;
            const text = name.firstChild!;
            const range = document.createRange();
            range.selectNodeContents(text);
            const textBottom = range.getBoundingClientRect().bottom;
            const nestedChip = name.querySelector('.account-state-chip');
            return {
              rowWidth: row.getBoundingClientRect().width, mainWidth: rect.width, mainBottom: rect.bottom,
              name: text.textContent, textBottom, nestedChipTop: nestedChip?.getBoundingClientRect().top ?? null,
              trailingTops: Array.from(row.querySelectorAll(':scope > .account-state-chip, :scope > form, :scope > a, :scope > .muted')).map(node => node.getBoundingClientRect().top),
            };
          }));
          await capture(page, info, `${route}-${width}-${textSize}-${theme}`, rows);
          await fits(page);
          expect(rows.length).toBeGreaterThan(0);
          for (const row of rows) {
            expect(row.mainWidth, `${route}: ${row.name}`).toBeGreaterThanOrEqual(row.rowWidth * 0.7);
            for (const top of row.trailingTops) expect(top).toBeGreaterThanOrEqual(row.mainBottom - 1);
            if (row.nestedChipTop !== null) expect(row.nestedChipTop).toBeGreaterThanOrEqual(row.textBottom - 1);
          }
        }
      }
    }
  });

  test('empty security fields have visible boundaries in both themes and forced colors without heading overflow', async ({ page }, info) => {
    await login(page);
    await page.setViewportSize({ width: 320, height: 900 });
    await page.goto('/settings/security');
    for (const theme of ['light', 'dark']) {
      await render(page, theme, 32);
      const fields = await boundaryContrast(page);
      const headings = await page.locator('.settings-pane .scribe-panel-head').evaluateAll(nodes => nodes.map(node => {
        const range = document.createRange();
        range.selectNodeContents(node);
        const panel = node.closest('.scribe-panel')!.getBoundingClientRect();
        return { text: node.textContent, panelLeft: panel.left, panelRight: panel.right, fragments: Array.from(range.getClientRects()).map(r => ({ left: r.left, right: r.right })) };
      }));
      await capture(page, info, `security-320-double-${theme}`, { fields, headings });
      await fits(page);
      expect(fields.length).toBeGreaterThanOrEqual(4);
      for (const field of fields) {
        expect(field.value).toBe('');
        expect(field.focused).toBe(false);
        expect(field.borderWidth).toBeGreaterThanOrEqual(1);
        expect(field.insideContrast, field.name).toBeGreaterThanOrEqual(3);
        expect(field.outsideContrast, field.name).toBeGreaterThanOrEqual(3);
      }
      for (const heading of headings) for (const rect of heading.fragments) {
        expect(rect.left).toBeGreaterThanOrEqual(heading.panelLeft);
        expect(rect.right, heading.text ?? '').toBeLessThanOrEqual(heading.panelRight);
      }
    }
    await page.emulateMedia({ forcedColors: 'active' });
    const fields = await boundaryContrast(page);
    await capture(page, info, 'security-forced-colors', fields);
    for (const field of fields) {
      expect(field.borderStyle).toBe('solid');
      expect(field.borderWidth).toBeGreaterThanOrEqual(1);
      expect(field.insideContrast).toBeGreaterThanOrEqual(3);
      expect(field.outsideContrast).toBeGreaterThanOrEqual(3);
    }
    const input = page.locator('input[name="current_password"]').first();
    await input.focus();
    await expect(input).toHaveCSS('outline-style', 'solid');
    await capture(page, info, 'security-forced-colors-focus');
  });

  test('native settings chooser omits the unavailable replay button', async ({ page }, info) => {
    await login(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/settings/account');
    const chooser = page.locator('[data-settings-mobile-nav]');
    await chooser.locator('summary').click();
    await expect(chooser).toHaveAttribute('open', '');
    await expect(page.locator('[data-tour-replay]')).toHaveCount(2);
    await expect(page.locator('[data-tour-replay]:visible')).toHaveCount(0);
    await capture(page, info, 'tour-native-hidden');
    await chooser.getByRole('link', { name: 'Security', exact: true }).click();
    await expect(page).toHaveURL(/\/settings\/security$/);
  });

  test('passwordless shared password fields keep visible boundaries on security and connections', async ({ page }, info) => {
    await login(page);
    fixture('passwordless-linked', true);
    await page.setViewportSize({ width: 320, height: 900 });
    for (const route of ['security', 'connections']) {
      await page.goto(`/settings/${route}`);
      await expect(page.locator('#set-password input[type="password"]')).toHaveCount(2);
      for (const theme of ['light', 'dark']) {
        await render(page, theme, 32);
        const fields = await boundaryContrast(page);
        await capture(page, info, `${route}-passwordless-${theme}`, fields);
        await fits(page);
        for (const field of fields) {
          expect(field.value).toBe('');
          expect(field.focused).toBe(false);
          expect(field.borderWidth).toBeGreaterThanOrEqual(1);
          expect(field.insideContrast, field.name).toBeGreaterThanOrEqual(3);
          expect(field.outsideContrast, field.name).toBeGreaterThanOrEqual(3);
        }
      }
    }
  });

  test('connections copy reflects password availability before and after setting one', async ({ page }, info) => {
    await login(page);
    fixture('linked', true);
    await page.goto('/settings/connections');
    await expect(page.locator('.settings-pane')).toContainText('You can also sign in with your email and password.');
    await expect(page.locator('form[action="/settings/connections/set-password"]')).toHaveCount(0);
    await capture(page, info, 'connections-password-copy');
    fixture('passwordless-linked', true);
    await page.reload();
    await expect(page.locator('.settings-pane')).toContainText('Set a password below to add email and password sign-in.');
    await expect(page.locator('.settings-pane')).not.toContainText('You can also sign in with your email and password.');
    const form = page.locator('form[action="/settings/connections/set-password"]');
    await expect(form).toBeVisible();
    await capture(page, info, 'connections-passwordless-copy');
    await form.locator('input[name="new_password"]').fill('correct-horse-settings-audit');
    await form.locator('input[name="new_password_confirm"]').fill('correct-horse-settings-audit');
    await form.getByRole('button', { name: 'Set password', exact: true }).click();
    await expect(form).toHaveCount(0);
    await expect(page.locator('.settings-pane')).toContainText('You can also sign in with your email and password.');
    expect(fixture('inspect').has_password).toBe(true);
  });
});

test('a blocked tour script leaves replay unavailable while native settings navigation works', async ({ page }, info) => {
  let blocked = 0;
  await page.route(/\/assets\/(?:dist\/)?tour[^/]*\.js(?:\?|$)/, route => { blocked++; return route.abort(); });
  await login(page);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/settings/account');
  await page.locator('[data-settings-mobile-nav] > summary').click();
  expect(blocked).toBeGreaterThan(0);
  await expect(page.locator('[data-tour-replay]:visible')).toHaveCount(0);
  await capture(page, info, 'tour-script-blocked-hidden');
  await page.locator('[data-settings-mobile-nav]').getByRole('link', { name: 'Security', exact: true }).click();
  await expect(page).toHaveURL(/\/settings\/security$/);
});

test('an unavailable fetch API leaves replay hidden even when the tour script loads', async ({ page }, info) => {
  await page.addInitScript(() => Object.defineProperty(window, 'fetch', { value: undefined, configurable: true }));
  let loaded = 0;
  page.on('response', response => {
    if (/\/assets\/(?:dist\/)?tour[^/]*\.js(?:\?|$)/.test(response.url()) && response.ok()) loaded++;
  });
  await login(page);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/settings/account');
  await page.locator('[data-settings-mobile-nav] > summary').click();
  expect(loaded).toBeGreaterThan(0);
  expect(await page.evaluate(() => typeof window.fetch)).toBe('undefined');
  await expect(page.locator('[data-tour-replay]')).toHaveCount(2);
  await expect(page.locator('[data-tour-replay]:visible')).toHaveCount(0);
  await capture(page, info, 'tour-fetch-unavailable-hidden');
});

test('successful tour enhancement reveals an operable replay control and restores focus', async ({ page }, info) => {
  await login(page);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/settings/account');
  await page.locator('[data-settings-mobile-nav] > summary').click();
  const replay = page.locator('[data-settings-mobile-nav] [data-tour-replay]');
  await expect(replay).toBeVisible();
  const [response] = await Promise.all([
    page.waitForResponse(response => new URL(response.url()).pathname === '/onboarding/replay'), replay.click(),
  ]);
  expect(response.ok()).toBe(true);
  await expect(page.getByRole('dialog')).toBeVisible();
  await expect(page.getByRole('dialog').getByRole('button', { name: 'Next', exact: true })).toBeFocused();
  await capture(page, info, 'tour-enhanced-replay');
  await page.keyboard.press('Escape');
  await expect(page.getByRole('dialog')).toHaveCount(0);
  await expect(replay).toBeFocused();
});
