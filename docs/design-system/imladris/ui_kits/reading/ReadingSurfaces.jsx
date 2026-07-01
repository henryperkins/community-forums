/* Reading-surfaces kit — Home, Feed, Search, Tags, and a single Tag.
   Faithful to templates/{home,feed,search}.php and tags/{index,show}.php. */
(function () {
  const RB = () => window.RB;
  const RD = () => window.RBReading;
  const nameOf = (u) => (RB().users[u] ? RB().users[u].name : u);

  // Thread row — a faithful recreation of partials/thread_row.php, reusing the
  // global .thread-row / .chip primitives. Used by Tag show.
  function ThreadRow({ t, showBoard }) {
    const { Monogram } = window.ImladrisDesignSystem_c3e027;
    const cls = ['thread-row'];
    if (t.unread) cls.push('thread-unread');
    if (t.pinned) cls.push('thread-pinned');
    if (t.status && t.status !== 'open') cls.push('thread-status-' + t.status);
    const statusChip = t.status === 'solved' ? <span className="chip chip-solved">Solved</span>
      : t.status === 'needs_answer' ? <span className="chip chip-needs">Needs answer</span>
      : t.status === 'decision_made' ? <span className="chip chip-decision_made">Decision made</span>
      : null;
    return (
      <li className={cls.join(' ')}>
        {t.unread ? <span className="unread-dot" title="Unread" /> : null}
        <Monogram name={nameOf(t.author)} username={t.author} />
        <div className="thread-row-main">
          {(t.pinned || statusChip) ? (
            <div className="thread-row-chips">
              {t.pinned ? <span className="chip chip-pinned">Pinned</span> : null}
              {statusChip}
            </div>
          ) : null}
          <a className="thread-title" href="#" onClick={(e) => e.preventDefault()}>{t.title}</a>
          <span className="thread-meta">
            {showBoard ? <><a className="thread-board" href="#" onClick={(e) => e.preventDefault()}><span className="hash">#</span>{t.board}</a> · </> : null}
            by {nameOf(t.author)} · {t.replies} {t.replies === 1 ? 'reply' : 'replies'} · {t.time}
          </span>
        </div>
        {t.starred ? <span className="thread-star" title="Starred">★</span> : null}
      </li>
    );
  }

  /* ── Home — board index ───────────────────────────────────────────────── */
  function Home({ onRoute }) {
    const cats = RB().categories;
    const stats = RD().boardStats;
    return (
      <div className="read-pad board-index">
        <h1 className="page-title">RetroBoards</h1>
        {cats.map((s) => (
          <section className="cat-block" key={s.name}>
            <h2 className="cat-title">{s.name}</h2>
            <ul className="board-list">
              {s.boards.map((b) => (
                <li className="board-row" key={b.slug}>
                  <a className="board-link" href="#" onClick={(e) => { e.preventDefault(); onRoute('tag', { slug: b.slug, board: true, name: b.name, desc: b.desc }); }}>
                    <span className="board-name"><span className="hash">#</span>{b.name}</span>
                    {b.desc ? <span className="board-desc">{b.desc}</span> : null}
                  </a>
                  <span className="board-stats">{b.count} threads · {stats[b.slug] != null ? stats[b.slug] : b.count * 6} posts</span>
                </li>
              ))}
            </ul>
          </section>
        ))}
      </div>
    );
  }

  /* ── Feed — Following / Latest ────────────────────────────────────────── */
  function Feed({ view, onView }) {
    const items = RD().feed;
    const latest = view === 'latest';
    const list = latest ? items.slice().sort((a, b) => a.threadId - b.threadId) : items;
    return (
      <div className="read-pad feed">
        <header className="board-header">
          <h1>{latest ? 'Latest' : 'Following'}</h1>
          <p className="muted">{latest ? 'Recent visible community activity.' : 'Recent activity from people, boards, and tags you follow.'}</p>
        </header>
        <nav className="inbox-tabs feed-tabs" aria-label="Feed views">
          <button className={'inbox-tab' + (!latest ? ' is-active' : '')} onClick={() => onView('following')}>Following</button>
          <button className={'inbox-tab' + (latest ? ' is-active' : '')} onClick={() => onView('latest')}>Latest</button>
        </nav>
        <ul className="feed-list">
          {list.map((it, i) => (
            <li className="feed-item" key={i}>
              <div className="feed-meta">
                <a className="post-author" href="#" onClick={(e) => e.preventDefault()}>{nameOf(it.author)}</a>
                <span className="muted">{it.isOp ? 'started a topic' : 'replied'}</span>
                <span className="post-time">{it.time}</span>
              </div>
              <a className="feed-thread" href="#" onClick={(e) => e.preventDefault()}>{it.threadTitle}</a>
              {' '}<span className="muted">in <span className="hash">#</span>{it.board}</span>
              <p className="feed-excerpt">{it.excerpt}</p>
            </li>
          ))}
        </ul>
        <nav className="pager">
          <button className="btn btn-small" type="button">Older →</button>
        </nav>
      </div>
    );
  }

  /* ── Search ───────────────────────────────────────────────────────────── */
  function Search({ query, onSearch }) {
    const [q, setQ] = React.useState(query || '');
    React.useEffect(() => { setQ(query || ''); }, [query]);
    const data = RD().search;
    const searched = (query || '').trim() !== '';
    const results = searched ? data.results : [];
    return (
      <div className="read-pad search-view">
        <header className="board-header">
          <h1>Search</h1>
          <form className="search-form" role="search" onSubmit={(e) => { e.preventDefault(); onSearch(q); }}>
            <input className="input" type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search threads and posts…" autoFocus />
            <button className="btn" type="submit">Search</button>
          </form>
        </header>
        {!searched ? (
          <p className="muted">Search thread titles and posts you can access.</p>
        ) : results.length === 0 ? (
          <p className="muted empty">No results for “{query}”.</p>
        ) : (
          <ul className="search-results">
            {results.map((r, i) => (
              <li className="search-result" key={i}>
                <a className="search-title" href={r.url} onClick={(e) => e.preventDefault()}>
                  {r.type === 'post' ? <span className="chip">post</span> : null}
                  {r.title}
                </a>
                <span className="search-board"><a href="#" onClick={(e) => e.preventDefault()}><span className="hash">#</span>{r.boardName}</a></span>
                {r.snippet ? <p className="search-snippet" dangerouslySetInnerHTML={{ __html: r.snippet }} /> : null}
              </li>
            ))}
          </ul>
        )}
      </div>
    );
  }

  /* ── Tags — directory ─────────────────────────────────────────────────── */
  function Tags({ onRoute }) {
    const tags = RD().tags;
    return (
      <div className="read-pad tag-view">
        <header className="board-header">
          <h1>Tags</h1>
          <p className="muted">Approved community topics you can follow for discovery.</p>
        </header>
        <ul className="tag-cloud">
          {tags.map((t) => (
            <li key={t.slug}>
              <a className="tag-card" href="#" onClick={(e) => { e.preventDefault(); onRoute('tag', { slug: t.slug, name: t.name, desc: t.desc }); }}>
                <span className="tag-name">{t.name} <span className="tag-count">· {t.threads.length}</span></span>
                <span className="tag-desc">{t.desc}</span>
              </a>
            </li>
          ))}
        </ul>
      </div>
    );
  }

  /* ── Tag — single (also serves a board listing from Home) ─────────────── */
  function TagShow({ ctx, onRoute }) {
    const [following, setFollowing] = React.useState(false);
    const isBoard = !!ctx.board;
    let threads;
    if (isBoard) {
      threads = RB().threads.filter((t) => t.board === ctx.slug);
    } else {
      const tag = RD().tags.find((t) => t.slug === ctx.slug);
      const ids = tag ? tag.threads : [];
      threads = ids.map((id) => RB().threads.find((t) => t.id === id)).filter(Boolean);
    }
    return (
      <div className="read-pad tag-view">
        <header className="board-header">
          <p className="breadcrumb"><a href="#" onClick={(e) => { e.preventDefault(); onRoute(isBoard ? 'home' : 'tags'); }}>{isBoard ? 'Home' : 'Tags'}</a></p>
          <h1><span className="hash" style={{ color: 'var(--gold-ink)' }}>#</span>{ctx.name || ctx.slug}</h1>
          {ctx.desc ? <p className="muted">{ctx.desc}</p> : null}
          {!isBoard ? (
            <div className="header-follow">
              <button className="linkbtn" type="button" onClick={() => setFollowing((v) => !v)}>{following ? 'Unfollow tag' : 'Follow tag'}</button>
              <span className="muted">Discovery feed only</span>
            </div>
          ) : null}
        </header>
        {threads.length === 0 ? (
          <p className="muted empty">{isBoard ? 'No topics in this board yet.' : 'No visible topics use this tag.'}</p>
        ) : (
          <ul className="thread-list">
            {threads.map((t) => <ThreadRow key={t.id} t={t} showBoard={!isBoard} />)}
          </ul>
        )}
      </div>
    );
  }

  window.RBReadingSurfaces = { Home, Feed, Search, Tags, TagShow, ThreadRow };
})();
