/* RetroBoards — top contributors (leaderboard). */
(function () {
  const ROMAN = ['I', 'II', 'III'];

  function Leaderboard({ onOpenProfile }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { Monogram } = DS;
    const RB = window.RB;
    const rows = RB.leaderboard;
    const top = rows.slice(0, 3);
    const rest = rows.slice(3);
    return (
      <div className="screen-pad">
        <div className="leaderboard-screen">
          <span className="eyebrow">The council</span>
          <h1 style={{ marginTop: 4 }}>Top contributors</h1>
          <p className="muted" style={{ margin: '0 0 18px', maxWidth: '56ch' }}>Ranked by Regard — the sum of Commends a member's counsel has earned.</p>

          {top.map((r, i) => {
            const u = RB.users[r.username];
            return (
              <div className="lb-top" key={r.username}>
                <span className="lb-rank-roman">{ROMAN[i]}</span>
                <Monogram name={u.name} username={u.username} size="lg" gilt />
                <div style={{ minWidth: 0 }}>
                  <div className="lb-name" style={{ cursor: 'pointer' }} onClick={() => onOpenProfile(r.username)}>{u.name}</div>
                  <div className="lb-handle">@{u.username} · {u.title}</div>
                </div>
                <span className="lb-rep"><span className="star-marker">✦</span>{r.rep.toLocaleString()}</span>
              </div>
            );
          })}

          <ul className="leaderboard-list">
            {rest.map((r, i) => {
              const u = RB.users[r.username];
              return (
                <li className="leaderboard-row" key={r.username}>
                  <span className="lb-rank">{i + 4}</span>
                  <Monogram name={u.name} username={u.username} size="sm" />
                  <span className="lb-name" style={{ fontSize: '1rem', cursor: 'pointer' }} onClick={() => onOpenProfile(r.username)}>{u.name}</span>
                  <span className="lb-row-rep"><span className="star-marker">✦</span>{r.rep.toLocaleString()}</span>
                </li>
              );
            })}
          </ul>

          <p className="lb-note">Regard is earned, never assigned — remove a post or a commend and it adjusts itself. The leaderboard is a record of counsel kept, not a contest.</p>
        </div>
      </div>
    );
  }

  window.RBLeaderboard = Leaderboard;
})();
