const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = 'http://localhost:8027';
const threadPath = '/t/30-how-should-a-durable-forum-thread-read';
const outFile = path.join(__dirname, 'interaction-results.json');

async function login(page, email) {
  await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill('password123');
  await page.getByRole('button', { name: /log in/i }).click();
  await page.waitForLoadState('networkidle');
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.count()) {
    await skip.click();
  }
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const results = {};

  const context = await browser.newContext({ colorScheme: 'light' });
  const page = await context.newPage();
  await login(page, 'alice@retro.test');

  await page.setViewportSize({ width: 1266, height: 854 });
  await page.goto(`${baseUrl}/u/alice`, { waitUntil: 'networkidle' });
  results.avatarProfile = await page.evaluate(() => ({
    imageCount: document.querySelectorAll('img.avatar-img').length,
    topbarImageCount: document.querySelectorAll('.topbar img.avatar-img').length,
  }));

  await page.goto(`${baseUrl}${threadPath}`, { waitUntil: 'networkidle' });
  results.avatarThread = await page.evaluate(() => ({
    postImageCount: document.querySelectorAll('.post img.avatar-img').length,
    participantImageCount: document.querySelectorAll('.thread-participants img.avatar-img').length,
    topbarImageCount: document.querySelectorAll('.topbar img.avatar-img').length,
    alicePostMonogramText: document.querySelector('#p121 .monogram')?.textContent.trim() || null,
  }));
  results.desktopComposition = await page.evaluate(() => {
    const toRect = (element) => {
      const box = element.getBoundingClientRect();
      return { top: Math.round(box.top), right: Math.round(box.right), bottom: Math.round(box.bottom), left: Math.round(box.left), width: Math.round(box.width), height: Math.round(box.height) };
    };
    const scroll = document.querySelector('.thread-scroll');
    const dock = document.querySelector('.thread-dock');
    return {
      viewportHeight: window.innerHeight,
      threadScroll: { clientHeight: scroll.clientHeight, ...toRect(scroll) },
      composerDock: toRect(dock),
      composerViewportShare: Number((dock.getBoundingClientRect().height / window.innerHeight).toFixed(3)),
    };
  });

  const trigger = page.locator('[data-topic-tools-open]').first();
  await trigger.click();
  const panel = page.locator('[data-topic-tools]');
  const focusSequence = [];
  for (let index = 0; index < 20; index += 1) {
    const state = await page.evaluate(() => ({
      tag: document.activeElement.tagName,
      label: document.activeElement.getAttribute('aria-label'),
      text: (document.activeElement.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 60),
      inPanel: document.querySelector('[data-topic-tools]').contains(document.activeElement),
    }));
    focusSequence.push(state);
    if (!state.inPanel) break;
    await page.keyboard.press('Tab');
  }
  await page.keyboard.press('Escape');
  results.topicToolsKeyboard = {
    initialFocus: focusSequence[0],
    focusSequence,
    escapedPanel: focusSequence.some((item) => !item.inPanel),
    returnedToTrigger: await page.evaluate(() => document.activeElement.hasAttribute('data-topic-tools-open')),
    hiddenAfterEscape: await panel.isHidden(),
  };

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${baseUrl}/c/general`, { waitUntil: 'networkidle' });
  results.boardMobile = await page.evaluate(() => {
    const toRect = (element) => {
      const box = element.getBoundingClientRect();
      return { top: Math.round(box.top), right: Math.round(box.right), bottom: Math.round(box.bottom), left: Math.round(box.left), width: Math.round(box.width), height: Math.round(box.height) };
    };
    const fab = document.querySelector('.fab');
    const lastRow = document.querySelector('.board-topics-list > li:last-child');
    const lastTitle = document.querySelector('.board-topics-list > li:last-child .thread-title');
    const overlaps = (first, second) => {
      const a = first.getBoundingClientRect();
      const b = second.getBoundingClientRect();
      return a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;
    };
    return {
      documentWidth: {
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
      },
      actions: [...document.querySelectorAll('.board-identity-actions button, .board-identity-actions a')]
        .map((element) => ({ text: element.textContent.trim(), ...toRect(element) })),
      activeBoardAriaCurrent: document.querySelector('nav a.active')?.getAttribute('aria-current') || null,
      floatingAction: {
        rect: toRect(fab),
        overlapsLastRow: overlaps(fab, lastRow),
        overlapsLastTitle: overlaps(fab, lastTitle),
      },
    };
  });

  await page.goto(`${baseUrl}${threadPath}`, { waitUntil: 'networkidle' });
  results.threadMobile = await page.evaluate(() => {
    const toRect = (element) => {
      const box = element.getBoundingClientRect();
      return { top: Math.round(box.top), right: Math.round(box.right), bottom: Math.round(box.bottom), left: Math.round(box.left), width: Math.round(box.width), height: Math.round(box.height) };
    };
    const pre = document.querySelector('pre');
    return {
      documentWidth: {
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
      },
      postActionTargets: [...document.querySelectorAll('#p121 .post-toolbar > button, #p121 .post-toolbar > form > button, #p121 .post-toolbar > details > summary')]
        .filter((element) => element.offsetParent !== null)
        .map((element) => ({ ariaLabel: element.getAttribute('aria-label'), ...toRect(element) })),
      codeBlock: {
        scrollWidth: pre.scrollWidth,
        clientWidth: pre.clientWidth,
        tabIndex: pre.tabIndex,
      },
    };
  });

  await page.locator('.thread-scroll').evaluate((element) => { element.scrollTop = 99999; });
  await page.locator('#p127 details.post-menu summary').click();
  results.mobilePostMenu = await page.evaluate(() => {
    const scroll = document.querySelector('.thread-scroll');
    const menu = document.querySelector('#p127 .post-menu-pop');
    const before = {
      scrollTop: Math.round(scroll.scrollTop),
      maxScrollTop: Math.round(scroll.scrollHeight - scroll.clientHeight),
      threadBottom: Math.round(scroll.getBoundingClientRect().bottom),
      menuBottom: Math.round(menu.getBoundingClientRect().bottom),
      fullyVisible: menu.getBoundingClientRect().bottom <= scroll.getBoundingClientRect().bottom,
    };
    scroll.scrollTop = 99999;
    const after = {
      scrollTop: Math.round(scroll.scrollTop),
      maxScrollTop: Math.round(scroll.scrollHeight - scroll.clientHeight),
      threadBottom: Math.round(scroll.getBoundingClientRect().bottom),
      menuBottom: Math.round(menu.getBoundingClientRect().bottom),
      fullyVisible: menu.getBoundingClientRect().bottom <= scroll.getBoundingClientRect().bottom,
    };
    return { beforeExtraScroll: before, afterExtraScroll: after };
  });
  await context.close();

  const noJsContext = await browser.newContext({
    colorScheme: 'light',
    javaScriptEnabled: false,
    viewport: { width: 1266, height: 854 },
  });
  const noJsPage = await noJsContext.newPage();
  await login(noJsPage, 'alice@retro.test');
  await noJsPage.goto(`${baseUrl}${threadPath}`, { waitUntil: 'networkidle' });
  results.noJavaScriptThread = await noJsPage.evaluate(() => {
    const toRect = (element) => {
      const box = element.getBoundingClientRect();
      return { top: Math.round(box.top), right: Math.round(box.right), bottom: Math.round(box.bottom), left: Math.round(box.left), width: Math.round(box.width), height: Math.round(box.height) };
    };
    const scroll = document.querySelector('.thread-scroll');
    const dock = document.querySelector('.thread-dock');
    const tools = document.querySelector('[data-topic-tools]');
    return {
      viewportHeight: window.innerHeight,
      documentHeight: document.documentElement.scrollHeight,
      threadEnhanced: document.querySelector('[data-thread-enhanced]')?.getAttribute('data-thread-enhanced') || null,
      threadScroll: { clientHeight: scroll.clientHeight, scrollHeight: scroll.scrollHeight, ...toRect(scroll) },
      composerDock: toRect(dock),
      topicTools: toRect(tools),
      visibleReadingShare: Number((scroll.clientHeight / window.innerHeight).toFixed(3)),
    };
  });
  await noJsContext.close();

  await browser.close();
  fs.writeFileSync(outFile, `${JSON.stringify(results, null, 2)}\n`, 'utf8');
  console.log(JSON.stringify(results, null, 2));
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
