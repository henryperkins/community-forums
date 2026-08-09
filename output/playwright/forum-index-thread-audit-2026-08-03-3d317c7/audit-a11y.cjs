const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const AxeBuilder = require('@axe-core/playwright').default;

const baseUrl = 'http://localhost:8027';
const outFile = path.join(__dirname, 'a11y-results.json');

async function scan(page, name, url, viewport) {
  await page.setViewportSize(viewport);
  await page.goto(`${baseUrl}${url}`, { waitUntil: 'networkidle' });
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
    .analyze();

  return {
    name,
    url: page.url(),
    viewport,
    violations: results.violations.map((violation) => ({
      id: violation.id,
      impact: violation.impact,
      help: violation.help,
      helpUrl: violation.helpUrl,
      nodes: violation.nodes.map((node) => ({
        target: node.target,
        html: node.html,
        failureSummary: node.failureSummary,
      })),
    })),
  };
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const report = [];

  const guest = await browser.newContext({ colorScheme: 'light' });
  const guestPage = await guest.newPage();
  report.push(await scan(guestPage, 'forum-index-guest-desktop-light', '/', { width: 1266, height: 854 }));
  report.push(await scan(guestPage, 'forum-index-guest-mobile-light', '/', { width: 390, height: 844 }));
  report.push(await scan(guestPage, 'board-general-guest-desktop-light', '/c/general', { width: 1266, height: 854 }));
  report.push(await scan(guestPage, 'thread-guest-desktop-light', '/t/30-how-should-a-durable-forum-thread-read', { width: 1266, height: 854 }));
  report.push(await scan(guestPage, 'thread-guest-mobile-light', '/t/30-how-should-a-durable-forum-thread-read', { width: 390, height: 844 }));
  await guest.close();

  const dark = await browser.newContext({ colorScheme: 'dark' });
  const darkPage = await dark.newPage();
  report.push(await scan(darkPage, 'forum-index-guest-mobile-dark', '/', { width: 390, height: 844 }));
  report.push(await scan(darkPage, 'thread-guest-desktop-dark', '/t/30-how-should-a-durable-forum-thread-read', { width: 1266, height: 854 }));
  await dark.close();

  const member = await browser.newContext({ colorScheme: 'light' });
  const memberPage = await member.newPage();
  await memberPage.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
  await memberPage.getByLabel('Email').fill('alice@retro.test');
  await memberPage.getByLabel('Password').fill('password123');
  await memberPage.getByRole('button', { name: /log in/i }).click();
  await memberPage.waitForLoadState('networkidle');
  const skip = memberPage.getByRole('button', { name: 'Skip' });
  if (await skip.count()) {
    await skip.click();
  }
  report.push(await scan(memberPage, 'thread-member-desktop-light', '/t/30-how-should-a-durable-forum-thread-read', { width: 1266, height: 854 }));
  report.push(await scan(memberPage, 'thread-member-mobile-light', '/t/30-how-should-a-durable-forum-thread-read', { width: 390, height: 844 }));
  await member.close();

  await browser.close();
  fs.writeFileSync(outFile, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
  console.log(JSON.stringify(report.map(({ name, violations }) => ({
    name,
    violations: violations.map(({ id, impact, nodes }) => ({ id, impact, nodes: nodes.length })),
  })), null, 2));
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
