import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

/**
 * Browser evidence for the `link_previews` graduation (ADR 0025).
 *
 * Two surfaces, both of which must work with no JavaScript at all:
 *
 *  - `/admin/link-previews` — the operator console that was the last missing
 *    piece: queue health, the host allowlist, the kill switch, the per-board
 *    opt-in, and per-row refresh/purge.
 *  - The unfurled card in a thread, and the author's own remove/restore control.
 *
 * The seed makes both operator choices (allowlist + board opt-in) and writes one
 * row straight into `fetched`; a hermetic run cannot make the outbound request
 * itself, and AppLinkPreviewTest already pins the fetch client against a real
 * pinned-IP server.
 *
 * Every test here MUTATES that posture on purpose (removing a card, opting a
 * board out) — which is the whole point — so each one restores the fixture
 * before it runs rather than trusting the state the previous project left.
 */
const repoRoot = path.resolve(__dirname, '..', '..');
const EVIDENCE_DIR = path.resolve(
  repoRoot,
  process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/browser',
);

const PREVIEW_TITLE = 'Threading conventions handbook';

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

/** Put the seeded posture back: board opted in, allowlist stored, card fetched. */
function resetLinkPreviewFixture(): void {
  runPhp(`
$settings->set('link_preview_allowed_hosts', ['previews.example.test']);
$settings->set('link_preview_kill_switch', false);
$db->run("UPDATE boards SET link_previews_enabled = 1 WHERE slug = 'general'");
$db->run("UPDATE link_previews
             SET status = 'fetched', title = ?, description = ?, site_name = ?, final_url = url,
                 http_status = 200, error = NULL, removed_by = NULL, removed_at = NULL,
                 fetched_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
           WHERE url LIKE 'https://previews.example.test/%'",
        ['Threading conventions handbook',
         'How long-running topics stay readable: quoting, summaries, and when to split.',
         'Example Handbook']);
`);
}

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.screenshot({ path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`), fullPage: true });
}

async function login(page: Page, email: string): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click();
  }
}

async function expectAxeClean(page: Page, info: TestInfo, include?: string): Promise<void> {
  let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
  if (include !== undefined) builder = builder.include(include);
  const results = await builder.analyze();
  const violations = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
  expect(violations, `${info.project.name} ${page.url()} serious/critical axe violations`).toEqual([]);
}

/** The thread carrying the seeded preview, reached the way a member would. */
async function previewThreadUrl(page: Page): Promise<string> {
  await page.goto('/c/general');
  const link = page.getByRole('link', { name: /Share your favourite keyboard shortcuts/ }).first();
  await expect(link).toBeVisible();
  return (await link.getAttribute('href')) ?? '/c/general';
}

test.beforeEach(() => {
  resetLinkPreviewFixture();
});

test('link preview console reports its gates and drives the allowlist and board opt-in', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await page.goto('/admin/link-previews');
  await expect(page.getByRole('heading', { level: 1, name: 'Features & badges' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Link previews');

  // The seed made both operator choices, so the "nothing is being fetched"
  // callout is absent and the board reads as live.
  await expect(page.locator('.link-preview-blockers')).toHaveCount(0);
  await expect(page.locator('textarea[name="allowed_hosts"]')).toHaveValue(/previews\.example\.test/);
  const boardRow = page
    .locator('.link-preview-board-table tr')
    .filter({ has: page.locator('code', { hasText: '/c/general' }) });
  await expect(boardRow.locator('.features-pill')).toHaveText('On');

  // Queue health: the seeded fetched + blocked rows are both classified, and the
  // blocked row explains itself without a second click.
  await expect(page.locator('.link-preview-table .state', { hasText: 'Rendering' })).toHaveCount(1);
  await expect(page.locator('.link-preview-table .state', { hasText: 'Blocked' })).toHaveCount(1);
  await expect(page.getByText('Preview host is not allowlisted.')).toBeVisible();

  await shot(page, info, 'link-previews-console');
  await expectAxeClean(page, info, '.admin-pane');

  // The status filter is a GET form with its own submit — no onchange handler,
  // because the strict CSP forbids inline script and this must work unenhanced.
  await page.selectOption('.link-preview-filter select[name="status"]', 'blocked');
  await page.click('.link-preview-filter button[type="submit"]');
  await expect(page).toHaveURL(/status=blocked/);
  await expect(page.locator('.link-preview-table tbody tr')).toHaveCount(1);

  // Opting the board out is one POST, and the console immediately explains the
  // consequence rather than silently going quiet.
  await page.goto('/admin/link-previews');
  await boardRow.getByRole('button', { name: /Disable link previews/ }).click();
  await expect(page.locator('.flash')).toContainText('Link previews disabled');
  await expect(page.locator('.link-preview-blockers')).toContainText('No public board has opted in');
  await shot(page, info, 'link-previews-console-opted-out');
});

test('an author can remove and restore the preview on their own post with no JavaScript', async ({ browser }, info) => {
  // A context with JavaScript disabled: the whole control has to be plain forms.
  const context = await browser.newContext({ javaScriptEnabled: false });
  const page = await context.newPage();
  try {
    await login(page, 'alice@retro.test');
    const threadUrl = await previewThreadUrl(page);
    await page.goto(threadUrl);

    const cards = page.locator('.link-preview-cards');
    await expect(cards.getByText(PREVIEW_TITLE)).toBeVisible();
    await shot(page, info, 'link-preview-card');

    await cards.getByRole('button', { name: new RegExp(`Remove the link preview for ${PREVIEW_TITLE}`) }).click();
    await expect(page.locator('.flash')).toContainText('Link preview removed.');
    await expect(page.getByText(PREVIEW_TITLE)).toHaveCount(0);
    await expect(page.getByText('Link preview removed from this post.')).toBeVisible();
    await shot(page, info, 'link-preview-removed-by-author');
    // No axe pass here — axe-core is JavaScript, and this context has none by
    // design. The same two states are scanned in the JS-enabled test below.

    // Restoring puts the row back in the fetch queue; the card returns when the
    // worker next refetches it, so the flash says so instead of implying it is
    // already back.
    await page.getByRole('button', { name: 'Restore preview' }).click();
    await expect(page.locator('.flash')).toContainText('Link preview restored');
    await expect(page.getByText('Link preview removed from this post.')).toHaveCount(0);
  } finally {
    await context.close();
  }
});

test('the preview card and its removed state are free of serious axe violations', async ({ page }, info) => {
  await login(page, 'alice@retro.test');
  const threadUrl = await previewThreadUrl(page);
  await page.goto(threadUrl);

  const cards = page.locator('.link-preview-cards');
  await expect(cards.getByText(PREVIEW_TITLE)).toBeVisible();
  await expectAxeClean(page, info, '.link-preview-cards');

  await cards.getByRole('button', { name: new RegExp(`Remove the link preview for ${PREVIEW_TITLE}`) }).click();
  await expect(page.getByText('Link preview removed from this post.')).toBeVisible();
  await expectAxeClean(page, info, '.link-preview-cards');
});

test('a member who is not the author sees the card but is offered no control over it', async ({ page }, info) => {
  await login(page, 'bob@retro.test');
  const threadUrl = await previewThreadUrl(page);
  await page.goto(threadUrl);

  await expect(page.locator('.link-preview-cards').getByText(PREVIEW_TITLE)).toBeVisible();
  await expect(page.getByRole('button', { name: /Remove the link preview/ })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Restore preview' })).toHaveCount(0);
  await shot(page, info, 'link-preview-other-member');

  // …and once the author takes it down, a non-author cannot tell it was there.
  runPhp(`
$db->run("UPDATE link_previews SET status = 'removed', title = NULL, description = NULL,
                 removed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
           WHERE url LIKE 'https://previews.example.test/%'");
`);
  await page.reload();
  await expect(page.getByText(PREVIEW_TITLE)).toHaveCount(0);
  await expect(page.getByText('Link preview removed from this post.')).toHaveCount(0);
});
