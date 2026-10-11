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
    // The exact fractional box, less any scrollbar: the rounded clientWidth and
    // clientHeight differ from it whenever the layout is fractional.
    const scrollbar = ta.offsetWidth - ta.clientWidth - parseFloat(a.borderLeftWidth) - parseFloat(a.borderRightWidth);
    const box = ta.getBoundingClientRect(), shadow = mirror.getBoundingClientRect();
    return { width: Math.abs(shadow.width - (box.width - scrollbar)) < 0.02, height: Math.abs(shadow.height - box.height) < 0.02, font: b.fontSize === a.fontSize && b.lineHeight === a.lineHeight, padding: b.padding === a.padding };
  })).toEqual({ width: true, height: true, font: true, padding: true });
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

// Fraction of each image's ink with matching ink within ±1 device pixel in the
// other. Identical wrapping and glyph placement scores 1 both ways.
async function inkAlignment(page: Page, a: Buffer, b: Buffer) {
  return page.evaluate(async ([a64, b64]) => {
    const load = (src: string) => new Promise<HTMLImageElement>(resolve => { const img = new Image(); img.onload = () => resolve(img); img.src = src; });
    const pixels = (img: HTMLImageElement) => {
      const canvas = document.createElement('canvas'); canvas.width = img.width; canvas.height = img.height;
      const context = canvas.getContext('2d')!; context.drawImage(img, 0, 0);
      return context.getImageData(0, 0, img.width, img.height);
    };
    const [first, second] = (await Promise.all([load(`data:image/png;base64,${a64}`), load(`data:image/png;base64,${b64}`)])).map(pixels);
    const width = Math.min(first.width, second.width), height = Math.min(first.height, second.height);
    const ink = (d: ImageData, x: number, y: number) => { const i = (y * d.width + x) * 4; return d.data[i] + d.data[i + 1] + d.data[i + 2] < 300; };
    const near = (d: ImageData, x: number, y: number) => {
      for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
        const nx = x + dx, ny = y + dy;
        if (nx >= 0 && ny >= 0 && nx < width && ny < height && ink(d, nx, ny)) return true;
      }
      return false;
    };
    let inkA = 0, inkB = 0, matchA = 0, matchB = 0;
    for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
      if (ink(first, x, y)) { inkA++; if (near(second, x, y)) matchA++; }
      if (ink(second, x, y)) { inkB++; if (near(first, x, y)) matchB++; }
    }
    return Math.min(matchA / inkA, matchB / inkB);
  }, [a.toString('base64'), b.toString('base64')]);
}

test('source highlights stay on their handles at 90% and 110% zoom and fractional widths', async ({ browser }, info) => {
  test.skip(info.project.name !== 'desktop', 'browser zoom is a desktop setting');
  // Zoom divides fluid columns into fractional CSS widths. A mirror sized from
  // the rounded clientWidth wraps a line differently and every later highlight
  // drifts; the dense draft below makes any half-pixel difference visible.
  const draft = Array.from({ length: 900 }, (_, i) => i % 37 === 0 ? '@alice' : ['i', 'il', 'a', 'wm', 'x.'][i % 5]).join(' ');
  for (const zoom of [0.9, 1.1]) {
    const context = await browser.newContext({ deviceScaleFactor: zoom, viewport: { width: Math.round(1000 / zoom), height: Math.round(900 / zoom) } });
    const page = await context.newPage();
    const { form, input: source, menu } = await reply(page, false);
    await source.fill('@');
    await expect(menu).toBeVisible();
    await source.press('Escape');
    const wrap = form.locator('.composer-input-wrap');
    const mirror = form.locator('.composer-input-mirror');
    for (const width of [555.55, 557.83, 560.3]) {
      await wrap.evaluate((el, px) => (el as HTMLElement).style.setProperty('width', `${px}px`), width);
      await source.fill(`${draft} ${width}`);
      await expect(mirror).toContainText(String(width));
      await expect.poll(() => source.evaluate((ta: HTMLTextAreaElement) => {
        const shadow = ta.parentElement!.querySelector<HTMLElement>('.composer-input-mirror')!;
        const style = getComputedStyle(ta);
        const scrollbar = ta.offsetWidth - ta.clientWidth - parseFloat(style.borderLeftWidth) - parseFloat(style.borderRightWidth);
        return Math.abs(shadow.getBoundingClientRect().width - (ta.getBoundingClientRect().width - scrollbar)) < 0.02;
      }), `zoom ${zoom}, width ${width}: mirror keeps the fractional content width`).toBe(true);
      await source.evaluate((ta: HTMLTextAreaElement) => { ta.scrollTop = 0; ta.dispatchEvent(new Event('scroll')); ta.blur(); });
      // Compare glyphs only: mirror ink alone, then textarea ink alone. Pin the
      // wrap above the sticky chrome (keeping its fractional left edge) so both
      // shots read the same pixels; screenshots in some engines scroll the page.
      const rect = () => wrap.evaluate(el => { const r = el.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height }; });
      await wrap.evaluate(el => {
        const left = el.getBoundingClientRect().x;
        const style = (el as HTMLElement).style;
        style.setProperty('position', 'fixed'); style.setProperty('top', '96px'); style.setProperty('left', `${left}px`);
        style.setProperty('z-index', '2147483647'); style.setProperty('background', '#fff');
      });
      const clip = await rect();
      await mirror.evaluate(el => el.querySelectorAll('mark').forEach(mark => {
        (mark as HTMLElement).style.setProperty('color', 'inherit', 'important');
        (mark as HTMLElement).style.setProperty('background', 'none', 'important');
        (mark as HTMLElement).style.setProperty('box-shadow', 'none', 'important');
      }));
      await source.evaluate((ta: HTMLTextAreaElement) => {
        ta.style.setProperty('color', 'transparent', 'important');
        ta.style.setProperty('caret-color', 'transparent', 'important');
        ta.parentElement!.querySelector<HTMLElement>('.composer-input-mirror')!.style.setProperty('color', '#000', 'important');
      });
      const mirrored = await page.screenshot({ clip });
      await source.evaluate((ta: HTMLTextAreaElement) => {
        ta.style.setProperty('color', '#000', 'important');
        ta.parentElement!.querySelector<HTMLElement>('.composer-input-mirror')!.style.setProperty('color', 'transparent', 'important');
      });
      const typed = await page.screenshot({ clip });
      expect(await rect()).toEqual(clip);
      await source.evaluate((ta: HTMLTextAreaElement) => {
        ta.style.removeProperty('color'); ta.style.removeProperty('caret-color');
        ta.parentElement!.querySelector<HTMLElement>('.composer-input-mirror')!.style.removeProperty('color');
      });
      await wrap.evaluate(el => ['position', 'top', 'left', 'z-index', 'background'].forEach(name => (el as HTMLElement).style.removeProperty(name)));
      const scratch = await context.newPage();
      const score = await inkAlignment(scratch, mirrored, typed);
      // Aligned frames score 1.0 (0.981 at worst: textarea and mirror glyphs
      // anti-alias differently at a fractional pixel ratio); one re-wrapped
      // line before the fix scored 0.79.
      expect(score, `zoom ${zoom}, width ${width}`).toBeGreaterThan(0.95);
      await scratch.close();
    }
    await context.close();
  }
});

test('Inbox rows and previews honor Show avatars in bylines, replies and mention person rows', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'the reading-pane preview is a desktop layout');
  const saved = JSON.parse(php(`$bob=(int)$db->fetchValue("SELECT id FROM users WHERE email='bob@retro.test'");
$thread=(int)$db->fetchValue("SELECT id FROM threads WHERE title='Share your favourite keyboard shortcuts' ORDER BY id DESC LIMIT 1");
$stars=new \\App\\Repository\\ThreadUserRepository($db);
echo json_encode(['bob'=>$bob,'thread'=>$thread,'starred'=>$stars->isStarred($bob,$thread),'prefs'=>$db->fetch('SELECT * FROM user_preferences WHERE user_id=?',[$bob])]);
$stars->setStar($bob,$thread,true);
(new \\App\\Repository\\UserPreferenceRepository($db))->merge($bob,['show_avatars'=>false]);`));
  try {
    await reply(page, false);
    await page.goto('/inbox?scope=starred&order=active');
    await expect(page.locator('.inbox-thread-list .thread-row')).not.toHaveCount(0);
    await expect(page.locator('.inbox-thread-list .thread-row .monogram')).toHaveCount(0);
    await page.locator(`[data-inbox-preview-url="/inbox/preview/${saved.thread}"]`).click();
    const preview = page.locator(`[data-inbox-preview="${saved.thread}"]`);
    await expect(preview.locator('.inbox-preview-posts > li').first()).toBeVisible();
    await expect(preview.locator('.inbox-preview-attribution .inbox-preview-author')).toBeVisible();
    await expect(preview.locator('.monogram')).toHaveCount(0);
    await expect(preview.locator('.composer-box')).toHaveAttribute('data-composer-avatars', '0');
    const source = preview.locator('textarea.composer-input');
    await source.focus();
    await source.fill('@');
    const person = preview.locator('.composer-reference-menu .is-person').first();
    await expect(person).toBeVisible();
    await expect(person).toHaveClass(/\bis-avatarless\b/);
    await expect(preview.locator('.composer-reference-menu .monogram')).toHaveCount(0);
    await capture(page, info, 'conversation-inbox-preview-avatarless');
  } finally {
    const encoded = Buffer.from(JSON.stringify(saved)).toString('base64');
    php(`$s=json_decode(base64_decode('${encoded}'),true);
(new \\App\\Repository\\ThreadUserRepository($db))->setStar($s['bob'],$s['thread'],(bool)$s['starred']);
$db->transaction(function() use($db,$s){$db->run('DELETE FROM user_preferences WHERE user_id=?',[$s['bob']]);
if($s['prefs']) $db->run('INSERT INTO user_preferences(user_id,prefs,updated_at) VALUES(?,?,?)',[$s['bob'],$s['prefs']['prefs'],$s['prefs']['updated_at']]);});`);
  }
});

test('the source mirror stays empty while there is nothing to highlight', async ({ page }) => {
  const { form, input, menu } = await reply(page, false);
  const mirror = form.locator('.composer-input-mirror');
  await mirror.evaluate(el => {
    (window as any).__mirrorWrites = 0;
    new MutationObserver(records => { (window as any).__mirrorWrites += records.length; }).observe(el, { childList: true, subtree: true, characterData: true });
  });
  // Its text is transparent and only positions highlights, so ordinary typing
  // (unknown handles included) never copies the draft or re-runs page styles.
  await input.pressSequentially('Plain prose with an @nobodyhere handle and more words', { delay: 5 });
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
  expect(await page.evaluate(() => (window as any).__mirrorWrites)).toBe(0);
  await expect(mirror).toBeEmpty();
  await input.fill('@');
  await expect(menu).toBeVisible();
  await input.press('Escape');
  await input.fill('Hello @alice');
  await expect(mirror.locator('mark')).toHaveText('@alice');
  await input.fill('Hello again');
  await expect(mirror).toBeEmpty();
});

test('a shrinking window cannot let the source mirror widen the page', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'resizes a desktop window');
  const { form, input, menu } = await reply(page, false);
  await input.fill('@');
  await expect(menu).toBeVisible();
  await input.press('Escape');
  await input.fill('Hello @alice, this draft keeps its highlight while the window narrows.');
  const mirror = form.locator('.composer-input-mirror');
  await expect(mirror.locator('mark')).toHaveText('@alice');
  await page.setViewportSize({ width: 390, height: 844 });
  // Read before the mirror's next frame: its stale width is clamped to the wrap.
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await expect.poll(() => mirror.evaluate(el => Math.abs(el.getBoundingClientRect().width
    - el.parentElement!.querySelector('textarea')!.getBoundingClientRect().width) < 0.02)).toBe(true);
});
