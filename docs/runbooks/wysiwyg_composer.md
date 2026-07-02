# Runbook - WYSIWYG Composer (`wysiwyg_composer`)

`wysiwyg_composer` gates only the Milkdown editor layer. **Default-ON as of
2026-07-02** (graduated out of deploy-dark; fully reversible via the
`features` override). `rich_composer=false` remains the broad kill switch and
prevents all enhanced composer assets from loading. Follows the same
conventions as `docs/runbooks/polls.md` and `docs/runbooks/topic_workflow.md`.

> **Golden rule:** for any editor logic defect, **disable the
> `wysiwyg_composer` flag first** (the Milkdown bundle stops loading and the
> composer falls back to the enhanced Markdown textarea; posting keeps
> working), then investigate. Disabling is non-destructive - posts, drafts,
> and uploads are untouched because the Markdown `<textarea>` is the only
> submit source.

## Roll back (disable)

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["wysiwyg_composer"]=false; $r->set("features",$f);'
```

Existing posts and drafts remain Markdown and need no migration; the enhanced
Markdown textarea composer keeps serving.

## Re-enable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); unset($f["wysiwyg_composer"]); $r->set("features",$f);'
```

Removing the override restores the default (ON). Setting the key to `true`
explicitly is equivalent.

## Emergency Disable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["rich_composer"]=false; $r->set("features",$f);'
```

This disables `composer.js`, the suggestion picker, and the WYSIWYG bundle.
Server-rendered textarea posting remains available.

## Verify

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php tests/Integration/Core/AppComposerSuggestTest.php tests/Integration/Core/AppMentionLinkRenderTest.php tests/Integration/Core/AppFeatureFlagTest.php
npm run check:wysiwyg
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts a11y.spec.ts -g "wysiwyg|composer"
```

Evidence covered by the browser gate:

- strict CSP asset load with no inline-script/style violations, exercised
  with **no features override** (proves the GA default mounts Milkdown)
- WYSIWYG new-topic submit, source-mode round trip, edit no-op preservation,
  preview parity, chips, internal URL paste, and mobile smoke
- textarea fallback for the `wysiwyg_composer=false` rollback
- axe scans for the enhanced toolbar, WYSIWYG surface, reference picker, and
  source-mode form

The gate-a screenshot suite intentionally pins `wysiwyg_composer=false` in
`tests/browser/seed.php`: those journeys capture the progressive-enhancement
textarea baseline (and drive `textarea.composer-input` directly), while this
spec owns the rich-surface evidence.

## Known Limits

Markdown remains canonical. Legacy Markdown may normalize after a user edits
through the rich surface, but a no-op edit must not rewrite the stored body.

Do not delete or hide the textarea in templates: it is the submit source,
source-mode editor, and no-JS fallback.
