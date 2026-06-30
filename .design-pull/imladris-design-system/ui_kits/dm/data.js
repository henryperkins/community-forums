/* Messages kit — seed data for private counsel (direct + group conversations).
   Same Imladris roster register as RetroBoards. Shared via window.RBDM. */
(function () {
  const users = {
    erestor:    { username: 'erestor',    name: 'Erestor',    presence: 'online'  },
    galadriel:  { username: 'galadriel',  name: 'Galadriel',  presence: 'online'  },
    elrond:     { username: 'elrond',     name: 'Elrond',     presence: 'online'  },
    glorfindel: { username: 'glorfindel', name: 'Glorfindel', presence: 'away'    },
    arwen:      { username: 'arwen',      name: 'Arwen',      presence: 'online'  },
    lindir:     { username: 'lindir',     name: 'Lindir',     presence: 'offline' },
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
        { id: 13, from: 'galadriel', time: '9m', body: 'Do that. And send me the rollback drill — Glorfindel will want it for the wardens.' },
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
        { id: 22, from: 'arwen',      time: '2h', body: 'Understood. I have the eval verdicts ready to read; they resolve cleanly into artifacts now.' },
        { id: 23, from: 'glorfindel', time: '1h', body: 'The rollback drill is set for Tuesday. Bring the audit trail — I want precedence recorded this time.' },
      ],
    },
    {
      id: 3, kind: 'direct', other: 'elrond', unread: false, time: '3h',
      preview: 'Recorded. I will amend the charter to say so plainly.',
      messages: [
        { id: 31, from: 'erestor', time: 'Today 09:10', body: 'The charter should say that testimony never outranks the work. People keep forgetting the order.' },
        { id: 32, from: 'elrond',  time: '3h', body: 'Recorded. I will amend the charter to say so plainly.' },
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
