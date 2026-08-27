---
name: imladris-design
description: Use this skill to generate well-branded interfaces and assets for RetroBoards / the Imladris design system — either for production or throwaway prototypes/mocks/etc. Contains essential design guidelines, colors, type, fonts, assets, and UI kit components for prototyping. Imladris is a Rivendell-register design language — parchment-and-evergreen surfaces, a single mallorn-gold accent, an eight-pointed elven star, all-serif type — for the RetroBoards "Community Inbox" forum.
user-invocable: true
---

Read the README.md file within this skill, and explore the other available files.

If creating visual artifacts (slides, mocks, throwaway prototypes, etc), copy assets out and create static HTML files for the user to view. If working on production code, you can copy assets and read the rules here to become an expert in designing with this brand.

If the user invokes this skill without any other guidance, ask them what they want to build or design, ask some questions, and act as an expert designer who outputs HTML artifacts _or_ production code, depending on the need.

## Where things are
- `README.md` — the full design guide: sources, content fundamentals (voice + the council lexicon), visual foundations, iconography, and the file index. **Read this first.**
- `styles.css` — the single entry point. Link it and you inherit all tokens, fonts, and base styles. It `@import`s `tokens/*.css` (colour, type, fonts, space) and `components.css`.
- `components/<group>/` — reusable React reference source (`.jsx` + `.d.ts` + `.prompt.md`). The stale `_ds_bundle.js` preview output is retired in this repository; compile the JSX in a consuming app or the upstream authoring environment. Read each `.prompt.md` for usage.
- `guidelines/*.card.html` — foundation specimens (colour, type, spacing, brand) you can open to see real values.
- `ui_kits/<product>/` — interactive recreations of the product, the best reference for how surfaces compose. `retroboards/` (Council Inbox, Profile, Leaderboard), `settings/` (the account console — and the showcase of the lapidary forms register), `auth/` (the login/register/reset/MFA/verify gate), `admin/` (the operator's console — dashboard, structure, users, email, webhooks, branding…).
- `assets/` — `elven-star.svg` (house mark), `commend-star.svg` (esteem mark), `brand/` mood imagery.

## The short version
- **Parchment** surfaces, **evergreen** brand, **one** mallorn-**gold** accent, **river**-blue for info, **twilight** for dark. Ink, never black.
- **All serif:** Cormorant Garamond (display), Marcellus (lapidary caps — labels/buttons/eyebrows), EB Garamond (body), JetBrains Mono (data). Self-hosted WOFF2 in `assets/fonts/` (OFL licenses alongside); no CDN.
- **Sentence case** copy; **you / the council** voice; the **lexicon** (counsel, commend, regard, marks of esteem). **No emoji in UI chrome** (authored posts + the composer’s emoji tools are supported features). Icons are **Lucide** + the two brand stars.
- Restrained radii, warm soft shadows, 1px hairlines, status-coloured left-rules, the gold **gilt** ring for precious avatars, faint star watermarks. Calm short motion; nothing bounces.
- Colour from **semantic tokens** (`--surface-raised`, `--brand`, `--accent-2`, `--on-done`) so the twilight register flips for free.

## To use the components in an HTML file

Link `styles.css` and write static markup with the documented class names (`.thread-row`, `.chip`, `.btn`, `.monogram`, …); see `components.css`. Imported React previews that reference `window.ImladrisDesignSystem_c3e027` are source handoffs, not executable previews in this mirror. Do not claim they are current unless the upstream compiler has rebuilt them from the checked-in JSX.
