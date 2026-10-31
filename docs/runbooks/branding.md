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
favicon, guest mode, preset, and custom CSS; the community name remains. Invalid
image uploads keep the previous asset and display a partial-success warning.
The upload pipeline accepts JPEG, PNG, GIF, and WebP. Files must be reselected
after any validation response because browsers cannot restore file inputs.

Theme safe mode suppresses package themes, not ordinary branding. Use the
branding reset for an unwanted operator palette. See `package_themes.md` for
safe-mode rollback. Custom CSS remains unavailable through the Features console
until its existing recovery blocker is resolved. A full token editor remains
outside this change.
