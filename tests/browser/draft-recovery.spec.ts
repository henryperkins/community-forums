import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import AxeBuilder from '@axe-core/playwright';

const root = path.resolve(__dirname, '../..');
const key = '~2Fthreads';
const text = 'Café — 東京. Do not publish this yet.';
const title = 'A title — café 東京';

function flags(rich: boolean, server = true): void {
  execFileSync('php', ['-r', String.raw`
require 'vendor/autoload.php';
\App\Core\Env::load('.env');
$c=\App\Core\Config::fromFile('config/config.php');
if (!str_starts_with($c->get('db')['database'], 'retroboards_draftfix_')) throw new RuntimeException('Use a private draftfix database');
$s=new \App\Repository\SettingRepository(new \App\Core\Database($c->get('db')));
$f=$s->get('features',[]);$f['wysiwyg_composer']=${rich ? 'true' : 'false'};$f['server_drafts']=${server ? 'true' : 'false'};$f['group_dms']=true;$s->set('features',$f);
`], { cwd: root, env: process.env });
}

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('carol@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await page.waitForURL(u => u.pathname !== '/login');
  if (await page.locator('body[data-tour="1"]').count()) await page.getByRole('button', { name: 'Skip', exact: true }).click();
}
async function open(page: Page, rich: boolean): Promise<void> {
  await page.goto('/compose?board=general');
  await expect(page.locator('form.composer textarea')).toHaveAttribute('data-rb-enhanced', '1');
  if (rich) await expect(page.locator('form.composer .ProseMirror')).toBeVisible();
}
function form(page: Page) { return page.locator('form[data-composer-context="new_thread"]'); }
function editor(page: Page, rich: boolean) { return form(page).locator(rich ? '.ProseMirror' : 'textarea[name=body]'); }
async function remote(page: Page) { return (await (await page.request.get('/api/drafts/' + key)).json()).draft; }
async function saveRemote(page: Page, revision: number, body: string, fields: Record<string, unknown>) {
  const token = await form(page).locator('[name=_token]').inputValue();
  const response = await page.request.post('/api/drafts/' + key, { form: { _token: token, revision: String(revision), body, title: String(fields.title ?? ''), metadata: JSON.stringify({ context: '/threads', state: { version: 1, fields } }) } });
  expect(response.status()).toBe(200);
  return (await response.json()).draft;
}
async function reset(page: Page, rich: boolean) {
  flags(rich); await login(page); await page.goto('/drafts');
  const token = await page.locator('[name=_token]').first().inputValue();
  expect((await page.request.post('/api/drafts/' + key + '/discard', { form: { _token: token } })).status()).toBe(200);
  await open(page, rich);
}
async function capture(page: Page, info: TestInfo, name: string) {
  const dir=path.resolve(root,process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/draft-recovery-2026-10-23/browser',info.project.name);
  mkdirSync(dir,{recursive:true});await page.screenshot({path:path.join(dir,name+'.png'),fullPage:true});
}

test.afterEach(() => flags(false));

for (const rich of [false, true]) test.describe(rich ? 'rich' : 'source', () => {
  test.beforeEach(async ({ page }) => reset(page, rich));

  test('reload and wrapper-only edits preserve the complete draft', async ({ page }, info) => {
    await form(page).getByLabel('Title', { exact: true }).fill(title);
    await form(page).getByLabel('Anonymous', { exact: true }).check();
    await editor(page, rich).fill(text);
    await expect.poll(async () => (await remote(page))?.body).toBe(text);
    await page.reload();
    await expect(form(page).getByLabel('Title', { exact: true })).toHaveValue(title);
    await expect(form(page).getByLabel('Anonymous', { exact: true })).toBeChecked();
    await expect.poll(() => form(page).locator('textarea[name=body]').inputValue()).toBe(text);
    await form(page).getByLabel('Title', { exact: true }).fill('Title-only change — β');
    await expect.poll(async () => (await remote(page))?.title).toBe('Title-only change — β');
    await form(page).getByLabel('Anonymous', { exact: true }).uncheck();
    await expect.poll(async () => (await remote(page))?.metadata?.state?.fields?.is_anonymous).toBe(false);
    await capture(page, info, (rich?'rich':'source')+'-restored-fields');
  });

  test('title-only work is saved and listed before a body exists', async ({ page }) => {
    await form(page).getByLabel('Title', { exact: true }).fill(title);
    await expect.poll(async () => (await remote(page))?.title).toBe(title);
    await page.reload();await expect(form(page).getByLabel('Title', { exact: true })).toHaveValue(title);
    await page.goto('/drafts');await expect(page.locator('[data-local-drafts-list]')).toContainText(title);
    await page.locator('[data-local-drafts-list]').getByRole('link',{name:'Resume',exact:true}).click();
    await expect(page).toHaveURL(/\/compose/);await expect(form(page).getByLabel('Title', { exact: true })).toHaveValue(title);
  });

  test('local-only fallback retains fields and board destination', async ({ page }) => {
    flags(rich,false);await open(page,rich);
    await form(page).getByLabel('Title',{exact:true}).fill(title);await editor(page,rich).fill(text);
    await form(page).locator('select[name=board_id]').selectOption({label:'Feedback'});
    await page.reload();await expect(form(page).getByLabel('Title',{exact:true})).toHaveValue(title);
    await expect(form(page).locator('select[name=board_id]').locator('option:checked')).toHaveText('Feedback');
    await expect(form(page).getByLabel('Anonymous',{exact:true})).toBeDisabled();
    await page.goto('/drafts');await page.locator('[data-drafts-list]:not([data-server-drafts])').getByRole('link',{name:'Resume',exact:true}).click();
    await expect(form(page).locator('select[name=board_id]').locator('option:checked')).toHaveText('Feedback');
    await expect.poll(()=>form(page).locator('textarea[name=body]').inputValue()).toBe(text);
  });

  test('legacy body-only local drafts recover the matching stored title', async ({ page }) => {
    await saveRemote(page,0,text,{title,board_id:'2',is_anonymous:true});
    await page.evaluate(value=>localStorage.setItem('rb-draft:carol:/threads',value),text);
    await open(page,rich);
    await expect(form(page).getByLabel('Title',{exact:true})).toHaveValue(title);
    await expect(form(page).getByLabel('Anonymous',{exact:true})).toBeChecked();
    await expect.poll(()=>form(page).locator('textarea[name=body]').inputValue()).toBe(text);
  });

  test('server Resume works in a fresh JavaScript-disabled context', async ({ page, browser }, info) => {
    const draft=await saveRemote(page,0,text,{title,board_id:'2',is_anonymous:true});
    const context=await browser.newContext({javaScriptEnabled:false,viewport:{width:320,height:740}});
    await context.addCookies(await page.context().cookies());const plain=await context.newPage();await plain.goto(new URL('/drafts',page.url()).href);
    const row=plain.locator('.account-draft-row').filter({hasText:title});await row.getByRole('link',{name:'Resume',exact:true}).click();
    await expect(plain).toHaveURL(new RegExp('/compose\\?draft='+draft.id));
    await expect(form(plain).getByLabel('Title',{exact:true})).toHaveValue(title);
    await expect(form(plain).locator('textarea[name=body]')).toHaveValue(text);
    await expect(form(plain).getByLabel('Anonymous',{exact:true})).toBeChecked();
    expect(await plain.evaluate(()=>document.documentElement.scrollWidth)).toBe(320);
    await capture(plain,info,(rich?'rich':'source')+'-nojs-resume');await context.close();
  });
});

for(const changed of ['equal','title','anonymous','title-and-anonymous','body']) test('conflict comparison: '+changed, async ({page},info)=>{
  await reset(page,true);
  await form(page).getByLabel('Title',{exact:true}).fill(title);await editor(page,true).fill(text);
  await expect.poll(async()=>(await remote(page))?.body).toBe(text);
  const saved=await remote(page);await saveRemote(page,saved.revision,text,{title,board_id:'2',is_anonymous:false});
  if(changed.includes('title'))await form(page).getByLabel('Title',{exact:true}).fill('Local title only');
  if(changed.includes('anonymous'))await form(page).getByLabel('Anonymous',{exact:true}).check();
  await editor(page,true).fill(text+' temporary');await editor(page,true).fill(changed==='body'?'Different local body':text);
  const panel=page.locator('.composer-draft-sync.is-conflict');await expect(panel).toBeVisible();
  await expect(panel.getByRole('region',{name:'In this browser',exact:true})).toContainText(changed==='body'?'Different local body':text);
  await expect(panel.getByRole('region',{name:'On the server',exact:true})).toContainText(text);
  const differences=panel.locator('[data-draft-differences]');
  if(changed==='equal')await expect(differences).toContainText('same');
  if(changed.includes('title'))await expect(differences).toContainText('Title');
  if(changed.includes('anonymous'))await expect(differences).toContainText('Anonymous');
  if(changed==='body')await expect(differences).toContainText('Body');
  await capture(page,info,'conflict-'+changed);
  if(changed==='title'){
    await panel.getByRole('button',{name:'Keep server',exact:true}).click();await expect(form(page).getByLabel('Title',{exact:true})).toHaveValue(title);
  }else if(changed==='title-and-anonymous'){
    await form(page).getByLabel('Title',{exact:true}).fill('Newer title while choosing');
    await panel.getByRole('button',{name:'Save local as next revision',exact:true}).click();
    await expect.poll(async()=>(await remote(page))?.title).toBe('Newer title while choosing');
    await expect.poll(async()=>(await remote(page))?.metadata?.state?.fields?.is_anonymous).toBe(true);
  }else{
    await panel.getByRole('button',{name:'Keep local',exact:true}).click();
    await expect.poll(async()=>(await remote(page))?.revision).toBe(saved.revision+1);
  }
});

test('comparison is accessible and contains long text at 320px and landscape',async({page},info)=>{
  await reset(page,true);await saveRemote(page,0,'Server '+text.repeat(60),{title:'Server title',board_id:'2',is_anonymous:false});
  await page.evaluate(value=>localStorage.setItem('rb-draft:carol:/threads',value),'Local '+text.repeat(60));await open(page,true);
  const panel=page.locator('.composer-draft-sync.is-conflict');await expect(panel).toBeVisible();
  for(const size of [{width:320,height:740},{width:844,height:390}]){
    await page.setViewportSize(size);expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBe(size.width);
    await panel.scrollIntoViewIfNeeded();await capture(page,info,'comparison-'+size.width);
  }
  const scan=await new AxeBuilder({page}).include('.composer-draft-sync').withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();
  expect(scan.violations).toEqual([]);
});

test('a second conflict compares and keeps the latest server revision', async ({ page }) => {
  await reset(page, false);
  await form(page).getByLabel('Title', { exact: true }).fill(title);
  await editor(page, false).fill('Local first body');
  await expect.poll(async () => (await remote(page))?.body).toBe('Local first body');
  const first = await remote(page);
  const second = await saveRemote(page, first.revision, 'Server revision two', { title: 'Second title', board_id: '2', is_anonymous: false });
  await editor(page, false).fill('Local newer body');
  const panel = page.locator('.composer-draft-sync.is-conflict');
  await expect(panel.getByRole('region', { name: 'On the server', exact: true })).toContainText('Server revision two');
  await saveRemote(page, second.revision, 'Server revision three', { title: 'Third title', board_id: '2', is_anonymous: true });
  await panel.getByRole('button', { name: 'Save local as next revision', exact: true }).click();
  await expect(panel.getByRole('region', { name: 'On the server', exact: true })).toContainText('Server revision three');
  await panel.getByRole('button', { name: 'Keep server', exact: true }).click();
  await expect(editor(page, false)).toHaveValue('Server revision three');
  await expect(form(page).getByLabel('Title', { exact: true })).toHaveValue('Third title');
  await expect(form(page).getByLabel('Anonymous', { exact: true })).toBeChecked();
  expect((await remote(page)).revision).toBe(second.revision + 1);
});

test('server Resume preserves a divergent complete browser copy until a choice', async ({ page }) => {
  await reset(page, false);
  await form(page).getByLabel('Title', { exact: true }).fill('Browser title');
  await editor(page, false).fill('Browser body');
  await expect.poll(async () => (await remote(page))?.body).toBe('Browser body');
  const saved = await remote(page);
  const changed = await saveRemote(page, saved.revision, 'Separate server body', { title: 'Separate server title', board_id: '2', is_anonymous: true });
  await page.goto('/compose?draft=' + changed.id);
  const panel = page.locator('.composer-draft-sync.is-conflict');
  await expect(panel.getByRole('region', { name: 'In this browser', exact: true })).toContainText('Browser body');
  await expect(panel.getByRole('region', { name: 'On the server', exact: true })).toContainText('Separate server body');
  await expect(form(page).getByLabel('Title', { exact: true })).toHaveValue('Browser title');
  await panel.getByRole('button', { name: 'Keep local', exact: true }).click();
  expect((await remote(page)).body).toBe('Separate server body');
});

for (const serverResume of [false, true]) test('an unavailable browser draft board requires an explicit choice: ' + (serverResume ? 'server Resume' : 'ordinary mount'), async ({ page }) => {
  await reset(page, false);
  const saved = await saveRemote(page, 0, 'Server body', { title: 'Server title', board_id: '2', is_anonymous: false });
  await page.evaluate(() => {
    localStorage.setItem('rb-draft:carol:/threads', 'Browser body');
    localStorage.setItem('rb-draft-state:carol:/threads', JSON.stringify({ version: 1, body: 'Browser body', fields: { title: 'Browser title', board_id: '999999999', is_anonymous: true } }));
  });
  await page.goto(serverResume ? '/compose?draft=' + saved.id : '/compose?board=general');
  const board = form(page).locator('select[name=board_id]');
  await expect(board).toHaveValue('');
  await expect(board).toHaveAttribute('required', '');
  await expect(form(page)).toContainText('The saved board is unavailable.');
  await expect(form(page).getByLabel('Anonymous', { exact: true })).toBeDisabled();
  await expect(editor(page, false)).toHaveValue('Browser body');
  const panel = page.locator('.composer-draft-sync.is-conflict');
  await expect(panel.getByRole('region', { name: 'In this browser', exact: true })).toContainText('Saved board is unavailable');
  await panel.getByRole('button', { name: 'Keep local', exact: true }).click();
  expect(await board.evaluate(node => (node as HTMLSelectElement).checkValidity())).toBe(false);
  await board.selectOption('2');
  await expect(board).toHaveValue('2');
  expect(await board.evaluate(node => (node as HTMLSelectElement).checkValidity())).toBe(true);
  await expect(form(page).locator('[data-draft-board-unavailable]')).toHaveCount(0);
});

test('late legacy hydration respects explicitly cleared and unchecked fields', async ({ page }) => {
  await reset(page, false);
  await saveRemote(page, 0, text, { title: 'Saved server title', board_id: '2', is_anonymous: true });
  await page.evaluate(value => localStorage.setItem('rb-draft:carol:/threads', value), text);
  let release!: () => void;
  const held = new Promise<void>(resolve => { release = resolve; });
  await page.route('**/api/drafts/~2Fthreads', async route => {
    if (route.request().method() === 'GET') await held;
    await route.continue();
  });
  await open(page, false);
  await form(page).getByLabel('Title', { exact: true }).fill('Temporary title');
  await form(page).getByLabel('Title', { exact: true }).fill('');
  await form(page).getByLabel('Anonymous', { exact: true }).check();
  await form(page).getByLabel('Anonymous', { exact: true }).uncheck();
  release();
  const panel = page.locator('.composer-draft-sync.is-conflict');
  await expect(panel).toBeVisible();
  await expect(form(page).getByLabel('Title', { exact: true })).toHaveValue('');
  await expect(form(page).getByLabel('Anonymous', { exact: true })).not.toBeChecked();
  await expect(panel.locator('[data-draft-differences]')).toHaveText('Different fields: Title, Anonymous.');
  expect((await remote(page)).title).toBe('Saved server title');
});

for (const existing of [false, true]) test('typing during rich editor import survives: ' + (existing ? 'saved draft' : 'first draft'), async ({ page }) => {
  await reset(page, false);
  if (existing) {
    await form(page).getByLabel('Title', { exact: true }).fill('Older saved title');
    await editor(page, false).fill(text);
    await expect.poll(async () => (await remote(page))?.body).toBe(text);
  }
  flags(true);
  let release!: () => void;
  const held = new Promise<void>(resolve => { release = resolve; });
  let requested!: () => void;
  const started = new Promise<void>(resolve => { requested = resolve; });
  await page.route('**/assets/dist/wysiwyg-composer-*.js', async route => { requested(); await held; await route.continue(); });
  await page.goto('/compose?board=general', { waitUntil: 'domcontentloaded' });
  await started;
  await form(page).getByLabel('Title', { exact: true }).fill('Typed during import — β');
  if (!existing) await form(page).locator('textarea[name=body]').fill('Native body during import');
  release();
  // Native typing keeps source mode active when the optional editor finishes.
  await expect(form(page).locator('textarea[name=body]')).toHaveAttribute('data-rb-enhanced', '1');
  await expect(form(page).getByLabel('Title', { exact: true })).toHaveValue('Typed during import — β');
  await expect.poll(() => form(page).locator('textarea[name=body]').inputValue()).toBe(existing ? text : 'Native body during import');
  await expect.poll(() => page.evaluate(() => JSON.parse(localStorage.getItem('rb-draft-state:carol:/threads') || '{}').fields?.title)).toBe('Typed during import — β');
  if (existing) {
    const panel = page.locator('.composer-draft-sync.is-conflict');
    await expect(panel).toBeVisible();
    await expect(panel.getByRole('region', { name: 'In this browser', exact: true })).toContainText('Typed during import — β');
    expect((await remote(page)).title).toBe('Older saved title');
  } else {
    await expect.poll(async () => (await remote(page))?.title).toBe('Typed during import — β');
  }
});

test('a group-title-only conflict shows both titles and keeps the selected server copy', async ({ page }) => {
  await reset(page, false);
  const token = await form(page).locator('[name=_token]').inputValue();
  await page.request.post('/api/drafts/~2Fmessages/discard', { form: { _token: token } });
  await page.goto('/messages/new');
  const dm = page.locator('form[data-composer-instance="dm-new-page"]');
  await expect(dm.locator('textarea')).toHaveAttribute('data-rb-enhanced', '1');
  await dm.locator('.dm-to-input').fill('alice, bob');
  await dm.locator('textarea[name=body]').fill(text);
  await expect.poll(async () => (await (await page.request.get('/api/drafts/~2Fmessages')).json()).draft?.body).toBe(text);
  const saved = (await (await page.request.get('/api/drafts/~2Fmessages')).json()).draft;
  const response = await page.request.post('/api/drafts/~2Fmessages', { form: {
    _token: token, draft_id: String(saved.id), revision: String(saved.revision), title: 'Server group title', body: text,
    metadata: JSON.stringify({ state: { version: 1, fields: { to: 'alice, bob', title: 'Server group title' } } }),
  } });
  expect(response.status()).toBe(200);
  // An empty optional title is a real state. Restoring To must reveal the group field.
  await page.reload();
  const panel = dm.locator('.composer-draft-sync.is-conflict');
  await expect(panel).toBeVisible();
  await expect(panel.locator('[data-draft-differences]')).toHaveText('Different fields: Title.');
  await expect(panel.getByRole('region', { name: 'On the server', exact: true })).toContainText('Server group title');
  await panel.getByRole('button', { name: 'Keep server', exact: true }).click();
  await expect(dm.locator('[name=title]')).toBeVisible();
  await expect(dm.locator('[name=title]')).toHaveValue('Server group title');
  await expect(dm.locator('.dm-chip')).toHaveCount(2);
  await expect(dm.locator('[name=to]')).toHaveValue('alice, bob');
});

test('a board rail edit alone saves the destination and restores the anonymity gate', async ({ page }) => {
  await reset(page, false);
  await form(page).getByLabel('Title', { exact: true }).fill(title);
  await form(page).getByLabel('Anonymous', { exact: true }).check();
  await editor(page, false).fill(text);
  await expect.poll(async () => (await remote(page))?.body).toBe(text);
  await page.setViewportSize({ width: 1100, height: 800 });
  await page.locator('[data-compose-board-picker="feedback"]').click();
  const board = await form(page).locator('[name=board_id]').inputValue();
  await expect.poll(async () => (await remote(page))?.metadata?.state?.fields?.board_id).toBe(board);
  await page.reload();
  await expect(form(page).locator('select[name=board_id] option:checked')).toHaveText('Feedback');
  await expect(form(page).getByLabel('Anonymous', { exact: true })).toBeDisabled();
  await expect(form(page).getByLabel('Anonymous', { exact: true })).not.toBeChecked();
});

test('body-only typing during import retains untouched local-only wrapper fields', async ({ page }) => {
  await reset(page, false);
  flags(false, false); await open(page, false);
  await form(page).getByLabel('Title', { exact: true }).fill(title);
  await form(page).getByLabel('Anonymous', { exact: true }).check();
  await editor(page, false).fill(text);
  flags(true, false);
  let release!: () => void;
  const held = new Promise<void>(resolve => { release = resolve; });
  let requested!: () => void;
  const started = new Promise<void>(resolve => { requested = resolve; });
  await page.route('**/assets/dist/wysiwyg-composer-*.js', async route => { requested(); await held; await route.continue(); });
  await page.goto('/compose?board=general', { waitUntil: 'domcontentloaded' });
  await started;
  await form(page).locator('textarea[name=body]').fill('Only the body changed during import');
  release();
  await expect(form(page).locator('textarea[name=body]')).toHaveAttribute('data-rb-enhanced', '1');
  await expect(form(page).getByLabel('Title', { exact: true })).toHaveValue(title);
  await expect(form(page).getByLabel('Anonymous', { exact: true })).toBeChecked();
  await expect(form(page).locator('textarea[name=body]')).toHaveValue('Only the body changed during import');
  await page.reload();
  await expect(form(page).getByLabel('Title', { exact: true })).toHaveValue(title);
  await expect.poll(() => form(page).locator('textarea[name=body]').inputValue()).toBe('Only the body changed during import');
});

for (const rich of [false, true]) test('message wrapper recovery keeps recipients, chips and a group title: ' + (rich ? 'rich' : 'source'), async ({ page }) => {
  await reset(page, rich);
  const token = await form(page).locator('[name=_token]').inputValue();
  await page.request.post('/api/drafts/~2Fmessages/discard', { form: { _token: token } });
  await page.goto('/messages/new');
  const dm = page.locator('form[data-composer-instance="dm-new-page"]');
  await expect(dm.locator('textarea')).toHaveAttribute('data-rb-enhanced', '1');
  for (const username of ['alice', 'bob']) {
    await dm.locator('.dm-to-input').fill(username);
    await dm.getByRole('option').filter({ hasText: '@' + username }).click();
  }
  await expect(dm.locator('.dm-chip')).toHaveCount(2);
  await dm.locator('[name=title]').fill('Private council — 東京');
  await dm.locator(rich ? '.ProseMirror' : 'textarea[name=body]').fill(text);
  await expect.poll(async () => (await (await page.request.get('/api/drafts/~2Fmessages')).json()).draft?.metadata?.state?.fields?.title).toBe('Private council — 東京');
  await page.reload();
  await expect(dm.locator('textarea')).toHaveAttribute('data-rb-enhanced', '1');
  await expect(dm.locator('.dm-chip')).toHaveCount(2);
  await expect(dm.locator('.dm-to-input')).toHaveValue('');
  await expect(dm.locator('[name=to]')).toHaveValue('alice, bob');
  await expect(dm.locator('[name=title]')).toBeVisible();
  await expect(dm.locator('[name=title]')).toHaveValue('Private council — 東京');
  await expect.poll(() => dm.locator('textarea[name=body]').inputValue()).toBe(text);
  // Removing a chip must update the restored canonical field, not erase both recipients.
  await dm.locator('.dm-chip').first().getByRole('button').click();
  await expect(dm.locator('[name=to]')).toHaveValue('bob');
  await page.reload();
  await expect(dm.locator('.dm-chip')).toHaveCount(1);
  await expect(dm.locator('[name=title]')).toBeVisible();
  await expect(dm.locator('[name=title]')).toHaveValue('Private council — 東京');
  expect(await dm.evaluate(node => Object.fromEntries(new FormData(node as HTMLFormElement)))).toMatchObject({ to: 'bob', title: 'Private council — 東京', body: text });
});

test('confirmation preserves a newer same-body title from another tab', async ({ page, context }) => {
  await reset(page, false);
  await form(page).getByLabel('Title', { exact: true }).fill('Older submitted title');
  await editor(page, false).fill(text);
  await expect.poll(async () => (await remote(page))?.body).toBe(text);
  const sibling = await context.newPage();
  await open(sibling, false);
  await form(sibling).getByLabel('Title', { exact: true }).fill('Newer unsubmitted title');
  await expect.poll(async () => (await remote(sibling))?.title).toBe('Newer unsubmitted title');
  await form(page).getByRole('button', { name: 'Create topic', exact: true }).click();
  await page.waitForURL(/\/t\/\d+/);
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('rb-draft-state:carol:/threads') || '{}').fields?.title)).toBe('Newer unsubmitted title');
  expect((await remote(page)).title).toBe('Newer unsubmitted title');
  await open(page, false);
  await expect(form(page).getByLabel('Title', { exact: true })).toHaveValue('Newer unsubmitted title');
  await expect(editor(page, false)).toHaveValue(text);
  await sibling.close();
});

test('confirmation clears the matching full local and server snapshot', async ({ page }) => {
  await reset(page, false);
  await form(page).getByLabel('Title', { exact: true }).fill('Confirmed complete snapshot');
  await editor(page, false).fill(text);
  await expect.poll(async () => (await remote(page))?.body).toBe(text);
  await form(page).getByRole('button', { name: 'Create topic', exact: true }).click();
  await page.waitForURL(/\/t\/\d+/);
  await expect.poll(() => page.evaluate(() => localStorage.getItem('rb-draft-state:carol:/threads'))).toBeNull();
  expect(await page.evaluate(() => localStorage.getItem('rb-draft:carol:/threads'))).toBeNull();
  expect(await remote(page)).toBeNull();
});

test('a malformed or mismatched companion never replaces a legacy raw body', async ({ page }) => {
  await reset(page, false);
  flags(false, false);
  for (const companion of ['{broken', JSON.stringify({ version: 1, body: 'Stale companion body', fields: { title: 'Stale title', board_id: '2' } })]) {
    await page.evaluate(({ companion, text }) => {
      localStorage.setItem('rb-draft:carol:/threads', text);
      localStorage.setItem('rb-draft-state:carol:/threads', companion);
    }, { companion, text });
    await open(page, false);
    await expect(editor(page, false)).toHaveValue(text);
    await expect(form(page).getByLabel('Title', { exact: true })).toHaveValue('');
  }
});

test('completion recognition preserves a changed group title after its feature is disabled', async ({ page }) => {
  await reset(page, false);
  let token = await form(page).locator('[name=_token]').inputValue();
  await page.request.post('/api/drafts/~2Fmessages/discard', { form: { _token: token } });
  await page.goto('/messages/new');
  const dm = page.locator('form[data-composer-instance="dm-new-page"]');
  await expect(dm.locator('textarea')).toHaveAttribute('data-rb-enhanced', '1');
  for (const username of ['alice', 'bob']) {
    await dm.locator('.dm-to-input').fill(username);
    await dm.getByRole('option').filter({ hasText: '@' + username }).click();
  }
  await dm.locator('[name=title]').fill('Submitted group title');
  await dm.locator('textarea[name=body]').fill(text);
  const submission = await dm.locator('[name=idempotency_key]').inputValue();
  await expect.poll(async () => (await (await page.request.get('/api/drafts/~2Fmessages')).json()).draft?.body).toBe(text);
  await dm.locator('.composer-send').click();
  await page.waitForURL(/\/messages\/\d+/);
  await expect.poll(() => page.evaluate(wanted => JSON.parse(sessionStorage.getItem('rb-draft:pending-submits') || '[]').some((item: { submission?: string; completed?: boolean }) => item.submission === wanted && item.completed), submission)).toBe(true);
  await expect.poll(async () => (await (await page.request.get('/api/drafts/~2Fmessages')).json()).draft).toBeNull();
  token = await page.locator('[name=_token]').first().inputValue();
  const created = await page.request.post('/api/drafts/~2Fmessages', { form: {
    _token: token, draft_id: '0', revision: '0', title: 'Newer hidden group title', body: text,
    metadata: JSON.stringify({ submission, state: { version: 1, fields: { to: 'alice, bob', title: 'Newer hidden group title' } } }),
  } });
  expect(created.status()).toBe(200);
  execFileSync('php', ['-r', String.raw`
require 'vendor/autoload.php';\App\Core\Env::load('.env');$c=\App\Core\Config::fromFile('config/config.php');
$s=new \App\Repository\SettingRepository(new \App\Core\Database($c->get('db')));$f=$s->get('features',[]);$f['group_dms']=false;$s->set('features',$f);
`], { cwd: root, env: process.env });
  await page.goto('/messages/new');
  await expect(dm.locator('textarea[name=body]')).toHaveValue(text);
  await expect(dm.locator('[name=title]')).toHaveCount(0);
  await expect(dm).not.toContainText('Newer hidden group title');
  expect((await (await page.request.get('/api/drafts/~2Fmessages')).json()).draft?.title).toBe('Newer hidden group title');
});
