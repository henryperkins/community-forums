import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Browser, type BrowserContext, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Slice 6 evidence for AdminContent.dc.html. The spec keeps RetroBoards' real
 * URLs, CSRF POST forms, typed destructive confirmations and roster flows while
 * checking the copied Imladris body metrics at 1280px and 390px.
 */
const REPO_ROOT = path.resolve(__dirname, '..', '..');
const EVIDENCE_DIR = path.resolve(REPO_ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/browser');

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  const bar = page.locator('.admin-bar');
  const narrow = (page.viewportSize()?.width ?? 1280) <= 390;
  const prior = narrow && await bar.count() > 0
    ? await bar.evaluate((element) => {
      const value = (element as HTMLElement).style.position;
      (element as HTMLElement).style.position = 'static';
      return value;
    })
    : null;
  try {
    await page.screenshot({
      path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`),
      fullPage: true,
      animations: 'disabled',
    });
  } finally {
    if (prior !== null) {
      await bar.evaluate((element, value) => {
        if (value) (element as HTMLElement).style.position = value;
        else (element as HTMLElement).style.removeProperty('position');
      }, prior);
    }
  }
}

async function login(page: Page): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.locator('input[name="email"]').fill('admin@retro.test');
  await page.locator('input[name="password"]').fill('password123');
  await page.locator('form[action="/login"] button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) await skip.click();
}

async function expectAxeClean(page: Page, info: TestInfo, register: string): Promise<void> {
  const result = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const violations = result.violations.filter((item) => item.impact === 'serious' || item.impact === 'critical');
  expect(violations, `${info.project.name} ${register} serious/critical axe violations`).toEqual([]);
}

async function exerciseThemeRegisters(page: Page, info: TestInfo, evidenceName: string): Promise<void> {
  for (const { theme, label, colorScheme } of [
    { theme: 'light', label: 'light', colorScheme: 'light' as const },
    { theme: 'dark', label: 'twilight', colorScheme: 'dark' as const },
  ]) {
    await page.emulateMedia({ colorScheme });
    await page.locator('html').evaluate((element, value) => element.setAttribute('data-theme', value), theme);
    await expectAxeClean(page, info, label);
    await shot(page, info, `${evidenceName}-${label}`);
  }
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.locator('html').evaluate((element) => element.setAttribute('data-theme', 'system'));
  await expectAxeClean(page, info, 'system-dark');
}

async function expectNoDocumentOverflow(page: Page): Promise<void> {
  const dimensions = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    document: document.documentElement.scrollWidth,
  }));
  expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport);
}

async function ensureTagFixtures(page: Page, count = 10): Promise<void> {
  for (let index = 1; index <= count; index++) {
    const suffix = String(index).padStart(2, '0');
    const slug = `slice-six-${suffix}`;
    await page.goto(`/admin/tags?q=${slug}&sort=name`);
    if (await page.locator(`input[name="slug"][value="${slug}"]`).count() > 0) continue;

    await page.goto('/admin/tags');
    const form = page.locator('.content-tag-create form');
    await form.locator('input[name="name"]').fill(`Slice Six ${suffix}`);
    await form.locator('input[name="slug"]').fill(slug);
    await form.locator('input[name="description"]').fill(`Catalogue evidence row ${suffix}.`);
    await form.getByRole('button', { name: 'Add tag' }).click();
    await expect(page.getByRole('status')).toContainText('Tag created');
  }
}

test('structure copies the category, board, and add-form anatomy in every register', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/structure');

  await expect(page.getByRole('heading', { level: 1, name: 'Boards & tags' })).toBeVisible();
  await expect(page.locator('.admin-tab.is-active')).toHaveText('Boards & categories');
  await expect(page.locator('.content-structure-intro')).toContainText('Categories group boards; boards are where topics live.');
  await expect(page.locator('.content-structure-intro')).toContainText('Archiving makes a board read-only — its topics stay readable and searchable.');
  await expect(page.locator('.content-structure-intro')).toContainText('Archiving makes a board read-only');
  await expect(page.locator('.content-category-card')).toHaveCount(2);
  await expect(page.locator('.content-board-description', { hasText: 'Talk about anything.' })).toBeVisible();
  await expect(page.locator('a.content-board-link[href="/c/general"]')).toHaveText('#General');
  await expect(page.locator('.content-add-board-card input[name="slug"]')).toHaveAttribute('placeholder', 'derived from the name');

  const metrics = await page.evaluate(() => {
    const head = document.querySelector('.content-category-card .admin-cat-head');
    const categoryArrow = document.querySelector('.content-icon-button-category');
    const boardArrow = document.querySelector('.content-icon-button-board');
    const grid = document.querySelector('.content-board-grid');
    const addCategoryButton = document.querySelector('.content-add-category-form > .btn');
    const addBoardButton = document.querySelector('.content-grid-actions > .btn');
    const slugInput = document.querySelector('.content-board-grid input[name="slug"]');
    const editWindowInput = document.querySelector('.content-edit-window-input');
    if (!(head instanceof HTMLElement) || !(categoryArrow instanceof HTMLElement)
      || !(boardArrow instanceof HTMLElement) || !(grid instanceof HTMLElement)
      || !(addCategoryButton instanceof HTMLElement) || !(addBoardButton instanceof HTMLElement)
      || !(slugInput instanceof HTMLElement) || !(editWindowInput instanceof HTMLElement)) {
      throw new Error('Content design probes are missing');
    }
    const buttonMetric = (element: HTMLElement) => {
      const style = getComputedStyle(element);
      return {
        padding: style.padding,
        fontSize: style.fontSize,
        letterSpacing: style.letterSpacing,
        border: style.borderTopWidth,
      };
    };
    return {
      headPadding: getComputedStyle(head).padding,
      headBackground: getComputedStyle(head).backgroundImage,
      categoryArrow: categoryArrow.getBoundingClientRect().width,
      boardArrow: boardArrow.getBoundingClientRect().width,
      gridColumns: getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length,
      addCategoryButton: buttonMetric(addCategoryButton),
      addBoardButton: buttonMetric(addBoardButton),
      slugFontSize: getComputedStyle(slugInput).fontSize,
      editWindowFontSize: getComputedStyle(editWindowInput).fontSize,
    };
  });
  expect(metrics.headPadding).toBe('13px 18px');
  expect(metrics.headBackground).toContain('linear-gradient');
  expect(metrics.addCategoryButton).toEqual({ padding: '9px 18px', fontSize: '12.8px', letterSpacing: '0.512px', border: '0px' });
  expect(metrics.addBoardButton).toEqual(metrics.addCategoryButton);
  expect(metrics.slugFontSize).toBe('13.76px');
  expect(metrics.editWindowFontSize).toBe('14.08px');

  const rename = page.locator('.content-category-name').first();
  await rename.focus();
  const focusMetrics = await rename.evaluate((element) => {
    const gold = document.createElement('span');
    gold.style.color = 'var(--gold-500)';
    const ring = document.createElement('span');
    ring.style.color = 'var(--focus-ring)';
    document.body.append(gold, ring);
    const style = getComputedStyle(element);
    const values = {
      outline: style.outlineStyle,
      border: style.borderTopColor,
      shadow: style.boxShadow,
      gold: getComputedStyle(gold).color,
      ring: getComputedStyle(ring).color,
    };
    gold.remove();
    ring.remove();
    return values;
  });
  expect(focusMetrics.outline).toBe('none');
  expect(focusMetrics.border).toBe(focusMetrics.gold);
  expect(focusMetrics.shadow).toContain('3px');
  expect(focusMetrics.shadow).toContain(focusMetrics.ring);
  if (info.project.name === 'mobile') {
    expect(metrics.categoryArrow).toBeGreaterThanOrEqual(44);
    expect(metrics.boardArrow).toBeGreaterThanOrEqual(44);
    expect(metrics.gridColumns).toBe(1);
    const tier = await page.locator('[data-admin-tier]').evaluate((element) => ({
      client: element.clientWidth,
      scroll: element.scrollWidth,
    }));
    expect(tier.scroll).toBeGreaterThan(tier.client);
    await expectNoDocumentOverflow(page);
  } else {
    expect(metrics.categoryArrow).toBe(30);
    expect(metrics.boardArrow).toBe(27);
    expect(metrics.gridColumns).toBe(2);
  }

  await rename.blur();
  await exerciseThemeRegisters(page, info, '01-content-structure');
});

test('tag catalogue search, sorting, unfiltered merge targets, pager, and empty copy are server rendered', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await ensureTagFixtures(page);
  await page.goto('/admin/tags?sort=usage');

  await expect(page.locator('.admin-tab.is-active')).toHaveText('Tags');
  await expect(page.locator('.content-tag-result-count')).toContainText(/1\d tags/);
  await expect(page.locator('.content-tag-row')).toHaveCount(8);
  await expect(page.locator('.content-tag-uses').first()).toHaveText(/\d+ uses/);
  await expect(page.locator('.content-tag-pager .pager-label')).toHaveText(/Page 1 of [2-9]/);
  const nextHref = await page.getByRole('link', { name: 'Next tag page' }).getAttribute('href');
  expect(nextHref).toContain('sort=usage');
  expect(nextHref).toContain('page=2');
  expect(nextHref).not.toContain('page=0');

  await page.locator('.content-tag-search input[name="q"]').fill('slice-six-01');
  await page.locator('.content-tag-search input[name="q"]').press('Enter');
  await expect(page).toHaveURL(/q=slice-six-01/);
  await expect(page.locator('.content-tag-result-count')).toHaveText('1 tag');
  await expect(page.locator('.content-tag-row')).toHaveCount(1);
  const mergeOptions = await page.locator('.content-tag-merge option').allTextContents();
  expect(mergeOptions).toContain('Slice Six 02');

  await page.locator('.content-tag-search input[name="q"]').fill('no-tag-matches-this-phrase');
  await page.locator('.content-tag-search input[name="q"]').press('Enter');
  await expect(page.getByRole('heading', { name: 'Nothing matches “no-tag-matches-this-phrase”' })).toBeVisible();
  await expect(page.getByText('Try a shorter phrase, or add it as a new tag above.')).toBeVisible();

  await page.goto('/admin/tags?sort=name');
  await expect(page.locator('.content-tag-sort-control.is-active')).toHaveText('A–Z');
  if (info.project.name === 'mobile') {
    const targets = await page.locator('.content-tag-row:first-child input:not([type="checkbox"]), .content-tag-row:first-child select, .content-tag-row:first-child button').evaluateAll(
      (elements) => elements.filter((element) => (element as HTMLElement).offsetParent !== null)
        .map((element) => (element as HTMLElement).getBoundingClientRect().height),
    );
    expect(targets.length).toBeGreaterThan(0);
    expect(Math.min(...targets)).toBeGreaterThanOrEqual(44);
    const enabledHitArea = await page.locator('.content-tag-row:first-child .content-tag-enabled').evaluate(
      (element) => (element as HTMLElement).getBoundingClientRect().height,
    );
    expect(enabledHitArea).toBeGreaterThanOrEqual(44);
    await expectNoDocumentOverflow(page);
  }
  await exerciseThemeRegisters(page, info, '02-content-tags');
});

test('board edit is the labelled FA-09 extrapolation and destructive links stay confirmations', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/structure');
  const general = page.locator('.content-board-row', { hasText: 'General' });
  await general.getByRole('link', { name: 'Edit', exact: true }).click();

  await expect(page.getByRole('heading', { name: 'Edit board' })).toBeVisible();
  await expect(page.locator('.content-board-edit-form')).toBeVisible();
  await expect(page.locator('.content-board-edit-form select[name="post_min_role"]')).toBeVisible();
  await expect(page.locator('.content-board-edit-form input[name="edit_window_minutes"]')).toBeVisible();
  await expect(page.locator('.content-roster-card')).toHaveCount(2);
  await expect(page.locator('.content-roster-card', { hasText: 'Moderators' })).toContainText('@alice');
  if (info.project.name === 'mobile') await expectNoDocumentOverflow(page);
  await expectAxeClean(page, info, 'board-edit');
  await shot(page, info, '03-content-board-edit');

  await page.goto('/admin/structure');
  await general.getByRole('link', { name: 'Delete', exact: true }).click();
  await expect(page.locator('.content-confirm-card')).toBeVisible();
  await expect(page.locator('select[name="move_to_board_id"]')).toBeVisible();
  await expect(page.locator('input[name="confirm"]')).toBeVisible();
  await expect(page.getByRole('button', { name: /Move threads and delete board/ })).toBeVisible();
  await expectAxeClean(page, info, 'board-delete-confirmation');
  await shot(page, info, '04-content-board-delete-confirmation');

  await page.goto('/admin/structure');
  await general.getByRole('link', { name: 'Archive', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Archive the “General” board?' })).toBeVisible();
  await expect(page.locator('select[name="move_to_board_id"]')).toHaveCount(0);
  await expect(page.locator('input[name="confirm"]')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Archive board' })).toBeVisible();
  await shot(page, info, '05-content-board-archive-confirmation');

  await page.goto('/admin/structure');
  const welcome = page.locator('.content-category-card', { has: page.locator('input.content-category-name[value="Welcome"]') });
  await welcome.getByRole('link', { name: 'Delete category' }).click();
  await expect(page.locator('.content-alert-danger')).toContainText('Move or delete them before deleting the category.');
  await expect(page.locator('input[name="confirm"]')).toHaveCount(0);

  await page.goto('/admin/structure');
  const emptyName = `Browser Empty ${info.project.name}`;
  const addCategory = page.locator('.content-add-category-form');
  await addCategory.locator('input[name="name"]').fill(emptyName);
  await addCategory.getByRole('button', { name: 'Add category' }).click();
  const emptyCategory = page.locator('.content-category-card', { has: page.locator(`input.content-category-name[value="${emptyName}"]`) });
  await emptyCategory.getByRole('link', { name: 'Delete category' }).click();
  await expect(page.getByRole('heading', { name: `Delete the “${emptyName}” category?` })).toBeVisible();
  await page.locator('input[name="confirm"]').fill(emptyName);
  await page.getByRole('button', { name: 'Delete category' }).click();
  await expect(page.getByRole('status')).toContainText('Category deleted');
  await expect(page.locator(`input.content-category-name[value="${emptyName}"]`)).toHaveCount(0);
});

test('tag merge uses the verbatim confirmation anatomy and remains a real GET-to-POST flow', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await ensureTagFixtures(page, 2);
  await page.goto('/admin/tags?q=slice-six-01&sort=name');
  await page.locator('.content-tag-merge select[name="target_id"]').selectOption({ label: 'Slice Six 02' });
  await page.locator('.content-tag-merge').getByRole('button', { name: 'Merge…' }).click();

  await expect(page.getByRole('heading', { name: 'Merge “Slice Six 01” into “Slice Six 02”?' })).toBeVisible();
  await expect(page.locator('.content-confirm-card')).toContainText('includes hidden, held, and deleted threads');
  await expect(page.locator('form[method="post"] input[name="target_id"]')).toHaveCount(1);
  await expect(page.getByRole('button', { name: 'Merge and remove “Slice Six 01”' })).toBeVisible();
  const impactWeight = await page.locator('.content-confirm-card .impact-list dd').first().evaluate(
    (element) => getComputedStyle(element).fontWeight,
  );
  expect(impactWeight).toBe('400');
  const codeMetrics = await page.locator('.content-confirm-card code').first().evaluate((element) => {
    const style = getComputedStyle(element);
    return {
      background: style.backgroundColor,
      padding: style.padding,
      radius: style.borderRadius,
      border: style.borderTopWidth,
      fontSize: style.fontSize,
    };
  });
  expect(codeMetrics).toEqual({
    background: 'rgba(0, 0, 0, 0)',
    padding: '0px',
    radius: '0px',
    border: '0px',
    fontSize: '13.44px',
  });
  await expectAxeClean(page, info, 'tag-merge-confirmation');
  await shot(page, info, '06-content-tag-merge-confirmation');
});

test('true no-JS tabs and forms navigate, mutate, confirm, and preserve the server contract', async ({ browser, baseURL }, info) => {
  test.setTimeout(300_000);
  test.skip(info.project.name !== 'desktop', 'one explicit no-JS 390px journey is sufficient');
  const context = await (browser as Browser).newContext({
    baseURL: baseURL!,
    javaScriptEnabled: false,
    viewport: { width: 390, height: 844 },
  });
  const page = await context.newPage();
  let axeContext: BrowserContext | undefined;
  try {
    await login(page);
    await page.goto('/admin/structure');
    await expect(page.locator('html')).not.toHaveClass(/has-js/);

    // Axe itself is injected JavaScript, so it cannot execute in the genuine
    // javaScriptEnabled:false journey. This companion context blocks every
    // application script request while leaving test-side injection available.
    axeContext = await (browser as Browser).newContext({
      baseURL: baseURL!,
      viewport: { width: 390, height: 844 },
    });
    await axeContext.route('**/assets/*.js', route => route.abort());
    await axeContext.addCookies(await context.cookies());
    const axePage = await axeContext.newPage();
    const axeRoute = async (href: string, label: string): Promise<void> => {
      const response = await axePage.goto(href, { waitUntil: 'networkidle' });
      expect(response?.status(), href).toBeLessThan(400);
      await expect(axePage.locator('html')).not.toHaveClass(/has-js/);
      await expectNoDocumentOverflow(axePage);
      await expectAxeClean(axePage, info, label);
    };
    await axeRoute('/admin/structure', 'no-js-structure');

    await page.getByRole('link', { name: 'Tags', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/tags$/);
    await expect(page.locator('.admin-tab.is-active')).toHaveText('Tags');
    await axeRoute('/admin/tags', 'no-js-tags');

    await ensureTagFixtures(page, 2);
    await page.goto('/admin/tags?q=slice-six-01&sort=name');
    const noJsSourceTag = page.locator('.content-tag-row', { has: page.locator('input[name="slug"][value="slice-six-01"]') });
    await noJsSourceTag.locator('select[name="target_id"]').selectOption({ label: 'Slice Six 02' });
    await noJsSourceTag.getByRole('button', { name: 'Merge…' }).click();
    await expect(page.getByRole('heading', { name: 'Merge “Slice Six 01” into “Slice Six 02”?' })).toBeVisible();
    await expect(page.locator('form[method="post"] input[name="target_id"]')).toHaveCount(1);
    await axeRoute(new URL(page.url()).pathname + new URL(page.url()).search, 'no-js-tag-merge-confirmation');
    await page.getByRole('link', { name: 'All tags', exact: true }).click();

    await page.getByRole('link', { name: 'Boards & categories', exact: true }).click();
    const categoryForm = page.locator('.content-add-category-form');
    await categoryForm.locator('input[name="name"]').fill('No JS Evidence');
    await categoryForm.getByRole('button', { name: 'Add category' }).click();
    await expect(page.getByRole('status')).toContainText('Category created');

    const addBoard = async (name: string, slug: string): Promise<void> => {
      const boardForm = page.locator('.content-add-board-card form');
      await boardForm.locator('select[name="category_id"]').selectOption({ label: '#No JS Evidence' });
      await boardForm.locator('input[name="name"]').fill(name);
      await boardForm.locator('input[name="slug"]').fill(slug);
      await boardForm.locator('input[name="description"]').fill(`${name} disposable evidence.`);
      await boardForm.locator('select[name="visibility"]').selectOption('private');
      await boardForm.locator('input[type="checkbox"][name="tags_enabled"]').uncheck();
      await boardForm.getByRole('button', { name: 'Add board' }).click();
      await expect(page.getByRole('status')).toContainText('Board created');
    };
    await addBoard('No JS Target', 'no-js-target');
    await addBoard('No JS Source', 'no-js-source');

    const category = page.locator('.content-category-card', { has: page.locator('input.content-category-name[value="No JS Evidence"]') });
    const sourceRow = page.locator('.content-board-row', { has: page.locator('a[href="/c/no-js-source"]') });
    const targetRow = page.locator('.content-board-row', { has: page.locator('a[href="/c/no-js-target"]') });
    const sourceEditHref = await sourceRow.getByRole('link', { name: 'Edit', exact: true }).getAttribute('href');
    expect(sourceEditHref).toMatch(/^\/admin\/boards\/\d+\/edit$/);

    // The blocked category confirmation is a real no-JS GET and cannot mutate.
    await category.getByRole('link', { name: 'Delete category' }).click();
    await expect(page.locator('.content-alert-danger')).toContainText('Move or delete them before deleting the category.');
    await expect(page.locator('input[name="confirm"]')).toHaveCount(0);
    await axeRoute(new URL(page.url()).pathname, 'no-js-category-delete-blocked');
    await page.getByRole('link', { name: 'Back to structure' }).click();

    // Move the disposable category itself; deleting it later restores the two
    // seeded categories' original relative order.
    await category.locator('button[aria-label="Move category No JS Evidence up"]').click();
    await expect(page.getByRole('status')).toContainText('Order updated');

    // Force the board-settings 422 anti-draft-loss path with a duplicate slug
    // (a blank required name is stopped by native HTML validation before POST)
    // and every checkbox explicitly submitted unchecked. Stored settings remain
    // unchanged.
    await sourceRow.getByRole('link', { name: 'Edit', exact: true }).click();
    const settingsForm = page.locator('.content-board-edit-form');
    await expect(settingsForm.locator('input[type="checkbox"][name="tags_enabled"]')).not.toBeChecked();
    await settingsForm.locator('input[name="slug"]').fill('general');
    await settingsForm.locator('input[name="description"]').fill('Updated without JavaScript.');
    for (const flag of ['allow_anonymous', 'require_approval', 'tags_enabled', 'wiki_enabled']) {
      await settingsForm.locator(`input[type="checkbox"][name="${flag}"]`).uncheck();
    }
    await settingsForm.getByRole('button', { name: 'Save board' }).click();
    await expect(page.getByText('That slug is already in use or reserved.')).toBeVisible();
    await expect(settingsForm.locator('input[name="description"]')).toHaveValue('Updated without JavaScript.');
    for (const flag of ['allow_anonymous', 'require_approval', 'tags_enabled', 'wiki_enabled']) {
      await expect(settingsForm.locator(`input[type="checkbox"][name="${flag}"]`)).not.toBeChecked();
    }
    await axeRoute(sourceEditHref!, 'no-js-board-edit');
    const axeSettingsForm = axePage.locator('.content-board-edit-form');
    await axeSettingsForm.locator('input[name="slug"]').fill('general');
    for (const flag of ['allow_anonymous', 'require_approval', 'tags_enabled', 'wiki_enabled']) {
      await axeSettingsForm.locator(`input[type="checkbox"][name="${flag}"]`).uncheck();
    }
    await axeSettingsForm.getByRole('button', { name: 'Save board' }).click();
    await expect(axePage.getByText('That slug is already in use or reserved.')).toBeVisible();
    await expectAxeClean(axePage, info, 'no-js-board-edit-422');

    // Add and remove both roster types through their ordinary POST forms.
    await page.locator('input[aria-label="Username to assign as moderator"]').fill('carol');
    await page.getByRole('button', { name: 'Assign moderator' }).click();
    await expect(page.getByRole('status')).toContainText('Moderator assigned');
    await expect(page.getByRole('link', { name: '@carol' })).toBeVisible();
    await page.locator('input[aria-label="Username to add as member"]').fill('bob');
    await page.getByRole('button', { name: 'Add member' }).click();
    await expect(page.getByRole('status')).toContainText('Member added');
    await expect(page.getByRole('link', { name: '@bob' })).toBeVisible();
    await axeRoute(sourceEditHref!, 'no-js-board-edit-with-rosters');
    await page.getByRole('button', { name: 'Remove @carol as moderator' }).click();
    await expect(page.getByRole('status')).toContainText('Moderator removed');
    await expect(page.getByRole('link', { name: '@carol' })).toHaveCount(0);
    await page.getByRole('button', { name: 'Remove @bob as member' }).click();
    await expect(page.getByRole('status')).toContainText('Member removed');
    await expect(page.getByRole('link', { name: '@bob' })).toHaveCount(0);
    await page.getByRole('link', { name: 'All boards', exact: true }).click();

    // Archive and unarchive POSTs both require the typed slug.
    await sourceRow.getByRole('link', { name: 'Archive', exact: true }).click();
    await axeRoute(new URL(page.url()).pathname, 'no-js-board-archive-confirmation');
    await page.locator('input[name="confirm"]').fill('no-js-source');
    await page.getByRole('button', { name: 'Archive board' }).click();
    await expect(page.getByRole('status')).toContainText('Board archived');
    await expect(sourceRow.getByRole('link', { name: 'Unarchive', exact: true })).toBeVisible();
    await sourceRow.getByRole('link', { name: 'Unarchive', exact: true }).click();
    await axeRoute(new URL(page.url()).pathname, 'no-js-board-unarchive-confirmation');
    await page.locator('input[name="confirm"]').fill('no-js-source');
    await page.getByRole('button', { name: 'Unarchive board' }).click();
    await expect(page.getByRole('status')).toContainText('Board restored');

    // Exercise the non-empty delete-with-move 422 without changing seeded
    // General: Feedback stays selected and the wrong slug is refused.
    await page.goto('/admin/structure');
    const generalRow = page.locator('.content-board-row', { has: page.locator('a[href="/c/general"]') });
    await generalRow.getByRole('link', { name: 'Delete', exact: true }).click();
    const generalDeletePath = new URL(page.url()).pathname;
    const moveSelect = page.locator('select[name="move_to_board_id"]');
    const feedbackValue = await moveSelect.locator('option', { hasText: '#Feedback (/c/feedback)' }).getAttribute('value');
    expect(feedbackValue).toBeTruthy();
    await moveSelect.selectOption(feedbackValue!);
    await page.locator('input[name="confirm"]').fill('wrong-general-slug');

    await axeRoute(generalDeletePath, 'no-js-general-delete-with-move');
    await axePage.locator('select[name="move_to_board_id"]').selectOption(feedbackValue!);
    await axePage.locator('input[name="confirm"]').fill('wrong-general-slug');
    await axePage.getByRole('button', { name: 'Move threads and delete board' }).click();
    await expect(axePage.locator('.content-alert-danger')).toContainText('Enter the board slug exactly');
    await expect(axePage.locator('select[name="move_to_board_id"]')).toHaveValue(feedbackValue!);
    await expectAxeClean(axePage, info, 'no-js-general-delete-with-move-422');

    await page.getByRole('button', { name: 'Move threads and delete board' }).click();
    await expect(page.locator('.content-alert-danger')).toContainText('Enter the board slug exactly');
    await expect(page.locator('select[name="move_to_board_id"]')).toHaveValue(feedbackValue!);
    await page.getByRole('link', { name: 'All boards', exact: true }).click();
    await expect(page.locator('a[href="/c/general"]')).toHaveCount(1);

    // Both disposable boards are empty, so exact typed deletes need no move
    // destination and leave the seeded boards untouched.
    await sourceRow.getByRole('link', { name: 'Delete', exact: true }).click();
    await expect(page.locator('select[name="move_to_board_id"]')).toHaveCount(0);
    await axeRoute(new URL(page.url()).pathname, 'no-js-source-delete-confirmation');
    await page.locator('input[name="confirm"]').fill('no-js-source');
    await page.getByRole('button', { name: 'Delete board' }).click();
    await expect(page.getByRole('status')).toContainText('Board deleted');
    await expect(page.locator('a[href="/c/no-js-source"]')).toHaveCount(0);

    await targetRow.getByRole('link', { name: 'Delete', exact: true }).click();
    await expect(page.locator('select[name="move_to_board_id"]')).toHaveCount(0);
    await axeRoute(new URL(page.url()).pathname, 'no-js-target-delete-confirmation');
    await page.locator('input[name="confirm"]').fill('no-js-target');
    await page.getByRole('button', { name: 'Delete board' }).click();
    await expect(page.getByRole('status')).toContainText('Board deleted');
    await expect(page.locator('a[href="/c/no-js-target"]')).toHaveCount(0);

    // The disposable category is now empty: its formerly blocked confirmation
    // becomes a real typed POST and cleanup restores the seeded category order.
    await category.getByRole('link', { name: 'Delete category' }).click();
    await expect(page.getByRole('heading', { name: 'Delete the “No JS Evidence” category?' })).toBeVisible();
    await expect(page.locator('input[name="confirm"]')).toBeVisible();
    await axeRoute(new URL(page.url()).pathname, 'no-js-category-delete-unblocked');
    await page.locator('input[name="confirm"]').fill('No JS Evidence');
    await page.getByRole('button', { name: 'Delete category' }).click();
    await expect(page.getByRole('status')).toContainText('Category deleted');
    await expect(page.locator('input.content-category-name[value="No JS Evidence"]')).toHaveCount(0);
    await axeRoute('/admin/structure', 'no-js-final-structure');
    await expectNoDocumentOverflow(page);
    await shot(page, info, '07-content-no-js');
  } finally {
    await axeContext?.close();
    await context.close();
  }
});
