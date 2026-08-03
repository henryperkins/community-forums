# Prototype Instructions

Run the local server yourself and open the preview in the browser available to this environment. Do not give the user server-start instructions when you can run it.

Before making substantial visual changes, use the Product Design plugin's `get-context` skill when the visual source is unclear or no longer matches the current goal. When the user gives durable prototype-specific design feedback, preferences, or decisions, record them in `AGENTS.md`.

When implementing from a selected generated mock, treat that image as the source of truth for layout, component anatomy, density, spacing, color, typography, visible content, and hierarchy.

Build app UI in `src/`. Keep `.openai/hosting.json`, `worker/index.js`, `scripts/prepare-sites-build.mjs`, and `tests/sites-worker.test.mjs` intact so the same local prototype can be handed to Sites. Before a Sites handoff, run `npm run build` and `npm run test:sites`; the build must leave `dist/client/index.html`, `dist/server/index.js`, and `dist/.openai/hosting.json`.

## Approved prototype decisions

- The Forum Index is a calm directory of readable boards only. Do not add the Imladris Reading Rooms right-side board preview or recent-topic pane; that would invent behavior and make `/` resemble `/inbox`.
- `/c/{slug}` is one board's topic list, ordered by latest post with pinned topics first. It has no Active, Newest, Unanswered, or Most replies controls and no reading pane.
- The canonical thread is the focused reading and reply surface.
- Keep Forum inbox labelled as the member's personal queue and Messages labelled as private conversation in shared navigation.
- `/c/{slug}` uses the selected Direction A board-identity band: evergreen `#2E4A3A`, parchment `#FAF6EC` text, and a `3px` mallorn-gold `#C29A44` bottom rule beneath the breadcrumb. This treatment is board-only; canonical thread headers remain parchment.
