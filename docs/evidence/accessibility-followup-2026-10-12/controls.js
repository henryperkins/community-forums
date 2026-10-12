async page => {
  const base = new URL(page.url()).origin;
  const coarse = await page.evaluate(() => matchMedia('(pointer: coarse)').matches);
  const name = coarse ? 'touch' : 'mouse';
  const out = 'docs/evidence/accessibility-followup-2026-10-12';
  const results = { pointer: name, checks: [], screenshots: [] };
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  function assert(condition, message) { if (!condition) throw new Error(message); }
  async function visit(route) {
    const response = await page.goto(base + route);
    assert(response.status() === 200, `${route}: HTTP ${response.status()}`);
    await page.evaluate(() => document.fonts.ready);
    const skip = page.getByRole('button', { name: 'Skip', exact: true });
    if (await skip.isVisible()) await skip.click();
  }
  async function shot(label) {
    const file = `${name}-${label}.png`;
    await page.screenshot({ path: `${out}/${file}` });
    results.screenshots.push(file);
  }
  async function noOverflow(label) {
    const amount = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
    assert(amount <= 1, `${label}: page overflow ${amount}px`);
  }
  async function theme(value) { await page.evaluate(value => document.documentElement.dataset.theme = value, value); }
  await page.context().clearCookies();
  await visit('/login');
  await page.getByLabel('Email', { exact: true }).fill(coarse ? 'alice@retro.test' : 'admin@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await page.waitForURL(url => !url.pathname.startsWith('/login'));
  await page.setViewportSize({ width: coarse ? 320 : 1280, height: 900 });
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await visit('/c/announcements');
  const boardTypes = await page.locator('.board-identity-facts dt, .thread-mark, .thread-row-status .chip').evaluateAll(elements => elements.map(el => ({
    label: el.textContent.trim(), size: parseFloat(getComputedStyle(el).fontSize),
    floor: parseFloat(getComputedStyle(document.documentElement).fontSize) * .7,
  })));
  assert(boardTypes.length >= 3, 'Need board labels, marks and status pills');
  for (const item of boardTypes) assert(item.size >= item.floor - .01, `Board type below floor: ${item.label}`);
  for (const value of ['light', 'dark']) { await theme(value); await noOverflow('board'); await shot(`board-${value}`); }
  results.checks.push({ surface: 'board', labels: boardTypes });

  await visit('/c/accessibility-review');
  const empty = page.locator('.board-empty-cta');
  assert(await empty.isVisible(), 'Missing empty-board action');
  const emptyBox = await empty.boundingBox();
  if (coarse) assert(emptyBox.height >= 44, 'Empty-board action too short');
  await empty.click();
  assert(await page.locator('#new-topic').evaluate(el => el.open), 'Empty-board action did not open composer');
  await noOverflow('empty board');
  await shot('empty-board-composer');
  results.checks.push({ surface: 'emptyBoard', actionHeight: emptyBox.height, opensComposer: true });

  // Controlled responses isolate the card layout and controls from upload storage.
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWP4z8AAAAMBAQCc479ZAAAAAElFTkSuQmCC', 'base64');
  await page.route('**/media/777', route => route.fulfill({ contentType: 'image/png', body: png }));
  await page.route('**/upload', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, id: 777,
    url: '/media/777', markdown: '![](/media/777)', width: 1, height: 1 }) }));
  const form = page.locator('#new-topic form.composer');
  // Start with an empty body even when an earlier inspection saved a draft.
  await form.locator('textarea[name=body]').fill('');
  await form.locator('input[type=file][data-composer-upload-input]').setInputFiles({ name: 'touch-target.png', mimeType: 'image/png', buffer: png });
  const card = form.locator('.composer-upload-card');
  await card.locator('.composer-upload-status').getByText('Uploaded image', { exact: false }).waitFor();
  const sizes = await card.locator('.composer-upload-actions .btn, .input').evaluateAll(elements => elements.filter(el => !el.hidden).map(el => ({
    label: el.getAttribute('aria-label') || el.textContent.trim(), height: el.getBoundingClientRect().height,
  })));
  assert(sizes.length >= 3, 'Missing upload controls');
  if (coarse) for (const item of sizes) assert(item.height >= 44, `Upload control too short: ${item.label}`);
  for (const value of ['light', 'dark']) { await theme(value); await noOverflow('upload card');
    await card.scrollIntoViewIfNeeded(); await shot(`upload-${value}`); }
  await card.getByRole('textbox', { name: 'Image alt text' }).fill('A descriptive upload fixture');
  await card.getByRole('button', { name: 'Remove touch-target.png', exact: true }).click();
  assert(await card.count() === 0, 'Upload removal failed');
  results.checks.push({ surface: 'upload', controls: sizes, removalWorks: true, responses: 'controlled' });

  await visit('/t/2-share-your-favourite-keyboard-shortcuts');
  const tag = page.locator('.thread-study-tags .tag').first();
  assert(await tag.count() === 1, 'Tag fixture missing');
  const tagSize = await tag.evaluate(el => parseFloat(getComputedStyle(el).fontSize));
  const reference = page.locator('.reference-card .ref-type').first();
  assert(await reference.count() === 1, 'Reference fixture missing');
  const referenceSize = await reference.evaluate(el => parseFloat(getComputedStyle(el).fontSize));
  for (const [role, label] of [['textbox', 'Reason for removal (required)'], ['combobox', 'Report reason'], ['textbox', 'Report details (optional)']]) {
    assert(await page.getByRole(role, { name: label, exact: true, includeHidden: true }).count() > 0, `Missing accessible name: ${label}`);
  }
  const css = await page.locator('link[rel=stylesheet]').evaluateAll(elements => elements.map(el => el.href).filter(url => url.includes('/assets/dist/app-style-')));
  assert(css.length === 1 && css[0].endsWith('/app-style-DxGgpq3E.css'), 'Page must use the rebuilt stylesheet');
  assert((await page.request.get(css[0])).status() === 200, 'Hashed stylesheet missing');
  for (const value of ['light', 'dark']) { await theme(value); await noOverflow('thread'); await shot(`thread-${value}`); }
  results.checks.push({ surface: 'thread', tagSize, referenceSize, namedRemoveAndReportControls: true, stylesheet: css[0] });

  await visit('/drafts');
  const draft = page.locator('.account-draft-chip').first();
  assert(await draft.count() === 1, 'Draft chip missing');
  const draftSize = await draft.evaluate(el => parseFloat(getComputedStyle(el).fontSize));
  for (const value of ['light', 'dark']) { await theme(value); await noOverflow('drafts'); await shot(`drafts-${value}`); }
  results.checks.push({ surface: 'drafts', chipSize: draftSize });
  assert(errors.length === 0, JSON.stringify(errors));
  results.browserErrors = errors;
  return results;
}
