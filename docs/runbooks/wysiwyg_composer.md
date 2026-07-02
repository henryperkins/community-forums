# Runbook - WYSIWYG Composer (`wysiwyg_composer`)

`wysiwyg_composer` gates only the Milkdown editor layer. `rich_composer=false`
remains the broad kill switch and prevents all enhanced composer assets from
loading.

## Enable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["wysiwyg_composer"]=true; $r->set("features",$f);'
```

## Disable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["wysiwyg_composer"]=false; $r->set("features",$f);'
```

Existing posts and drafts remain Markdown and need no migration.

## Emergency Disable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["rich_composer"]=false; $r->set("features",$f);'
```

This disables `composer.js`, the suggestion picker, and the WYSIWYG bundle.
Server-rendered textarea posting remains available.

## Verify

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php tests/Integration/Core/AppComposerSuggestTest.php tests/Integration/Core/AppMentionLinkRenderTest.php
npm run check:wysiwyg
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts a11y.spec.ts -g "wysiwyg|composer"
```

Evidence covered by the browser gate:

- strict CSP asset load with no inline-script/style violations
- WYSIWYG new-topic submit, source-mode round trip, edit no-op preservation, preview parity, chips, internal URL paste, and mobile smoke
- textarea fallback for `wysiwyg_composer=false`
- axe scans for the enhanced toolbar, WYSIWYG surface, reference picker, and source-mode form

## Known Limits

Markdown remains canonical. Legacy Markdown may normalize after a user edits
through the rich surface, but a no-op edit must not rewrite the stored body.

The WYSIWYG layer is optional. Do not delete or hide the textarea in templates:
it is the submit source, source-mode editor, and no-JS fallback.
