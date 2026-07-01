**Monogram / StarButton / Reaction** — the identity & appreciation set.

**Monogram** — the brand avatar. Colour is hashed from `username`, so a member is always the same colour.
```jsx
<Monogram name="Erestor" username="erestor" />
<Monogram name="Erestor" username="erestor" size="lg" gilt presence="online" />
```
Use `gilt` for "precious" avatars (OP, accepted answer, profile, leaderboard top-3). Sizes sm/md/lg/xl.

**StarButton** — star a topic (personal bookmark): `<StarButton active />`. Gold when active.

**Reaction** — appreciation chip reading "✦ Name · count":
```jsx
<Reaction name="Commend" count="31" active />
<Reaction name="Kindled" count="12" icon={<i data-lucide="flame" />} />
```
The Imladris reaction set is **Commend** (gold star, default), **Kindled** (flame), **Seconded** (check), **Illuminating** (sparkle). The sum of Commends a member earns is their **Regard** (reputation).
