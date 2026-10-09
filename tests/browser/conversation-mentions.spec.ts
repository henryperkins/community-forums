import { expect, test, type Page, type TestInfo } from '@playwright/test';
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
function mode(rich: boolean) {
  php(`$settings=new \\App\\Repository\\SettingRepository($db);
$features=$settings->get('features', []); if(!is_array($features)) $features=[];
$features['wysiwyg_composer']=${rich ? 'true' : 'false'}; $settings->set('features',$features);
$db->run("DELETE FROM server_drafts WHERE user_id=(SELECT id FROM users WHERE email='bob@retro.test')");`);
}
async function reply(page: Page, rich: boolean) {
  mode(rich);
  await page.goto('/login');
  await page.locator('input[name=email]').fill('bob@retro.test');
  await page.locator('input[name=password]').fill('password123');
  await page.locator('button[type=submit]').click();
  await page.waitForURL(u => !u.pathname.endsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible().catch(() => false)) await skip.click();
  const topic = php(`$t=$db->fetch("SELECT id,slug FROM threads WHERE title='Share your favourite keyboard shortcuts' ORDER BY id DESC LIMIT 1"); echo '/t/'.$t['id'].'-'.$t['slug'];`);
  await page.goto(topic);
  const form = page.locator('form.composer[data-composer-context=reply]').first();
  const input = rich ? form.locator('.ProseMirror') : form.locator('textarea.composer-input');
  await expect(input).toBeAttached();
  await input.focus();
  await expect(input).toBeVisible();
  return { form, input, menu: form.locator('.composer-reference-menu') };
}
test.afterEach(() => mode(false));

async function capture(page: Page, info: TestInfo, name: string) {
  const target = process.env.RB_EVIDENCE_DIR
    ? path.resolve(repoRoot, process.env.RB_EVIDENCE_DIR, info.project.name, `${name}.png`)
    : info.outputPath(`${name}.png`);
  mkdirSync(path.dirname(target), { recursive: true });
  await page.screenshot({ path: target });
}

for (const rich of [false, true]) {
  test(`${rich ? 'rich' : 'textarea'} bare @ shows people and accepts with a trailing space`, async ({ page }, info) => {
    const { form, input, menu } = await reply(page, rich);
    await input.fill('@');
    await expect(menu).toBeVisible();
    const alice = menu.locator('.is-person').filter({ hasText: '@alice' });
    await expect(alice).toBeVisible();
    await expect(alice.locator('.monogram')).toHaveCount(1);
    const avatar = await alice.locator('.monogram').evaluate(el => {
      const style = getComputedStyle(el); return { width: style.width, height: style.height, font: style.fontSize };
    });
    expect(avatar).toEqual({ width: '28px', height: '28px', font: '10.56px' });
    await expect(alice.locator('.composer-reference-meta')).toContainText('in this topic');
    await capture(page, info, `conversation-mention-people-${rich ? 'rich' : 'textarea'}`);
    await alice.click();
    if (rich) {
      await expect(input).toHaveText('@alice ');
      await expect(form.locator('.composer-mention-chip')).toHaveText('@alice');
      await expect(form.locator('.composer-input-mirror')).toBeHidden();
    } else {
      await expect(input).toHaveValue('@alice ');
      await expect(input).toHaveJSProperty('selectionStart', 7);
    }
    await input.press('x');
    if (rich) await expect(input).toHaveText('@alice x');
    else await expect(input).toHaveValue('@alice x');
  });
}

test('textarea insertion leaves following punctuation and whitespace intact', async ({ page }) => {
  const { input, menu } = await reply(page, false);
  for (const [prefix, tail] of [['', ','], ['', ' '], ['', ')'], ['', '!'], ['', ']'], ['**Thanks ', '**'], ['*Thanks ', '*'], ['_Thanks ', '_'], ['~~Thanks ', '~~'], ['||Thanks ', '||']]) {
    await input.fill(`${prefix}@ali${tail}`);
    await input.evaluate((el: HTMLTextAreaElement, pos) => {
      el.setSelectionRange(pos, pos);
      el.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    }, prefix.length + 4);
    await expect(menu).toBeVisible();
    await menu.locator('.is-person').filter({ hasText: '@alice' }).click();
    await expect(input).toHaveValue(`${prefix}@alice${tail}`);
    await expect(input).toHaveJSProperty('selectionStart', prefix.length + 6);
  }
});

test('source mirror marks only known prose handles and follows textarea geometry and scrolling', async ({ page }, info) => {
  const { form, input, menu } = await reply(page, true);
  await form.getByRole('button', { name: 'Source', exact: true }).click();
  const source = form.locator('textarea.composer-input');
  await source.fill('@');
  await expect(menu).toBeVisible();
  await source.press('Escape');
  const draft = '@alice @never_returned alice@alice.test a*@alice.test **@alice** *@alice* ~~@alice~~ `@alice`\n``multiline\n@alice``\n```\n@alice\n```\n~~~\n@alice\n~~~\n\n    @alice\n\t@alice\n\n> ~~~\n> @alice\n> ~~~\n\n- ```\n  @alice\n  ```\n\n' + 'a long line of text '.repeat(80) + '\n@alice\n';
  await source.fill(draft);
  const mirror = form.locator('.composer-input-mirror');
  await expect(mirror).toHaveAttribute('aria-hidden', 'true');
  await expect(mirror.locator('mark')).toHaveCount(5);
  expect(await mirror.textContent()).toBe(draft + '\u200b');
  await expect(source).toHaveValue(draft);
  await expect.poll(async () => source.evaluate((ta: HTMLTextAreaElement) => {
    const mirror = ta.parentElement!.querySelector<HTMLElement>('.composer-input-mirror')!;
    const a = getComputedStyle(ta), b = getComputedStyle(mirror);
    return { width: parseFloat(b.width) - (ta.clientWidth + parseFloat(a.borderLeftWidth) + parseFloat(a.borderRightWidth)), height: parseFloat(b.height) - (ta.clientHeight + parseFloat(a.borderTopWidth) + parseFloat(a.borderBottomWidth)), font: b.fontSize === a.fontSize && b.lineHeight === a.lineHeight, padding: b.padding === a.padding };
  })).toEqual({ width: 0, height: 0, font: true, padding: true });
  await source.evaluate((ta: HTMLTextAreaElement) => { ta.scrollTop = 80; ta.scrollLeft = 10; ta.dispatchEvent(new Event('scroll')); });
  const scroll = await source.evaluate((ta: HTMLTextAreaElement) => ({ top: ta.scrollTop, left: ta.scrollLeft }));
  await expect(mirror).toHaveJSProperty('scrollTop', scroll.top);
  await expect(mirror).toHaveJSProperty('scrollLeft', scroll.left);
  // Mention paint cannot change the glyph widths or the submitted draft.
  const markGeometry = await mirror.locator('mark').first().evaluate(el => {
    const style = getComputedStyle(el); return [style.padding, style.borderWidth];
  });
  expect(markGeometry).toEqual(['0px', '0px']);
  await source.evaluate((ta: HTMLTextAreaElement) => { ta.scrollTop = 0; ta.scrollLeft = 0; ta.dispatchEvent(new Event('scroll')); });
  await capture(page, info, 'conversation-mention-source-wash');
  await form.getByRole('button', { name: 'Rich text', exact: true }).click();
  await expect(mirror).toBeHidden();
  await expect(input).toBeVisible();
});

for (const rich of [false, true]) {
  test(`${rich ? 'rich source' : 'textarea'} email, code, fences and empty # never open the picker`, async ({ page }) => {
    const { form, menu } = await reply(page, rich);
    if (rich) await form.getByRole('button', { name: 'Source', exact: true }).click();
    const input = form.locator('textarea.composer-input');
    for (const text of ['mail@', '`@', '```\n@', '~~~\n@', '``code\n@', '``code ` literal\n@', '   ```php\n@', '    @', '\t@', '> ~~~\n> @', '- ```\n  @', '- > ```\n  > @', '> - > ~~~\n>   > @', '- - ```\n    @', '#', ':']) {
      await input.fill(text);
      await expect(menu).toBeHidden();
    }
    // Closing either delimiter restores normal mention discovery.
    await input.fill('~~~\ncode\n~~~\n@');
    await expect(menu).toBeVisible();
    await input.fill('``code\n@alice``\n@');
    await expect(menu).toBeVisible();
    await input.fill('> ~~~\n> @alice\n> ~~~\n\n@');
    await expect(menu).toBeVisible();
    await input.fill('- ```\n  @alice\n  ```\n\n@');
    await expect(menu).toBeVisible();
    await input.fill('- > ```\n  > @alice\n  > ```\n\n@');
    await expect(menu).toBeVisible();
    await input.fill('> - > ~~~\n>   > @alice\n>   > ~~~\n\n@');
    await expect(menu).toBeVisible();
  });

  test(`${rich ? 'rich source' : 'textarea'} block boundaries and nested list prose restore all reference pickers and highlights`, async ({ page }) => {
    const { form, menu } = await reply(page, rich);
    if (rich) await form.getByRole('button', { name: 'Source', exact: true }).click();
    const input = form.locator('textarea.composer-input');
    for (const trigger of ['@', '#gener', ':smil']) {
      for (const prefix of ['stray `text\n\n', 'stray `text\n\n## Heading\n', 'stray `text\n- new item ', '> ~~~\n> code\n\n', '- ```\n  code\n\n', '- item\n    - nested ', '- item\n    continuation ', 'paragraph\n    continuation ']) {
        await input.fill(prefix + trigger);
        await expect(menu, JSON.stringify(prefix + trigger)).toBeVisible();
      }
    }
    await input.fill('@');
    await expect(menu).toBeVisible();
    await input.press('Escape');
    await input.fill('stray `text\n\n@alice\n\n- item\n    - nested @alice\n      continuation @alice\n\n> ~~~\n> @alice\n\n@alice');
    await expect(form.locator('.composer-input-mirror mark')).toHaveCount(4);
  });
}

test('rich mention acceptance beside punctuation stays dismissed through textarea synchronization', async ({ page }) => {
  const { form, input, menu } = await reply(page, true);
  await input.fill('@ali,');
  await input.press('ArrowLeft');
  await expect(menu).toBeVisible();
  await menu.locator('.is-person').filter({ hasText: '@alice' }).click();
  await expect(input).toHaveText('@alice,');
  await expect(form.locator('textarea.composer-input')).toHaveValue('@alice,');
  await form.locator('textarea.composer-input').dispatchEvent('input');
  await expect(menu).toBeHidden();
  await input.press('Backspace');
  await expect(input).toHaveText('@alic,');
  await expect(menu).toBeVisible();
});

test('one outstanding bare-mention query inserts at the current caret rather than its original range', async ({ page }) => {
  const { input, menu } = await reply(page, false);
  let requests = 0;
  let release!: () => void;
  const held = new Promise<void>(resolve => { release = resolve; });
  await page.route('**/composer/suggest?*', async route => { requests++; await held; await route.continue(); });
  await input.fill('@ @');
  await expect.poll(() => requests).toBe(1);
  await input.evaluate((el: HTMLTextAreaElement) => {
    el.setSelectionRange(1, 1);
    el.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    el.dispatchEvent(new Event('input', { bubbles: true }));
  });
  release();
  await expect(menu).toBeVisible();
  await menu.locator('.is-person').filter({ hasText: '@alice' }).click();
  await expect(input).toHaveValue('@alice @');
  expect(requests).toBe(1);
});

test('rich line-final mentions serialize canonical handles and handoff whitespace through posting and editing', async ({ page }) => {
  php(`$users=new \\App\\Repository\\UserRepository($db);
  foreach(['linefinal_w','linefinal__w'] as $handle) if(!$users->findByUsername($handle)) $users->create(['username'=>$handle,'email'=>$handle.'@retro.test','password_hash'=>(new \\App\\Security\\PasswordHasher())->hash('password123')]);`);
  const { form, input, menu } = await reply(page, true);
  for (const prefix of ['literal_ ', 'literal* ', 'literal\\ ']) {
    const handle = prefix === 'literal\\ ' ? '@linefinal__w' : '@linefinal_w';
    await input.fill(prefix + handle);
    await expect(menu).toBeVisible();
    await menu.locator('.is-person').filter({ hasText: handle }).click();
    await expect(input).toHaveText(prefix + handle + ' ');
    const source = form.locator('textarea.composer-input');
    await expect(source).toHaveValue(new RegExp(handle + ' $'));
    expect(await source.inputValue()).not.toContain('&#x20;');
    await form.getByRole('button', { name: 'Source', exact: true }).click();
    await expect(source).toBeVisible();
    expect(await source.inputValue()).not.toContain('&#x20;');
    await form.getByRole('button', { name: 'Rich text', exact: true }).click();
    await expect(input).toBeVisible();
  }
  await form.getByRole('button', { name: 'Reply', exact: true }).click();
  await page.waitForURL(u => /^#p\d+$/.test(u.hash));
  const id = Number(new URL(page.url()).hash.slice(2));
  const stored = php(`echo $db->fetchValue('SELECT body FROM posts WHERE id=?',[${id}]);`);
  expect(stored).toContain('@linefinal__w');
  expect(stored).not.toContain('&#x20;');
  const post = page.locator(`#p${id}`);
  await post.locator('.post-menu > summary').click();
  await post.locator('[data-post-disclosure-open]').filter({ hasText: 'Edit' }).click();
  const edit = post.locator('form.composer[data-composer-context=edit]');
  await expect(edit.locator('.ProseMirror')).toBeVisible();
  await edit.getByRole('button', { name: 'Source', exact: true }).click();
  await expect(edit.locator('textarea.composer-input')).toHaveValue(stored);
});

test('a long email-local-part-free draft highlights promptly without repeated paint on autosize', async ({ page }) => {
  const { form, input, menu } = await reply(page, false);
  await input.fill('@');
  await expect(menu).toBeVisible();
  await input.press('Escape');
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
  await form.locator('.composer-input-mirror').evaluate(el => {
    (window as any).__mentionPaints = 0;
    new MutationObserver(() => (window as any).__mentionPaints++).observe(el, { childList: true });
  });
  // A completed token isolates input/autosize paint from new suggestion data.
  const draft = 'a'.repeat(19000) + '\n@alice.';
  const started = Date.now();
  await input.fill(draft);
  await expect(form.locator('.composer-input-mirror mark')).toHaveText('@alice');
  expect(Date.now() - started).toBeLessThan(3000);
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
  expect(await page.evaluate(() => (window as any).__mentionPaints)).toBe(1);
});

test('person suggestions reject external avatar URLs and render identity as text', async ({ page }) => {
  const { form, input, menu } = await reply(page, false);
  await page.route('**/composer/suggest?*', route => route.fulfill({
    status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, items: [
      { type: 'user', label: '@alice', token: '@alice', markdown: '@alice', initials: '<A>', mono: 'mono-3', avatar: 'https://external.invalid/avatar.png', meta: '<Alice> · in this topic', participant: true },
    ] }),
  }));
  await input.fill('@ali');
  const option = menu.locator('.is-person');
  await expect(option).toBeVisible();
  await expect(option.locator('img')).toHaveCount(0);
  await expect(option.locator('.monogram.mono-3')).toHaveText('<A>');
  await expect(option.locator('.composer-reference-meta')).toHaveText('<Alice> · in this topic');
  await expect(option.locator('[style]')).toHaveCount(0);
  await expect(form.locator('.composer-input-mirror')).toHaveAttribute('aria-hidden', 'true');
});

test('rich mentions beyond the notification cap keep their muted dashed cue', async ({ page }) => {
  const { form, input } = await reply(page, true);
  await input.fill(Array.from({ length: 11 }, (_, i) => `@member_${i}`).join(' '));
  await expect(form.locator('.composer-mention-chip')).toHaveCount(11);
  const muted = form.locator('.composer-mention-chip.is-muted');
  await expect(muted).toHaveCount(1);
  expect(await muted.evaluate(el => getComputedStyle(el).outlineStyle)).toBe('dashed');
  expect(await muted.evaluate(el => getComputedStyle(el).backgroundColor)).toBe('rgba(0, 0, 0, 0)');
});

for (const theme of ['light', 'dark']) {
  test(`${theme} server preview preserves the mention ink, ruled link and wash without padding`, async ({ page }, info) => {
    const original = php(`$id=(int)$db->fetchValue("SELECT id FROM users WHERE email='bob@retro.test'");
$row=$db->fetch('SELECT * FROM user_preferences WHERE user_id=?',[$id]);
echo json_encode(['id'=>$id,'row'=>$row]);`);
    try {
      php(`$id=(int)$db->fetchValue("SELECT id FROM users WHERE email='bob@retro.test'");
(new \\App\\Repository\\UserPreferenceRepository($db))->merge($id,['theme'=>'${theme}','show_preview'=>true]);`);
      const { form, input } = await reply(page, false);
      await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
      const toggle = form.getByRole('button', { name: 'Preview', exact: true });
      if (await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
      const response = page.waitForResponse(r => new URL(r.url()).pathname === '/composer/preview'
        && r.request().method() === 'POST' && (r.request().postData() || '').includes('Hello @alice.'));
      await input.fill('Hello @alice.');
      const result = await response;
      expect(result.ok()).toBe(true);
      expect((await result.json()).html).toContain('class="mention"');
      const mention = form.locator('.composer-preview.formatted-content a.mention');
      await expect(mention).toHaveText('@alice');
      await expect(mention).toHaveAttribute('href', '/u/alice');
      const appearance = await mention.evaluate(el => {
        const style = getComputedStyle(el);
        const probe = document.createElement('span');
        probe.style.color = 'var(--on-brand-subtle)';
        probe.style.fontWeight = 'var(--weight-medium)';
        probe.style.borderRadius = 'var(--radius-sm)';
        el.parentElement!.appendChild(probe);
        const expected = getComputedStyle(probe);
        const result = { ink: style.color === expected.color, weight: style.fontWeight === expected.fontWeight,
          radius: style.borderRadius === expected.borderRadius, underline: style.textDecorationLine.includes('underline'),
          wash: style.backgroundColor, shadow: style.boxShadow, padding: style.padding,
          clone: style.getPropertyValue('box-decoration-break') === 'clone' || style.getPropertyValue('-webkit-box-decoration-break') === 'clone' };
        probe.remove();
        return result;
      });
      expect(appearance).toMatchObject({ ink: true, weight: true, radius: true, underline: true, padding: '0px', clone: true });
      expect(appearance.wash).not.toBe('rgba(0, 0, 0, 0)');
      expect(appearance.shadow).toContain('0px 0px 0px 2px');
      await capture(page, info, `conversation-mention-preview-${theme}`);
    } finally {
      const encoded = Buffer.from(original).toString('base64');
      php(`$s=json_decode(base64_decode('${encoded}'),true);
$db->transaction(function() use($db,$s){$db->run('DELETE FROM user_preferences WHERE user_id=?',[$s['id']]);
if($s['row']) $db->run('INSERT INTO user_preferences(user_id,prefs,updated_at) VALUES(?,?,?)',[$s['id'],$s['row']['prefs'],$s['row']['updated_at']]);});`);
    }
  });
}
