/* Reading-surfaces kit — seed data for the public/member reading routes:
   home (board index), feed, search, tags, notifications, connections.
   Reuses the RetroBoards roster, boards, and threads (window.RB); this file
   adds only what those surfaces need. Shared via window.RBReading.
   Mirrors templates/{home,feed,search,notifications,compose}.php,
   templates/tags/{index,show}.php, templates/profile/connections.php. */
(function () {
  const RB = window.RB;

  // Post counts per board (threads come from RB.categories `count`). Home shows both.
  const boardStats = {
    announcements: 84, introductions: 213, 'the-valley': 612,
    interpretability: 388, evaluations: 547, 'capability-disclosure': 164, 'audit-trails': 331,
  };

  // Public tag directory (approved discovery topics). Each maps to thread ids in RB.threads.
  const tags = [
    { slug: 'evaluations',     name: 'evaluations',     desc: 'Rites, gates, and the verdicts they leave behind.', threads: [1, 5] },
    { slug: 'audit-trails',    name: 'audit-trails',    desc: 'Who changed what — and whether you can prove the rollback.', threads: [2] },
    { slug: 'interpretability', name: 'interpretability', desc: 'Reading the machine without reading a verdict into the map.', threads: [5] },
    { slug: 'disclosure',      name: 'disclosure',      desc: 'What we publish, and when.', threads: [3] },
    { slug: 'governance',      name: 'governance',      desc: 'How the council keeps counsel.', threads: [4] },
    { slug: 'rollback',        name: 'rollback',        desc: 'Drills, precedence, and undoing safely.', threads: [2] },
    { slug: 'first-posts',     name: 'first-posts',     desc: 'Newcomers finding their footing.', threads: [6] },
  ];

  // Following feed — recent activity from people you follow.
  const feed = [
    { author: 'galadriel', isOp: true,  time: '2h',  threadId: 1, threadSlug: 'evaluations-as-ritual', threadTitle: 'Evaluations as ritual, not gate', board: 'evaluations',
      excerpt: 'We keep treating the eval suite as a turnstile — pass and forget. I want to argue it should be a rite: something the whole council performs, reads, and remembers.' },
    { author: 'glorfindel', isOp: false, time: '5h', threadId: 2, threadSlug: 'who-changed-what', threadTitle: 'Who changed what — and can you prove the rollback?', board: 'audit-trails',
      excerpt: 'We ran the rollback drill on Tuesday. The surprise was not the revert — it was discovering two actors could edit the same setting with no record of precedence.' },
    { author: 'arwen', isOp: true, time: '6h', threadId: 5, threadSlug: 'reading-attention-as-a-map', threadTitle: 'Reading attention as a map, not a verdict', board: 'interpretability',
      excerpt: 'Attention tells you where the model looked, not what it concluded. I keep watching people read a verdict into a heatmap.' },
    { author: 'elrond', isOp: true, time: '1d', threadId: 3, threadSlug: 'on-exposing-capability', threadTitle: 'On exposing capability before we are asked', board: 'capability-disclosure',
      excerpt: 'A decision, recorded: we publish the capability notes the same day we brief the council — not after. Disclosure that trails the briefing is not disclosure.' },
    { author: 'lindir', isOp: true, time: '8h', threadId: 6, threadSlug: 'newly-arrived', threadTitle: 'Newly arrived — keeper of songs, learner of evals', board: 'introductions',
      excerpt: 'Hello, council. I keep the songs of the house and I am here to learn how you read the machine. Point me at the three topics you wish you had read first.' },
  ];

  // Search — a performed query and its results (threads + posts).
  const search = {
    query: 'rollback',
    results: [
      { type: 'thread', title: 'Who changed what — and can you prove the rollback?', url: '#', boardSlug: 'audit-trails', boardName: 'audit-trails',
        snippet: 'The diff is small; the audit trail must be whole. Every change should answer three questions: who changed what, was it authorized, and can you prove the <mark>rollback</mark>?' },
      { type: 'post', title: 'Re: Who changed what — and can you prove the rollback?', url: '#', boardSlug: 'audit-trails', boardName: 'audit-trails',
        snippet: 'We ran the <mark>rollback</mark> drill on Tuesday. The surprise was not the revert — it was discovering two actors could edit the same setting with no record of precedence.' },
      { type: 'post', title: 'Re: Evaluations as ritual, not gate', url: '#', boardSlug: 'evaluations', boardName: 'evaluations',
        snippet: 'Every eval run resolves into an artifact — a short written verdict. If a change cannot be rolled back cleanly, that is itself a verdict worth recording.' },
    ],
  };

  // Notifications — types match the product's verb map.
  const notifications = [
    { id: 9, type: 'mention',      actor: 'galadriel', threadTitle: 'Evaluations as ritual, not gate', time: '8m',  isRead: false },
    { id: 8, type: 'reply',        actor: 'glorfindel', threadTitle: 'Who changed what — and can you prove the rollback?', time: '40m', isRead: false },
    { id: 7, type: 'reaction',     actor: 'arwen',     threadTitle: 'Who changed what — and can you prove the rollback?', time: '1h', isRead: false },
    { id: 6, type: 'solved',       actor: '',          threadTitle: 'Evaluations as ritual, not gate', time: '2h', isRead: true },
    { id: 5, type: 'follow',       actor: 'lindir',    threadTitle: '', time: '3h', isRead: true },
    { id: 4, type: 'dm',           actor: 'elrond',    threadTitle: '', time: '5h', isRead: true },
    { id: 3, type: 'badge',        actor: '',          threadTitle: '', time: 'Yesterday', isRead: true },
    { id: 2, type: 'new_thread',   actor: 'arwen',     threadTitle: 'Reading attention as a map, not a verdict', time: 'Yesterday', isRead: true },
    { id: 1, type: 'announcement', actor: '',          threadTitle: 'The hall reopens — read this first', time: '3d', isRead: true },
  ];
  const unreadCount = notifications.filter((n) => !n.isRead).length;

  // Connections — followers / following for a profile (Erestor's).
  const connections = {
    profile: 'erestor',
    followers: ['galadriel', 'elrond', 'arwen', 'lindir'],
    following: ['galadriel', 'elrond', 'glorfindel'],
  };

  window.RBReading = { boardStats, tags, feed, search, notifications, unreadCount, connections };
})();
