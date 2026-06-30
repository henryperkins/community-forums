**ThreadRow / Post / Composer / JoinBar / Tabs / ParticipantStack** — the forum surface set. These compose the Council Inbox, the conversation, and the composer.

```jsx
// Inbox list — the author is a prominent byline (gilt avatar, name, tier, regard)
<ul className="thread-list">
  <ThreadRow title="Evaluations as ritual, not gate" author="Galadriel" authorSeed="galadriel"
    authorTier="Loremaster" authorRep="5.1k" presence="online" giltAuthor status="solved"
    replies={23} time="2h" commends={31} starred
    snippet="We keep treating the eval suite as a turnstile. What if it were a rite the whole council…" />
  <ThreadRow title="Who changed what — and can you prove the rollback?" author="Erestor" authorSeed="erestor"
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
<Tabs variant="segment" items={['Hall','Watch']} value="Hall" onChange={…} />
<Tabs variant="pill" items={['All','Unread','Starred','Mine']} value="All" onChange={…} />
<Composer postingAs="Erestor" sendLabel="Reply" count="0 / 20000" />
<JoinBar />   {/* guest state */}
```

For the one-line compact density add `is-compact` to the `<ul className="thread-list">` — the byline folds into the meta line (small avatar + name) so the person stays present. The status word on a row also lights its left-rule (solved→leaf, needs_answer→amber, decision_made→green, pinned→gold).

**Identity decorations.** `authorTier` (`Member`/`Veteran`/`Loremaster`/`Legend`) renders a coloured tier pill; `authorRep` shows the gold ✦ regard (commends earned); `presence` adds the leaf/amber/grey dot; `giltAuthor` rings the avatar in gold. On `Post`, `handle` + `authorTitle` form the signature line (`@handle · Title`) and `rep` becomes the stacked regard plinth under the avatar. The reusable classes — `.tier`/`.tier-legend…`, `.regard`, `.regard-block` — are available to any custom byline.
