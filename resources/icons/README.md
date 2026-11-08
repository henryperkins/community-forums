# Icons

The forum's icons are Microsoft's Fluent UI System Icons (ADR 0060), drawn
inline by `templates/partials/icon.php`.

- `icons.json` maps each forum icon name to its Fluent icon (`fluent`, the
  `@fluentui/svg-icons` file slug), the artwork sizes it is drawn from, and its
  styles. The first style listed is the one drawn by default; `filled` is
  drawn for a selected state when listed.
- `fluent/` holds the pinned sources from `@fluentui/svg-icons` 1.1.341 and
  their MIT licence. Every file is referenced by `icons.json`.
- `bin/build-icons.php` validates the sources and writes
  `src/Support/IconSet.php`. Do not edit that file by hand.

## Adding or changing an icon

1. Find the Fluent icon by meaning on <https://fluenticons.co> and note its
   slug, for example `comment_add`.
2. Add or change the entry in `icons.json`. Use sizes 16 and 20 for an icon
   drawn at the standard icon size, so it follows the member's icon size.
3. Add each source as `fluent/<slug>_<size>_<style>.svg` from the pinned
   release, for example
   `https://cdn.jsdelivr.net/npm/@fluentui/svg-icons@1.1.341/icons/comment_add_16_regular.svg`.
4. Run `composer build:icons`, then `composer check:icons`.
5. Draw it with `$this->partial('partials/icon', ['name' => '…'])`.
   `tests/Unit/Support/IconsTest.php` fails if a template draws an unmapped
   name or the set carries an icon no template draws.
