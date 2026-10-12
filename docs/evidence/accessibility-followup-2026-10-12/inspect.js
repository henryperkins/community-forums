async page => {
  const base = new URL(page.url()).origin;
  const coarse = await page.evaluate(() => matchMedia('(pointer: coarse)').matches);
  const name = coarse ? 'touch' : 'mouse';
  const out = 'docs/evidence/accessibility-followup-2026-10-12';
  const results = { pointer: name, checks: [], screenshots: [] };
  function assert(condition, message) {
    if (!condition) throw new Error(message);
  }
  async function visit(route) {
    const response = await page.goto(base + route);
    assert(response.status() === 200, `${route}: HTTP ${response.status()}`);
    await page.evaluate(() => document.fonts.ready);
    const skip = page.getByRole('button', { name: 'Skip', exact: true });
    if (await skip.isVisible()) await skip.click();
  }
  async function login(email) {
    await page.context().clearCookies();
    await visit('/login');
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password123');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(url => !url.pathname.startsWith('/login'));
  }
  async function appearance(theme, density = 'comfortable') {
    await page.evaluate(({ theme, density }) => {
      document.documentElement.dataset.theme = theme;
      document.documentElement.dataset.density = density;
    }, { theme, density });
  }
  async function shot(label) {
    const file = `${name}-${label}.png`;
    await page.screenshot({ path: `${out}/${file}` });
    results.screenshots.push(file);
  }
  async function noOverflow(label) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
    assert(overflow <= 1, `${label}: page overflow ${overflow}px`);
  }
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await login('erestor@retro.test');
  for (const width of coarse ? [320, 390] : [1280]) {
    await page.setViewportSize({ width, height: 900 });
    await visit('/inbox?scope=for_you&order=active');
    for (const theme of ['light', 'dark']) {
      for (const density of ['comfortable', 'compact']) {
        await appearance(theme, density);
        await noOverflow(`inbox ${width} ${theme} ${density}`);
        const geometry = await page.locator('[data-inbox-row]').evaluateAll(rows => {
          const root = parseFloat(getComputedStyle(document.documentElement).fontSize);
          return rows.map(row => {
            const group = row.querySelector('.thread-row-chips');
            const bounds = group.getBoundingClientRect();
            const chips = [...group.querySelectorAll('.chip')].map(chip => {
              const box = chip.getBoundingClientRect(), style = getComputedStyle(chip);
              return {
                reason: chip.classList.contains('chip-reason'), text: chip.textContent.trim(),
                size: parseFloat(style.fontSize), floor: root * .7,
                transform: style.textTransform,
                fits: box.left >= bounds.left - 1 && box.right <= bounds.right + 1,
                unclipped: chip.scrollHeight <= chip.clientHeight + 1,
              };
            });
            return { height: row.getBoundingClientRect().height, chips };
          });
        });
        const chips = geometry.flatMap(row => row.chips);
        assert(geometry.length >= 8, 'Inbox must exercise a populated queue');
        const reasons = chips.filter(chip => chip.reason), statuses = chips.filter(chip => !chip.reason);
        assert(reasons.length > 0 && statuses.length > 0, 'Missing reason/status coverage');
        for (const chip of chips) {
          assert(chip.size >= chip.floor - .01, `Below type floor: ${chip.text}`);
          assert(chip.fits && chip.unclipped, `Clipped chip: ${chip.text}`);
        }
        assert(Math.min(...reasons.map(chip => chip.size)) > Math.max(...statuses.map(chip => chip.size)), 'Reason must be larger than status');
        assert(reasons.every(chip => chip.transform === 'none'), 'Reason must retain sentence case');
        results.checks.push({ surface: 'inbox', width, theme, density, rows: geometry.length,
          reasonSize: reasons[0].size, statusSize: statuses[0].size,
          rowHeights: [...new Set(geometry.map(row => row.height))] });
        await shot(`inbox-${width}-${theme}-${density}`);
      }
    }
  }

  // Real recipient selection and edge taps, including adjacent wrapped rows.
  for (const width of coarse ? [320, 390] : [1280]) {
    await page.setViewportSize({ width, height: 900 });
    await visit('/messages/new');
    const field = page.locator('.dm-compose .dm-to-input');
    for (const username of ['bob', 'carol', 'alice', 'galadriel']) {
      await field.fill(username);
      await page.locator('.dm-compose [role=option]').filter({ hasText: '@' + username }).first().click();
    }
    await field.fill('');
    await field.press('Tab');
    const chips = page.locator('.dm-compose .dm-chip');
    assert(await chips.count() === 4, 'Four recipients must be selected');
    const geometry = await chips.evaluateAll(elements => elements.map(chip => {
      const c = chip.getBoundingClientRect();
      const b = chip.querySelector('button').getBoundingClientRect();
      return { chip: { x: c.x, y: c.y, width: c.width, height: c.height },
        button: { x: b.x, y: b.y, width: b.width, height: b.height } };
    }));
    for (const [index, item] of geometry.entries()) {
      const b = item.button, c = item.chip;
      assert(b.width >= (coarse ? 44 : 24) && b.height >= (coarse ? 44 : 24), 'Recipient target too small');
      assert(b.x >= c.x && b.y >= c.y && b.x + b.width <= c.x + c.width && b.y + b.height <= c.y + c.height, 'Target leaves recipient chip');
      for (const other of geometry.slice(index + 1)) {
        const q = other.button;
        assert(b.x + b.width <= q.x || q.x + q.width <= b.x || b.y + b.height <= q.y || q.y + q.height <= b.y, 'Recipient targets overlap');
      }
    }
    if (coarse && width === 320) assert(new Set(geometry.map(item => item.chip.y)).size >= 2, 'Must exercise wrapped chips');
    for (const theme of ['light', 'dark']) {
      await appearance(theme);
      await noOverflow(`recipients ${width}`);
      await shot(`recipients-${width}-${theme}`);
    }
    const canonical = page.locator('.dm-compose input[name=to]');
    for (const edge of ['top', 'trailing']) {
      const button = chips.first().locator('button');
      await button.scrollIntoViewIfNeeded();
      const box = await button.boundingBox();
      const x = edge === 'top' ? box.x + box.width / 2 : box.x + box.width - 2;
      const y = edge === 'top' ? box.y + 2 : box.y + box.height / 2;
      const hits = await button.evaluate((button, point) => button.contains(document.elementFromPoint(point.x, point.y)), { x, y });
      assert(hits, `Recipient ${edge} hit intercepted`);
      if (coarse) await page.touchscreen.tap(x, y); else await page.mouse.click(x, y);
    }
    assert(await canonical.inputValue() === 'alice, galadriel', 'Edge taps removed the wrong recipients');
    results.checks.push({ surface: 'recipients', width, geometry, correctEdgeRemovals: true });
  }

  await visit('/u/galadriel');
  const toggles = page.locator('[data-activity-toggle]');
  assert(await toggles.count() > 1, 'Profile needs multiple activity rows');
  const toggle = toggles.first();
  await toggle.scrollIntoViewIfNeeded();
  const activity = await toggle.evaluate(button => {
    const box = button.getBoundingClientRect(), row = button.closest('.profile-activity-line').getBoundingClientRect();
    return { width: box.width, height: box.height, rowHeight: row.height,
      fits: box.top >= row.top && box.bottom <= row.bottom };
  });
  assert(activity.width >= (coarse ? 44 : 28) && activity.height >= (coarse ? 44 : 28) && activity.fits, 'Activity target does not fit its row');
  await toggle.click();
  assert(await toggle.getAttribute('aria-expanded') === 'true', 'Profile disclosure failed to open');
  await toggles.nth(1).click();
  assert(await toggle.getAttribute('aria-expanded') === 'true', 'Neighbour disclosure closed the first');
  for (const theme of ['light', 'dark']) {
    await appearance(theme);
    await noOverflow('profile');
    await page.locator('#recent-activity').screenshot({ path: `${out}/${name}-profile-${theme}.png` });
    results.screenshots.push(`${name}-profile-${theme}.png`);
  }
  results.checks.push({ surface: 'profile', ...activity, independentDisclosures: true });

  await visit('/settings/sessions');
  const state = page.locator('.account-state-chip').first();
  assert(await state.count() === 1, 'Current session state missing');
  results.checks.push({ surface: 'session', size: await state.evaluate(el => parseFloat(getComputedStyle(el).fontSize)) });
  await noOverflow('sessions');
  await shot('sessions');
  return results;
}
