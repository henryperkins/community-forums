import { expect, test, type Locator, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const output = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/browser');

function fixture(command: string) {
  execFileSync('php', ['tests/browser/members-people-remediation-fixture.php', command], {
    cwd: root, env: { ...process.env, APP_ENV: 'test', MAIL_DRIVER: 'array' }, stdio: 'pipe',
  });
}

async function login(page: Page) {
  await page.goto('/login');
  await page.locator('[name=email]').fill('mp-admin@retro.test');
  await page.locator('[name=password]').fill('password123');
  await page.locator('button[type=submit]').click();
  await page.waitForURL(url => url.pathname !== '/login');
}

/**
 * Read the browser's painted pixels. WebKit can report the intended dark CSS
 * background while its native select still paints white, so computed styles
 * and axe alone cannot detect this regression. Decode the screenshot through
 * Canvas without inserting content or changing the tested control's styling.
 */
async function paintedControl(page: Page, control: Locator, screenshotPath: string) {
  const png = await control.screenshot({ path: screenshotPath });
  return page.evaluate(async encoded => {
    const bytes = Uint8Array.from(atob(encoded), character => character.charCodeAt(0));
    const bitmap = await createImageBitmap(new Blob([bytes], { type: 'image/png' }));
    const canvas = document.createElement('canvas');
    canvas.width = bitmap.width; canvas.height = bitmap.height;
    const context = canvas.getContext('2d')!;
    context.drawImage(bitmap, 0, 0); bitmap.close();
    const { data, width, height } = context.getImageData(0, 0, canvas.width, canvas.height);
    const rgb = (x: number, y: number) => Array.from(data.slice((y * width + x) * 4, (y * width + x) * 4 + 3));
    // Blank top-center interior excludes text, border, rounded corners and arrow.
    const colors = new Map<string, number>();
    for (let y = Math.round(height * .12); y < Math.round(height * .20); y++) {
      for (let x = Math.round(width * .47); x < Math.round(width * .53); x++) {
        const key = rgb(x, y).join(','); colors.set(key, (colors.get(key) ?? 0) + 1);
      }
    }
    const background = [...colors].sort((a, b) => b[1] - a[1])[0][0].split(',').map(Number);
    const luminance = (color: number[]) => color.map(value => {
      const channel = value / 255;
      return channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4;
    }).reduce((sum, channel, index) => sum + channel * [.2126, .7152, .0722][index], 0);
    const backgroundLuminance = luminance(background);
    let readableTextPixels = 0;
    let maximumTextContrast = 1;
    // Exclude the border and native/custom arrow from the selected-label region.
    for (let y = Math.round(height * .23); y < Math.round(height * .78); y++) {
      for (let x = Math.round(width * .05); x < Math.round(width * .70); x++) {
        const foregroundLuminance = luminance(rgb(x, y));
        const contrast = (Math.max(foregroundLuminance, backgroundLuminance) + .05)
          / (Math.min(foregroundLuminance, backgroundLuminance) + .05);
        maximumTextContrast = Math.max(maximumTextContrast, contrast);
        if (contrast >= 4.5) readableTextPixels++;
      }
    }
    return { background, maximumTextContrast, readableTextPixels, width, height };
  }, png.toString('base64'));
}

let cookies: Awaited<ReturnType<import('@playwright/test').BrowserContext['cookies']>>;
test.beforeAll(async ({ browser, baseURL }) => {
  fixture('seed');
  const context = await browser.newContext({ baseURL });
  await login(await context.newPage());
  cookies = await context.cookies();
  await context.close();
});
test.afterAll(() => fixture('light'));

for (const theme of ['light', 'dark']) {
  for (const javaScriptEnabled of [true, false]) {
    test(`Members select paint and native filters: ${theme}, JavaScript ${javaScriptEnabled ? 'on' : 'off'}`, async ({ browser, baseURL }, info) => {
      fixture(theme);
      const context = await browser.newContext({
        baseURL, javaScriptEnabled, viewport: info.project.use.viewport,
        deviceScaleFactor: info.project.use.deviceScaleFactor,
        isMobile: info.project.use.isMobile, hasTouch: info.project.use.hasTouch,
      });
      await context.addCookies(cookies);
      const page = await context.newPage();
      const directory = path.join(output, info.project.name, `${theme}-js-${javaScriptEnabled}`);
      fs.mkdirSync(directory, { recursive: true });
      try {
        expect((await page.goto('/admin/users'))!.status()).toBe(200);
        await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
        await page.evaluate(() => document.fonts.ready);
        const form = page.locator('.member-directory-filter-form');
        const surface = await paintedControl(page, form.locator('[name=q]'), path.join(directory, 'search.png'));
        const results = [];
        for (const name of ['role', 'status']) {
          const control = form.locator(`select[name=${name}]`);
          const paint = await paintedControl(page, control, path.join(directory, `${name}.png`));
          results.push({ name, ...paint, expectedBackground: surface.background });
          expect.soft(Math.max(...paint.background.map((value, index) => Math.abs(value - surface.background[index]))), `${name} painted background must match the adjacent themed search input`).toBeLessThanOrEqual(12);
          expect.soft(paint.readableTextPixels, `${name} selected label must visibly paint text with at least 4.5:1 contrast`).toBeGreaterThanOrEqual(8);
        }
        fs.writeFileSync(path.join(directory, 'paint.json'), JSON.stringify(results, null, 2) + '\n');
        await info.attach('painted-select-colors', { body: Buffer.from(JSON.stringify(results, null, 2)), contentType: 'application/json' });
        await page.screenshot({ path: path.join(directory, 'members.png'), fullPage: false });
        const role = form.locator('select[name=role]');
        const status = form.locator('select[name=status]');
        await role.focus(); await page.keyboard.press('Tab');
        await expect(status).toBeFocused();
        await role.selectOption('user'); await status.selectOption('active');
        await form.getByRole('button', { name: 'Apply filters', exact: true }).click();
        await expect(page).toHaveURL(url => url.pathname === '/admin/users' && url.searchParams.get('role') === 'user' && url.searchParams.get('status') === 'active');
        await expect(page.locator('.member-directory-member-link', { hasText: 'mp-target' })).toBeVisible();
        await expect(page.locator('.member-directory-member-link', { hasText: 'mp-admin' })).toHaveCount(0);
        expect((await page.goto('/admin/roles'))!.status()).toBe(200);
        await expect(page.locator('.role-create-form')).toBeVisible();
        await page.screenshot({ path: path.join(directory, 'people.png'), fullPage: false });
      } finally { await context.close(); }
    });
  }
}

test('Members filter keyboard focus is painted in forced colors', async ({ browser, browserName, baseURL }, info) => {
  test.skip(browserName !== 'chromium', 'Chromium provides forced-colors emulation.');
  fixture('light');
  const context = await browser.newContext({
    baseURL, forcedColors: 'active', viewport: info.project.use.viewport,
    deviceScaleFactor: info.project.use.deviceScaleFactor,
    isMobile: info.project.use.isMobile, hasTouch: info.project.use.hasTouch,
  });
  await context.addCookies(cookies);
  const page = await context.newPage();
  const directory = path.join(output, info.project.name, 'forced-colors');
  fs.mkdirSync(directory, { recursive: true });
  try {
    await page.goto('/admin/users');
    await page.evaluate(() => document.fonts.ready);
    const form = page.locator('.member-directory-filter-form');
    await form.locator('[name=q]').focus();
    for (const name of ['role', 'status']) {
      const control = form.locator(`select[name=${name}]`);
      const box = (await control.boundingBox())!;
      const clip = { x: Math.floor(box.x - 6), y: Math.floor(box.y - 6), width: Math.ceil(box.width + 12), height: Math.ceil(box.height + 12) };
      const before = await page.screenshot({ clip, path: path.join(directory, `${name}-before-focus.png`) });
      await page.keyboard.press('Tab');
      await expect(control).toBeFocused();
      const after = await page.screenshot({ clip, path: path.join(directory, `${name}-keyboard-focus.png`) });
      const changedOutsidePixels = await page.evaluate(async ({ before, after, box, clip }) => {
        const decode = async (encoded: string) => {
          const bitmap = await createImageBitmap(new Blob([Uint8Array.from(atob(encoded), character => character.charCodeAt(0))], { type: 'image/png' }));
          const canvas = document.createElement('canvas'); canvas.width = bitmap.width; canvas.height = bitmap.height;
          const context = canvas.getContext('2d')!; context.drawImage(bitmap, 0, 0); bitmap.close();
          return context.getImageData(0, 0, canvas.width, canvas.height);
        };
        const first = await decode(before); const second = await decode(after);
        const scale = first.width / clip.width;
        let changed = 0;
        for (let y = 0; y < first.height; y++) {
          for (let x = 0; x < first.width; x++) {
            const cssX = x / scale + clip.x; const cssY = y / scale + clip.y;
            if (cssX >= box.x - .5 && cssX <= box.x + box.width + .5 && cssY >= box.y - .5 && cssY <= box.y + box.height + .5) continue;
            const offset = (y * first.width + x) * 4;
            if ([0, 1, 2].some(channel => Math.abs(first.data[offset + channel] - second.data[offset + channel]) > 10)) changed++;
          }
        }
        return changed;
      }, { before: before.toString('base64'), after: after.toString('base64'), box, clip });
      await info.attach(`${name}-forced-colors-focus`, { body: Buffer.from(JSON.stringify({ changedOutsidePixels })), contentType: 'application/json' });
      expect.soft(changedOutsidePixels, `${name} needs a visible keyboard focus ring when forced colors suppresses box shadows`).toBeGreaterThan(8);
    }
  } finally { await context.close(); }
});
