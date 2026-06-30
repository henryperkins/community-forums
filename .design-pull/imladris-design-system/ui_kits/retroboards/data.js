/* RetroBoards seed data — illustrative council content in the Imladris register.
   Shared with the kit's JSX via window.RB. */
(function () {
  const users = {
    erestor:    { username: 'erestor',    name: 'Erestor',    tier: 'Legend',   title: 'Loremaster of Imladris', rep: 3985, presence: 'online' },
    galadriel:  { username: 'galadriel',  name: 'Galadriel',  tier: 'Loremaster', title: 'Lady of the Wood',     rep: 5120, presence: 'online' },
    elrond:     { username: 'elrond',     name: 'Elrond',     tier: 'Legend',   title: 'Master of the House',    rep: 8740, presence: 'online' },
    glorfindel: { username: 'glorfindel', name: 'Glorfindel', tier: 'Veteran',  title: 'Captain of the Vale',    rep: 2140, presence: 'away' },
    arwen:      { username: 'arwen',      name: 'Arwen',      tier: 'Veteran',  title: 'Evenstar',               rep: 1760, presence: 'online' },
    lindir:     { username: 'lindir',     name: 'Lindir',     tier: 'Member',   title: 'Keeper of Songs',        rep: 940,  presence: 'offline' },
  };

  const categories = [
    {
      name: 'The Commons',
      boards: [
        { slug: 'announcements', name: 'announcements', desc: 'Notices from the house', count: 12 },
        { slug: 'introductions', name: 'introductions', desc: 'Newcomers, say hello', count: 31 },
        { slug: 'the-valley',    name: 'the-valley',    desc: 'Open talk of the vale', count: 88 },
      ],
    },
    {
      name: 'Vilya · Expose',
      boards: [
        { slug: 'interpretability',     name: 'interpretability',     desc: 'Reading the machine', count: 47 },
        { slug: 'evaluations',          name: 'evaluations',          desc: 'Tests, rites, gates',  count: 63 },
        { slug: 'capability-disclosure', name: 'capability-disclosure', desc: 'What we publish, when', count: 22 },
        { slug: 'audit-trails',         name: 'audit-trails',         desc: 'Who changed what',     count: 39 },
      ],
    },
  ];

  const threads = [
    {
      id: 1, board: 'evaluations', author: 'galadriel', status: 'solved', pinned: false,
      title: 'Evaluations as ritual, not gate', replies: 23, time: '2h', commends: 31, starred: true, unread: false,
      snippet: 'We keep treating the eval suite as a turnstile. What if it were a rite the whole council kept?',
      participants: ['galadriel', 'elrond', 'arwen', 'erestor', 'lindir'],
      posts: [
        { author: 'galadriel', op: true, time: '2 days ago', rep: '5.1k',
          body: 'We keep treating the eval suite as a turnstile — pass and forget. I want to argue it should be a rite: something the whole council performs, reads, and remembers.',
          reactions: [{ name: 'Commend', count: 31, on: true }, { name: 'Illuminating', count: 9, icon: 'spark' }] },
        { author: 'elrond', time: '1 day ago', rep: '8.7k', staff: true,
          body: 'Agreed. A gate asks "may this pass?" A rite asks "what did we learn, and who will carry it?" The second question is the one that compounds.',
          reactions: [{ name: 'Seconded', count: 14, icon: 'check' }] },
        { author: 'arwen', time: '22h', rep: '1.7k', accepted: true,
          body: 'Here is the shape that worked for us: every eval run resolves into an artifact — a short written verdict, linked from the topic. The rite is reading the last three before you open a new one.',
          reactions: [{ name: 'Commend', count: 26, on: true }, { name: 'Kindled', count: 7, icon: 'flame' }] },
      ],
    },
    {
      id: 2, board: 'audit-trails', author: 'erestor', status: 'open', pinned: false,
      title: 'Who changed what — and can you prove the rollback?', replies: 41, time: '5h', commends: 54, starred: false, unread: true,
      snippet: 'The diff is small; the audit trail must be whole. Here is the rollback drill we ran on Tuesday.',
      participants: ['erestor', 'glorfindel', 'elrond', 'galadriel'],
      posts: [
        { author: 'erestor', op: true, time: '2 days ago', rep: '3.9k',
          body: 'The diff is small; the audit trail must be whole. AI proposes; the council approves. Every change should answer three questions: who changed what, was it authorized, and can you prove the rollback?',
          reactions: [{ name: 'Commend', count: 54, on: false }, { name: 'Seconded', count: 19, icon: 'check' }] },
        { author: 'glorfindel', time: '1 day ago', rep: '2.1k',
          body: 'We ran the rollback drill on Tuesday. The surprise was not the revert — it was discovering two actors could edit the same setting with no record of precedence. Fixed now.',
          reactions: [{ name: 'Kindled', count: 11, icon: 'flame' }] },
      ],
    },
    {
      id: 3, board: 'capability-disclosure', author: 'elrond', status: 'decision_made', pinned: false,
      title: 'On exposing capability before we are asked', replies: 12, time: '1d', commends: 28, starred: false, unread: false,
      snippet: 'A decision, recorded: we publish the capability notes the same day we brief the council, not after.',
      participants: ['elrond', 'galadriel', 'erestor'],
      posts: [
        { author: 'elrond', op: true, time: '3 days ago', rep: '8.7k', staff: true,
          body: 'A decision, recorded: we publish the capability notes the same day we brief the council — not after. Disclosure that trails the briefing is not disclosure; it is a press release.',
          reactions: [{ name: 'Commend', count: 28, on: false }] },
      ],
    },
    {
      id: 4, board: 'announcements', author: 'elrond', status: 'open', pinned: true,
      title: 'The hall reopens — read this first', replies: 8, time: '3d', commends: 64, starred: false, unread: false,
      snippet: 'A short charter for how we keep counsel here: verify, record, and never let testimony outrank the work.',
      participants: ['elrond', 'erestor'],
      posts: [
        { author: 'elrond', op: true, time: '5 days ago', rep: '8.7k', staff: true,
          body: 'Welcome back. A short charter for how we keep counsel here: status is verified, not asserted; outcomes resolve into artifacts; testimony never outranks the work. Star this topic — we will amend it as the council grows.',
          reactions: [{ name: 'Commend', count: 64, on: false }] },
      ],
    },
    {
      id: 5, board: 'interpretability', author: 'arwen', status: 'needs_answer', pinned: false,
      title: 'Reading attention as a map, not a verdict', replies: 17, time: '6h', commends: 19, starred: false, unread: true,
      snippet: 'Attention tells you where the model looked, not what it concluded. How do you keep the two separate?',
      participants: ['arwen', 'lindir', 'galadriel'],
      posts: [
        { author: 'arwen', op: true, time: '1 day ago', rep: '1.7k',
          body: 'Attention tells you where the model looked, not what it concluded. I keep watching people read a verdict into a heatmap. How do you keep the map and the verdict separate in your own notes?',
          reactions: [{ name: 'Kindled', count: 6, icon: 'flame' }] },
      ],
    },
    {
      id: 6, board: 'introductions', author: 'lindir', status: 'open', pinned: false,
      title: 'Newly arrived — keeper of songs, learner of evals', replies: 4, time: '8h', commends: 12, starred: false, unread: false,
      snippet: 'Hello, council. I keep the songs of the house and I am here to learn how you read the machine.',
      participants: ['lindir', 'arwen'],
      posts: [
        { author: 'lindir', op: true, time: '8 hours ago', rep: '940',
          body: 'Hello, council. I keep the songs of the house and I am here to learn how you read the machine. Point me at the three topics you wish you had read first.',
          reactions: [{ name: 'Commend', count: 12, on: false }] },
      ],
    },
  ];

  const leaderboard = [
    { username: 'elrond', rep: 8740 },
    { username: 'galadriel', rep: 5120 },
    { username: 'erestor', rep: 3985 },
    { username: 'glorfindel', rep: 2140 },
    { username: 'arwen', rep: 1760 },
    { username: 'lindir', rep: 940 },
  ];

  const badges = [
    { label: 'Welcome', on: true }, { label: 'First Thread', on: true },
    { label: 'Conversation Starter', on: true }, { label: 'Well-Liked', on: true },
    { label: 'Trusted Answerer', on: true }, { label: 'Problem Solver', on: true },
    { label: 'Anniversary', on: true }, { label: 'Well of 1,000', on: false, locked: true },
  ];

  // Compact regard formatter: 5120 → "5.1k", 940 → "940".
  function fmt(n) {
    n = Number(n) || 0;
    return n >= 1000 ? (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k' : String(n);
  }

  window.RB = { users, categories, threads, leaderboard, badges, currentUserKey: 'erestor', fmt };
})();
