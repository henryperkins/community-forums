**ThreadRow / Post / Composer / JoinBar / Tabs / ParticipantStack** — the forum surface set. These compose the Council Inbox, the conversation, and the composer.

```jsx
// Inbox list — the author is a prominent byline (gilt avatar, name, tier, regard)
<ul className="thread-list">
  <ThreadRow presentation="inbox" title="Evaluations as ritual, not gate" author="Galadriel" authorSeed="galadriel"
    authorTier="Loremaster" authorRep="5.1k" presence="online" giltAuthor status="solved"
    replies={23} time="2h" commends={31} starred
    snippet="We keep treating the eval suite as a turnstile. What if it were a rite the whole council…" />
  <ThreadRow presentation="inbox" title="Who changed what — and can you prove the rollback?" author="Erestor" authorSeed="erestor"
    authorTier="Legend" authorRep="3.9k" presence="online" giltAuthor
    replies={41} time="5h" commends={54} unread />
</ul>

// Conversation — decorated identity column + signature line
<Post author="Erestor" authorSeed="erestor" authorTier="Legend" handle="erestor"
  authorTitle="Loremaster of Imladris" presence="online" op rep="3.9k" time="2 days ago"
  reactions={<><Reaction name="Commend" count="31" active /><Reaction name="Seconded" count="8" icon={<i data-lucide="check" />} /></>}>
  <p>The diff is small; the audit trail must be whole.</p>
</Post>

// Tabs + composer / join-bar
<Tabs variant="segment" mode="toggle" aria-label="Density" items={['Hall','Watch']} value="Hall" onChange={…} />
<Tabs variant="pill" mode="toggle" aria-label="Scope" items={['All','Unread','Starred','Mine']} value="All" onChange={…} />
<Tabs variant="underline" mode="links" ruled aria-label="Profile activity" value="overview"
  items={[{ label: 'Overview', value: 'overview', href: '?tab=overview' }, …]}
  onChange={(v, e) => { e.preventDefault(); show(v); }} />
// mode — tabs: a tablist over panels (default) · links: page sections at real
//   URLs (aria-current) · toggle: a filter or an order (role="group", aria-pressed)
<Composer identity="Erestor" submitLabel="Reply" draftSaved />
<JoinBar />   {/* guest state */}
```

`presentation` takes production's three names: `inbox` (the personal queue — reason, snippet, and `onToggleStar` / `select` / `actions` slots), `board` (the canonical index), `default` (a topic list inside a page, such as a tag). The star is the commend glyph everywhere: a toggle in the queue, outlined until starred; a marker elsewhere. Never type ★ or ☆.

For the one-line compact density add `is-compact` to the `<ul className="thread-list">` — the byline folds into the meta line (small avatar + name) so the person stays present. The status word on a row also lights its left-rule (solved→leaf, needs_answer→amber, decision_made→green, pinned→gold).

**Identity decorations.** `authorTier` (`Member`/`Veteran`/`Loremaster`/`Legend`) renders a coloured tier pill; `authorRep` shows the gold ✦ regard (commends earned); `presence` adds the leaf/amber/grey dot; `giltAuthor` rings the avatar in gold. On `Post`, `handle` + `authorTitle` form the signature line (`@handle · Title`) and `rep` becomes the stacked regard plinth under the avatar. The reusable classes — `.tier`/`.tier-legend…`, `.regard`, `.regard-block` — are available to any custom byline.
