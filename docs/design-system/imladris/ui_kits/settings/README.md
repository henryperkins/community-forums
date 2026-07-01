# Settings UI kit — the account console

An interactive, faithful recreation of RetroBoards' **account settings**, rendered in the Imladris language. It composes the design-system primitives (`window.ImladrisDesignSystem_c3e027`) and is the natural showcase of the **lapidary forms register** (engraved scribe-panels, set-gem checks, choice cards, switches).

Open **`index.html`**. Product layout lives in `kit.css`; the primitives come from the system's `styles.css` + `_ds_bundle.js`.

## What it shows
A sticky **left-rail subnav** (faithful to the product's `.settings` grid at desktop) over 13 sections, grouped Account / Reading & writing / Council:
- **Profile** — avatar (gilt monogram + upload), identity fields, bio + signature, custom profile field-rows.
- **Security** — password change, and a working **2FA enrolment flow** (start → authenticator secret → verify → recovery codes → rotate/disable).
- **Privacy** — visibility + DM selects, jewel **gem-check** toggles.
- **Appearance** — theme & density **choice cards**, font size, reduced-motion switch, export/reset.
- **Reading / Composing** — reading selects + gem checks; composer switches.
- **Drafts · Notifications · Connections · Sessions · Blocks · Boards** — list surfaces (digest, subscriptions, OAuth providers, device sessions, blocked users, favorite/mute boards).
- **Account** — export, deactivate, and a **danger-zone** delete.

## Interactive (faked)
Rail switches sections; the 2FA flow advances through its states; board favorite/mute toggles flip; choice cards and switches respond. All state is in-memory React; seed data is in `data.js`.

## Files
`index.html` · `kit.css` (shell + lapidary section styles) · `data.js` (the member + their account data on `window.RBSettings`) · `SettingsSections.jsx` (the section panes) · `SettingsApp.jsx` (rail + routing).

## Fidelity
Recreates the existing `templates/account/*.php` sections — fields, labels, and copy match the product. The rail layout mirrors the real `.settings` desktop grid; the lapidary treatment is the design system's own form register applied to these ceremonial surfaces.
