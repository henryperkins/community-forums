**PresenceList / PresenceRow** — who is at the council right now. The sidebar roster: a leaf dot per member, the live count beside the heading, a `+n more` tail.

```jsx
<PresenceList
  count={34}
  people={[
    { name: 'Elrond',    username: 'elrond',    where: '#counsel', gilt: true, staff: true },
    { name: 'Galadriel', username: 'galadriel', title: 'Loremaster' },
    { name: 'Erestor',   username: 'erestor',   presence: 'away', where: '#lore' },
  ]}
  footer={<a className="link-quiet" href="/members">See everyone</a>}
/>
```

- `presence` is `online` (leaf) · `away` (amber) · `offline` (grey). Colour never carries the meaning alone — the dot has a `title`/`aria-label` on the surfaces that use it.
- `where` beats `title` on the sub-line: a location (`#lore`) reads as live; a cosmetic rank reads as static. Pick one per surface and keep it consistent.
- `loading` renders three skeleton rows and a `·` count — presence arrives after first paint, so use it rather than an empty flash.
- `max={0}` renders everyone. For a full directory page, pass `layout="grid"` — the same rows, laid out wide in `.presence-grid` — or map `PresenceRow` yourself.
- Presence is opt-in per member (account privacy → "Show when I'm online"). Never imply the roster is everyone who is here.
