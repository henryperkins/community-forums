import { test, expect, type Page, type BrowserContext } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { openNewMessage } from './create-menu-helpers';
const ROOT = path.resolve(__dirname, '../..');
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/create-menu-2026-10-07');
const widths = [320, 390, 860, 861, 1024, 1280, 1440];
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });
let topic: string, conversation: string;
const sessions = new Map<string, Awaited<ReturnType<BrowserContext['cookies']>>>();
const matrixResults: Record<string, unknown>[] = [];
const captureResults: Record<string, unknown>[] = [];
function php(code: string) {
    return execFileSync('php', ['-r', `require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd().'/.env'); $config=\\App\\Core\\Config::fromFile(getcwd().'/config/config.php');
if (!preg_match('/^retroboards_e2e_[a-z0-9_]+$/',$config->get('db.database'))) throw new RuntimeException('Use a private browser database.');
$db=new \\App\\Core\\Database($config->get('db')); ${code}`], { cwd: ROOT, env: process.env }).toString().trim();
}
test.beforeAll(() => {
    const fixture = JSON.parse(php(`
$users=new \\App\\Repository\\UserRepository($db); $ids=[];
foreach (['menu_member','menu_other','menu_newcomer'] as $name) {
 $user=$users->findByUsername($name); $id=$user['id'] ?? $users->create(['username'=>$name,'email'=>$name.'@retro.test','display_name'=>'Create menu member','password_hash'=>(new \\App\\Security\\PasswordHasher())->hash('password123')]);
 $db->run('UPDATE users SET onboarded_at=UTC_TIMESTAMP(),email_verified_at=UTC_TIMESTAMP(),post_count=? WHERE id=?',[$name==='menu_newcomer'?0:1,$id]);
 (new \\App\\Repository\\UserPreferenceRepository($db))->merge((int)$id,['rail_open'=>true]); $ids[$name]=(int)$id;
}
$thread=$db->fetch("SELECT t.id,t.slug FROM threads t JOIN boards b ON b.id=t.board_id WHERE b.slug='general' ORDER BY t.id LIMIT 1");
$id=(new \\App\\Repository\\ConversationRepository($db))->findOrCreateBetween($ids['menu_member'],$ids['menu_other']);
$m=new \\App\\Repository\\DmMessageRepository($db);
if ($m->countByConversation($id)===0)
 for ($i=0;$i<25;$i++) $m->create($id,$ids['menu_other'],'A letter for the room '.$i,'<p>A letter for the room '.$i.'</p>');
(new \\App\\Repository\\ConversationRepository($db))->touch($id,gmdate('Y-m-d H:i:s'));
echo json_encode(['topic'=>'/t/'.$thread['id'].'-'.$thread['slug'],'conversation'=>'/messages/'.$id]);`));
    topic = fixture.topic;
    conversation = fixture.conversation;
});
async function member(page: Page, who = 'menu_member') {
    if (sessions.has(who)) {
        await page.context().addCookies(sessions.get(who)!);
        return;
    }
    await page.goto('/login');
    await page.locator('[name=email]').fill(`${who}@retro.test`);
    await page.locator('[name=password]').fill('password123');
    await page.locator('button[type=submit]').click();
    await page.waitForURL(url => url.pathname !== '/login');
    sessions.set(who, await page.context().cookies());
}
function routes() {
    return ['/', '/c/general', '/tags', topic, '/inbox', '/messages', conversation,
        '/messages/new', '/compose', '/search', '/notifications', '/feed', '/u/menu_member', '/drafts', '/settings/account', '/missing-create-menu-page'];
}
function memberAppearance(theme: string, large: boolean) {
    php(`$id=(new \\App\\Repository\\UserRepository($db))->findByUsername('menu_member')['id'];
    (new \\App\\Repository\\UserPreferenceRepository($db))->merge((int)$id,['theme'=>'${theme}','font_size'=>'${large ? 'large' : 'medium'}']);`);
}
async function appearance(page: Page, theme: string, large: boolean, signedIn: boolean) {
    if (signedIn && await page.locator('header.forum-bar').count()) {
        await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
        await expect(page.locator('html')).toHaveAttribute('data-font-size', large ? 'large' : 'medium');
        await page.evaluate(() => document.fonts.ready);
    } else {
        // Guests have no saved appearance preference. Exercise the same root
        // attributes as the member preference so both CSS variants are covered.
        await page.evaluate(({ theme, large }) => {
            document.documentElement.dataset.theme = theme;
            document.documentElement.dataset.fontSize = large ? 'large' : 'medium';
            return document.fonts.ready;
        }, { theme, large });
    }
}
async function capture(page: Page, name: string, project: string, fullPageOverride?: boolean) {
    const dir = path.join(OUT, process.env.E2E_LAYOUT_BROWSER ?? 'chromium', project);
    fs.mkdirSync(dir, { recursive: true });
    const menuOpen = await page.locator('[data-create-menu][open]').count() > 0;
    if (menuOpen)
        await expect(page.locator('.create-menu-panel')).toBeVisible();
    const imagePath = path.join(dir, `${name}.png`);
    const root = await page.locator('html').evaluate(el => ({ theme: el.dataset.theme, fontSize: el.dataset.fontSize }));
    const fullPage = fullPageOverride ?? !menuOpen;
    await page.screenshot({ path: imagePath, fullPage, animations: 'disabled' });
    if (menuOpen)
        await expect(page.locator('.create-menu-panel')).toBeVisible();
    captureResults.push({ file: path.relative(OUT, imagePath), route: new URL(page.url()).pathname, viewport: page.viewportSize(), ...root, menuOpen, fullPage });
}
test.afterAll(async ({}, info) => {
    fs.mkdirSync(OUT, { recursive: true });
    const browser = process.env.E2E_LAYOUT_BROWSER ?? 'chromium';
    const prefix = `${browser}-${info.project.name}`;
    // Playwright restarts a worker after a failed test. Keep every worker's
    // matrix rows so a native reading-floor failure cannot erase earlier lanes.
    // Its output directory is cleared at the beginning of each browser run.
    const fragments = path.join(OUT, '.artifacts', 'create-menu-results');
    fs.mkdirSync(fragments, { recursive: true });
    fs.writeFileSync(path.join(fragments, `${prefix}-${info.workerIndex}.json`), JSON.stringify({
        checks: matrixResults, captures: captureResults,
    }));
    const parts = fs.readdirSync(fragments).filter(file => file.startsWith(`${prefix}-`) && file.endsWith('.json'))
        .map(file => JSON.parse(fs.readFileSync(path.join(fragments, file), 'utf8')));
    const checks = parts.flatMap(part => part.checks);
    fs.writeFileSync(path.join(OUT, `${prefix}-results.json`), JSON.stringify({
        browser, project: info.project.name, routeChecks: checks.length,
        checks, captures: parts.flatMap(part => part.captures),
    }, null, 2) + '\n');
});
async function contract(page: Page, route: string, width: number, theme: string, large: boolean, signedIn: boolean, dms = true, javaScriptEnabled = true) {
    await page.goto(route);
    await appearance(page, theme, large, signedIn);
    const hasHeader = await page.locator('header.forum-bar').count() > 0;
    const leading = (route === '/' && signedIn) || route === '/c/general' || route === topic;
    const row = page.locator('[data-subheader]');
    await expect(row, route).toHaveCount(hasHeader && (signedIn || leading) ? 1 : 0);
    const result = { route, finalRoute: new URL(page.url()).pathname, width, height: 844, theme, fontSize: large ? 'large' : 'medium',
        state: !signedIn ? 'guest' : dms ? 'member' : 'member-dms-off', javaScriptEnabled, hasHeader };
    if (!hasHeader) {
        matrixResults.push({ ...result, passed: true });
        return; // auth redirects retain their own shell
    }
    await expect(page.locator('.forum-bar-compose')).toHaveCount(0);
    if (signedIn) {
        const trigger = page.locator('[data-create-trigger]');
        await expect(trigger, route).toBeVisible();
        await expect(page.locator('[data-create-menu]')).toHaveCount(dms ? 1 : 0);
        await expect(row.locator('a[href^="/compose"]')).toHaveAttribute('href', route === '/c/general' || route === topic ? '/compose?board=general' : '/compose');
        if (dms)
            await expect(row.locator('[data-new-message]')).toHaveAttribute('href', '/messages/new');
        const box = (await trigger.boundingBox())!;
        expect(box.height, route).toBeGreaterThanOrEqual(44);
        if (width <= 860) {
            expect(box.width, route).toBe(44);
            await expect(row.locator('.create-label')).toBeHidden();
            await expect(trigger.locator('.icon').first()).toBeVisible();
            if (dms)
                await expect(trigger.locator('.icon').last()).toBeHidden();
        }
        else
            await expect(row.locator('.create-label')).toBeVisible();
        if (route === '/compose')
            await expect(row.locator('a[href^="/compose"]')).toHaveAttribute('aria-current', 'page');
        if (route === '/messages/new' && dms)
            await expect(row.locator('[data-new-message]')).toHaveAttribute('aria-current', 'page');
    }
    else
        await expect(page.locator('[data-create-trigger]')).toHaveCount(0);
    const g = await page.evaluate(() => {
        const send = document.querySelector('.dm-shell[data-dm-conversation] .dm-composer .composer-send')?.getBoundingClientRect();
        const sendVisibleIntersection = send
            ? Math.max(0, Math.min(send.right, innerWidth) - Math.max(send.left, 0))
                * Math.max(0, Math.min(send.bottom, innerHeight) - Math.max(send.top, 0)) / (send.width * send.height)
            : undefined;
        return { w: document.documentElement.clientWidth, sw: document.documentElement.scrollWidth, sh: document.documentElement.scrollHeight, h: innerHeight,
        bar: document.querySelector('.forum-bar')!.getBoundingClientRect().height, first: document.querySelector('main')?.firstElementChild?.hasAttribute('data-subheader'),
        room: document.querySelector('.dm-shell,.inbox-shell')?.getBoundingClientRect().top,
        roomBottom: document.querySelector('.dm-shell,.inbox-shell')?.getBoundingClientRect().bottom,
        row: document.querySelector('[data-subheader]')?.getBoundingClientRect().bottom,
        dock: document.querySelector('.dm-shell[data-dm-conversation] .dm-composer')?.getBoundingClientRect().bottom,
        send: send?.bottom, sendVisibleIntersection };
    });
    expect(g.sw, `${route}: horizontal overflow`).toBeLessThanOrEqual(g.w);
    expect(g.bar, `${route}: header height`).toBe(width <= 860 && signedIn ? 108 : 62);
    if (signedIn || leading)
        expect(g.first, `${route}: skip destination starts at row`).toBe(true);
    let noPageScroll = true;
    if (g.room !== undefined) {
        expect(g.room, `${route}: room below row`).toBeGreaterThanOrEqual(g.row! - 1);
        noPageScroll = g.sh <= g.h + 1;
        if (!javaScriptEnabled && width <= 860 && route === conversation) {
            if (!noPageScroll) {
                const label = `native-limit-${width}-${theme}-${large ? 'large' : 'normal'}-no-js`;
                await capture(page, `${label}-viewport`, 'mobile', false);
                await capture(page, `${label}-full-page`, 'mobile', true);
            }
            expect.soft(g.sh, `${route}: room adds no page scroll`).toBeLessThanOrEqual(g.h + 1);
        } else
            expect(g.sh, `${route}: room adds no page scroll`).toBeLessThanOrEqual(g.h + 1);
        if (g.dock !== undefined) {
            expect(g.dock, `${route}: composer within room`).toBeLessThanOrEqual(g.roomBottom! + 1);
            if (!javaScriptEnabled && width <= 860 && route === conversation)
                expect.soft(g.dock, `${route}: composer dock`).toBeLessThanOrEqual(g.h + 1);
            else
                expect(g.dock, `${route}: composer dock`).toBeLessThanOrEqual(g.h + 1);
            expect(g.send, `${route}: Send within viewport`).toBeLessThanOrEqual(g.h + 1);
            expect(g.sendVisibleIntersection, `${route}: Send visibly docked`).toBeGreaterThanOrEqual(0.99);
        }
    }
    if (leading) {
        if (route === '/') {
            await expect(row.locator('[data-directory-context]')).toHaveText('Boards');
            await expect(page.locator('[data-sidebar] [data-directory-nav]')).toHaveCount(1);
            await expect(row.locator('[data-directory-nav]')).toHaveCount(0);
        } else {
            await expect(page.getByRole('navigation', { name: 'Breadcrumb', exact: true })).toHaveCount(1);
            await expect(row.getByRole('navigation', { name: 'Breadcrumb', exact: true })).toHaveCount(1);
        }
        await expect(page.locator('main h1')).toHaveCount(1);
    }
    const composerInViewport = g.dock === undefined || g.dock <= g.h + 1;
    const geometryPassed = noPageScroll && composerInViewport;
    matrixResults.push({ ...result, geometry: g, noPageScroll, composerInViewport, geometryPassed, passed: geometryPassed });
}
for (const width of widths)
    test(`member route matrix ${width}px`, async ({ page }, info) => {
        test.skip((width <= 860) !== (info.project.name === 'mobile'));
        test.setTimeout(180000);
        await member(page);
        await page.setViewportSize({ width, height: 844 });
        for (const theme of ['light', 'dark'])
            for (const large of [false, true]) {
                memberAppearance(theme, large);
                for (const route of routes())
                    await contract(page, route, width, theme, large, true);
            }
        memberAppearance('light', true);
        await page.goto('/c/general');
        await appearance(page, 'light', true, true);
        await capture(page, `board-${width}-light-large-js`, info.project.name);
        await page.locator('[data-create-trigger]').click();
        await expect(page.locator('.create-menu-panel')).toBeVisible();
        await capture(page, `menu-${width}-light-large-js`, info.project.name);
    });
test('keyboard creation, shared menu dismissal and Messages focus/draft recovery', async ({ page }, info) => {
    await member(page);
    memberAppearance('dark', true);
    await page.goto('/c/general');
    const trigger = page.locator('[data-create-trigger]');
    await trigger.focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('.create-menu-panel')).toBeVisible();
    await page.keyboard.press('Tab');
    await expect(page.locator('[data-subheader] a[href^="/compose"]')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(trigger).toBeFocused();
    await expect(page.locator('[data-create-menu]')).not.toHaveAttribute('open', '');
    await trigger.press('Enter');
    await expect(page.locator('.create-menu-panel')).toBeVisible();
    await page.keyboard.press('Tab');
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/compose\?board=general$/);
    await expect(page.locator('select[name=board_id] option:checked')).toContainText('General');
    await page.goto('/inbox');
    await page.locator('[data-create-trigger]').click();
    await page.locator('[data-inbox-scope-menu] > summary').focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('[data-create-menu]')).not.toHaveAttribute('open', '');
    await page.keyboard.press('Escape');
    const viewport = page.viewportSize()!;
    await page.setViewportSize({ ...viewport, height: 400 });
    await page.locator('[data-create-trigger]').click();
    await expect(page.locator('.create-menu-panel')).toBeVisible();
    await page.locator('[data-inbox-list]').evaluate(el => { el.scrollTop = el.scrollHeight; });
    await expect(page.locator('[data-create-menu]')).not.toHaveAttribute('open', '');
    await page.setViewportSize(viewport);
    await page.goto('/messages');
    await openNewMessage(page);
    await expect(page).toHaveURL(/\/messages$/);
    await expect(page.locator('.dm-dialog')).toHaveAttribute('aria-modal', 'true');
    await expect(page.locator('.dm-dialog .dm-to-input')).toBeFocused();
    await page.locator('.dm-dialog .dm-to-input').fill('unknown_member');
    await page.locator('.dm-dialog textarea[name=body]').fill('Keep my typed letter from shared creation.');
    await page.locator('.dm-dialog .composer-send').click();
    await expect(page.locator('[data-dm-compose]')).toBeVisible();
    await expect(page.locator('.dm-dialog textarea[name=body]')).toHaveValue('Keep my typed letter from shared creation.');
    await capture(page, 'dialog-error-dark-large-js', info.project.name);
    await page.keyboard.press('Escape');
    await expect(page.locator('[data-create-trigger]')).toBeFocused();
    await expect(page.locator('[data-dm-compose]')).toBeHidden();
    await page.goto('/messages');
    expect((await new AxeBuilder({ page }).include('[data-subheader]').analyze()).violations).toEqual([]);
});
test('classic scrollbar and Large text fit 320px', async ({ page }, info) => { test.skip(info.project.name !== 'desktop'); await member(page); memberAppearance('dark', true); await page.setViewportSize({ width: 320, height: 844 }); await contract(page, '/inbox', 320, 'dark', true, true); await capture(page, 'classic-scrollbar-320-dark-large-js', info.project.name); });
for (const javaScriptEnabled of [true, false])
    test.describe(javaScriptEnabled ? 'native with JS' : 'native without JS', () => {
        test.use({ javaScriptEnabled });
        test('guest and dms-off routes retain the conditional row', async ({ browser, baseURL }, info) => {
            test.setTimeout(600000);
            const context = await browser.newContext({ baseURL, javaScriptEnabled, isMobile: info.project.name === 'mobile', hasTouch: info.project.name === 'mobile' });
            const page = await context.newPage();
            const projectWidths = widths.filter(w => (w <= 860) === (info.project.name === 'mobile'));
            for (const width of projectWidths) {
                await page.setViewportSize({ width, height: 844 });
                for (const theme of ['light', 'dark'])
                    for (const large of [false, true])
                        for (const route of routes())
                            await contract(page, route, width, theme, large, false, true, javaScriptEnabled);
            }
            await member(page);
            const previous = php(`$s=new \\App\\Repository\\SettingRepository($db);echo json_encode($s->get('features',[]));$f=$s->get('features',[]);$f['dms']=false;$s->set('features',$f);`);
            try {
                for (const width of projectWidths) {
                    await page.setViewportSize({ width, height: 844 });
                    for (const theme of ['light', 'dark'])
                        for (const large of [false, true]) {
                            memberAppearance(theme, large);
                            for (const route of routes())
                                await contract(page, route, width, theme, large, true, false, javaScriptEnabled);
                        }
                }
                memberAppearance('dark', true);
                await page.goto('/c/general');
                await appearance(page, 'dark', true, true);
                await capture(page, `dms-off-board-${page.viewportSize()!.width}-dark-large-${javaScriptEnabled ? 'js' : 'no-js'}`, info.project.name);
                await page.locator('[data-create-trigger]').click();
                await expect(page).toHaveURL(/\/compose\?board=general$/);
            }
            finally {
                php(`(new \\App\\Repository\\SettingRepository($db))->set('features',json_decode(base64_decode('${Buffer.from(previous).toString('base64')}'),true));`);
                await context.close();
            }
        });
        test('representative page and open menu captures', async ({ page }, info) => {
            test.setTimeout(240000);
            await member(page);
            const width = info.project.name === 'mobile' ? 320 : 1440;
            await page.setViewportSize({ width, height: 844 });
            const pages = [
                ['home', '/'], ['board', '/c/general'], ['topic', topic],
                ['messages', '/messages'], ['conversation', conversation],
                ['inbox', '/inbox'], ['error', '/missing-create-menu-page'],
            ];
            for (const theme of ['light', 'dark'])
                for (const large of [false, true]) {
                    memberAppearance(theme, large);
                    for (const [name, route] of pages) {
                        await contract(page, route, width, theme, large, true, true, javaScriptEnabled);
                        const label = `${name}-${width}-${theme}-${large ? 'large' : 'normal'}-${javaScriptEnabled ? 'js' : 'no-js'}`;
                        await capture(page, label, info.project.name);
                        if (name === 'board') {
                            await page.locator('[data-create-trigger]').click();
                            await expect(page.locator('.create-menu-panel')).toBeVisible();
                            const bounds = await page.locator('.create-menu-panel').boundingBox();
                            expect(bounds!.x).toBeGreaterThanOrEqual(0);
                            expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(width);
                            await capture(page, `menu-${width}-${theme}-${large ? 'large' : 'normal'}-${javaScriptEnabled ? 'js' : 'no-js'}`, info.project.name);
                        }
                    }
                }
        });
    });
test.describe('no JavaScript', () => {
    test.use({ javaScriptEnabled: false });
    test('member route matrix and native creation preserve leading content and draft errors', async ({ page }, info) => {
        test.setTimeout(600000);
        await member(page);
        for (const width of widths.filter(w => (w <= 860) === (info.project.name === 'mobile'))) {
            await page.setViewportSize({ width, height: 844 });
            for (const theme of ['light', 'dark'])
                for (const large of [false, true]) {
                    memberAppearance(theme, large);
                    for (const route of routes())
                        await contract(page, route, width, theme, large, true, true, false);
                }
        }
        memberAppearance('dark', true);
        await page.goto('/messages');
        await openNewMessage(page);
        await expect(page).toHaveURL(/\/messages\/new$/);
        await page.locator('.dm-compose input[name=to]').fill('unknown_member');
        await page.locator('.dm-compose textarea[name=body]').fill('My no-JS dialog error draft.');
        // Reproduce the server's origin=dialog failure with JS unavailable; this field
        // is the one emitted by the real in-place dialog form.
        await page.locator('.dm-compose form').evaluate(form => { const f = document.createElement('input'); f.type = 'hidden'; f.name = 'origin'; f.value = 'dialog'; form.appendChild(f); });
        await page.locator('.dm-compose .composer-send').click();
        await expect(page).toHaveURL(/\/messages$/);
        await expect(page.locator('[data-dm-compose]')).toBeVisible();
        await expect(page.locator('.dm-dialog textarea[name=body]')).toHaveValue('My no-JS dialog error draft.');
        await expect(page.locator('summary.dm-new-btn')).toHaveCount(0);
        await expect(page.locator('.dm-dialog')).not.toHaveAttribute('role', 'dialog');
        await capture(page, 'dialog-error-dark-large-no-js', info.project.name);
        await page.locator('.dm-dialog [data-close-compose]').first().click();
        await expect(page.locator('[data-dm-compose]')).toBeHidden();
    });
});
