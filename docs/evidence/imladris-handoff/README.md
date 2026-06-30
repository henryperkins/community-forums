# Imladris Engineering-Handoff — fidelity pass evidence

Browser evidence for the second Imladris fidelity pass: bringing the live,
server-rendered surfaces up to the *RetroBoards Engineering Handoff* figures and
the §10 token spec. Captured in Chromium against the real app (`php -S` over the
seeded `retroboards_e2e` database) at desktop (1280×800) and mobile (390×844).
Every surface is real server-rendered HTML + external `app.css`/`app.js` under the
strict CSP (`script-src 'self'; style-src 'self'`, no inline) — no canvas markup.

The matching server-side behaviour is exercised by
`tests/Integration/Core/AppImladrisFidelityTest.php` (DESIGN §13: behaviour is
tested, not just drawn).

| File | Surface | Shows |
|---|---|---|
| `02-thread-member.png` | Conversation (§5.1) | Participant avatar stack under the byline; the **one-line topic action bar** (Star · Notify · Save · Pin · Lock) replacing the old stacked raw links; the Marcellus, grouped composer toolbar |
| `07-thread-grouped-posts.png` | Conversation (§5.1) | Consecutive same-author replies **drop the repeated avatar + name** (the second note shows only its timestamp, body aligned under the first) |
| `08-thread-accepted-plate.png` | Accepted answer (§5.1) | The accepted reply rendered as a green plate with the **"Marked as the answer"** caption; the header gains the *Solved* chip and a *Clear accepted answer* control |
| `09-new-topic-modal.png` | New Topic (§5.2) | The `<details>` New-Topic composer upgraded (JS) into a centred **modal over a blurred scrim**, with the title field, grouped toolbar, and Create / Cancel |
| `10-composer-toolbar.png` | Composer (§5.2) | The Markdown toolbar close-up — sentence-case **Marcellus** labels grouped emphasis / block / insert by hairline separators |
| `11-profile-tabs-overview.png` | Profile (§5.4) | The twilight identity cover with reputation relabelled **"Regard"**, and the **Overview / Topics / Posts / Commends** activity tabs |
| `12-profile-posts-tab.png` | Profile (§5.4) | The Posts tab (`?tab=posts`) — a real, crawlable URL listing the member's posts |
| `13-mobile-thread.png` | Mobile thread (§6) | The conversation + action bar folded to one column at 390px |
| `14-mobile-new-topic-modal.png` | Mobile New Topic (§6) | The New-Topic modal as a full-width sheet on mobile |

The earlier captures (`01`, `03`–`06`) are from the first fidelity pass and remain
the evidence for the profile cover, guest join-bar, compact density, settings
cards, and leaderboard footnote.
