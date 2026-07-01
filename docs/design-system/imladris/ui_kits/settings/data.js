/* Settings kit — seed state (the signed-in member + their account data). */
(function () {
  window.RBSettings = {
    user: { name: 'Erestor', username: 'erestor', email: 'erestor@imladris.council', tier: 'Legend', title: 'Loremaster of Imladris', rep: 3985 },
    sessions: [
      { id: 's1', ua: 'Firefox 128 · macOS', ip: '10.0.4.18', last: 'just now', current: true },
      { id: 's2', ua: 'Safari · iPhone', ip: '10.0.4.91', last: '3 hours ago', current: false },
      { id: 's3', ua: 'Chrome 126 · Windows', ip: '198.51.100.7', last: 'yesterday', current: false },
    ],
    providers: [
      { name: 'Google', linked: true, email: 'erestor@imladris.council' },
      { name: 'GitHub', linked: false, configured: true },
      { name: 'Apple', linked: false, configured: false },
    ],
    blocks: [
      { name: 'Saruman', username: 'saruman' },
    ],
    boards: [
      { cat: 'The Commons', items: [
        { name: 'announcements', fav: true, muted: false },
        { name: 'introductions', fav: false, muted: false },
        { name: 'the-valley', fav: false, muted: true },
      ] },
      { cat: 'Vilya · Expose', items: [
        { name: 'interpretability', fav: true, muted: false },
        { name: 'evaluations', fav: true, muted: false },
        { name: 'audit-trails', fav: false, muted: false },
      ] },
    ],
    subscriptions: [
      { label: 'Evaluations as ritual, not gate', kind: 'thread', freq: 'Watching', email: true },
      { label: '#audit-trails', kind: 'board', freq: 'Tracking', email: false },
    ],
    drafts: [
      { title: 'On the precedence of edits', board: 'audit-trails', when: '2 days ago' },
      { title: 'Untitled topic', board: 'the-valley', when: '1 week ago' },
    ],
    recoveryCodes: ['imla-3kf9-2a', 'imla-77qd-h1', 'imla-pb42-9c', 'imla-x8mn-4t', 'imla-5rty-0v', 'imla-9wlk-6e'],
  };
})();
