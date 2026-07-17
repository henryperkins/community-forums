/* Messages kit — seed data for private counsel (direct + group conversations).
   Same Imladris roster register as RetroBoards. Shared via window.RBDM.
   v2 (reimagine): users carry joined/tier for the details rail; a couple of
   threads carry same-author runs + a trailing message from me, so grouping and
   the read receipt read true. */
(function () {
  const users = {
    erestor:    { username: 'erestor',    name: 'Erestor',    presence: 'online',  joined: 'Third Age, 2018', tier: 'Loremaster' },
    galadriel:  { username: 'galadriel',  name: 'Galadriel',  presence: 'online',  joined: 'Third Age, 2012', tier: 'Legend'     },
    elrond:     { username: 'elrond',     name: 'Elrond',     presence: 'online',  joined: 'Third Age, 2009', tier: 'Legend'     },
    glorfindel: { username: 'glorfindel', name: 'Glorfindel', presence: 'away',    joined: 'Third Age, 2015', tier: 'Veteran'    },
    arwen:      { username: 'arwen',      name: 'Arwen',      presence: 'online',  joined: 'Third Age, 2016', tier: 'Veteran'    },
    lindir:     { username: 'lindir',     name: 'Lindir',     presence: 'offline', joined: 'Third Age, 2019', tier: 'Member'     },
  };

  const me = 'erestor';

  /* Each conversation: direct (with `other`) or group (with `title` + `members`).
     `messages` are ordered oldest→newest; `mine` is derived in the view. */
  const conversations = [
    {
      id: 1, kind: 'direct', other: 'galadriel', unread: true, time: '9m',
      preview: 'Send me the rollback drill — Glorfindel will want it for the wardens.',
      messages: [
        { id: 11, from: 'galadriel', time: 'Yesterday 18:40', body: 'Erestor — I read your note on audit trails before the council met. It holds. The three questions are the right ones.' },
        { id: 12, from: 'erestor',   time: 'Yesterday 19:02', body: 'Then it is ready to record. I will mark the accepted answer and link the written verdict from the topic.',
          refs: [{ type: 'Topic', title: 'Who changed what — and can you prove the rollback?', meta: '#audit-trails · 41 replies', url: '#' }] },
        { id: 13, from: 'erestor',   time: 'Yesterday 19:04', body: 'The rollback drill is drafted as well. I will attach it once Glorfindel names the day.',
          quote: { from: 'galadriel', text: 'The three questions are the right ones.' } },
        { id: 14, from: 'galadriel', time: '9m', body: 'Do that. And send me the rollback drill — Glorfindel will want it for the wardens.' },
      ],
    },
    {
      id: 2, kind: 'group', title: 'Vilya · wardens', unread: true, time: '1h',
      members: [
        { username: 'erestor',    role: 'owner' },
        { username: 'elrond',     role: 'member' },
        { username: 'glorfindel', role: 'member' },
        { username: 'arwen',      role: 'member' },
        { username: 'lindir',     role: 'member', left: true },
      ],
      preview: 'Glorfindel: the rollback drill is set for Tuesday. Bring the audit trail.',
      messages: [
        { id: 21, from: 'elrond',     time: '3h', body: 'Wardens — we keep counsel here on what does not yet belong in the open hall. Verify before you carry it further.' },
        { id: 22, from: 'arwen',      time: '2h', body: 'Understood. I have the eval verdicts ready to read; they resolve cleanly into artifacts now.',
          refs: [{ type: 'Topic', title: 'Eval verdicts — the eight that resolved this cycle', meta: '#evals · 12 replies', url: '#' }] },
        { id: 23, from: 'glorfindel', time: '1h', body: 'The rollback drill is set for Tuesday. Bring the audit trail — I want precedence recorded this time.',
          quote: { from: 'arwen', text: 'They resolve cleanly into artifacts now.' } },
      ],
    },
    {
      id: 3, kind: 'direct', other: 'elrond', unread: false, time: '2h', read: true,
      preview: 'Thank you. Send me the wording before it is entered into the charter.',
      messages: [
        { id: 31, from: 'erestor', time: 'Today 09:10', body: 'The charter should say that testimony never outranks the work. People keep forgetting the order.' },
        { id: 32, from: 'elrond',  time: '3h', body: 'Recorded. I will amend the charter to say so plainly.' },
        { id: 33, from: 'erestor', time: '2h', body: 'Thank you. Send me the wording before it is entered into the charter.' },
      ],
    },
    {
      id: 4, kind: 'direct', other: 'arwen', unread: false, time: 'Yesterday',
      preview: 'The accepted answer reads well now. Thank you for the gilt.',
      messages: [
        { id: 41, from: 'arwen',   time: 'Yesterday', body: 'The accepted answer reads well now. Thank you for the gilt.' },
      ],
    },
    {
      id: 5, kind: 'direct', other: 'glorfindel', unread: false, time: '2d',
      preview: 'Two actors could edit one setting with no record of precedence. Fixed.',
      messages: [
        { id: 51, from: 'glorfindel', time: '2 days ago', body: 'Two actors could edit one setting with no record of precedence. Fixed now — the warden log keeps order.' },
      ],
    },
    {
      id: 6, kind: 'direct', other: 'lindir', unread: false, time: '5d',
      preview: 'Thank you for the three topics. I have read all of them twice.',
      messages: [
        { id: 61, from: 'lindir',  time: '5 days ago', body: 'Thank you for the three topics. I have read all of them twice. The songs will keep them.' },
      ],
    },
  ];

  // Reasons offered when reporting a message (mapped to readable labels in the view).
  const reportReasons = ['spam', 'harassment', 'off_topic', 'other'];

  window.RBDM = { users, me, conversations, reportReasons };
})();
