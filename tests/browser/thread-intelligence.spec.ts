import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test, type BrowserContext, type Locator, type Page, type TestInfo } from '@playwright/test';

const repoRoot = path.resolve(__dirname, '..', '..');
const evidenceDir = path.join(repoRoot, 'docs', 'evidence', 'browser');
const fixtureScript = path.join(__dirname, 'thread-intelligence-fixture.php');

type FixtureState = {
  fallback: { path: string };
  brief: { path: string; source_id: number; source_path: string; related_path: string };
  last_good: { path: string };
  source_invalid: { path: string };
  admin: { thread_title: string };
};

function projectKey(info: TestInfo): 'desktop' | 'mobile' {
  return info.project.name === 'mobile' ? 'mobile' : 'desktop';
}

function fixture(action: string, info: TestInfo): FixtureState {
  const output = execFileSync(process.env.PHP_BINARY ?? 'php', [fixtureScript, action, projectKey(info)], {
    cwd: repoRoot,
    env: {
      ...process.env,
      APP_KEY: process.env.APP_KEY,
      OPENAI_API_KEY: process.env.OPENAI_API_KEY,
      DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e',
    },
  }).toString();

  return JSON.parse(output) as FixtureState;
}

/**
 * `fullPage` is the wrong instrument for the Living Brief. The Study's thread
 * column is its own scroll container, so the document stays viewport-height and
 * a page-level capture records only what the pane has not scrolled past. That
 * was survivable while curation lived in the viewport-pinned Topic tools drawer;
 * the redesign moved it into the scroller, and a page-level shot then framed the
 * footer's header while leaving the disclosure body it is named for out of frame.
 *
 * Naming the element is necessary but not sufficient: what a scroll container
 * clips is never painted, and no capture can record pixels the compositor did
 * not draw — an element shot of a surface taller than the pane comes back with
 * the overflow as flat background.
 *
 * What has to be measured is the scroller, not the viewport. `.thread-conversation`
 * is `calc(100dvh - var(--topbar-h) - 48px)` and pins `.thread-dock` below the
 * scroller (app.css:1942-1955), so the visible budget is the viewport less the
 * topbar, that 48px, and the reply dock — roughly 290px on desktop. Padding by a
 * constant guesses at that chrome and gets it wrong; `fit()` reads `clientHeight`
 * off the element's own scrolling ancestor instead, so the pad is whatever the
 * chrome actually costs, on either viewport, and survives a change to it.
 *
 * The assertion is containment for the same reason. Comparing the element's
 * height against a viewport height set two lines earlier is near-tautological,
 * and it stayed silent on a genuinely clipped capture. Whether the element's rect
 * lies inside the scroller's rect is the question the artifact depends on, and it
 * cannot be satisfied while any part of the surface is still cut off.
 *
 * The admin console is an ordinary document and keeps its page-level capture.
 */
type Fit = { height: number; port: number; deficit: number; clipped: boolean };

/** Measure an element against the ancestor that actually clips it. */
const fit = (node: Element): Fit => {
  let scroller: Element | null = node.parentElement;
  while (scroller !== null && scroller !== document.body && scroller !== document.documentElement) {
    const overflowY = getComputedStyle(scroller).overflowY;
    if (overflowY === 'auto' || overflowY === 'scroll' || overflowY === 'overlay') break;
    scroller = scroller.parentElement;
  }
  const rect = node.getBoundingClientRect();
  const height = Math.ceil(rect.height);
  if (scroller === null || scroller === document.body || scroller === document.documentElement) {
    const port = document.documentElement.clientHeight;
    return { height, port, deficit: height - port, clipped: rect.top < -1 || rect.bottom > port + 1 };
  }
  const box = scroller.getBoundingClientRect();
  return {
    height,
    port: scroller.clientHeight,
    deficit: height - scroller.clientHeight,
    // A pixel of tolerance for subpixel layout; a clipped surface misses by far more.
    clipped: rect.top < box.top - 1 || rect.bottom > box.bottom + 1,
  };
};

async function shot(page: Page, info: TestInfo, name: string, element?: string): Promise<void> {
  await page.evaluate(() => window.scrollTo(0, 0));
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
  if (name === '79-admin-thread-intelligence' && info.project.name === 'mobile') {
    await page.locator('.thread-intelligence-admin .table-scroll').evaluate((region) => {
      region.scrollLeft = region.scrollWidth;
    });
  }
  const target = path.join(evidenceDir, info.project.name, `${name}.png`);
  if (element === undefined) {
    await page.screenshot({ path: target, fullPage: true, animations: 'disabled' });
    return;
  }

  const locator = page.locator(element);
  const viewport = page.viewportSize();
  expect(viewport, 'element captures need a known viewport to restore').not.toBeNull();
  try {
    // Growing the viewport grows the scroller with it and reflows the surface, so
    // re-measure each pass rather than trusting the first deficit.
    for (let pass = 0; pass < 4; pass += 1) {
      const measured = await locator.evaluate(fit);
      if (measured.deficit <= 0) break;
      const current = page.viewportSize()!;
      await page.setViewportSize({ width: current.width, height: current.height + measured.deficit + 8 });
    }
    await locator.scrollIntoViewIfNeeded();

    const framed = await locator.evaluate(fit);
    expect(
      framed.clipped,
      `${element} is still clipped by its scroller (${framed.height}px surface in a ${framed.port}px port): `
        + 'the capture would record unpainted background where the overflow is',
    ).toBe(false);
    await locator.screenshot({ path: target, animations: 'disabled' });
  } finally {
    await page.setViewportSize(viewport!);
  }
}

async function visit(page: Page, url: string): Promise<void> {
  const response = await page.goto(url);
  expect(response, `no response for ${url}`).not.toBeNull();
  expect(response!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
}

async function openTopicTools(page: Page, section: 'watch' | 'standing' | 'tags' | 'memory' | 'management') {
  const trigger = page.getByRole('button', { name: 'Topic tools', exact: true });
  await trigger.click();
  const tools = page.locator('[data-topic-tools]');
  await expect(tools).toBeVisible();
  await tools.evaluate(async (element) => Promise.all(element.getAnimations().map((animation) => animation.finished)));
  const details = tools.locator(`[data-topic-tools-section="${section}"]`);
  if (!(await details.evaluate((element) => (element as HTMLDetailsElement).open))) await details.locator(':scope > summary').click();
  return { tools, details };
}

/**
 * The curator controls now live inside three nested `<details>` disclosures
 * (`.lb-amend`, `.lb-more`, `.lb-confirm`), so nothing in them is actionable —
 * with JS or without it — until the disclosure is opened. Idempotent, because a
 * form POST re-renders the page with every disclosure shut again.
 */
async function openDisclosure(details: Locator): Promise<void> {
  // Read the attribute rather than the DOM property: this also runs inside the
  // javaScriptEnabled:false context, where page-world evaluate() is dead. The
  // summary is opened from the keyboard rather than clicked, which is both the
  // stronger claim (a disclosure nobody can reach by keyboard is broken) and the
  // only one that does not depend on Playwright's pointer actionability sampling
  // — that sampling stalls against the brief's entrance animation in a context
  // with no script engine driving the frame loop.
  if (await details.getAttribute('open') === null) {
    const summary = details.locator(':scope > summary');
    await summary.focus();
    await expect(summary).toBeFocused();
    await summary.page().keyboard.press('Enter');
  }
  await expect(details).toHaveAttribute('open', '');
}

/**
 * Restore is no longer one `<select>` of versions: every row carries its own
 * form, so `form[action$="/summary/restore"]` matches several elements on any
 * topic with history and Playwright's strict mode rejects it. Each row's button
 * carries a distinct accessible name ("Restore version 3") instead, which is
 * what this reads back off the row rather than hard-coding a version number
 * that shifts with every publish.
 */
async function versionRow(scope: Locator, index: number): Promise<{
  row: Locator;
  version: number;
  label: string;
  restore: Locator;
}> {
  const row = scope.locator('.lb-version').nth(index);
  await expect(row).toBeVisible();
  const version = Number((await row.locator('.lb-version-v').innerText()).trim().replace(/^v/i, ''));
  expect(Number.isInteger(version), 'version row should carry a numeric vN').toBe(true);
  const label = (await row.locator('.lb-version-who').innerText()).trim();
  return { row, version, label, restore: row.getByRole('button', { name: `Restore version ${version}`, exact: true }) };
}

function threadIdOf(threadPath: string): number {
  const match = threadPath.match(/^\/t\/(\d+)/);
  expect(match, `no thread id in ${threadPath}`).not.toBeNull();
  return Number(match![1]);
}

/**
 * With JavaScript off a submit button performs a real navigation, so the click
 * must be paired with the load it causes; asserting straight afterwards races
 * the document being torn down.
 */
async function submitNative(page: Page, button: Locator): Promise<void> {
  await Promise.all([page.waitForEvent('load'), button.click()]);
}

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1_000 }).catch(() => false)) {
    await skip.click();
  }
}

async function login(page: Page, email: string): Promise<void> {
  await page.context().clearCookies();
  await visit(page, '/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill('password123');
  await page.getByRole('button', { name: 'Log in' }).click();
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));
  await dismissTour(page);
}

async function loginWithoutJavaScript(page: Page, email: string): Promise<void> {
  await visit(page, '/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill('password123');
  await Promise.all([
    page.waitForURL((url) => !url.pathname.endsWith('/login')),
    page.getByRole('button', { name: 'Log in' }).click(),
  ]);
}

async function enterThemeSafeMode(page: Page): Promise<boolean> {
  await visit(page, '/admin/themes/safe-mode');
  // Structural state read: the enter and exit forms are mutually exclusive
  // (theme_safe_mode.php:43-69), so the status prose can be reworded freely.
  const enter = page.getByRole('button', { name: 'Enter safe mode' });
  if (!await enter.isVisible({ timeout: 2000 }).catch(() => false)) {
    return false;
  }

  await enter.click();
  await expect(page.getByRole('status').getByText('Theme safe mode is on.')).toBeVisible();
  return true;
}

async function exitThemeSafeMode(page: Page, changed: boolean): Promise<void> {
  if (!changed) return;

  await visit(page, '/admin/themes/safe-mode');
  const exit = page.getByRole('button', { name: 'Exit safe mode' });
  if (await exit.isVisible({ timeout: 1_000 }).catch(() => false)) {
    await page.locator('form:has(input[name="exit"]) input[name="current_password"]').fill('password123');
    await exit.click();
    await expect(page.getByRole('status').getByText('Theme safe mode was exited.')).toBeVisible();
  }
}

function generationRow(page: Page, threadTitle: string) {
  return page.getByRole('link', { name: threadTitle, exact: true }).locator('xpath=ancestor::tr');
}

async function expectNoSeriousA11yViolations(page: Page, info: TestInfo, include: string): Promise<void> {
  // Land any running entrance animation first. axe reads the composited colour,
  // so a card still fading in reports its foreground blended toward the surface
  // — mid-fade axe read the brief's gold and rust inks as #8a6d35 and #a55a44 at
  // 3.9:1 and 4.06:1, against a background it had blended too. Settled, the real
  // tokens #7E5F22 and #9C4A33 on --surface-sunken #ECE4D2 measure 4.68:1 and
  // 4.82:1: a 0.18 margin over AA, which is exactly why the fade must land
  // before the scan rather than be argued away.
  // Infinite animations cannot be finished; they are left running.
  await page.evaluate(() => {
    for (const animation of document.getAnimations()) {
      try {
        animation.finish();
      } catch {
        /* an infinite animation has no finished state to jump to */
      }
    }
  });
  const result = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .include(include)
    .analyze();
  const violations = result.violations.filter((item) => item.impact === 'serious' || item.impact === 'critical');
  expect(violations, `${info.project.name} ${page.url()} ${include} serious/critical axe violations`).toEqual([]);
}

async function expectNoHorizontalOverflow(page: Page, selector: string): Promise<void> {
  const metrics = await page.locator(selector).evaluateAll((nodes) => nodes.map((node) => ({
    clientWidth: (node as HTMLElement).clientWidth,
    scrollWidth: (node as HTMLElement).scrollWidth,
  })));
  expect(metrics.length, `${selector} should have rendered elements`).toBeGreaterThan(0);
  for (const metric of metrics) {
    expect(metric.scrollWidth, `${selector} content should wrap`).toBeLessThanOrEqual(metric.clientWidth + 1);
  }
}

test.describe.configure({ mode: 'serial' });

test('fallback, curator empty state, and generated Living Brief render safely with provenance, navigation, and responsive wrapping', async ({ page }, info) => {
  const state = fixture('reset-brief', info);

  // A member without curator authority sees the deterministic fallback and
  // nothing else: the empty panel is curator-only markup and must not leak.
  await visit(page, state.fallback.path);
  await expect(page.locator('.related-topic-fallback')).toBeVisible();
  await expect(page.locator('.living-brief')).toHaveCount(0);
  await expect(page.locator('.living-brief-empty')).toHaveCount(0);
  await expect(page.locator('.thread-memory-slot')).not.toBeEmpty();

  await visit(page, state.brief.path);
  const brief = page.locator('.living-brief');
  await expect(brief).toBeVisible();
  // The section's only heading was replaced by an accessible name on the
  // section itself, so the label is now an attribute rather than an <h2>.
  await expect(brief).toHaveAttribute('aria-label', 'Living brief');
  await expect(brief.locator('.living-brief-head h2')).toHaveCount(0);
  await expect(brief).toContainText('AI-generated living brief');
  await expect(brief).toContainText('Version 1');
  await expect(brief.locator('time')).toHaveAttribute('datetime', /Z$/);
  await expect(brief.getByRole('link', { name: 'AI-generated living brief' })).toHaveAttribute('href', '/privacy#thread-intelligence');
  await expect(brief.locator('.living-brief-sources a')).toHaveCount(8);
  await expect(brief.locator('.living-brief-related-card')).toHaveCount(1);
  // A reader with no curator authority gets the brief and none of its tools.
  await expect(brief.locator('.living-brief-curator')).toHaveCount(0);

  await brief.locator('.living-brief-sources a').first().click();
  await expect(page).toHaveURL(new RegExp(`#p${state.brief.source_id}$`));
  await page.goBack();
  await brief.locator('.living-brief-related-card').click();
  await expect(page).toHaveURL(new RegExp(state.brief.related_path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$'));
  await page.goBack();

  await expectNoHorizontalOverflow(page, '.living-brief');
  await expectNoHorizontalOverflow(page, '.living-brief-related-card');
  await expectNoHorizontalOverflow(page, '.living-brief-sources li');
  if (info.project.name === 'mobile') {
    const cards = await page.locator('.living-brief-related-card').evaluateAll((nodes) => nodes.map((node) => ({
      left: Math.round(node.getBoundingClientRect().left),
      width: Math.round(node.getBoundingClientRect().width),
    })));
    expect(new Set(cards.map((card) => card.left)).size).toBe(1);
    expect(cards[0].width).toBeGreaterThan(250);
  }
  await shot(page, info, '76-living-brief', '.living-brief');

  // The same brief-less topic, seen by a curator: the empty panel renders
  // beside the fallback and explains the eligibility ladder's actual denial.
  await login(page, 'alice@retro.test');
  await visit(page, state.fallback.path);
  await expect(page.locator('.related-topic-fallback')).toBeVisible();
  const empty = page.locator('.living-brief-empty');
  await expect(empty).toBeVisible();
  await expect(empty).toHaveAttribute('aria-label', 'No living brief yet');
  await expect(empty.locator('.living-brief-empty-eyebrow')).toHaveText('No brief yet');
  const emptyCopy = empty.locator('.living-brief-empty-copy');
  await expect(emptyCopy).toContainText('eight eligible posts');
  await expect(emptyCopy).toContainText('This one has 3.');
  // Nothing has ever been published here, so the composer names a first summary
  // and Retire — which needs a brief to act on — stays out of the panel.
  await expect(empty.locator('.lb-amend > summary')).toHaveText('Write the first summary');
  await expect(empty.locator('.lb-confirm')).toHaveCount(0);
  await expect(empty.locator('.lb-versions')).toHaveCount(0);
  await expect(empty.getByRole('button', { name: 'Refresh', exact: true })).toBeDisabled();
  await expectNoHorizontalOverflow(page, '.living-brief-empty');
  await shot(page, info, '75-thread-intelligence-fallback', '.thread-memory-slot');
});

test('curator tools hang off the brief, and edit, real-worker refresh, retirement, restoration and explicit resume preserve lineage', async ({ page }, info) => {
  const state = fixture('reset-brief', info);
  const threadId = threadIdOf(state.brief.path);
  await login(page, 'alice@retro.test');
  await visit(page, state.brief.path);

  // The drawer no longer carries the controls — only a pointer at them.
  const memory = await openTopicTools(page, 'memory');
  await expect(memory.details.locator('form')).toHaveCount(0);
  const jump = memory.details.getByRole('link', { name: "Go to the brief's curator tools" });
  await expect(jump).toHaveAttribute('href', `#living-brief-curator-${threadId}`);
  await jump.click();
  // The anchor doubles as the modal's closer, and the fragment navigation still runs.
  await expect(page.locator('[data-topic-tools]')).toBeHidden();
  await expect(page).toHaveURL(new RegExp(`#living-brief-curator-${threadId}$`));

  const curator = page.locator(`#living-brief-curator-${threadId}`);
  await expect(curator).toBeVisible();
  // Exactly one such panel in the document, and it hangs off the brief it acts on.
  await expect(page.locator('.living-brief > .living-brief-curator')).toHaveCount(1);
  await expect(page.locator('[id^="living-brief-curator-"]')).toHaveCount(1);

  // One primary action in the row; everything else is a disclosure away.
  await expect(curator.locator('.living-brief-curator-row').getByRole('button', { name: 'Refresh', exact: true })).toBeVisible();
  const amend = curator.locator('.lb-amend');
  await expect(amend.locator('> summary')).toHaveText('Amend');
  await openDisclosure(amend);
  const more = curator.locator('.lb-more');
  await expect(more.locator('.lb-more-shut')).toBeVisible();
  await openDisclosure(more);
  await expect(more.locator('.lb-more-open')).toBeVisible();
  await expect(more.locator('.lb-more-shut')).toBeHidden();
  await expect(more.getByRole('button', { name: 'Pause automatic refresh' })).toBeVisible();
  await expect(more.locator('.lb-confirm > summary')).toHaveText('Retire brief');
  // Restore is per row now: no <select>, and several forms share the action.
  await expect(curator.locator('select[name="summary_id"]')).toHaveCount(0);
  await expect(curator.locator('form[action$="/summary/restore"] input[name="summary_id"]').first())
    .toHaveAttribute('type', 'hidden');
  await expectNoHorizontalOverflow(page, '.lb-more-body');
  await shot(page, info, '77-living-brief-curator-controls', '.living-brief');

  const editor = amend.locator('form[action$="/summary"]');
  await editor.locator('textarea[name="body"]').fill(`Curator baseline for ${projectKey(info)} with retained public evidence.`);
  await editor.locator('input[name="source_post_ids"]').fill(String(state.brief.source_id));
  await editor.getByRole('button', { name: 'Publish amendment' }).click();
  await expect(page.locator('.living-brief')).toContainText('AI-generated · curator edited');
  await expect(page.locator('.living-brief')).toContainText('@alice');

  fixture('prepare-refresh', info);
  await page.reload();
  await curator.locator('.living-brief-curator-row').getByRole('button', { name: 'Refresh', exact: true }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Refresh queued' })).toBeVisible();
  fixture('run-refresh', info);
  await page.reload();
  await expect(page.locator('.living-brief')).toContainText('AI-generated living brief');
  await expect(page.locator('.living-brief')).toContainText('Curator baseline carried forward');

  // The curator edit is still reachable as its own row in the version history.
  await openDisclosure(more);
  expect(await more.locator('form[action$="/summary/restore"]').count(),
    'several restore forms share one action, so only the per-row names are safe selectors').toBeGreaterThan(1);
  const edited = more.locator('.lb-version', { hasText: 'AI-generated · curator edited' }).first();
  await expect(edited).toBeVisible();

  const confirm = more.locator('.lb-confirm');
  await openDisclosure(confirm);
  await confirm.getByRole('button', { name: 'Confirm retirement' }).click();
  await expect(page.locator('.living-brief')).toHaveCount(0);

  // Retiring pauses automation, so the curator-only panel takes over: it names
  // the absence honestly, promotes Restore, and offers Resume as the primary.
  const empty = page.locator('.living-brief-empty');
  await expect(empty).toBeVisible();
  await expect(empty).toHaveAttribute('aria-label', 'No living brief showing');
  await expect(empty.locator('.living-brief-empty-eyebrow')).toHaveText('No brief showing');
  await expect(empty.locator('.lb-amend > summary')).toHaveText('Write a new summary');
  await expect(empty.getByRole('button', { name: 'Resume automatic refresh' })).toBeVisible();
  await expect(empty.getByRole('button', { name: 'Refresh', exact: true })).toHaveCount(0);
  await expect(empty.locator('.lb-confirm')).toHaveCount(0);

  const target = await versionRow(empty, 0);
  await target.restore.click();
  const brief = page.locator('.living-brief');
  await expect(brief).toBeVisible();
  await expect(brief).toContainText(`Version ${target.version}`);
  await expect(brief).toContainText(target.label);
  // The brief is back and automation is still paused — this is the
  // member-visible status line, on the brief itself rather than in the drawer.
  await expect(brief.locator('.living-brief-status.is-paused'))
    .toContainText('Automatic refresh is paused for this topic. The brief stands as published.');
  // A paused topic must not offer a dead Refresh above a near-duplicate sentence.
  await expect(curator.locator('.living-brief-curator-row').getByRole('button', { name: 'Refresh', exact: true })).toHaveCount(0);

  await curator.getByRole('button', { name: 'Resume automatic refresh' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Automatic refresh resumed.' })).toBeVisible();
  await expect(page.locator('.living-brief .living-brief-status.is-paused')).toHaveCount(0);
  await expect(curator.getByRole('button', { name: 'Resume automatic refresh' })).toHaveCount(0);
  await expect(curator.locator('.living-brief-curator-row').getByRole('button', { name: 'Refresh', exact: true })).toBeVisible();
  // Resume and Pause swap places by state; Pause lives in the More disclosure.
  await openDisclosure(more);
  await expect(more.getByRole('button', { name: 'Pause automatic refresh' })).toBeVisible();
});

test('the brief entrance and the More disclosure honour prefers-reduced-motion', async ({ page }, info) => {
  const state = fixture('reset-brief', info);
  await login(page, 'alice@retro.test');
  await visit(page, state.brief.path);

  const brief = page.locator('.living-brief');
  const more = brief.locator('.lb-more');
  await expect(brief).toHaveCSS('animation-name', 'lbFade');
  await openDisclosure(more);
  await expect(more.locator('.lb-more-body')).toHaveCSS('animation-name', 'lbFade');

  // The global clamp only shortens durations; the per-component opt-out is what
  // removes the animation itself, and only this can prove it.
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await expect(brief).toHaveCSS('animation-name', 'none');
  await expect(more.locator('.lb-more-body')).toHaveCSS('animation-name', 'none');

  // The memory slot renders one card either way, so the curator-only panel is
  // covered by the same opt-out.
  await visit(page, state.fallback.path);
  await expect(page.locator('.living-brief-empty')).toHaveCSS('animation-name', 'none');
  await page.emulateMedia({ reducedMotion: 'no-preference' });
  await expect(page.locator('.living-brief-empty')).toHaveCSS('animation-name', 'lbFade');
});

test('provider failure, budget exhaustion, and stale sources preserve or suppress the correct last-good content', async ({ page }, info) => {
  const state = fixture('reset-guardrails', info);
  try {
    await login(page, 'alice@retro.test');
    await visit(page, state.last_good.path);
    await expect(page.locator('.living-brief')).toContainText('Last good brief remains published');
    await expect(page.locator('.living-brief')).toContainText('Version 1');
    // The denial explains itself under the curator's own primary action, which
    // is disabled rather than absent, instead of inside the topic-tools drawer.
    const curatorRow = page.locator('.living-brief .living-brief-curator-row');
    await expect(curatorRow.getByRole('button', { name: 'Refresh', exact: true })).toBeDisabled();
    await expect(page.locator('.living-brief .living-brief-curator-note'))
      .toContainText('Daily refresh capacity has been reached');
    await shot(page, info, '78-living-brief-last-good', '.living-brief');

    fixture('invalidate-source', info);
    try {
      await visit(page, state.source_invalid.path);
      await expect(page.locator('.living-brief')).toHaveCount(0);
      await expect(page.locator('.related-topic-fallback')).toBeVisible();
      await expect(page.locator('.living-brief-related-card')).toHaveCount(0);
      // Suppressed-as-stale leaves the version rows behind, so the curator panel
      // says "no brief showing" rather than pretending none was ever drawn.
      await expect(page.locator('.living-brief-empty'))
        .toHaveAttribute('aria-label', 'No living brief showing');
    } finally {
      fixture('restore-source', info);
    }
  } finally {
    fixture('restore-guardrails', info);
  }
});

test('admin status and restorative retry, reconcile, latch, pause, thread, and budget controls remain operable', async ({ page }, info) => {
  const state = fixture('reset-admin', info);
  await login(page, 'admin@retro.test');
  const themeSafeModeChanged = await enterThemeSafeMode(page);

  try {
    await visit(page, '/admin/thread-intelligence');
    const dashboard = page.locator('.thread-intelligence-admin');
    await expect(page.getByRole('heading', { level: 1, name: 'General & intelligence' })).toBeVisible();
    await expect(page.locator('.admin-tab.is-active[aria-current="page"]')).toHaveText('Thread Intelligence');
    await expect(dashboard.locator('.ti-intro')).toHaveText('Automated context for long topics. Staff set the terms; the model proposes and local validation decides. Everything it writes is evidenced below, and the egress brake is one button away.');
    const statusCards = dashboard.locator('.ti-status-card');
    await expect(statusCards).toHaveCount(4);
    await expect(statusCards.locator('.ti-status-label')).toHaveText(['Product flags', 'Provider', 'Heartbeat', 'Generation']);
    await expect(dashboard.getByRole('heading', { level: 2, name: 'Recovery controls' })).toBeVisible();
    await expect(dashboard).toContainText('community memory on');
    await expect(dashboard).toContainText('automated context on');
    await expect(dashboard).toContainText('Ready');
    await expect(dashboard.locator('.ti-controls-actions form')).toHaveCount(2);
    await expect(dashboard.locator('.ti-budget-calls .ti-budget-label')).toHaveText('Calls');
    await expect(dashboard.locator('.ti-budget-tokens .ti-budget-label')).toHaveText('Input tokens');
    await expect(dashboard.locator('.ti-budget-calls .ti-budget-value')).toHaveText(/\d+ of 100/);
    await expect(dashboard.getByRole('heading', { level: 2, name: 'Queue states' })).toBeVisible();
    const queueCards = dashboard.locator('.ti-queue-card');
    await expect(queueCards).toHaveCount(6);
    await expect(queueCards).toHaveText([
      /^\s*\d+\s*Idle\s*threads?\s*$/,
      /^\s*\d+\s*Queued\s*threads?\s*$/,
      /^\s*\d+\s*Running\s*threads?\s*$/,
      /^\s*\d+\s*Retry\s*threads?\s*$/,
      /^\s*\d+\s*Dead\s*threads?\s*$/,
      /^\s*\d+\s*Review required\s*threads?\s*$/,
    ]);
    await expect(dashboard.locator('.ti-contract-grid')).toBeVisible();
    await expect(dashboard.locator('.ti-contract-card')).toBeVisible();
    await expect(dashboard.locator('.ti-evidence-card')).toBeVisible();
    await expect(dashboard.locator('.ti-evidence-card thead th')).toHaveText([
      'ID',
      'When',
      'Topic',
      'Outcome',
      'Input tokens',
      'Contract',
      'Evidence',
      'Actions',
    ]);
    const outcomeRegisters = await dashboard.locator('[data-ti-outcome]').evaluateAll((nodes) => nodes.map((node) => ({
      register: node.getAttribute('data-ti-outcome'),
      status: node.getAttribute('data-ti-generation-status'),
    })));
    expect(outcomeRegisters.length).toBeGreaterThan(0);
    expect(outcomeRegisters.every(({ register, status }) => ['done', 'neutral', 'attention'].includes(register ?? '') && Boolean(status))).toBe(true);
    await expect(dashboard.locator('.ti-evidence').first()).toBeVisible();
    await expect(dashboard.getByText('Failed only', { exact: true })).toHaveCount(0);
    await expect(dashboard.getByText('Digest', { exact: true })).toHaveCount(0);
    const rhythm = await dashboard.evaluate((element) => {
      const gap = (before: Element, after: Element): number => Math.round(after.getBoundingClientRect().top - before.getBoundingClientRect().bottom);
      const intro = element.querySelector('.ti-intro')!;
      const status = element.querySelector('.ti-status-grid')!;
      const controls = element.querySelector('.ti-controls')!;
      const budget = element.querySelector('.ti-budget')!;
      const queue = element.querySelector('.ti-queue-section')!;
      const contract = element.querySelector('.ti-contract-grid')!;
      return [gap(intro, status), gap(status, controls), gap(controls, budget), gap(budget, queue), gap(queue, contract)];
    });
    expect(rhythm).toEqual([22, 16, 16, 16, 16]);

    let row = generationRow(page, state.admin.thread_title);
    await row.getByRole('button', { name: 'Retry', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Refresh queued' })).toBeVisible();
    row = generationRow(page, state.admin.thread_title);
    await row.getByRole('button', { name: 'Reconcile', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Reconciliation queued' })).toBeVisible();

    row = generationRow(page, state.admin.thread_title);
    await row.getByRole('button', { name: 'Pause', exact: true }).click();
    row = generationRow(page, state.admin.thread_title);
    await expect(row.getByRole('button', { name: 'Resume', exact: true })).toBeVisible();
    await row.getByRole('button', { name: 'Resume', exact: true }).click();

    await page.getByRole('button', { name: 'Pause generation' }).click();
    await expect(dashboard.locator('[data-ti-status="generation"]')).toHaveClass(/queue-status-unavailable/);
    await expect(page.getByRole('button', { name: 'Resume generation' })).toBeVisible();
    await page.getByRole('button', { name: 'Resume generation' }).click();
    await expect(dashboard.locator('[data-ti-status="generation"]')).not.toHaveClass(/queue-status-unavailable/);

    fixture('latch-provider', info);
    await page.reload();
    await expect(dashboard.locator('[data-ti-status="provider"]')).toHaveClass(/queue-status-attention/);
    await expect(dashboard).toContainText('latched');
    await expect(dashboard).toContainText('Provider configuration is latched');
    await page.getByRole('button', { name: 'Retry provider configuration' }).click();
    await expect(dashboard.locator('[data-ti-status="provider"]')).not.toHaveClass(/queue-status-attention/);
    await expect(dashboard).toContainText('available');

    fixture('exhaust-budget', info);
    await page.reload();
    await expect(dashboard.locator('.ti-budget')).toContainText('Calls 100 of 100');
    fixture('reset-admin', info);
    await page.reload();
    await expect(dashboard.locator('.ti-budget')).not.toContainText('Calls 100 of 100');
    await shot(page, info, '79-admin-thread-intelligence');
  } finally {
    fixture('reset-admin', info);
    await exitThemeSafeMode(page, themeSafeModeChanged);
  }
});

test('flags-off admin route stays live while its Thread Intelligence tab is disabled', async ({ page }, info) => {
  fixture('disable-features', info);
  try {
    await login(page, 'admin@retro.test');
    await visit(page, '/admin/thread-intelligence');
    await expect(page.getByRole('heading', { level: 1, name: 'General & intelligence' })).toBeVisible();
    await expect(page.getByText('Both product flags are off; generation remains dark.')).toBeVisible();
    const tab = page.locator('.admin-tab.is-disabled[data-destination="/admin/thread-intelligence"]');
    await expect(tab).toBeVisible();
    await expect(tab).toHaveAttribute('aria-disabled', 'true');
    await expect(page.locator('.admin-tab.is-active[aria-current="page"]')).toHaveCount(0);
  } finally {
    fixture('enable-features', info);
  }
});

test('no-JS: Living Brief navigation, every disclosure, and all five curator forms remain native', async ({ browser, baseURL }, info) => {
  const state = fixture('reset-brief', info);
  // An AI-selected related topic is only shown while the published summary's own
  // generation still owns the relation row (selectRelated()), so a run that has
  // already refreshed this topic would leave `reset-brief` republishing v1 beside
  // a relation the newer generation owns. Publishing a fresh generation makes the
  // related card deterministic whether or not the earlier tests ran.
  fixture('run-refresh', info);
  const threadId = threadIdOf(state.brief.path);
  // Reduced motion as well as no JavaScript: with no script engine driving the
  // page's frame loop, Playwright's pointer actionability sampling stalls
  // against the brief's entrance fade. The fade's own opt-out is proved by its
  // dedicated test above; here the point is the forms, not the animation.
  const context: BrowserContext = await browser.newContext({
    javaScriptEnabled: false,
    reducedMotion: 'reduce',
    baseURL,
  });
  const page = await context.newPage();
  try {
    await loginWithoutJavaScript(page, 'alice@retro.test');
    await visit(page, state.brief.path);
    await expect(page.locator('.living-brief')).toBeVisible();

    await page.locator('.living-brief-sources a').first().click();
    await expect(page).toHaveURL(new RegExp(`#p${state.brief.source_id}$`));
    await page.goBack();
    const related = page.locator('.living-brief-related-card');
    await related.focus();
    await expect(related).toBeFocused();
    await related.press('Enter');
    await expect(page).toHaveURL(new RegExp(state.brief.related_path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$'));
    await page.goBack();

    // The topic-tools drawer is a plain <details> stack without JS, and its
    // Living Brief section is now one ordinary in-page anchor.
    const tools = page.locator('[data-topic-tools]');
    const drawer = tools.locator('[data-topic-tools-section="memory"]');
    const summary = drawer.locator(':scope > summary');
    await expect(tools).toBeVisible();
    await expect(drawer).toBeVisible();
    await expect(summary).toBeVisible();
    await summary.focus();
    await expect(summary).toBeFocused();
    await summary.press('Enter');
    await expect(drawer).toHaveAttribute('open', '');
    const jump = drawer.getByRole('link', { name: "Go to the brief's curator tools" });
    await expect(jump).toHaveAttribute('href', `#living-brief-curator-${threadId}`);
    await jump.click();
    await expect(page).toHaveURL(new RegExp(`#living-brief-curator-${threadId}$`));

    const curator = page.locator(`#living-brief-curator-${threadId}`);
    const amend = curator.locator('.lb-amend');
    const more = curator.locator('.lb-more');

    // (1) The disclosures open natively — no script involved.
    await openDisclosure(amend);
    await expect(amend.locator('form[action$="/summary"]')).toBeVisible();
    await openDisclosure(more);
    await expect(more.locator('form[action$="/related"]')).toBeVisible();
    await expect(more.locator('.lb-versions .lb-version').first()).toBeVisible();

    // (2) Amend submits as a plain POST.
    await amend.locator('textarea[name="body"]').fill(`No-JS curator baseline for ${projectKey(info)}.`);
    await amend.locator('input[name="source_post_ids"]').fill(String(state.brief.source_id));
    await submitNative(page, amend.getByRole('button', { name: 'Publish amendment' }));
    await expect(page.locator('.living-brief')).toContainText('AI-generated · curator edited');

    // (3) Restore submits as a plain POST, addressed by its per-row name.
    await openDisclosure(more);
    const original = more.locator('.lb-version', { hasText: 'AI-generated living brief' }).first();
    const originalVersion = Number((await original.locator('.lb-version-v').innerText()).trim().replace(/^v/i, ''));
    await submitNative(page, original.getByRole('button', { name: `Restore version ${originalVersion}`, exact: true }));
    await expect(page.locator('.living-brief')).toContainText(`Version ${originalVersion}`);
    await expect(page.locator('.living-brief')).toContainText('AI-generated living brief');

    // (4) The Retire confirm step works without JS: a nested <details> guard in
    // front of a real POST, not a script-driven dialog.
    await openDisclosure(more);
    const confirm = more.locator('.lb-confirm');
    await expect(confirm.getByRole('button', { name: 'Confirm retirement' })).toBeHidden();
    await openDisclosure(confirm);
    await expect(confirm.getByRole('button', { name: 'Confirm retirement' })).toBeVisible();
    await submitNative(page, confirm.getByRole('button', { name: 'Confirm retirement' }));
    await expect(page.locator('.living-brief')).toHaveCount(0);

    // Restore from the curator-only panel, where the rows are promoted.
    const empty = page.locator('.living-brief-empty');
    await expect(empty).toBeVisible();
    const target = await versionRow(empty, 0);
    await submitNative(page, target.restore);
    await expect(page.locator('.living-brief')).toContainText(`Version ${target.version}`);
    // Retirement paused automation, and restoring says so on the brief itself.
    await expect(page.locator('.living-brief .living-brief-status.is-paused')).toBeVisible();

    // (5) Resume and Pause are both plain POSTs, in whichever slot their state puts them.
    await submitNative(page, curator.getByRole('button', { name: 'Resume automatic refresh' }));
    await expect(page.locator('.living-brief .living-brief-status.is-paused')).toHaveCount(0);
    await openDisclosure(more);
    await submitNative(page, more.getByRole('button', { name: 'Pause automatic refresh' }));
    await expect(page.locator('.living-brief .living-brief-status.is-paused')).toBeVisible();
    await submitNative(page, curator.getByRole('button', { name: 'Resume automatic refresh' }));
    await expect(page.locator('.living-brief .living-brief-status.is-paused')).toHaveCount(0);

    // (6) Refresh submits once the eligibility ladder allows it.
    fixture('prepare-refresh', info);
    await visit(page, state.brief.path);
    const refresh = curator.locator('.living-brief-curator-row').getByRole('button', { name: 'Refresh', exact: true });
    await expect(refresh).toBeEnabled();
    await submitNative(page, refresh);
    await expect(page.getByRole('status').filter({ hasText: 'Refresh queued' })).toBeVisible();
  } finally {
    await context.close();
  }
});

test('no-JS: Thread Intelligence recovery controls remain native POST forms', async ({ browser, baseURL }) => {
  const context: BrowserContext = await browser.newContext({ javaScriptEnabled: false, baseURL });
  const page = await context.newPage();
  try {
    await loginWithoutJavaScript(page, 'admin@retro.test');
    await visit(page, '/admin/thread-intelligence');

    const generationForm = page.locator('form[action="/admin/thread-intelligence/generation/pause"], form[action="/admin/thread-intelligence/generation/resume"]');
    const providerForm = page.locator('form[action="/admin/thread-intelligence/provider/retry"]');
    await expect(generationForm).toHaveCount(1);
    await expect(providerForm).toHaveCount(1);
    await expect(generationForm).toHaveAttribute('method', 'post');
    await expect(providerForm).toHaveAttribute('method', 'post');
    await expect(generationForm.locator('input[name="_token"]')).toHaveCount(1);
    await expect(providerForm.locator('input[name="_token"]')).toHaveCount(1);
  } finally {
    await context.close();
  }
});

test('axe: Living Brief, provenance, curator footer, empty state, fallback, and admin surfaces have no serious findings', async ({ page }, info) => {
  const state = fixture('reset-admin', info);
  // Same reason as the no-JS test: scan a brief whose own generation still owns
  // the related row, so `.living-brief-related` is present to be scanned.
  fixture('run-refresh', info);
  await login(page, 'admin@retro.test');
  const themeSafeModeChanged = await enterThemeSafeMode(page);
  try {
    await visit(page, '/admin/thread-intelligence');
    await expectNoSeriousA11yViolations(page, info, '.thread-intelligence-admin');

    await login(page, 'alice@retro.test');
    await visit(page, state.brief.path);
    // The section lost its heading in the redesign and names itself instead, so
    // the swap is scanned rather than assumed.
    await expect(page.locator('.living-brief')).toHaveAttribute('aria-label', 'Living brief');
    await expect(page.locator('.living-brief-head h2')).toHaveCount(0);
    await expectNoSeriousA11yViolations(page, info, '.living-brief');
    await expectNoSeriousA11yViolations(page, info, '.living-brief-sources');
    await expectNoSeriousA11yViolations(page, info, '.living-brief-related');

    // Every curator control, including the ones a disclosure hides by default.
    const curator = page.locator('.living-brief .living-brief-curator');
    await openDisclosure(curator.locator('.lb-amend'));
    await openDisclosure(curator.locator('.lb-more'));
    await openDisclosure(curator.locator('.lb-confirm'));
    await expectNoSeriousA11yViolations(page, info, '.living-brief-curator');

    await openTopicTools(page, 'memory');
    await expectNoSeriousA11yViolations(page, info, '[data-topic-tools]');

    await visit(page, state.fallback.path);
    await expectNoSeriousA11yViolations(page, info, '.related-topic-fallback');
    await expect(page.locator('.living-brief-empty')).toBeVisible();
    await openDisclosure(page.locator('.living-brief-empty .lb-amend'));
    await openDisclosure(page.locator('.living-brief-empty .lb-more'));
    await expectNoSeriousA11yViolations(page, info, '.living-brief-empty');
  } finally {
    await login(page, 'admin@retro.test');
    await exitThemeSafeMode(page, themeSafeModeChanged);
  }
});
