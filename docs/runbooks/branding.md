# Runbook: operator branding

`branding` is default-on and exposes `/admin/branding`. Admins can change the
community name, community text (tagline, description, directory welcome),
primary/accent colours (shared, and optionally per light/dark mode),
base/light/dark logos, favicon, the signed-out
visitor's default mode, the style preset (Classic, Retro, Ledger or Vellum)
with optional sidebar, type and corner overrides, and the default density. Signed-in members retain
their personal appearance preferences. Every setting's owner, default, fallback,
surfaces and reset behaviour is listed in ADR 0054. Saves apply on the next
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
uses saved colours/logos. Each mode uses its own logo variant with the base
logo as fallback; System follows the device's colour preference for both the
colours and the logo.
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

## Identity on every entry point

The logo resolves once and renders on the member topbar, the console bar and
every sign-in screen: log in, registration (including the invitation landing),
forgot and reset password, email verification and MFA. On those screens it sits
on a raised plate in the page's raised surface, so a logo drawn for the light
topbar stays legible on the constant twilight stage. Without any logo, or with
branding switched off, each surface keeps the built-in house mark and name.

- Light mode shows the light variant, else the base logo, else the house mark.
- Dark mode shows the dark variant, else the base logo, else the house mark.
- System renders both choices and the device's colour preference picks one,
  without JavaScript: a `<picture>` when both modes have images, or the image
  in one mode and the house mark in the other when only one mode has an image.
  The first paint is already correct, and changing the device setting switches
  the logo immediately.

Community text is plain text; leave a field blank to use its default.

| Field | Limit | Default | Where it appears |
|---|---|---|---|
| Tagline | 80 | None | Under the identity on sign-in screens; above the directory heading |
| Community description | 300 | "{name} — a community forum." | Search and link-preview description for pages without their own |
| Welcome heading | 80 | "Every board in the valley" | Board directory heading |
| Welcome introduction | 600 | "Browse the boards the council keeps and pick one to see its topics." | Board directory introduction; each line is a paragraph, followed by the inbox pointer |

Whitespace is tidied on save. Over-limit text, control characters and
bidirectional-override characters are refused: the form returns with the whole
draft and an error beside the field, and nothing is saved. Text is always shown
escaped, never as HTML.

Members choose Light, Dark or System under Settings → Appearance. Each choice
shows a thumbnail painted from that mode's resolved palette — the active package
or preset, then operator colours — and names the base (package name, Retro or
Classic). The member's mode, density, text size and motion preferences remain
theirs.

## Colour roles and precedence

Each mode resolves each role independently: **mode-specific colour → shared
colour → active package → built-in preset**. The shared Primary and Accent
fields apply to both modes. *Adjust colours for light and dark modes* opens four
optional fields — light primary, light accent, dark primary, dark accent — that
override the shared colour in that mode only; blank inherits it. Use them when
one colour cannot serve both: a deep brand green that reads well on parchment
usually needs a lighter tone on twilight surfaces.

- **Review.** Each mode is checked with its own colours on its own surfaces,
  and a warning names the field to change (for example *Dark mode primary* when
  that field has a value, otherwise *Primary colour*). The acknowledgement
  covers exactly the reviewed per-mode colours; the audit entry lists them.
- **Suggestions.** When a mode's colour draws a warning there, the field shows
  the nearest tone of it that passes that mode's review. **Use suggestion**
  fills the field (with JavaScript); without JavaScript, type the value shown.
  Suggestions are never applied automatically.
- **State strips.** Under the sample, *Light* and *Dark* strips show action,
  hover, pressed, focus, selected, unread and link states with each mode's
  resolved colours. Without JavaScript they show the saved colours (your
  draft's while you preview it); with it they follow the fields as you type.
- **Reset.** *Reset colours…* clears the shared and the per-mode fields
  together. History and restore include the per-mode colours.

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

## Style presets and density

A **style preset** sets four parts together, for both light and dark mode:

| Preset | Surfaces | Sidebar | Headings | Corners |
|---|---|---|---|---|
| Classic | Parchment / twilight | Sunken | Cormorant Garamond | Standard (4–12px) |
| Retro | Amber / ember | Sunken | Cormorant Garamond | Standard |
| Ledger | Cool mist / slate | Flush with the page | EB Garamond | Crisp (2–6px) |
| Vellum | Bright vellum / warm charcoal | Raised panel | Marcellus | Soft (6–12px) |

Text is always EB Garamond, labels Marcellus and data JetBrains Mono — all
self-hosted, so nothing is fetched from a font service.

- **Overrides.** *Sidebar*, *Headings and text* and *Corners* default to
  *Preset*; choosing a value replaces that part only. Colours entered above
  still override the preset's action and accent roles.
- **Packages.** An active package theme owns the base: the preset's surfaces,
  sidebar, type and corners are suspended and only parts you override apply
  over the package. Theme safe mode (or deactivating the package) brings the
  preset back in full.
- **Default density.** Applies to signed-out visitors and to members who have
  never saved a density. A member's own choice always wins, and saving the
  appearance page records the density it shows as their choice.
- **Where it shows.** Every member and visitor page, including sign-in screens.
  The branding pages and history keep the built-in style for their own
  controls; the sample beside the form shows the style you are editing, and a
  draft preview shows a draft's style across the community.
- **Reset.** *Reset appearance defaults…* returns the preset to Classic, clears
  the three overrides and sets the density default back to Comfortable.

## Drafts and whole-site preview

**Save draft** stores the whole form as your private draft; **Publish branding**
is the only action that changes what visitors see. Each administrator has at
most one draft, and the branding form shows it (with an *Unpublished draft*
panel) until it is published or discarded. Drafts survive signing out.

- **Private.** Nobody else sees a draft. Images uploaded into a draft are
  private: other people get 404 for them until the draft is published.
- **Preview.** *Preview the community with this draft* shows the draft on every
  page you open in this session, under a dark preview bar: the directory,
  boards, your appearance settings and, through the bar's *Sign-in page* link,
  the sign-in screen. *Exit preview* returns to the published branding. Your
  own light/dark preference still applies; the draft's visitor mode only
  affects signed-out visitors once published.
- **Publish.** Open *Review and publish* (or the branding page) and choose
  **Publish branding**. Contrast warnings need the usual acknowledgement. If
  someone published after your draft was started, the page says so and
  publishing asks you to review and confirm replacing their change.
- **Discard.** *Discard draft…* asks for confirmation, deletes the draft and its
  private images, and leaves the published branding and history untouched.

## History, restore and conflicts

Every published change to the branding document is a revision under
**Appearance → History** (`/admin/branding/history`): branding saves, the
community name in Settings, restores and resets. The first recorded change also
keeps the configuration it replaced. Each revision lists who made it, when and
which settings changed; logos are kept with it.

- **Review and restore.** A revision's page compares it with the live branding,
  with logo thumbnails and colour swatches. Restoring publishes it again as a
  new revision, so a restore is undone the same way. If an image a revision
  used no longer exists, the page names it and restoring uses its fallback.
  Colours that draw contrast warnings against the *current* active theme need
  the same audited acknowledgement as a save.
- **Scope.** A restore replaces the community name and text, logos and favicon,
  colours, visitor mode, preset and stored custom CSS. It never changes package
  activation, theme safe mode, feature flags or members' own appearance.
- **Conflicts.** The branding form remembers the revision it was opened at. If
  another administrator published since, saving returns the form unchanged
  with a list of the newer revisions and saves nothing. Review them in History,
  or tick **Publish this form's branding and replace those changes** and save
  again; the replaced revision stays in History. A page opened before
  versioning existed is treated the same way.

## Recovery and limitations

**Undo and reset** under the form offers three independent resets — colours,
logos and favicon, and appearance defaults (visitor mode and preset). Each opens
a confirmation step that says what it clears, works without JavaScript, leaves
every other group untouched, and is recorded as a revision that History can
restore. The branding pages themselves never apply the saved colours, so these
controls stay readable even when a saved palette is hard to read; package themes
have their own recovery in theme safe mode.


Each saved logo variant and favicon has its own removal checkbox. Select it and
save branding to clear only that asset; the other submitted settings and assets
are retained. Removal choices survive validation errors and take effect only
after a valid save. Choose either removal or a replacement file for each asset,
not both. Removed paths fall back to the base logo or built-in chrome, and the
change is audited; the stored media file is retained, as with a full reset.

The full reset requires typing `RESET`. It clears stored colours, all logo
variants, favicon, guest mode, preset, and custom CSS; the community name,
community text and package-theme state remain. It is recorded as a revision, so
History can restore the configuration it replaced. Clear an individual text field and save to
restore its default. The form links to package safe mode when that subsystem is on.
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
