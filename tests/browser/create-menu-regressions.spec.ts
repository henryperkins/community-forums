import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { openNewMessage } from './create-menu-helpers';

const ROOT = path.resolve(__dirname, '../..');
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/create-menu-2026-10-07/regression-fixes');
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });
const names = ['abcdefghijklmnopqrstuvwxyz01234567', 'abcdefghijklmnopqrstuvwxyz0123456789012345', 'Unbroken'.repeat(10)];
let fixture: { conversation: string; boards: { slug: string; name: string; topic: string }[] };

function php(code: string) {
  return execFileSync('php', ['-r', `require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd().'/.env'); $config=\\App\\Core\\Config::fromFile(getcwd().'/config/config.php');
if (!preg_match('/^retroboards_e2e(?:_[a-z0-9_]+)?$/',$config->get('db.database'))) throw new RuntimeException('Use a dedicated browser database.');
$db=new \\App\\Core\\Database($config->get('db')); ${code}`], { cwd: ROOT, env: process.env }).toString().trim();
}

test.beforeAll(() => {
  fixture = JSON.parse(php(`
$users=new \\App\\Repository\\UserRepository($db); $ids=[];
foreach (['create_fix_member','create_fix_other'] as $name) {
 $user=$users->findByUsername($name); $id=$user['id'] ?? $users->create(['username'=>$name,'email'=>$name.'@retro.test','display_name'=>$name,'password_hash'=>(new \\App\\Security\\PasswordHasher())->hash('password123')]);
 $db->run('UPDATE users SET onboarded_at=UTC_TIMESTAMP(),email_verified_at=UTC_TIMESTAMP(),post_count=1 WHERE id=?',[$id]); $ids[$name]=(int)$id;
}
$conversation=(new \\App\\Repository\\ConversationRepository($db))->findOrCreateBetween($ids['create_fix_member'],$ids['create_fix_other']);
$messages=new \\App\\Repository\\DmMessageRepository($db);
if (!$messages->countByConversation($conversation)) $messages->create($conversation,$ids['create_fix_other'],'A letter for creation regression checks.','<p>A letter for creation regression checks.</p>');
$boards=new \\App\\Repository\\BoardRepository($db); $topics=new \\App\\Repository\\ThreadRepository($db); $result=[];
$posting=new \\App\\Service\\PostingService($db,$topics,new \\App\\Repository\\PostRepository($db),$boards,$users,new \\App\\Support\\Markdown(new \\App\\Support\\HtmlSanitizer()),new \\App\\Security\\WriteGate(),new \\App\\Security\\BoardPolicy(),$config);
foreach (json_decode(base64_decode('${Buffer.from(JSON.stringify(names)).toString('base64')}'),true) as $index=>$name) {
 $slug='create-fix-long-'.$index; $board=$db->fetch('SELECT * FROM boards WHERE slug=?',[$slug]);
 if (!$board) { $category=(new \\App\\Repository\\CategoryRepository($db))->create('Creation regression '.$index); $id=$boards->create(['category_id'=>$category,'slug'=>$slug,'name'=>$name,'visibility'=>'public']); $board=$boards->find($id); }
 $thread=$db->fetch('SELECT * FROM threads WHERE board_id=? ORDER BY id LIMIT 1',[$board['id']]);
 if (!$thread) { $created=$posting->createThread(\\App\\Domain\\User::fromRow($users->find($ids['create_fix_member'])),['board_id'=>$board['id'],'title'=>'Long breadcrumbs retain the full board name','body'=>'Public topic for the breadcrumb regression.']); $thread=$topics->find($created['thread_id']); }
 $result[]=['slug'=>$slug,'name'=>$name,'topic'=>'/t/'.$thread['id'].'-'.$thread['slug']];
}
echo json_encode(['conversation'=>'/messages/'.$conversation,'boards'=>$result]);`));
});

async function member(page: Page) {
  await page.goto('/login');
  await page.locator('[name=email]').fill('create_fix_member@retro.test');
  await page.locator('[name=password]').fill('password123');
  await page.locator('button[type=submit]').click();
  await page.waitForURL(url => url.pathname !== '/login');
}

async function appearance(page: Page, theme: string, large = true) {
  await page.evaluate(({ theme, large }) => {
    document.documentElement.dataset.theme = theme;
    document.documentElement.dataset.fontSize = large ? 'large' : 'medium';
    return document.fonts.ready;
  }, { theme, large });
}

async function capture(page: Page, name: string, project: string) {
  const dir = path.join(OUT, process.env.E2E_LAYOUT_BROWSER ?? 'chromium', project);
  fs.mkdirSync(dir, { recursive: true });
  await page.screenshot({ path: path.join(dir, `${name}.png`), animations: 'disabled' });
}

test('shared creation opens a visible modal from every Messages pane through the 900px boundary', async ({ page }, info) => {
  test.setTimeout(120000);
  await member(page);
  const widths = info.project.name === 'mobile' ? [320, 390, 860, 900] : [861, 901, 1440];
  for (const width of widths) {
    await page.setViewportSize({ width, height: 844 });
    for (const [theme, large] of [['light', false], ['dark', true]] as const) {
      for (const route of ['/messages', fixture.conversation, '/messages/new']) {
        await page.goto(route);
        await appearance(page, theme, large);
        await openNewMessage(page);
        await expect(page).toHaveURL(new RegExp(`${route}$`));
        const dialog = page.locator('.dm-dialog');
        await expect(dialog, `${route} at ${width}px`).toBeVisible();
        await expect(dialog).toHaveAttribute('aria-modal', 'true');
        const bounds = (await dialog.boundingBox())!;
        expect(bounds.x).toBeGreaterThanOrEqual(0);
        expect(bounds.x + bounds.width).toBeLessThanOrEqual(width + 1);
        expect(bounds.y).toBeGreaterThanOrEqual(0);
        expect(bounds.y + bounds.height).toBeLessThanOrEqual(845);
        await expect(dialog.locator('.dm-to-input')).toBeFocused();
        await page.keyboard.press('Shift+Tab');
        await expect(dialog.locator('[data-close-compose]').first()).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        await expect(page.locator('[data-create-trigger]')).toBeFocused();
      }
    }
  }
  await page.setViewportSize({ width: info.project.name === 'mobile' ? 390 : 1440, height: 844 });
  await page.goto(fixture.conversation);
  await appearance(page, 'dark');
  await openNewMessage(page);
  await capture(page, 'conversation-compose-dialog', info.project.name);
  await page.locator('.dm-dialog [data-close-compose]').last().click();
  await expect(page.locator('.dm-dialog')).toBeHidden();
});

test('leaving recipient suggestions restores Escape and cancels delayed reopening', async ({ page }, info) => {
  await member(page);
  await page.goto('/messages');
  await openNewMessage(page);
  const to = page.locator('.dm-dialog .dm-to-input');
  const suggest = page.locator('.dm-dialog .dm-suggest');
  const body = page.locator('.dm-dialog textarea[name=body]');
  await body.fill('Keep my message draft while I dismiss the recipient picker.');
  await to.fill('create_fix_o');
  await expect(suggest).toBeVisible();
  await page.keyboard.press('Tab');
  await expect(suggest).toBeHidden();
  await page.keyboard.press('Escape');
  await expect(page.locator('.dm-dialog')).toBeHidden();
  await expect(page.locator('[data-create-trigger]')).toBeFocused();

  await openNewMessage(page);
  await expect(to).toHaveValue('create_fix_o');
  await expect(body).toHaveValue('Keep my message draft while I dismiss the recipient picker.');
  await to.fill('create_fix_ot');
  await expect(suggest).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(suggest).toBeHidden();
  await expect(page.locator('.dm-dialog')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('.dm-dialog')).toBeHidden();

  await openNewMessage(page);
  let release!: () => void;
  const delayed = new Promise<void>(resolve => { release = resolve; });
  let started!: () => void;
  const requestStarted = new Promise<void>(resolve => { started = resolve; });
  await page.route('**/composer/suggest?**', async route => {
    started();
    await delayed;
    await route.fulfill({ json: { items: [{ token: '@create_fix_other', label: '@create_fix_other', meta: 'Recipient' }] } }).catch(() => {});
  });
  try {
    await to.fill('create_fix_othe');
    await requestStarted;
    const requestFinished = new Promise<void>(resolve => {
      const finished = (request: { url(): string }) => {
        if (!request.url().includes('/composer/suggest?')) return;
        page.off('requestfinished', finished);
        page.off('requestfailed', finished);
        resolve();
      };
      page.on('requestfinished', finished);
      page.on('requestfailed', finished);
    });
    await page.keyboard.press('Tab');
    release();
    // The response can be aborted or received and ignored; flush the route before checking.
    await page.unrouteAll({ behavior: 'wait' });
    await requestFinished;
    await expect(suggest).toBeHidden();
    await expect(to).toHaveAttribute('aria-expanded', 'false');
    await expect(to).toHaveValue('create_fix_othe');
    await capture(page, 'recipient-blur-preserves-draft', info.project.name);
    await page.keyboard.press('Escape');
    await expect(page.locator('.dm-dialog')).toBeHidden();
  } finally {
    release();
    await page.unrouteAll({ behavior: 'wait' });
  }
});

test('uncancellable recipient results cannot reopen suggestions after blur', async ({ page }) => {
  await page.addInitScript(() => {
    const originalFetch = window.fetch.bind(window);
    window.fetch = (resource, options) => {
      if (String(resource).includes('/composer/suggest?')) {
        return new Promise<Response>(resolve => {
          (window as any).resolveLateRecipientSuggestions = () => resolve(new Response(JSON.stringify({
            items: [{ token: '@create_fix_other', label: '@create_fix_other', meta: 'Recipient' }],
          }), { headers: { 'Content-Type': 'application/json' } }));
        });
      }
      return originalFetch(resource, options);
    };
  });
  await member(page);
  await page.goto('/messages');
  await openNewMessage(page);
  const to = page.locator('.dm-dialog .dm-to-input');
  await to.fill('create_fix_o');
  await expect.poll(() => page.evaluate(() => typeof (window as any).resolveLateRecipientSuggestions)).toBe('function');
  await page.keyboard.press('Tab');
  await page.evaluate(async () => {
    (window as any).resolveLateRecipientSuggestions();
    await new Promise<void>(resolve => requestAnimationFrame(() => resolve()));
  });
  await expect(to).toHaveValue('create_fix_o');
  await expect(to).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('.dm-dialog .dm-suggest')).toBeHidden();
  await page.keyboard.press('Escape');
  await expect(page.locator('.dm-dialog')).toBeHidden();
});

test('recipient suggestions remain selectable by pointer', async ({ page }, info) => {
  await member(page);
  for (const theme of ['light', 'dark']) {
    await page.goto('/messages');
    await appearance(page, theme);
    await openNewMessage(page);
    await page.locator('.dm-dialog .dm-to-input').fill('create_fix_o');
    const option = page.locator('.dm-dialog .dm-suggest [role=option]').first();
    await expect(option).toBeVisible();
    await expect(option).toHaveAttribute('aria-selected', 'true');
    expect(await option.evaluate(el => getComputedStyle(el).getPropertyValue('--dm-active-wash').trim())).not.toBe('');
    await expect(option).not.toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    if (info.project.name === 'mobile') await option.tap();
    else await option.click();
    await expect(page.locator('.dm-dialog input[name=to]')).toHaveValue('create_fix_other');
    await expect(page.locator('.dm-dialog .dm-suggest')).toBeHidden();
    await expect(page.locator('.dm-dialog .dm-to-input')).toBeFocused();
    const chip = page.locator('.dm-dialog .dm-chip');
    await expect(chip).not.toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    await expect(chip).toHaveCSS('border-top-style', 'solid');
    await expect(chip).toHaveCSS('border-top-width', '1px');
    expect((await new AxeBuilder({ page }).include('.dm-dialog')
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze()).violations).toEqual([]);
    await capture(page, `recipient-chip-${theme}`, info.project.name);
    await page.keyboard.press('Escape');
    await expect(page.locator('.dm-dialog')).toBeHidden();
    await openNewMessage(page);
    await expect(page.locator('.dm-dialog input[name=to]')).toHaveValue('create_fix_other');
    await expect(chip).toContainText('create_fix_other');
  }
});

test('long topic and board breadcrumbs wrap inside the shared row for guests and members', async ({ page }, info) => {
  test.setTimeout(120000);
  const widths = info.project.name === 'mobile' ? [320, 390] : [861, 1440];
  for (const signedIn of [false, true]) {
    if (signedIn) await member(page);
    for (const width of widths) {
      await page.setViewportSize({ width, height: 844 });
      for (const [theme, large] of [['light', false], ['dark', true]] as const) {
        for (const board of fixture.boards) {
          for (const route of [board.topic, '/c/' + board.slug]) {
            await page.goto(route);
            await appearance(page, theme, large);
            const crumb = page.locator('[data-subheader] .breadcrumb');
            await expect(crumb).toContainText(board.name);
            const bounds = await crumb.evaluate(el => {
              const leading = el.closest('.forum-subheader-leading')!.getBoundingClientRect();
              return { leading: { left: leading.left, right: leading.right },
                children: Array.from(el.children).map(child => ({ left: child.getBoundingClientRect().left, right: child.getBoundingClientRect().right, width: child.clientWidth, scrollWidth: child.scrollWidth })),
                pageWidth: document.documentElement.scrollWidth, viewportWidth: document.documentElement.clientWidth };
            });
            for (const child of bounds.children) {
              expect(child.left).toBeGreaterThanOrEqual(bounds.leading.left - 1);
              expect(child.right).toBeLessThanOrEqual(bounds.leading.right + 1);
              expect(child.scrollWidth).toBeLessThanOrEqual(child.width + 1);
            }
            // The board's separate heading has an existing long-token limit; isolate
            // its breadcrumb while checking the complete topic page for overflow.
            if (route === board.topic) expect(bounds.pageWidth).toBeLessThanOrEqual(bounds.viewportWidth);
            if (signedIn) {
              const trigger = page.locator('[data-create-trigger]');
              const box = (await trigger.boundingBox())!;
              expect(box.height).toBeGreaterThanOrEqual(44);
              expect(await trigger.evaluate(el => {
                const r = el.getBoundingClientRect();
                const hit = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2);
                return hit === el || el.contains(hit);
              })).toBe(true);
            }
          }
        }
      }
    }
  }
  await page.goto(fixture.boards[2].topic);
  await appearance(page, 'dark');
  await capture(page, 'long-topic-breadcrumb', info.project.name);
});

test.describe('native creation', () => {
  test.use({ javaScriptEnabled: false });
  test('creation on narrow reading panes stays a real destination with retained validation drafts', async ({ page }, info) => {
    await member(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(fixture.conversation);
    await openNewMessage(page);
    await expect(page).toHaveURL(/\/messages\/new$/);
    await page.locator('.dm-compose input[name=to]').fill('unknown_create_recipient');
    await page.locator('.dm-compose textarea[name=body]').fill('Retain my native message draft.');
    await page.locator('.dm-compose .composer-send').click();
    await expect(page.locator('.dm-compose input[name=to]')).toHaveValue('unknown_create_recipient');
    await expect(page.locator('.dm-compose textarea[name=body]')).toHaveValue('Retain my native message draft.');
    await expect(page.locator('.dm-dialog')).toBeHidden();
    await capture(page, 'native-validation-draft', info.project.name);
  });
});
