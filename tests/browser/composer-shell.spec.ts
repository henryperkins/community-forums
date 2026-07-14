import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Locator, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const repoRoot = path.resolve(__dirname, '..', '..');
const TEST_EMAIL = 'alice@retro.test';

function runPhp(code: string): string {
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
$settings = new \\App\\Repository\\SettingRepository($db);
${code}
`;
  return execFileSync('php', ['-r', php], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString();
}

function setWysiwygComposer(enabled: boolean): void {
  runPhp(`
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
$features['rich_composer'] = true;
$features['wysiwyg_composer'] = ${enabled ? 'true' : 'false'};
$settings->set('features', $features);
`);
}

function setComposingPrefs(prefs: { enterToSend?: boolean; showPreview?: boolean; smartLists?: boolean }): void {
  const email64 = Buffer.from(TEST_EMAIL, 'utf8').toString('base64');
  runPhp(`
$email = base64_decode('${email64}');
$user = $db->fetch('SELECT id FROM users WHERE email = ?', [$email]);
if ($user) {
    $repo = new \\App\\Repository\\UserPreferenceRepository($db);
    $repo->merge((int) $user['id'], [
        '__v' => \\App\\Support\\PreferenceSchema::VERSION,
        'enter_to_send' => ${prefs.enterToSend === false ? 'false' : 'true'},
        'show_preview' => ${prefs.showPreview === false ? 'false' : 'true'},
        'smart_lists' => ${prefs.smartLists === false ? 'false' : 'true'},
    ]);
}
`);
}

function setDisplayName(displayName: string): void {
  const email64 = Buffer.from(TEST_EMAIL, 'utf8').toString('base64');
  const name64 = Buffer.from(displayName, 'utf8').toString('base64');
  runPhp(`
$db->run('UPDATE users SET display_name = ? WHERE email = ?', [base64_decode('${name64}'), base64_decode('${email64}')]);
`);
}

function resetComposerOverrides(): void {
  const email64 = Buffer.from(TEST_EMAIL, 'utf8').toString('base64');
  runPhp(`
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
unset($features['rich_composer'], $features['wysiwyg_composer']);
$settings->set('features', $features);
$email = base64_decode('${email64}');
$user = $db->fetch('SELECT id FROM users WHERE email = ?', [$email]);
if ($user) {
    $db->run('UPDATE users SET display_name = ? WHERE id = ?', ['Alice Avery', (int) $user['id']]);
    $repo = new \\App\\Repository\\UserPreferenceRepository($db);
    $repo->merge((int) $user['id'], [
        '__v' => \\App\\Support\\PreferenceSchema::VERSION,
        'enter_to_send' => true,
        'show_preview' => true,
        'smart_lists' => true,
    ]);
    $db->run('DELETE FROM server_drafts WHERE user_id = ?', [(int) $user['id']]);
}
`);
}

function serverDraftCount(): number {
  return Number(runPhp(`
$row = $db->fetch('SELECT id FROM users WHERE email = ?', ['${TEST_EMAIL}']);
echo $row ? (string) $db->fetchValue('SELECT COUNT(*) FROM server_drafts WHERE user_id = ?', [(int) $row['id']]) : '0';
`).trim());
}

function seededThreadPath(title: string): string {
  const title64 = Buffer.from(title, 'utf8').toString('base64');
  return runPhp(`
$title = base64_decode('${title64}');
$row = $db->fetch('SELECT id, slug FROM threads WHERE title = ? ORDER BY id DESC LIMIT 1', [$title]);
echo $row ? '/t/' . (int) $row['id'] . '-' . $row['slug'] : '';
`).trim();
}

test.beforeEach(() => {
  resetComposerOverrides();
});

test.afterEach(() => {
  resetComposerOverrides();
});

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click();
    await expect(page.locator('.tour-popover')).toHaveCount(0);
  }
}

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', TEST_EMAIL);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));
  await dismissTour(page);
}

async function visit(page: Page, url: string): Promise<void> {
  const response = await page.goto(url);
  expect(response, `no response for ${url}`).not.toBeNull();
  expect(response!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
}

async function openBoardComposer(page: Page): Promise<Locator> {
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  const form = page.locator('form.composer-shell[data-composer-instance^="new-thread-board-"]').first();
  await expect(form).toBeVisible();
  return form;
}

async function openReplyComposer(page: Page): Promise<Locator> {
  const path = seededThreadPath('Share your favourite keyboard shortcuts');
  expect(path).toMatch(/^\/t\/\d+-/);
  await visit(page, path);
  const form = page.locator('form.reply-composer.composer-shell');
  await expect(form).toBeVisible();
  return form;
}

async function expectNoSeriousA11yViolations(page: Page, include: string): Promise<void> {
  const result = await new AxeBuilder({ page })
    .include(include)
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const violations = result.violations.filter((violation) =>
    violation.impact === 'serious' || violation.impact === 'critical',
  );
  expect(violations, `${include} serious/critical axe violations`).toEqual([]);
}

test('contained anatomy uses the format row and named action slots', async ({ page }) => {
  setWysiwygComposer(false);
  await login(page);
  const form = await openBoardComposer(page);
  const box = form.locator('.composer-box');
  const formatSlot = box.locator('[data-composer-format-slot]');
  const toolbar = formatSlot.locator('.composer-toolbar');

  await expect(toolbar).toBeVisible();
  const ordered = await box.evaluate((element) => {
    const selectors = [
      '.composer-toolbar',
      'textarea.composer-input',
      '[data-composer-upload-tray]',
      '.composer-actions-bar',
    ];
    const nodes = selectors.map((selector) => element.querySelector(selector));
    return nodes.every(Boolean) && nodes.slice(0, -1).every((node, index) =>
      Boolean(node!.compareDocumentPosition(nodes[index + 1]!) & Node.DOCUMENT_POSITION_FOLLOWING),
    );
  });
  expect(ordered).toBe(true);

  const actionOrder = await toolbar.locator('[data-composer-action]').evaluateAll((buttons) =>
    buttons.map((button) => button.getAttribute('data-composer-action')),
  );
  expect(actionOrder).toEqual([
    'bold', 'italic', 'strike', 'code', 'quote', 'h2', 'list', 'orderedList', 'codeblock', 'spoiler', 'link',
  ]);

  const bold = toolbar.getByRole('button', { name: 'Bold (Ctrl+B)', exact: true });
  await expect(bold).toHaveAttribute('aria-keyshortcuts', 'Control+B Meta+B');
  expect((await bold.textContent())!.trim()).toBe('');
  await expect(form.locator('[data-composer-actions-start-slot]').getByRole('button', { name: 'Formatting' })).toBeVisible();
  await expect(form.locator('[data-composer-actions-end-slot]').getByRole('button', { name: 'Preview' })).toBeVisible();
  await expect(form.getByRole('button', { name: /Attach images|Emoji/ })).toHaveCount(0);
  await expect(form.locator('.composer-send')).toBeDisabled();
  await expect(form.locator('.composer-count')).toBeHidden();

  const surfaces = await page.locator('html').evaluate(async (html) => {
    const values: string[] = [];
    for (const theme of ['light', 'dark']) {
      html.setAttribute('data-theme', theme);
      values.push(getComputedStyle(document.querySelector('.composer-box')!).backgroundColor);
    }
    return values;
  });
  expect(surfaces[0]).not.toBe(surfaces[1]);
  await expectNoSeriousA11yViolations(page, `[data-composer-instance="${await form.getAttribute('data-composer-instance')}"]`);
});

test('submit state keeps canonical markdown successful and announces sending', async ({ page }) => {
  setWysiwygComposer(false);
  await login(page);
  const form = await openBoardComposer(page);
  const textarea = form.locator('textarea.composer-input');
  const send = form.locator('.composer-send');

  await form.locator('input[name="title"]').fill('Contained submit state');
  await textarea.fill('Canonical markdown stays successful.');
  await expect(send).toBeEnabled();
  await form.evaluate((element) => {
    element.addEventListener('submit', (event) => event.preventDefault(), { once: true });
  });
  await send.click();

  await expect(form).toHaveAttribute('aria-busy', 'true');
  await expect(form).toHaveClass(/\bis-submitting\b/);
  await expect(send).toBeDisabled();
  await expect(textarea).toBeEnabled();
  await expect(form.locator('[data-composer-submit-status]')).toHaveText('Sending…');
  await expect(form.getByRole('button', { name: 'Formatting', exact: true })).toBeEnabled();
});

test('textarea Enter keeps list authoring quote and code contexts editorial before sending', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'desktop Enter-to-send contract');
  setWysiwygComposer(false);
  setComposingPrefs({ enterToSend: true, smartLists: true });
  await login(page);
  const form = await openBoardComposer(page);
  const textarea = form.locator('textarea.composer-input');
  const title = `Textarea Enter matrix ${Date.now()}`;
  await form.locator('input[name="title"]').fill(title);

  await textarea.fill('1. first');
  await textarea.press('Enter');
  await expect(textarea).toHaveValue('1. first\n2. ');
  await textarea.press('Enter');
  await expect(textarea).toHaveValue('1. first\n');

  await textarea.fill('> quoted');
  await textarea.press('Enter');
  await expect(textarea).toHaveValue('> quoted\n> ');
  await textarea.press('Enter');
  await expect(textarea).toHaveValue('> quoted\n');

  await textarea.fill('```\ncode');
  await textarea.press('Enter');
  await expect(textarea).toHaveValue('```\ncode\n');
  await textarea.fill('`inline');
  await textarea.press('Enter');
  await expect(textarea).toHaveValue('`inline\n');
  await textarea.fill('hard break');
  await textarea.press('Shift+Enter');
  await expect(textarea).toHaveValue('hard break\n');

  await textarea.fill('- final item');
  await textarea.press('Enter');
  await expect(textarea).toHaveValue('- final item\n- ');
  await textarea.press('Enter');
  await expect(textarea).toHaveValue('- final item\n');
  await textarea.press('Enter');
  await page.waitForURL(/\/t\/\d+-/);
  await expect(page.getByRole('heading', { name: title })).toBeVisible();
});

test('textarea list Enter stays editorial when smart continuation is off', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'desktop Enter-to-send contract');
  setWysiwygComposer(false);
  setComposingPrefs({ enterToSend: true, smartLists: false });
  await login(page);
  const form = await openBoardComposer(page);
  const textarea = form.locator('textarea.composer-input');
  const before = page.url();
  await form.locator('input[name="title"]').fill('No smart continuation');
  await textarea.fill('- item');
  await textarea.press('Enter');
  await expect(textarea).toHaveValue('- item\n');
  expect(page.url()).toBe(before);
});

test('textarea Ctrl+Enter sends from a list when the preference is off', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'desktop modifier contract');
  setWysiwygComposer(false);
  setComposingPrefs({ enterToSend: false });
  await login(page);
  const form = await openBoardComposer(page);
  const title = `Textarea forced send ${Date.now()}`;
  await form.locator('input[name="title"]').fill(title);
  await form.locator('textarea.composer-input').fill('- forced from list');
  await form.locator('textarea.composer-input').press('Control+Enter');
  await page.waitForURL(/\/t\/\d+-/);
  await expect(page.getByRole('heading', { name: title })).toBeVisible();
});

test('textarea mobile Enter edits and the send button submits', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile soft-Enter contract');
  setWysiwygComposer(false);
  setComposingPrefs({ enterToSend: true });
  await login(page);
  const form = await openBoardComposer(page);
  const title = `Textarea mobile send ${Date.now()}`;
  const textarea = form.locator('textarea.composer-input');
  await form.locator('input[name="title"]').fill(title);
  await textarea.fill('first line');
  await textarea.press('Enter');
  await expect(textarea).toHaveValue('first line\n');
  await textarea.type('second line');
  await form.locator('.composer-send').click();
  await page.waitForURL(/\/t\/\d+-/);
  await expect(page.getByRole('heading', { name: title })).toBeVisible();
});

test('textarea in-flight Enter produces one POST and announces sending', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'desktop in-flight keyboard contract');
  setWysiwygComposer(false);
  setComposingPrefs({ enterToSend: true });
  let releaseRoute = () => {};
  const routeGate = new Promise<void>((resolve) => { releaseRoute = resolve; });
  let postCount = 0;
  let submitState: { busy: string | null; disabled: boolean; status: string | null } | null = null;
  page.on('console', (message) => {
    if (!message.text().startsWith('__rb_submit_state__')) return;
    submitState = JSON.parse(message.text().slice('__rb_submit_state__'.length));
  });
  await page.route('**/threads', async (route) => {
    if (route.request().method() === 'POST') {
      postCount++;
      await routeGate;
    }
    await route.continue();
  });
  await login(page);
  const form = await openBoardComposer(page);
  const textarea = form.locator('textarea.composer-input');
  await form.locator('input[name="title"]').fill(`Textarea in-flight ${Date.now()}`);
  await textarea.fill('One request only');
  await form.evaluate((element) => {
    element.addEventListener('submit', () => {
      console.log('__rb_submit_state__' + JSON.stringify({
        busy: element.getAttribute('aria-busy'),
        disabled: (element.querySelector('.composer-send') as HTMLButtonElement).disabled,
        status: element.querySelector('[data-composer-submit-status]')!.textContent,
      }));
      window.setTimeout(() => {
        element.querySelector('textarea.composer-input')!.dispatchEvent(
          new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }),
        );
      }, 0);
    }, { once: true });
  });
  try {
    await textarea.press('Enter', { noWaitAfter: true });
    await expect.poll(() => postCount).toBe(1);
    await expect.poll(() => submitState).toEqual({ busy: 'true', disabled: true, status: 'Sending…' });
    await page.waitForTimeout(150);
    expect(postCount).toBe(1);
  } finally {
    releaseRoute();
  }
  await page.waitForURL(/\/t\/\d+-/);
});

test('Escape blurs the textarea unless an open suggestion menu consumes it', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'shared keyboard precedence is verified once');
  setWysiwygComposer(false);
  await login(page);
  const form = await openBoardComposer(page);
  const textarea = form.locator('textarea.composer-input');
  await textarea.focus();
  await textarea.press('Escape');
  await expect(textarea).not.toBeFocused();

  await textarea.fill('@ali');
  await expect(form.locator('.composer-reference-menu')).toBeVisible();
  await textarea.press('Escape');
  await expect(form.locator('.composer-reference-menu')).toBeHidden();
  await expect(textarea).toBeFocused();
});

test('format row toggle persists and icon tooltips work for pointer and keyboard', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'tooltip register is verified once on desktop');
  setWysiwygComposer(false);
  await login(page);
  let form = await openBoardComposer(page);
  let toggle = form.getByRole('button', { name: 'Formatting' });
  let toolbar = form.locator('.composer-toolbar');

  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(toolbar).toBeVisible();
  await toggle.click();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await expect(toolbar).toBeHidden();
  expect(await page.evaluate(() => localStorage.getItem('rb-composer:format-row'))).toBe('closed');

  await page.reload();
  await page.locator('details.composer-details > summary').click();
  form = page.locator('form.composer-shell[data-composer-instance^="new-thread-board-"]').first();
  toggle = form.getByRole('button', { name: 'Formatting' });
  toolbar = form.locator('.composer-toolbar');
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await expect(toolbar).toBeHidden();
  await toggle.click();

  const bold = toolbar.getByRole('button', { name: 'Bold (Ctrl+B)', exact: true });
  await bold.hover();
  await expect.poll(() => bold.evaluate((button) => getComputedStyle(button, '::after').opacity)).toBe('1');
  await page.keyboard.press('Tab');
  await bold.focus();
  expect(await bold.evaluate((button) => button.matches(':focus-visible'))).toBe(true);
  await expect.poll(() => bold.evaluate((button) => getComputedStyle(button, '::after').opacity)).toBe('1');
});

test('draft counter preview and textarea auto-growth stay inside the shell', async ({ page }) => {
  setWysiwygComposer(false);
  setComposingPrefs({ showPreview: true });
  await login(page);
  let form = await openBoardComposer(page);
  let textarea = form.locator('textarea.composer-input');
  const initialHeight = await textarea.evaluate((element) => element.getBoundingClientRect().height);
  await textarea.fill('one\ntwo\nthree\nfour\nfive\nsix\nseven\neight');
  await expect.poll(() => textarea.evaluate((element) => element.getBoundingClientRect().height)).toBeGreaterThan(initialHeight);

  await textarea.fill('a'.repeat(18_000));
  await expect(form.locator('.composer-count')).toBeVisible();
  await expect(form.locator('.composer-count')).toHaveText('18000 / 20000');
  const draftMeta = form.locator('[data-composer-draft-slot]');
  await expect(draftMeta).toContainText('Draft saved ·');
  const discard = draftMeta.getByRole('button', { name: 'Discard draft' });
  await expect(discard).toHaveText('Discard');
  await expect(form.locator('.composer-draft-sync')).toContainText('Saved to server drafts.', { timeout: 5000 });
  expect(serverDraftCount()).toBeGreaterThan(0);
  await discard.click();
  await expect(textarea).toHaveValue('');
  await expect.poll(serverDraftCount).toBe(0);

  const previewToggle = form.getByRole('button', { name: 'Preview' });
  const preview = form.locator('.composer-preview');
  await expect(previewToggle).toHaveAttribute('aria-expanded', 'true');
  await textarea.fill('**preview shell**');
  await expect(preview.locator('strong')).toHaveText('preview shell', { timeout: 5000 });
  await previewToggle.click();
  await expect(preview).toBeHidden();
  expect(await page.evaluate(() => localStorage.getItem('rb-composer:preview'))).toBe('closed');

  await visit(page, '/messages/new');
  form = page.locator('form[data-composer-instance="dm-new-page"]');
  textarea = form.locator('textarea.composer-input');
  await textarea.fill('d'.repeat(4500));
  await expect(form.locator('.composer-count')).toHaveText('4500 / 5000');
});

test('rich preview defaults closed and fetches only when opened', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'lazy rich preview is verified once');
  setWysiwygComposer(true);
  setComposingPrefs({ showPreview: true });
  let previewRequests = 0;
  page.on('request', (request) => {
    if (new URL(request.url()).pathname === '/composer/preview') previewRequests++;
  });
  await login(page);
  const form = await openBoardComposer(page);
  const editor = form.locator('.wysiwyg-composer .ProseMirror');
  await expect(editor).toBeVisible();
  const toggle = form.getByRole('button', { name: 'Preview' });
  const preview = form.locator('.composer-preview');
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await expect(preview).toBeHidden();
  await editor.fill('lazy preview');
  await page.waitForTimeout(500);
  expect(previewRequests).toBe(0);
  await toggle.click();
  await expect(preview).toContainText('lazy preview', { timeout: 5000 });
  expect(previewRequests).toBeGreaterThan(0);
});

test('slash and reference popovers do not reflow the composer box', async ({ page }, info) => {
  setWysiwygComposer(false);
  await login(page);
  const form = await openBoardComposer(page);
  const box = form.locator('.composer-box');
  const textarea = form.locator('textarea.composer-input');

  await textarea.fill('/task');
  const slash = form.locator('.composer-slash-menu');
  await expect(slash).toBeVisible();
  const slashDelta = await box.evaluate((element) => {
    const menu = element.querySelector<HTMLElement>('.composer-slash-menu')!;
    const openHeight = element.getBoundingClientRect().height;
    menu.hidden = true;
    const closedHeight = element.getBoundingClientRect().height;
    menu.hidden = false;
    return Math.abs(openHeight - closedHeight);
  });
  expect(slashDelta).toBeLessThanOrEqual(1);
  await expect(slash).toHaveCSS('position', info.project.name === 'mobile' ? 'fixed' : 'absolute');
  await expectNoSeriousA11yViolations(page, `[data-composer-instance="${await form.getAttribute('data-composer-instance')}"]`);

  await page.keyboard.press('Escape');
  await textarea.fill('@ali');
  const reference = form.locator('.composer-reference-menu');
  await expect(reference).toBeVisible();
  const referenceDelta = await box.evaluate((element) => {
    const menu = element.querySelector<HTMLElement>('.composer-reference-menu')!;
    const openHeight = element.getBoundingClientRect().height;
    menu.hidden = true;
    const closedHeight = element.getBoundingClientRect().height;
    menu.hidden = false;
    return Math.abs(openHeight - closedHeight);
  });
  expect(referenceDelta).toBeLessThanOrEqual(1);
  await expect(reference).toHaveCSS('position', info.project.name === 'mobile' ? 'fixed' : 'absolute');
});

test('mobile compact dock expands to a contained formatting overflow and anonymous disclosure', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile compact contract');
  setWysiwygComposer(false);
  setDisplayName('Alice Avery of the Extremely Long Community Identity Fixture');
  await login(page);
  const form = await openReplyComposer(page);
  const send = form.locator('.composer-send');
  await expect(form).not.toHaveClass(/\bis-expanded\b/);
  await expect(send).toBeVisible();
  await expect(form.locator('.composer-format-slot')).toBeHidden();
  await expect(form.locator('.composer-meta-row')).toBeHidden();
  await expect(form.locator('.composer-identity')).toBeHidden();
  await expectNoSeriousA11yViolations(page, `[data-composer-instance="${await form.getAttribute('data-composer-instance')}"]`);

  const textarea = form.locator('textarea.composer-input');
  await textarea.focus();
  await expect(form).toHaveClass(/\bis-expanded\b/);
  await expect(form.locator('.composer-format-slot')).toBeVisible();
  await expect(form.locator('.composer-meta-row')).toBeVisible();

  const toolbar = form.locator('.composer-toolbar');
  for (const action of ['bold', 'italic', 'list', 'link']) {
    await expect(toolbar.locator(`[data-composer-action="${action}"]`)).toBeVisible();
  }
  const more = toolbar.getByRole('button', { name: 'More formatting' });
  await expect(more).toBeVisible();
  await more.click();
  const overflow = form.locator('.composer-format-overflow');
  await expect(overflow).toBeVisible();
  await expect(overflow.locator('[data-composer-overflow-action]')).toHaveCount(7);
  await expect(overflow.getByRole('button', { name: 'Heading' })).toBeVisible();
  await more.click();
  await expect(overflow).toBeHidden();
  const toolbarLayout = await toolbar.evaluate((element) => ({
    overflowX: getComputedStyle(element).overflowX,
    contained: element.scrollWidth <= element.clientWidth + 1,
  }));
  expect(toolbarLayout).toEqual({ overflowX: 'hidden', contained: true });

  const disclosure = form.locator('.composer-anonymous-disclosure');
  const anonymous = form.getByRole('checkbox', { name: 'Anonymous' });
  await expect(disclosure).toBeVisible();
  await anonymous.check();
  await expect(disclosure).toBeVisible();
  await expect(form.locator('.composer-identity')).toBeVisible();
  const identityClip = await form.locator('.composer-identity-copy').evaluate((element) => ({
    textOverflow: getComputedStyle(element).textOverflow,
    clipped: element.scrollWidth > element.clientWidth,
  }));
  expect(identityClip).toEqual({ textOverflow: 'ellipsis', clipped: true });
  const contained = await form.evaluate((element) => {
    const formRect = element.getBoundingClientRect();
    const identityRect = element.querySelector('.composer-identity')!.getBoundingClientRect();
    return identityRect.left >= formRect.left - 1
      && identityRect.right <= formRect.right + 1
      && document.documentElement.scrollWidth <= document.documentElement.clientWidth;
  });
  expect(contained).toBe(true);
  await expectNoSeriousA11yViolations(page, `[data-composer-instance="${await form.getAttribute('data-composer-instance')}"]`);

  await page.setViewportSize({ width: 195, height: 422 });
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
});
