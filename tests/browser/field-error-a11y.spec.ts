import { expect, test, type Page } from '@playwright/test';
import path from 'node:path';

/**
 * Browser evidence for the member-surface field-error work (DESIGN §13).
 *
 * PHPUnit already asserts the 422 markup contract
 * ({@link ../Integration/Core/AppMemberFieldErrorA11yTest.php}). What only a
 * real browser can show is the part that is not in the markup:
 *
 *  - autofocus actually lands on the errored control after a server re-render,
 *  - :user-invalid paints before any round-trip, and carries a non-colour
 *    indicator (until ADR 0033 the engraved inputs had border:0 and drew their
 *    edge as gradient layers under a clip-path, so this watched the wrong
 *    property; the frame is a real border now and the check reads all three),
 *  - the shell's scroll-padding and gutter reservations resolve to real values.
 */

const REPO_ROOT = path.resolve(__dirname, '..', '..');
const EVIDENCE_DIR = path.resolve(
  REPO_ROOT,
  process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/member-field-error-a11y',
);

async function login(page: Page): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="email"]', 'bob@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await Promise.all([
    page.waitForURL((url) => !url.pathname.endsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) await skip.click();
}

async function shot(page: Page, name: string, testInfo = test.info()): Promise<void> {
  // Preserve the errored field's focus, but keep sticky chrome at the page top.
  await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
  await page.screenshot({
    path: path.join(EVIDENCE_DIR, testInfo.project.name, `${name}.png`),
    fullPage: true,
    animations: 'disabled',
  });
}

test.describe('member field errors', () => {
  test('a 422 re-render focuses the first errored field and links it to its message', async ({ page }) => {
    await login(page);
    await page.goto('/settings/account');

    // ftp:// satisfies the input's own type=url constraint, so this reaches the
    // server and comes back as a real 422 — the path a member actually takes.
    await page.fill('input[name="website"]', 'ftp://example.com');
    // A second, later error: the signature rule (max 3 lines) is server-only.
    await page.fill('textarea[name="signature"]', 'one\ntwo\nthree\nfour');
    await page.locator('form[action="/settings/account"] button[type="submit"]').click();

    const website = page.locator('input[name="website"]');
    await expect(website).toHaveAttribute('aria-invalid', 'true');

    // aria-describedby must resolve to a message that is really on the page.
    const describedBy = await website.getAttribute('aria-describedby');
    expect(describedBy).toBeTruthy();
    const message = page.locator(`#${describedBy}`);
    await expect(message).toHaveClass(/field-error/);
    await expect(message).toHaveText('Enter a valid http(s) URL.');

    // The whole point of the autofocus: focus is ON the first problem.
    await expect(website).toBeFocused();

    // The later error is wired too, but did not steal the focus.
    const signature = page.locator('textarea[name="signature"]');
    await expect(signature).toHaveAttribute('aria-invalid', 'true');
    await expect(signature).not.toBeFocused();
    expect(await page.locator('[autofocus]').count()).toBe(1);

    // .field-cell keeps the errored control paired with its message in ONE grid
    // cell; a bare error sibling would claim a cell of its own and shunt the
    // next field sideways. Asserted structurally so it holds at either viewport
    // (.field-grid collapses to a single column on mobile).
    const pairedInOneCell = await website.evaluate((el) => {
      const cell = el.closest('.field-cell');
      return cell !== null
        && cell.parentElement?.classList.contains('field-grid') === true
        && cell.querySelector('.field-error') !== null;
    });
    expect(pairedInOneCell).toBe(true);

    await shot(page, 'settings-422-wired');
  });

  test(':user-invalid paints an engraved field before any round-trip', async ({ page }) => {
    await login(page);
    await page.goto('/settings/account');

    // Read the whole frame, not one property of it. Watching box-shadow alone
    // was right when this was written — the edge was a clip-path octagon and an
    // inset shadow was the one part of it the clip did not cut. The 2026-08-09
    // rewrite moved the edge to background layers and left :user-invalid
    // restating the identical --shadow-inset, so this assertion went red and
    // stayed red until ADR 0033. The frame is a real border now.
    const frame = (el: HTMLElement) => {
      const style = getComputedStyle(el);
      return `${style.borderColor}|${style.boxShadow}|${style.outlineColor}`;
    };
    const website = page.locator('input[name="website"]');
    const pristine = await website.evaluate(frame);

    // Real keystrokes, then blur: :user-invalid deliberately does not match
    // until the member has actually interacted with the control.
    await website.click();
    await website.pressSequentially('not-a-url');
    await page.locator('input[name="location"]').click();

    await expect(website).toHaveJSProperty('validity.valid', false);
    expect(await website.evaluate((el) => el.matches(':user-invalid'))).toBe(true);

    const invalid = await website.evaluate(frame);
    expect(invalid).not.toBe(pristine);

    // Never colour alone — the label carries a text indicator, which is also
    // the only signal a screen reader gets for this client-side state.
    const indicator = await website.evaluate((el) => {
      const label = el.closest('.field');
      const span = label?.querySelector('span');
      return span ? getComputedStyle(span, '::after').content : '';
    });
    expect(indicator).toContain('check this field');

    await shot(page, 'user-invalid-engraved');
  });

  test('the shell reserves scroll padding for the sticky topbar and a stable gutter', async ({ page }) => {
    await login(page);
    await page.goto('/inbox');

    // #post-N permalinks would otherwise land underneath the sticky topbar.
    const topbar = await page.evaluate(
      () => getComputedStyle(document.documentElement).getPropertyValue('--topbar-h').trim(),
    );
    const scrollPadding = await page.evaluate(
      () => getComputedStyle(document.documentElement).scrollPaddingTop,
    );
    expect(topbar).toBe('62px');
    expect(scrollPadding).toBe('74px');

    const list = page.locator('.inbox-list');
    if (await list.count()) {
      expect(await list.first().evaluate((el) => getComputedStyle(el).scrollbarGutter)).toBe('stable');
      // dvh resolves; the shell must never make the document scroll sideways.
      const height = await list.first().evaluate((el) => el.getBoundingClientRect().height);
      expect(height).toBeGreaterThan(0);
    }
    const overflows = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
    );
    expect(overflows).toBe(false);

    await shot(page, 'inbox-shell-scrolling');
  });
});
