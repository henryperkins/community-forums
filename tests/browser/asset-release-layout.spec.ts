import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'config/assets.json'), 'utf8'));
const output = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/asset-release-retention-2026-09-27');
const browserName = process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium';
test.use({ browserName });

// The server renders a real member page, but its asset references represent
// HTML received before a deployment. All asset responses come from the app's
// actual public directory; no CSS or JavaScript response is mocked.
for (const javaScriptEnabled of [true, false]) {
  test.describe(`${browserName}, JavaScript ${javaScriptEnabled ? 'on' : 'off'}`, () => {
    test.use({ javaScriptEnabled });

    for (const age of [0, 1, 2]) {
      test(`member layout survives HTML from ${age} asset releases ago`, async ({ page }, testInfo) => {
        await page.goto('/login');
        await page.locator('[name=email]').fill('elrond@retro.test');
        await page.locator('[name=password]').fill('password123');
        await page.locator('button[type=submit]').click();
        await page.waitForURL(url => !url.pathname.startsWith('/login'));
        const skipTour = page.getByRole('button', { name: 'Skip', exact: true });
        if (await skipTour.isVisible()) await skipTour.click();

        const errors: string[] = [];
        const assetResponses = new Map<string, number>();
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => {
          if (message.type() === 'error' || message.type() === 'warning') errors.push(message.text());
        });
        page.on('response', response => {
          if (new URL(response.url()).pathname.startsWith('/assets/')) {
            assetResponses.set(new URL(response.url()).pathname, response.status());
          }
        });
        const release = manifest.releases[age];
        expect(release, 'Build must retain both preceding releases').toBeTruthy();
        const replacements = Object.fromEntries(['app-style', 'imladris-style', 'app'].map(name => {
          const extension = name === 'app' ? 'js' : 'css';
          const logical = name === 'app' ? 'app.js' : `${name.replace('-style', '')}.css`;
          const old = release.files.find((url: string) =>
            new RegExp(`/assets/dist/${name}-[A-Za-z0-9_-]+\\.${extension}$`).test(url));
          expect(old).toBeTruthy();
          return [manifest.urls[logical], old];
        }));
        await page.route('**/*', async route => {
          if (!route.request().isNavigationRequest() || new URL(route.request().url()).pathname !== '/') {
            return route.continue();
          }
          const response = await route.fetch();
          let html = await response.text();
          for (const [current, previous] of Object.entries(replacements)) html = html.replaceAll(current, previous as string);
          await route.fulfill({ response, body: html });
        });

        const response = await page.goto('/');
        expect(response?.status()).toBe(200);
        await expect(page).toHaveTitle(/.+/);
        await expect(page.locator('body')).toHaveAttribute('data-user', 'elrond');
        await expect(page.getByRole('heading', { name: 'Every board in the valley' })).toBeVisible();
        await expect(page.locator('.skip-link')).toHaveCSS('position', 'absolute');
        expect((await page.locator('.skip-link').boundingBox())!.x).toBeLessThan(0);
        await expect(page.locator('header.forum-bar')).toHaveCSS('height', '62px');
        await expect(page.locator('body')).toHaveCSS('margin', '0px');
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(page.viewportSize()!.width);

        const mobile = testInfo.project.name === 'mobile';
        const rail = page.locator('.board-rail');
        if (mobile && javaScriptEnabled) {
          await expect(rail).toHaveCSS('position', 'fixed');
          expect(await rail.evaluate(element => element.getBoundingClientRect().x)).toBeLessThan(0);
          expect((await page.locator('#main').boundingBox())!.y).toBe(62);
          await expect(page.locator('.nav-toggle:visible')).toHaveCount(1);
          await page.locator('[data-nav-toggle]').click();
          await expect.poll(() => rail.evaluate(element => element.getBoundingClientRect().x)).toBe(0);
          await page.locator('[data-nav-toggle]').click();
          await expect.poll(() => rail.evaluate(element => element.getBoundingClientRect().x)).toBeLessThan(0);
        } else if (mobile) {
          // The explicit scripting:none contract is a styled, stacked rail.
          // Its board links and native account disclosure remain usable.
          await expect(page.locator('.nav-toggle:visible')).toHaveCount(0);
          await expect(rail).toBeVisible();
          const railBox = (await rail.boundingBox())!;
          expect((await page.locator('#main').boundingBox())!.y).toBeGreaterThanOrEqual(railBox.y + railBox.height - 1);
          await page.locator('.identity-menu > summary').click();
          await expect(page.locator('.identity-menu-panel')).toBeVisible();
          await page.locator('.identity-menu > summary').click();
          await expect(page.locator('.identity-menu-panel')).toBeHidden();
        } else {
          await expect(page.locator('.app-shell')).toHaveCSS('display', 'grid');
          await expect(rail).toHaveCSS('position', 'sticky');
          await page.locator('.identity-menu > summary').click();
          await expect(page.locator('.identity-menu-panel')).toBeVisible();
          await page.locator('.identity-menu > summary').click();
          await expect(page.locator('.identity-menu-panel')).toBeHidden();
        }

        for (const url of Object.values(replacements) as string[]) {
          if (url.endsWith('.js') && !javaScriptEnabled) continue;
          expect(assetResponses.get(url), `Asset requested by the rendered page: ${url}`).toBe(200);
        }
        expect([...assetResponses].filter(([, status]) => status >= 400)).toEqual([]);
        expect(errors).toEqual([]);
        if (age === 1) {
          fs.mkdirSync(output, { recursive: true });
          await page.screenshot({ path: path.join(output,
            `${testInfo.project.name}-${browserName}-${javaScriptEnabled ? 'js' : 'no-js'}.png`) });
        }
      });
    }
  });
}
