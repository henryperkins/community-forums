# Member-surface field errors — browser evidence

Status: complete for the member/auth/composer field-error pass.

Captured 2026-08-09 against the real PHP application and a freshly seeded browser
database (`retroboards_e2e`), with `prepare.sh` re-seeding exactly as `npm run
evidence` does. Spec: `tests/browser/field-error-a11y.spec.ts` (desktop 1280×800
and mobile 390×844).

## What this covers

The admin console got accessible field errors with the round-2 audit (ADR 0023,
`tests/Integration/Admin/AppFieldErrorA11yTest.php`). The helpers it introduced —
`field_error()` / `field_attrs()` in `src/Support/helpers.php` — were never applied
to the member surface, which kept hand-rolled `<span class="field-error">` markup
with no programmatic link between a control and its message. This pass propagates
them to the auth, account, setup, composer, and moderation templates.

PHPUnit owns the markup contract
(`tests/Integration/Core/AppMemberFieldErrorA11yTest.php`, 6 tests). These captures
cover the three things the markup alone cannot prove.

## Captures

### `settings-422-wired.png`

`/settings/account` submitted with **two** server-only errors: `ftp://example.com`
in Website (passes the input's own `type=url` constraint, fails the server's
`^https?://`) and a four-line Signature.

- The Website input carries `aria-invalid="true"` and an `aria-describedby` that
  resolves to a rendered `.field-error` reading "Enter a valid http(s) URL."
- Focus is **on** the Website input after the re-render — the first error, not the
  first field. Signature is wired too but did not take focus; the document holds
  exactly one `autofocus`.
- `.field-cell` keeps the errored input and its message in one `.field-grid` cell.
  A bare error sibling would have claimed a grid cell of its own and shunted the
  next field sideways.

### `user-invalid-engraved.png`

`:user-invalid` after real keystrokes and a blur — no round-trip involved.

- `.input-engraved` sets `border: 0` and draws its edge with an inset `box-shadow`
  under a `clip-path`, so the `border-color` rule the guides suggest would have been
  **invisible** on every auth and account field. The capture shows the restated
  shadow, asserted against the pristine value.
- The label reads "Website — check this field": colour is never the only indicator,
  and with no JS in this path that generated text is also the only signal a screen
  reader gets for the client-side state.
- Location, beside it in the same grid row, is untouched.

### `inbox-shell-scrolling.png`

`/inbox` at both viewports.

- `scroll-padding-block-start` on the root resolves to `74px` (`--topbar-h` + 12).
  Without it a `#post-N` permalink scrolls its post to y=0, under the sticky topbar.
- `.inbox-list` reserves a stable scrollbar gutter, so the independently scrolling
  pane does not shift sideways once it grows past the fold.
- The document never scrolls horizontally at 390px.

## Regression runs

- `vendor/bin/phpunit` — 2609 tests, 18935 assertions, 0 failures (2 pre-existing skips).
- `playwright test composer-shell composer-expansion account-console` — 55 passed.
- `RB_BROWSER_DARK_SURFACES=1 playwright test a11y.spec.ts` — 34 passed (axe, no
  serious violations).

## Deliberate omissions

- **`enterkeyhint="send"` on the composer** was proposed and rejected.
  `composer.js` disables Enter-to-send on coarse pointers
  (`if (coarsePointer() || !prefs.enterToSend) return;`), which is precisely where
  `enterkeyhint` has any effect — the hint would promise a submit the code refuses.
  `enterkeyhint="search"` went on the dedicated search inputs instead, where Enter
  really does submit.
- **No `:user-valid` success colour.** Green borders on every satisfied field would
  compete with the error signal. One rule to add if that call is revisited.
- `config/imladris-runtime-baseline.json` `application_surface.sha256` was refreshed:
  this pass changes `templates/` and `public/assets/`, which is exactly what that
  tripwire watches. No design-owned class is overridden —
  `test_app_css_never_overrides_a_design_owned_console_class` still passes.
