# Runbook: operator branding

`branding` is default-on and exposes `/admin/branding`. Admins can change the
community name, primary/accent colours, base/light/dark logos, favicon, the
signed-out visitor's default mode, and the Classic/Retro preset. Signed-in
members retain their personal appearance preferences.
Suspended or banned admins can read the form, but cannot save or reset branding.

## Preview and save

The sample shows the selected visitor mode and preset without changing the
operator's account appearance. JavaScript also previews unsaved colours and
logo files. Without JavaScript, the form still saves normally and the sample
uses saved colours/logos. System follows the device's colour preference but
uses the base logo; explicit Light/Dark uses that variant with a base fallback.
Selecting a logo's removal control previews the resulting fallback immediately.
Each saved asset has its own thumbnail. Native file inputs keep selected
filenames visible without JavaScript. With JavaScript, every selected logo and
favicon has a local thumbnail and pending filename, even when that variant is
not used by the main sample. **Clear selected …** cancels the local selection
and restores the saved thumbnail; it does not remove a saved asset.

Package themes supply their palette ahead of the stored preset. Operator colours
still override package action colours. A session package preview remains private;
saving branding changes the public settings, so contrast warnings always use the
globally active package rather than only the operator's preview.

Colours must be six-digit hex values or blank to use the default. Before saving,
the server checks link/indicator contrast against the normal light and dark
content surfaces and chooses button ink. Warnings preserve the form and do not
write settings. Choose new colours and submit for another review, or explicitly
acknowledge the displayed risks and save. The acknowledgement applies only to
the reviewed candidate and is recorded as `branding_contrast_override` together
with the settings change. This check does not certify every specialized surface.

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
