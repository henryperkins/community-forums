/* RetroBoards — the conversation reading pane (right column). */
(function () {
  const ic = (d) => <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ display: 'block' }}><path d={d} /></svg>;
  const ICONS = {
    flame: ic('M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z'),
    check: ic('M20 6L9 17l-5-5'),
    spark: ic('M9.94 15.5A2 2 0 0 0 8.5 14.06l-6.13-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.13a.5.5 0 0 1 .96 0L14.06 8.5A2 2 0 0 0 15.5 9.94l6.13 1.58a.5.5 0 0 1 0 .96L15.5 14.06a2 2 0 0 0-1.44 1.44l-1.58 6.13a.5.5 0 0 1-.96 0z'),
  };

  function Conversation({ thread, user, onBack, starred, onStar, replyValue, onReplyChange, onSend, isOpenMobile }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { EightPointStar, Post, Reaction, Composer, JoinBar, StarButton, ParticipantStack, Monogram } = DS;
    const RB = window.RB;
    if (!thread) {
      return (
        <div className={'inbox-reading' + (isOpenMobile ? ' is-open' : '')}>
          <div style={{ textAlign: 'center', padding: '80px 24px', maxWidth: '44ch', margin: '0 auto', color: 'var(--text-muted)' }}>
            <span style={{ color: 'var(--green-400)', opacity: .6, display: 'inline-block' }}><EightPointStar size={54} style={{ opacity: 1 }} /></span>
            <p style={{ fontFamily: 'var(--font-display)', fontSize: '1.5rem', color: 'var(--text-strong)', margin: '14px 0 6px' }}>Choose a topic to read</p>
            <p style={{ margin: 0 }}>The council's threads open here, beside the inbox.</p>
          </div>
        </div>
      );
    }
    const author = RB.users[thread.author];
    return (
      <div className={'inbox-reading' + (isOpenMobile ? ' is-open' : '')}>
        <div className="reading-wrap">
          <header className="thread-head">
            <span className="thread-head-star" aria-hidden="true"><EightPointStar size={130} variant="watermark" style={{ opacity: 1, width: 130, height: 130 }} /></span>
            <button className="breadcrumb" onClick={onBack}>
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
              Inbox / #{thread.board}
            </button>
            <h1>{thread.title}</h1>
            <div className="thread-head-meta">
              <div className="convo-byline">
                <Monogram name={author.name} username={author.username} size="lg" gilt presence={author.presence} />
                <div className="convo-byline-id">
                  <div className="convo-byline-name">
                    <a href="#" onClick={(e) => e.preventDefault()}>{author.name}</a>
                    <span className={'tier tier-' + author.tier.toLowerCase()}>{author.tier}</span>
                    <span className="regard">
                      <svg viewBox="0 0 100 100" width="11" height="11" aria-hidden="true"><path fill="currentColor" d="M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z" /></svg>
                      {RB.fmt(author.rep)}
                    </span>
                  </div>
                  <p className="convo-byline-sub"><span className="sign-title">{author.title}</span> · opened this topic · {thread.replies} replies</p>
                </div>
              </div>
              <div className="convo-head-actions">
                <div className="convo-participants">
                  <span className="convo-participants-label">In council</span>
                  <ParticipantStack members={thread.participants.map((u) => ({ name: RB.users[u].name, username: u }))} max={4} />
                </div>
                <StarButton active={starred} onClick={onStar} />
              </div>
            </div>
          </header>

          <div className="post-stream">
            {thread.posts.map((p, i) => {
              const pa = RB.users[p.author];
              const reactions = (p.reactions || []).map((r, j) => (
                <Reaction key={j} name={r.name} count={r.count} active={r.on} icon={r.icon ? ICONS[r.icon] : undefined} />
              ));
              return (
                <Post key={i} author={pa.name} authorSeed={p.author} authorHref="#"
                  authorTier={pa.tier} handle={pa.username} authorTitle={pa.title} presence={pa.presence}
                  time={p.time} rep={p.rep}
                  op={p.op} staff={p.staff} accepted={p.accepted}
                  reactions={reactions.length ? reactions : null}>
                  <p style={{ margin: 0 }}>{p.body}</p>
                </Post>
              );
            })}
          </div>

          {user ? (
            <Composer identity={user.name} submitLabel="Reply"
              value={replyValue} onChange={(e) => onReplyChange(e.target.value)}
              count={(replyValue ? replyValue.length : 0) + ' / 20000'}
              onSubmit={(e) => { e.preventDefault(); onSend(); }} />
          ) : (
            <JoinBar />
          )}
        </div>
      </div>
    );
  }

  window.RBConversation = Conversation;
})();
