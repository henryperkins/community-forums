/* RetroBoards — the inbox list pane (middle column). */
(function () {
  const Plus = { d: 'M12 5v14M5 12h14' };

  function Inbox({ board, threads, density, onDensity, filter, onFilter, sort, onSort, activeId, onOpen, user, onNewTopic, hiddenOnMobile }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { ThreadRow, Tabs, Button } = DS;
    const PlusIcon = <svg className="btn-icon" viewBox="0 0 24 24" aria-hidden="true"><path d={Plus.d} /></svg>;
    const RB = window.RB;
    let eyebrow = 'For you', heading = 'The Council Inbox', desc = null;
    if (board) {
      const b = RB.categories.flatMap((c) => c.boards).find((x) => x.slug === board);
      eyebrow = '#' + board;
      heading = b ? b.name : board;
      desc = b ? b.desc : null;
    }
    return (
      <div className={'inbox-list' + (hiddenOnMobile ? ' is-hidden' : '')}>
        <div className="inbox-list-head">
          <span className="eyebrow">{eyebrow}</span>
          <div className="inbox-list-head-row">
            <h1 className="thread-title-display" style={{ fontFamily: 'var(--font-display)', fontWeight: 500, fontSize: '1.85rem', margin: 0, color: 'var(--text-strong)' }}>{board ? <span><span className="hash">#</span>{heading}</span> : heading}</h1>
            {user ? <Button icon={PlusIcon} onClick={onNewTopic}>New topic</Button> : null}
          </div>
          {desc ? <p className="muted" style={{ margin: '4px 0 0', fontSize: '.95rem' }}>{desc}</p> : null}
        </div>

        <div className="inbox-toolbar">
          <Tabs variant="segment" items={['Hall', 'Watch']} value={density} onChange={onDensity} />
        </div>
        <div className="inbox-sort">
          <Tabs variant="underline" items={['Active', 'Newest', 'Unanswered']} value={sort} onChange={onSort} />
          <Tabs variant="pill" items={['All', 'Unread', 'Starred', 'Mine']} value={filter} onChange={onFilter} />
        </div>

        {threads.length ? (
          <ul className={'thread-list' + (density === 'Watch' ? ' is-compact' : '')}>
            {threads.map((t) => (
              <ThreadRow
                key={t.id}
                title={t.title}
                author={RB.users[t.author].name}
                authorSeed={t.author}
                authorTier={RB.users[t.author].tier}
                authorRep={RB.fmt(RB.users[t.author].rep)}
                presence={RB.users[t.author].presence}
                giltAuthor={RB.users[t.author].rep >= 3000}
                status={t.status}
                pinned={t.pinned}
                replies={t.replies}
                time={t.time}
                commends={t.commends}
                starred={t.starred}
                unread={t.unread}
                snippet={t.snippet}
                showBoard={!board}
                board={t.board}
                boardName={t.board}
                active={t.id === activeId}
                onClick={(e) => { e.preventDefault(); onOpen(t.id); }}
                style={{ cursor: 'pointer' }}
              />
            ))}
          </ul>
        ) : (
          <div className="inbox-empty" style={{ textAlign: 'center', padding: '56px 16px', color: 'var(--text-muted)' }}>
            <p style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--text-strong)', margin: '0 0 4px' }}>Nothing here yet</p>
            <p style={{ margin: 0 }}>No topics match this filter.</p>
          </div>
        )}
      </div>
    );
  }

  window.RBInbox = Inbox;
})();
