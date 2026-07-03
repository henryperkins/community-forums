# Custom Emoji Runbook

Custom emoji is default-on as of 2026-07-03 and remains operator-reversible with
`features.custom_emoji=false`.

## Surface

- Admin surface: `/admin`, section `Custom emoji`.
- Member surface: Markdown shortcodes render in posts outside `code` and `pre`.
- Reaction surface: only entries saved with `Allow as a reaction` appear in the
  reaction picker.

## Add Or Update An Emoji

1. Prepare a static PNG or WebP asset.
2. Use either a reviewed `/emoji/{file}.png|webp` path or a finalized `/media/{id}`
   asset path.
3. In `/admin`, enter the shortcode without colons, a readable name, the asset
   path, and the MIME type.
4. Check `Allow as a reaction` only for shortcodes that are appropriate for
   lightweight public reactions.
5. Save the entry and verify a test post renders `:shortcode:` as an image while
   inline code such as `` `:shortcode:` `` stays literal.

## Media Moderation

- Do not use externally hosted emoji assets.
- Review files before adding them to `/emoji/` or before referencing a finalized
  `/media/{id}` asset.
- Keep assets small, static, and non-animated. The service accepts only
  `image/png` and `image/webp`.
- Use names that describe the visible image because the rendered image keeps the
  shortcode as alt text and the name as its title.

## Disable A Shortcode

Use the `Disable` action in the `/admin` custom emoji table.

Effects:
- New Markdown renders the literal `:shortcode:` text instead of an image.
- Existing post bodies are not rewritten.
- The shortcode disappears from the reaction picker.
- Existing reaction rows remain as historical records.

Re-enable with the `Enable` action if the asset is cleared for use again.

## Rollback

Set:

```json
{"custom_emoji": false}
```

in the `features` setting.

Effects:
- `/admin/custom-emoji` create/enable/disable routes return 404.
- The admin custom emoji panel is hidden.
- Markdown rendering no longer replaces custom shortcodes.
- The custom shortcode reaction picker is unavailable.
- Existing `custom_emoji` and `reactions` rows are preserved.

Remove the override to restore the default-on posture.

## Evidence

- PHPUnit: `tests/Integration/Core/AppCustomEmojiGiphyTest.php`
- Default posture: `tests/Integration/Core/AppPhase4CarryoverFoundationTest.php`
- Browser evidence: `tests/browser/gate-a.spec.ts`
- A11y evidence: `tests/browser/a11y.spec.ts`
