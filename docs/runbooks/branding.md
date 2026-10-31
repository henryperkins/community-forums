# Runbook: operator branding

`branding` is default-on and exposes `/admin/branding`. Admins can change the
community name, primary/accent colours, base/light/dark logos, favicon, the
signed-out visitor's default mode, and the Classic/Retro preset. Signed-in
members retain their personal appearance preferences. Saves apply on the next
page load; already-open pages keep their current appearance until navigation
or reload. The dynamic brand stylesheet is versioned by settings, serving package
and resolver version, so settings do not require an asset rebuild.
Suspended or banned admins can read the form, but cannot save or reset branding.

## Preview and save

The sample shows the selected visitor mode and preset without changing the
operator's account appearance. It uses the public component classes for selected
header navigation, board selection, feed-menu selection, unread counts/dot,
content, an action button and a focusable field. The menu sample stays visible
while editing; its example links remain within the preview. JavaScript also previews unsaved colours and
logo files. Without JavaScript, the form still saves normally and the sample
uses saved colours/logos. System follows the device's colour preference but
uses the base logo; explicit Light/Dark uses that variant with a base fallback.
Selecting a logo's removal control previews the resulting fallback immediately.
On narrow screens, **View appearance preview** jumps from the appearance
controls to the sample; **Back to appearance controls** returns keyboard focus
to those fields. Both links work without JavaScript. The colour helpers show
the resolved theme defaults and explain that clearing an override restores
them. With System selected, the no-JavaScript helper lists both Light and Dark;
the enhanced helper follows the device preference and adds a colour swatch.
Each saved asset has its own thumbnail. Native file inputs keep selected
filenames visible without JavaScript. With JavaScript, every selected logo and
favicon has a local thumbnail and pending filename, even when that variant is
not used by the main sample. **Clear selected …** cancels the local selection
and restores the saved thumbnail; it does not remove a saved asset.

Package themes supply their palette ahead of the stored preset. Operator colours
still override package brand actions, selections and highlights. A session package preview remains private;
saving branding changes the public settings, so contrast warnings always use the
globally active package rather than only the operator's preview.

Colours must be six-digit hex values or blank to use the default. Before saving,
the server checks link/indicator contrast against the normal light and dark
content surfaces, plus navigation, menu, unread-count and hover/pressed text
pairs. It chooses readable filled-action ink and derives soft backgrounds and
contrasting text from the resolved theme. Hover/press darkening is limited when
necessary to retain readable labels. Warnings preserve the form and do not
write settings. Choose new colours and submit for another review, or explicitly
acknowledge the displayed risks and save. The acknowledgement applies only to
the reviewed candidate and is recorded as `branding_contrast_override` together
with the settings change. These checks cover the declared brand roles, not arbitrary package styling or
custom CSS. A chosen foreground can still fail on content surfaces in one mode;
its warning and explicit override remain necessary.

## Colour roles and precedence

- Primary: actions, links, keyboard focus, selected header/rail/menu states and
  their soft backgrounds, hover and pressed fills. Text on selected/fill states
  is derived to meet 4.5:1; exact chosen foregrounds retain contrast review.
- Accent: unread dots/counts, selection rules, highlighted reactions and buttons,
  board-label ink and other existing semantic gold highlights. Soft fills and
  their ink are derived; primitive decorative gold scales are not overwritten.
- Success, warning, decisions, moderation roles, presence and the constant
  twilight stage retain separate semantic families. Decision/moderator and
  draft-conflict surfaces no longer borrow mutable brand washes.
- Clearing either field restores that role from the active package or selected
  built-in preset. Package safe mode suppresses the package, then the stored
  Classic/Retro base resumes. Operator colours continue to overlay that base.
  Retro dark uses its dark ink on amber menu selections when colours are unset;
  System follows the same pairing on a dark device.
- The preview follows the package visible in the admin session. Public save
  review always uses the globally active package; a private preview does not
  become public through a branding save.

## Recovery and limitations

Each saved logo variant and favicon has its own removal checkbox. Select it and
save branding to clear only that asset; the other submitted settings and assets
are retained. Removal choices survive validation errors and take effect only
after a valid save. Choose either removal or a replacement file for each asset,
not both. Removed paths fall back to the base logo or built-in chrome, and the
change is audited; the stored media file is retained, as with a full reset.

Reset requires typing `RESET`. It clears stored colours, all logo variants,
favicon, guest mode, preset, and custom CSS; the community name and package-theme
state remain. The form links to package safe mode when that subsystem is on.
Reset has its own form: Enter in its confirmation field performs reset without
saving pending branding edits or uploading selected files. Native confirmation
validation keeps an ordinary invalid attempt on the page with its pending edits;
the server independently enforces the same confirmation. Cleared or invalid
saved mode/preset values render as System/Classic, including without JavaScript.

The form lists configured image formats, dimensions and effective file/request
limits. Defaults are JPEG, PNG, GIF and WebP, up to 5 MiB and 4096 × 4096 pixels
per image; the deployed PHP transport allows 8 MiB per file and 10 MiB per
request so the application can report rejected files. An operator's lower PHP
cap reduces the displayed effective file limit. The request limit includes all
files and the remaining multipart form data; leave room below it.

PHP size/intake errors, interrupted uploads and storage-intake failures return
the form at 422 with an error beside the affected file. No settings, other
assets or pending removals are saved; the typed draft and removal choices are
retained. Files must be reselected after any validation response because browsers
cannot restore file inputs. A rejected image that reached the content-processing
pipeline retains the existing partial-success behavior: other branding updates
save, the previous rejected asset stays, and a visible warning names the failure.

If PHP discards the entire oversized POST, its values and security token never
reach the application. The existing CSRF failure path reports a specific 413
without authorizing a write. Nothing is saved; the server cannot recover the
unreceived draft. Return to Branding and choose smaller or fewer images.

Theme safe mode suppresses package themes, not ordinary branding. Use the
branding reset for an unwanted operator palette. See `package_themes.md` for
safe-mode rollback. Custom CSS remains unavailable through the Features console
until its existing recovery blocker is resolved. A full token editor remains
outside this change.
