import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const repoRoot = path.resolve(__dirname, '..', '..');

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
$features['wysiwyg_composer'] = ${enabled ? 'true' : 'false'};
$settings->set('features', $features);
`);
}

function resetToolbarTestState(): void {
  setWysiwygComposer(false);
  runPhp(`
$db->run("DELETE FROM server_drafts WHERE user_id = (SELECT id FROM users WHERE email = 'bob@retro.test')");
`);
}

test.beforeEach(() => {
  resetToolbarTestState();
});

test.afterEach(() => {
  resetToolbarTestState();
});

async function login(page: Page, email: string): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible().catch(() => false)) { await skip.click(); }
}

async function openNewTopicComposer(page: Page) {
  await page.goto('/c/general');
  const details = page.locator('details.composer-details#new-topic');
  const promoted = page.locator('.board-identity-actions [data-open-topic-composer]');
  const fab = page.locator('a.fab[href="#new-topic"]');
  const summary = details.locator(':scope > summary');
  const opener = await promoted.isVisible() ? promoted : (await fab.isVisible() ? fab : summary);
  await opener.click();
  await expect(details).toHaveJSProperty('open', true);
  return page.locator('form.composer').first();
}

test('toolbar reflects active marks across rich and source modes', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'mode semantics are viewport-independent');
  setWysiwygComposer(true);
  await login(page, 'bob@retro.test');
  const form = await openNewTopicComposer(page);
  const editor = form.locator('.wysiwyg-composer .ProseMirror');
  await expect(editor).toBeVisible();

  const bold = form.locator('.composer-toolbar [data-composer-action="bold"]');
  await editor.fill('bold');
  await editor.press(process.platform === 'darwin' ? 'Meta+A' : 'Control+A');
  await bold.click();
  await expect(bold).toHaveAttribute('aria-pressed', 'true');

  // Switching modes refreshes from the textarea selection without relying on a
  // document-wide selectionchange listener.
  await form.getByRole('button', { name: 'Source' }).click();
  await expect(bold).toHaveAttribute('aria-pressed', 'false');
  const textarea = form.locator('textarea.composer-input');
  await textarea.evaluate((element: HTMLTextAreaElement) => {
    element.setSelectionRange(2, 6);
    element.dispatchEvent(new Event('select', { bubbles: true }));
  });
  await expect(bold).toHaveAttribute('aria-pressed', 'true');

  await form.getByRole('button', { name: 'Rich text' }).click();
  await editor.locator('strong').click();
  await expect(bold).toHaveAttribute('aria-pressed', 'true');

  // A collapsed selection uses stored marks when a keyboard shortcut toggles
  // the format for subsequent typing, even before the document changes.
  await editor.press(process.platform === 'darwin' ? 'Meta+B' : 'Control+B');
  await expect(bold).toHaveAttribute('aria-pressed', 'false');
  await editor.press(process.platform === 'darwin' ? 'Meta+B' : 'Control+B');
  await expect(bold).toHaveAttribute('aria-pressed', 'true');
});

test('rich toolbar follows caret movement through every supported format', async ({ page }) => {
  setWysiwygComposer(true);
  await login(page, 'bob@retro.test');
  const form = await openNewTopicComposer(page);
  const editor = form.locator('.wysiwyg-composer .ProseMirror');
  await expect(editor).toBeVisible();
  await form.getByRole('button', { name: 'Source' }).click();
  await form.locator('textarea.composer-input').fill([
    '**bold**', '*italic*', '~~strike~~', '`||literal||`', '> quote', '## heading',
    '- bullet', '1. numbered', '```\n||fenced||\n```', '[linked](https://example.com)',
    '||secret||', 'plain',
  ].join('\n\n'));
  await form.getByRole('button', { name: 'Rich text' }).click();
  await expect(editor.locator('h2')).toHaveText('heading');

  async function placeCaret(selector: string, offset = 1): Promise<void> {
    await editor.locator(selector).evaluate((element, position) => {
      element.closest<HTMLElement>('[contenteditable]')!.focus();
      const text = element.firstChild!;
      window.getSelection()!.setBaseAndExtent(text, position, text, position);
    }, offset);
  }

  for (const [key, selector, offset] of [
    ['bold', 'strong', 1], ['italic', 'em', 1], ['strike', 'del', 1],
    ['code', 'p > code', 3], ['quote', 'blockquote p', 1], ['h2', 'h2', 1],
    ['list', 'ul li p', 1], ['orderedList', 'ol li p', 1],
    ['codeblock', 'pre code', 3], ['link', 'a', 1], ['spoiler', 'p:text-is("||secret||")', 3],
  ] as const) {
    await test.step(key, async () => {
      await placeCaret(selector, offset);
      // Check both the row and its compact overflow copy, including hidden
      // controls that must be correct as soon as the menu opens.
      const controls = form.locator(`[data-composer-action="${key}"]`);
      for (const control of await controls.all()) {
        await expect(control).toHaveAttribute('aria-pressed', 'true');
      }
      if (key === 'code' || key === 'codeblock') {
        for (const control of await form.locator('[data-composer-action="spoiler"]').all()) {
          await expect(control).toHaveAttribute('aria-pressed', 'false');
        }
      }
      await placeCaret('p:text-is("plain")');
      for (const control of await controls.all()) {
        await expect(control).toHaveAttribute('aria-pressed', 'false');
      }
    });
  }
});

test('overflow menu is dismissable from its own toggle and restores focus', async ({ page }, info) => {
  // .composer-more-wrap is display:none until @media (max-width: 640px), so the
  // overflow menu only exists at mobile widths.
  test.skip(info.project.name !== 'mobile', 'the overflow menu is a compact-width control');
  setWysiwygComposer(false);
  await login(page, 'bob@retro.test');
  const form = await openNewTopicComposer(page);

  const more = form.getByRole('button', { name: 'More formatting', exact: true });
  const overflow = form.locator('.composer-format-overflow');

  await more.click();
  await expect(overflow).toBeVisible();

  // Focus is still on the toggle, which is a SIBLING of the panel - Escape here
  // previously reached no handler and the menu stayed open.
  await more.focus();
  await page.keyboard.press('Escape');
  await expect(overflow).toBeHidden();
  await expect(more).toHaveAttribute('aria-expanded', 'false');
  await expect(more).toBeFocused();
  await expect(page.locator('details#new-topic')).toHaveJSProperty('open', true);
});

test('collapsed formatting row explains itself and stays recoverable', async ({ page }, info) => {
  setWysiwygComposer(false);
  await login(page, 'bob@retro.test');
  const form = await openNewTopicComposer(page);

  const toggle = form.getByRole('button', { name: 'Formatting', exact: true });
  const bar = form.locator('.composer-toolbar');

  await expect(bar).toBeVisible();
  await expect(toggle).toHaveAttribute('data-tip', 'Hide formatting toolbar');
  await toggle.hover();
  await expect.poll(() => toggle.evaluate((button) => getComputedStyle(button, '::after').opacity)).toBe('1');

  await toggle.click();
  await expect(bar).toBeHidden();
  // The control that caused it must say so, or a collapsed row reads as a bug.
  await expect(toggle).toHaveAttribute('data-tip', 'Show formatting toolbar');
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await expect(toggle).toBeVisible();

  // Reach it through keyboard navigation after moving the pointer away, so
  // hover cannot mask a missing focus-visible tooltip on either viewport.
  await page.mouse.move(0, 0);
  await toggle.press('Tab');
  await page.keyboard.press('Shift+Tab');
  await expect(toggle).toBeFocused();
  await expect.poll(() => toggle.evaluate((button) => getComputedStyle(button, '::after').opacity)).toBe('1');
  const tip = await toggle.evaluate((button) => {
    const style = getComputedStyle(button, '::after');
    const left = button.getBoundingClientRect().left + parseFloat(style.left);
    return {
      keyboardFocus: button.matches(':focus-visible'),
      overflow: getComputedStyle(button.closest('.composer-actions-start')!).overflow,
      left,
      right: left + parseFloat(style.width),
      viewport: window.innerWidth,
    };
  });
  expect(tip.keyboardFocus).toBe(true);
  expect(tip.overflow).toBe('visible');
  expect(tip.left).toBeGreaterThanOrEqual(0);
  expect(tip.right).toBeLessThanOrEqual(tip.viewport);
  await page.screenshot({ path: info.outputPath('format-tooltip.png') });

  await toggle.press('Enter');
  await expect(bar).toBeVisible();
  await expect(toggle).toHaveAttribute('data-tip', 'Hide formatting toolbar');
});

const PNG_1X1 =
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWP4z8AAAAMBAQCc479ZAAAAAElFTkSuQmCC';

test('upload card does not change height while the upload is in flight', async ({ page }, info) => {
  await page.route('**/media/777', route => route.fulfill({ contentType: 'image/png', body: Buffer.from(PNG_1X1, 'base64') }));
  setWysiwygComposer(false);
  // Hold the response open so the in-flight state can actually be measured.
  await page.route('**/upload', async (route) => {
    await new Promise((r) => setTimeout(r, 2000));
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ ok: true, id: 777, url: '/media/777', markdown: '![](/media/777)', width: 1, height: 1 }),
    });
  });

  await login(page, 'bob@retro.test');
  const form = await openNewTopicComposer(page);
  const card = form.locator('.composer-upload-card');
  const progress = form.locator('.composer-upload-card progress');

  await form.locator('input[type="file"][data-composer-upload-input]')
    .setInputFiles({ name: 'pic.png', mimeType: 'image/png', buffer: Buffer.from(PNG_1X1, 'base64') });
  await expect(card).toHaveCount(1);
  await page.waitForTimeout(400);

  const during = await card.first().boundingBox();
  const duringProgress = await progress.first().boundingBox();
  expect(during, 'card should be measurable mid-upload').not.toBeNull();

  // The thumbnail is [hidden] until the upload resolves. If it drops out of the
  // grid flow the meta block falls into the 48px thumb track, wraps, and the card
  // stands ~120px taller than its settled height — a visible jump on completion.
  expect(duringProgress!.width, 'progress bar must span the meta track while uploading')
    .toBeGreaterThan(during!.width / 2);

  await expect(form.locator('.composer-upload-status')).toContainText('Uploaded image', { timeout: 10000 });
  await page.waitForTimeout(400);

  const after = await card.first().boundingBox();
  expect(Math.abs(after!.height - during!.height),
    `card height moved ${during!.height} -> ${after!.height} when the upload landed`).toBeLessThanOrEqual(1);
  await expect(form.locator('.composer-upload-thumb')).toBeVisible();
});

test('a second in-flight upload stacks without disturbing the first card', async ({ page }, info) => {
  await page.route(/\/media\/80[12]$/, route => route.fulfill({ contentType: 'image/png', body: Buffer.from(PNG_1X1, 'base64') }));
  test.skip(info.project.name !== 'desktop', 'stacking geometry verified once');
  setWysiwygComposer(false);
  let n = 0;
  await page.route('**/upload', async (route) => {
    n++;
    await new Promise((r) => setTimeout(r, 1200));
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ ok: true, id: 800 + n, url: `/media/${800 + n}`, markdown: `![](/media/${800 + n})`, width: 1, height: 1 }),
    });
  });

  await login(page, 'bob@retro.test');
  const form = await openNewTopicComposer(page);
  const input = form.locator('input[type="file"][data-composer-upload-input]');
  const cards = form.locator('.composer-upload-card');

  await input.setInputFiles({ name: 'one.png', mimeType: 'image/png', buffer: Buffer.from(PNG_1X1, 'base64') });
  await expect(cards).toHaveCount(1);
  await expect(form.locator('.composer-upload-status').first()).toContainText('Uploaded image', { timeout: 10000 });
  const settled = await cards.nth(0).boundingBox();

  await input.setInputFiles({ name: 'two.png', mimeType: 'image/png', buffer: Buffer.from(PNG_1X1, 'base64') });
  await expect(cards).toHaveCount(2);
  await page.waitForTimeout(400);

  const firstDuringSecond = await cards.nth(0).boundingBox();
  expect(Math.abs(firstDuringSecond!.height - settled!.height),
    'a settled card must not resize because a sibling is uploading').toBeLessThanOrEqual(1);
  const second = await cards.nth(1).boundingBox();
  expect(Math.abs(second!.height - settled!.height),
    'an in-flight card must already match its settled height').toBeLessThanOrEqual(1);
});

// --- Inline image preview sizing (COMPOSER.md §7; option "B") ---------------
// Inline media is a PREVIEW: it fits the editor column and is capped, so it stays
// at its insertion point without swallowing the surface. Preview stays full
// fidelity. Fixtures are generated so the shapes are explicit.
const SHAPES: Array<{ name: string; w: number; h: number }> = [
  { name: 'wide', w: 1600, h: 600 },
  { name: 'tall', w: 900, h: 1600 },
  { name: 'small', w: 120, h: 90 },
];
const EDITOR_IMAGE_MAX_HEIGHT = 240;

function makeImage(w: number, h: number): Buffer {
  const out = execFileSync('php', ['-r', `
$im = imagecreatetruecolor(${w}, ${h});
for ($y = 0; $y < ${h}; $y++) { imageline($im, 0, $y, ${w}, $y, imagecolorallocate($im, 60 + intval(150 * $y / ${h}), 90, 160)); }
ob_start(); imagepng($im); echo base64_encode(ob_get_clean());
`], { cwd: repoRoot });
  return Buffer.from(out.toString(), 'base64');
}

for (const shape of SHAPES) {
  test(`inline ${shape.name} image is a capped in-place preview, Preview stays full size`, async ({ page }, info) => {
    test.skip(info.project.name !== 'desktop', 'sizing rules are viewport-independent');
    setWysiwygComposer(true);
    await login(page, 'bob@retro.test');
    const form = await openNewTopicComposer(page);
    const editor = form.locator('.wysiwyg-composer .ProseMirror');
    await expect(editor).toBeVisible();

    // Server drafts persist across tests; start from an empty document.
    await editor.click();
    await page.keyboard.press('Control+a');
    await page.keyboard.press('Backspace');
    await page.keyboard.type('Text before. ');

    await form.locator('input[type="file"][data-composer-upload-input]')
      .setInputFiles({ name: `${shape.name}.png`, mimeType: 'image/png', buffer: makeImage(shape.w, shape.h) });
    await expect(form.locator('.composer-upload-status').first()).toContainText('Uploaded image', { timeout: 15000 });
    await page.waitForTimeout(700);

    // Milkdown leaves a transient empty img (src="null", 0x0) beside the real
    // node; target the uploaded media explicitly.
    const img = editor.locator('img[src^="/media/"]').last();
    const box = (await img.boundingBox())!;
    const editorBox = (await editor.boundingBox())!;

    expect(box.width, `${shape.name}: must not overflow the editor column`).toBeLessThanOrEqual(editorBox.width + 1);
    expect(box.height, `${shape.name}: must respect the ${EDITOR_IMAGE_MAX_HEIGHT}px preview cap`)
      .toBeLessThanOrEqual(EDITOR_IMAGE_MAX_HEIGHT + 1);
    // Aspect ratio preserved - a squashed preview misreports the post.
    const ratio = (box.width / box.height) / (shape.w / shape.h);
    expect(Math.abs(ratio - 1), `${shape.name}: aspect ratio must be preserved`).toBeLessThan(0.02);
    // Never upscale something already smaller than the cap.
    expect(box.width, `${shape.name}: must not be upscaled past natural width`).toBeLessThanOrEqual(shape.w + 1);
  });
}
