import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const repoRoot = path.resolve(__dirname, '..', '..');
function php(code: string): string {
  return execFileSync('php', ['-r', `require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd().'/.env');
$config=\\App\\Core\\Config::fromFile(getcwd().'/config/config.php');
$db=new \\App\\Core\\Database($config->get('db'));
${code}`], { cwd: repoRoot, env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' } }).toString();
}
type Fixture = { post: number; topic: string; bob: number; reactions: unknown[]; prefs: unknown };
let fixture: Fixture;

test.beforeEach(() => {
  // Use Bob's own post: browser toggles are real, self-reactions do not alter
  // reputation, and the existing topic/reactions are restored after every test.
  fixture = JSON.parse(php(`
$bob=(int)$db->fetchValue("SELECT id FROM users WHERE email='bob@retro.test'");
$alice=(int)$db->fetchValue("SELECT id FROM users WHERE email='alice@retro.test'");
$candidate=$db->fetch("SELECT p.id,p.thread_id,t.slug FROM posts p JOIN threads t ON t.id=p.thread_id JOIN boards b ON b.id=t.board_id WHERE p.user_id=? AND p.is_deleted=0 AND p.is_pending=0 AND b.visibility='public' ORDER BY p.id LIMIT 1",[$bob]);
$p=(int)($candidate['id']??0);
if(!$p) throw new RuntimeException('Missing seeded public Bob post');
$snapshot=['post'=>$p,'topic'=>'/t/'.$candidate['thread_id'].'-'.$candidate['slug'],'bob'=>$bob,'reactions'=>$db->fetchAll('SELECT * FROM reactions WHERE post_id=?',[$p]),'prefs'=>$db->fetch('SELECT * FROM user_preferences WHERE user_id=?',[$bob])];
$db->transaction(function() use($db,$p,$alice){$db->run('DELETE FROM reactions WHERE post_id=?',[$p]); $db->run('INSERT INTO reactions(post_id,user_id,emoji,created_at) VALUES(?,?,?,UTC_TIMESTAMP())',[$p,$alice,'👍']);});
(new \\App\\Repository\\UserPreferenceRepository($db))->merge($bob,['__v'=>\\App\\Support\\PreferenceSchema::VERSION,'reduced_motion'=>false,'show_reactions'=>true]);
echo json_encode($snapshot);`));
});
test.afterEach(() => {
  if (!fixture) return;
  const encoded = Buffer.from(JSON.stringify(fixture)).toString('base64');
  php(`$s=json_decode(base64_decode('${encoded}'),true);
$db->transaction(function() use($db,$s){
 $db->run('DELETE FROM reactions WHERE post_id=?',[$s['post']]);
 foreach($s['reactions'] as $r) $db->run('INSERT INTO reactions(id,post_id,user_id,emoji,created_at) VALUES(?,?,?,?,?)',[$r['id'],$r['post_id'],$r['user_id'],$r['emoji'],$r['created_at']]);
 $db->run('DELETE FROM user_preferences WHERE user_id=?',[$s['bob']]);
 if($s['prefs']) $db->run('INSERT INTO user_preferences(user_id,prefs,updated_at) VALUES(?,?,?)',[$s['bob'],$s['prefs']['prefs'],$s['prefs']['updated_at']]);
});`);
});

async function login(page: Page) {
  await page.goto('/login');
  await page.locator('input[name=email]').fill('bob@retro.test');
  await page.locator('input[name=password]').fill('password123');
  await page.locator('button[type=submit]').click();
  await page.waitForURL(u => !u.pathname.endsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible().catch(() => false)) await skip.click();
}
async function open(page: Page) {
  await login(page);
  await page.goto(fixture.topic);
  const post = page.locator(`#p${fixture.post}`);
  await post.scrollIntoViewIfNeeded();
  return post;
}
function chip(post: Locator, label = 'Commend') {
  return post.locator(`.reactions > .reaction-form button[data-label="${label}"]`);
}
async function toggle(page: Page, button: Locator, survives = true) {
  const response = page.waitForResponse(r => r.url().endsWith(`/posts/${fixture.post}/react`) && r.request().method() === 'POST');
  await button.click();
  const result = await response;
  expect(result.ok()).toBe(true);
  expect((await result.json()).ok).toBe(true);
  if (survives) await expect(button).not.toHaveAttribute('aria-disabled');
}
async function capture(page: Page, info: TestInfo, name: string) {
  const target = process.env.RB_EVIDENCE_DIR
    ? path.resolve(repoRoot, process.env.RB_EVIDENCE_DIR, info.project.name, `${name}.png`)
    : info.outputPath(`${name}.png`);
  mkdirSync(path.dirname(target), { recursive: true });
  await page.screenshot({ path: target });
}

test('surviving reaction counts tick locally, describe current reactors and retain the button node', async ({ page }, info) => {
  const post = await open(page);
  const button = chip(post);
  const original = await button.elementHandle();
  await expect(button.locator('.reaction-n-val')).toHaveText('1');
  await expect(button.locator('.reaction-tip')).toContainText('@alice');
  await expect(button).not.toHaveAttribute('title');
  await button.focus();
  await toggle(page, button);
  await expect(button).toHaveAttribute('aria-pressed', 'true');
  await expect(button.locator('.reaction-n-val')).toHaveText('2');
  await expect(button.locator('.reaction-n-val')).toHaveClass(/\bis-up\b/);
  await expect(button.locator('.reaction-tip')).toContainText('You and @alice');
  const description = await button.getAttribute('aria-describedby');
  expect(description).toBeTruthy();
  await expect(button.locator(`#${description}`)).toHaveAttribute('aria-hidden', 'true');
  expect(await original!.evaluate(el => el.isConnected)).toBe(true);
  await expect(button).toBeFocused();
  await toggle(page, button);
  await expect(button.locator('.reaction-n-val')).toHaveText('1');
  await expect(button.locator('.reaction-n-val')).toHaveClass(/\bis-down\b/);
  await expect(button).toHaveAttribute('aria-pressed', 'false');
  await expect(button.locator('.reaction-tip')).not.toContainText('You');
  await page.keyboard.press('Tab');
  await button.focus();
  await expect.poll(() => button.locator('.reaction-tip').evaluate(el => getComputedStyle(el).opacity)).toBe('1');
  await capture(page, info, 'conversation-reaction-tip');
});

test('same post and emoji cannot submit twice while the toggle is outstanding', async ({ page }) => {
  const post = await open(page);
  let requests = 0;
  let release!: () => void;
  const held = new Promise<void>(resolve => { release = resolve; });
  await page.route(`**/posts/${fixture.post}/react`, async route => { requests++; await held; await route.continue(); });
  const button = chip(post);
  await button.focus();
  await button.click();
  await expect(button).toHaveAttribute('aria-disabled', 'true');
  await expect(button).toBeFocused();
  const related = post.locator('.reaction-form').filter({ has: page.locator('input[name=emoji][value="👍"]') });
  await expect(related).toHaveCount(3);
  await expect(related.locator('button[aria-disabled=true]')).toHaveCount(3);
  await related.evaluateAll(forms => forms.forEach(form => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))));
  expect(requests).toBe(1);
  release();
  await expect(button).not.toHaveAttribute('aria-disabled');
  await expect(button.locator('.reaction-n-val')).toHaveText('2');
  expect(requests).toBe(1);
});

test('completing a delayed toggle respects a deliberate focus move', async ({ page }) => {
  const post = await open(page);
  let release!: () => void;
  const held = new Promise<void>(resolve => { release = resolve; });
  await page.route(`**/posts/${fixture.post}/react`, async route => { await held; await route.continue(); });
  const button = chip(post);
  await button.click();
  await expect(button).toHaveAttribute('aria-disabled', 'true');
  const destination = post.locator('.post-menu > summary');
  await destination.focus();
  await expect(destination).toBeFocused();
  release();
  await expect(button).not.toHaveAttribute('aria-disabled');
  await expect(button.locator('.reaction-n-val')).toHaveText('2');
  await expect(destination).toBeFocused();
});

test('an ambiguous lost response offers Reload and never automatically reposts the toggle', async ({ page }) => {
  const post = await open(page);
  await page.goto(fixture.topic + `#p${fixture.post}`);
  const button = chip(post);
  let requests = 0;
  await page.route(`**/posts/${fixture.post}/react`, async route => {
    requests++;
    // The server commits, then the response is lost. Retrying would undo the
    // reaction; Reload must read the real committed state instead.
    const response = await route.fetch();
    expect(response.ok()).toBe(true);
    await route.abort('failed');
  });
  await button.click();
  const status = post.locator('.reactions [role=status]');
  await expect(status).toContainText('Could not confirm the reaction.');
  const reload = status.getByRole('link', { name: 'Reload to check its state.' });
  await expect(reload).toBeVisible();
  await expect(button).not.toHaveAttribute('aria-disabled');
  await expect(post.locator('.reaction-form[data-reaction-pending]')).toHaveCount(0);
  await expect(button).toHaveAttribute('aria-pressed', 'false');
  expect(requests).toBe(1);
  await Promise.all([
    page.waitForResponse(r => r.request().method() === 'GET' && new URL(r.url()).pathname === fixture.topic),
    reload.click(),
  ]);
  await expect(chip(page.locator(`#p${fixture.post}`))).toHaveAttribute('aria-pressed', 'true');
  await expect(chip(page.locator(`#p${fixture.post}`)).locator('.reaction-n-val')).toHaveText('2');
  expect(requests).toBe(1);
});

test('reaction refusals show the kernel reason without claiming an uncertain toggle', async ({ page }) => {
  const post = await open(page);
  const button = chip(post);
  // Reuse real kernel-rendered refusals: authorization, not found and CSRF.
  const board = php(`echo $db->fetchValue('SELECT t.board_id FROM posts p JOIN threads t ON t.id=p.thread_id WHERE p.id=?',[${fixture.post}]);`);
  try {
    php(`$db->run('UPDATE boards SET is_archived=1 WHERE id=?',[${board}]);`);
    await button.click();
    await expect(post.locator('.reaction-status')).toHaveText('This board is archived and is read-only.');
    await expect(button).not.toHaveAttribute('aria-disabled');
  } finally { php(`$db->run('UPDATE boards SET is_archived=0 WHERE id=?',[${board}]);`); }
  const action = await button.evaluate(el => (el.closest('form') as HTMLFormElement).action);
  await button.evaluate(el => (el.closest('form') as HTMLFormElement).action = '/posts/99999999/react');
  await button.click();
  await expect(post.locator('.reaction-status')).toHaveText('Post not found.');
  await button.evaluate((el, action) => {
    const form = el.closest('form') as HTMLFormElement;
    form.action = action;
    (form.querySelector('[name=_token]') as HTMLInputElement).value = 'invalid';
  }, action);
  await button.click();
  await expect(post.locator('.reaction-status')).toContainText('security token was invalid');
  await expect(post.locator('.reaction-status a')).toHaveCount(0);
  await expect(button).toHaveAttribute('aria-pressed', 'false');
});

test('reaction recovery after a failed reply uses a readable GET topic destination', async ({ page }) => {
  await open(page);
  const form = page.locator('form.composer[data-composer-context=reply]').first();
  await form.evaluate((el: HTMLFormElement) => {
    // Native submit exercises the server's anti-draft-loss 422 render.
    (el.querySelector('textarea[name=body]') as HTMLTextAreaElement).value = '';
    el.submit();
  });
  await page.waitForURL(/\/reply$/);
  const post = page.locator(`#p${fixture.post}`);
  await page.route(`**/posts/${fixture.post}/react`, route => route.abort('failed'));
  await chip(post).click();
  const reload = post.getByRole('link', { name: 'Reload to check its state.' });
  await expect(reload).toBeVisible();
  await Promise.all([
    page.waitForResponse(r => r.request().method() === 'GET' && new URL(r.url()).pathname === fixture.topic),
    reload.click(),
  ]);
  await expect(page.locator(`#p${fixture.post}`)).toBeVisible();
});

test('hidden reactor tips cannot widen the page before interaction', async ({ page }, info) => {
  const post = await open(page);
  await page.mouse.move(0, 0);
  const button = chip(post);
  await button.evaluate((el: HTMLElement) => {
    el.style.position = 'fixed'; el.style.top = '300px'; el.style.right = '0';
    el.querySelector('.reaction-tip')!.lastElementChild!.textContent = '@very_long_reactor_handle and 20 others';
  });
  await expect(button.locator('.reaction-tip')).toBeHidden();
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(page.viewportSize()!.width);
  await capture(page, info, 'conversation-reaction-hidden-tip-edge');
});

test('removing a keyboard-focused chip on a narrow fine-pointer viewport focuses the visible menu without scrolling', async ({ page }) => {
  await page.setViewportSize({ width: 700, height: 844 });
  const post = await open(page);
  await post.locator('.post-menu > summary').click();
  await toggle(page, post.locator('.post-menu-touch button[data-label="Heart"]'));
  await post.locator('.post-menu > summary').click();
  const heart = chip(post, 'Heart');
  await heart.focus();
  const top = await page.evaluate(() => window.scrollY);
  await heart.press('Enter');
  await expect(heart).toHaveCount(0);
  await expect(post.locator('.post-menu > summary')).toBeFocused();
  expect(await page.evaluate(() => window.scrollY)).toBe(top);
});

test('unknown reactor metadata falls back to one native title without a stale description', async ({ page }) => {
  const post = await open(page);
  const button = chip(post);
  await toggle(page, button);
  await page.route(`**/posts/${fixture.post}/react`, async route => {
    const response = await route.fetch();
    const data = await response.json();
    // Counts may survive when every named reactor is excluded by the server.
    await route.fulfill({ response, json: { ...data, reactors: [] } });
  });
  await toggle(page, button);
  await expect(button).toHaveAttribute('title', 'Commend');
  await expect(button).not.toHaveAttribute('aria-describedby');
  await expect(button.locator('.reaction-tip')).toHaveCount(0);
});

test('touch reaction addition enters the tray and removing it updates every menu instance', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'touch picker behavior');
  const post = await open(page);
  await post.locator('.post-menu > summary').click();
  const menuButton = post.locator('.post-menu-touch button[data-label="Heart"]');
  await toggle(page, menuButton);
  const heart = chip(post, 'Heart');
  await expect(heart).toBeVisible();
  await expect(heart).toHaveAttribute('aria-pressed', 'true');
  await expect(heart.locator('.reaction-n-val')).toHaveText('1');
  await expect(menuButton).toHaveAttribute('aria-pressed', 'true');
  await post.locator('.post-menu > summary').click();
  await capture(page, info, 'conversation-touch-reaction-tray');
  await toggle(page, heart, false);
  await expect(heart).toHaveCount(0);
  await expect(post.locator('.post-menu > summary')).toBeFocused();
  await post.locator('.post-menu > summary').click();
  await expect(menuButton).toHaveAttribute('aria-pressed', 'false');
  await expect(post.locator('.reaction-add button[data-label="Heart"]')).toHaveAttribute('aria-pressed', 'false');
});

test('reactor tips remain inside either viewport edge', async ({ page }) => {
  const post = await open(page);
  const button = chip(post);
  const width = page.viewportSize()!.width;
  for (const edge of ['left', 'right']) {
    // Exercise the same placement routine at both boundaries independently of
    // whichever rail width happens to position this seeded post today.
    await button.evaluate((el: HTMLElement, edge) => {
      el.style.position = 'fixed'; el.style.top = '300px';
      el.style.left = edge === 'left' ? '0px' : 'auto';
      el.style.right = edge === 'right' ? '0px' : 'auto';
    }, edge);
    await page.keyboard.press('Tab');
    await button.focus();
    await expect.poll(async () => button.locator('.reaction-tip').evaluate(el => {
      const rect = el.getBoundingClientRect(); return rect.left >= 15 && rect.right <= document.documentElement.clientWidth - 15;
    })).toBe(true);
    const box = await button.locator('.reaction-tip').boundingBox();
    expect(box!.x).toBeGreaterThanOrEqual(15);
    expect(box!.x + box!.width).toBeLessThanOrEqual(width - 15);
    await button.evaluate((el: HTMLElement) => el.blur());
  }
});

test('twilight reaction tips support keyboard focus and have no serious accessibility violations', async ({ page }, info) => {
  php(`(new \\App\\Repository\\UserPreferenceRepository($db))->merge(${fixture.bob},['theme'=>'dark']);`);
  const post = await open(page);
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  const button = chip(post);
  await page.keyboard.press('Tab');
  await button.focus();
  await expect(button).toBeFocused();
  await expect.poll(() => button.locator('.reaction-tip').evaluate(el => getComputedStyle(el).opacity)).toBe('1');
  await capture(page, info, 'conversation-reaction-tip-twilight');
  const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).include(`#p${fixture.post}`).analyze();
  expect(results.violations.filter(v => v.impact === 'serious' || v.impact === 'critical')).toEqual([]);
});

for (const preference of ['operating system', 'account']) {
  test(`${preference} reduced motion suppresses reaction count animation`, async ({ page }) => {
    if (preference === 'operating system') await page.emulateMedia({ reducedMotion: 'reduce' });
    else php(`(new \\App\\Repository\\UserPreferenceRepository($db))->merge(${fixture.bob},['reduced_motion'=>true]);`);
    const post = await open(page);
    if (preference === 'account') await expect(page.locator('html')).toHaveAttribute('data-reduced-motion', '1');
    const button = chip(post);
    await toggle(page, button);
    await expect(button.locator('.reaction-n-val')).toHaveClass(/\bis-up\b/);
    expect(await button.locator('.reaction-n-val').evaluate(el => getComputedStyle(el).animationName)).toBe('none');
  });
}

test('without JavaScript a reaction remains a native POST and refreshed pressed state', async ({ browser }, info) => {
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: info.project.use.viewport, isMobile: info.project.name === 'mobile', hasTouch: info.project.name === 'mobile', baseURL: info.project.use.baseURL });
  const page = await context.newPage();
  try {
    const post = await open(page);
    const button = chip(post);
    await expect(button).toHaveAttribute('aria-pressed', 'false');
    await Promise.all([page.waitForURL(u => u.hash === `#p${fixture.post}`), button.click()]);
    await expect(chip(page.locator(`#p${fixture.post}`))).toHaveAttribute('aria-pressed', 'true');
    await expect(chip(page.locator(`#p${fixture.post}`)).locator('.reaction-n-val')).toHaveText('2');
  } finally { await context.close(); }
});
