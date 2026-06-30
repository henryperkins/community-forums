# Auth UI kit — the gate

An interactive recreation of RetroBoards' **authentication** screens, framed in the ceremonial twilight register. It composes the design-system primitives (`window.ImladrisDesignSystem_c3e027`).

Open **`index.html`**. The auth stage lives in `kit.css`; primitives come from `styles.css` + `_ds_bundle.js`.

## What it shows
The product's six `templates/auth/*` views, as one state machine (a top-right switcher — a kit affordance, not part of the product — jumps between them):
- **Log in** — email + password, the OAuth row (Google / GitHub / Apple), forgot-password and register links.
- **Create your account** — username, display name, email, password + confirm.
- **Reset your password** — email entry, plus the "link sent" confirmation state.
- **Choose a new password** — new + confirm.
- **Two-factor verification** — authenticator / recovery code.
- **Confirm your email / Email verified** — the pending and success states.

The card itself is faithful to the product's `.auth-card`; it is framed with the wordmark above, a faint eight-point **star watermark** behind, and a colophon below — the same ceremonial twilight cover used for profiles. Fields use the lapidary engraved inputs.

## Files
`index.html` · `kit.css` (the twilight stage + card + OAuth + switcher) · `AuthApp.jsx` (the six views + the switcher).

## Fidelity
Field sets and copy match `templates/auth/*.php`. The twilight framing is presentation chrome for a login page (which carries site branding); the form content is the real thing.
