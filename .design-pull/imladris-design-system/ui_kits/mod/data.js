/* Moderation kit — seed data for the warden's table. The triage side of
   RetroBoards: reports, the approval hold, appeals review, and a member's own
   appeal view. Imladris council register. Shared via window.RBMod.
   Mirrors templates/mod/{reports,approvals,appeals}.php + appeals/index.php. */
(function () {
  const moderator = { name: 'Glorfindel', username: 'glorfindel' };

  // Reports queue (mod/reports). Targets are either a post-in-thread or a DM message.
  const reports = [
    {
      id: 412, status: 'open', reason_code: 'harassment',
      reporter_username: 'lindir', created_at: '11 minutes ago',
      post: {
        thread_id: 88, thread_slug: 'on-the-naming-of-wards', thread_title: 'On the naming of wards',
        body: 'You weren\u2019t in the room when the rollback failed, so spare us the lecture. People who actually ship don\u2019t need your ceremony.',
      },
      reason: 'Tone aimed at a person, not the argument. Second time this week from the same account.',
    },
    {
      id: 409, status: 'triaged', reason_code: 'spam',
      reporter_username: 'arwen', created_at: '40 minutes ago',
      post: {
        thread_id: 73, thread_slug: 'eval-harness-v2', thread_title: 'Eval harness v2 \u2014 call for testers',
        body: 'Boost your council standing FAST \u2014 commendations, badges, leaderboard rank. Visit my profile for the link, first ten are free\u2026',
      },
      reason: 'Same copy posted in four topics. Link farm.',
    },
    {
      id: 406, status: 'open', reason_code: 'harassment',
      reporter_username: 'arwen', created_at: '2 hours ago',
      dm: {
        conversation_title: 'Direct message', kind: 'direct', message_id: 1841,
        sender_display: 'unknown', sender_username: 'mellon',
        body: 'I know which boards you moderate. Keep removing my posts and we\u2019ll see how long that lasts.',
      },
      reason: 'Veiled threat in a DM after I removed a rule-breaking reply.',
    },
    {
      id: 401, status: 'open', reason_code: 'off_topic',
      reporter_username: 'erestor', created_at: '5 hours ago',
      post: {
        thread_id: 41, thread_slug: 'who-changed-what', thread_title: 'Who changed what \u2014 and can you prove the rollback?',
        body: 'Off the audit thread: has anyone tried the new forge in the eastern hall? The fires there run hotter than Mount Doom, I swear by it.',
      },
      reason: '',
    },
  ];

  // Approval hold (mod/approvals): topics and replies held by anti-abuse / board rules.
  const approvals = {
    threads: [
      { id: 220, title: 'Proposal: require a written verdict before any merge', author_username: 'arwen', board_slug: 'governance', created_at: '2026-04-18 09:12' },
      { id: 219, title: 'New warden intake \u2014 spring cohort', author_username: 'lindir', board_slug: 'wardens', created_at: '2026-04-18 08:40' },
    ],
    posts: [
      { id: 5120, thread_id: 73, thread_slug: 'eval-harness-v2', thread_title: 'Eval harness v2 \u2014 call for testers', author_username: 'celebrian', board_slug: 'tooling', created_at: '2026-04-18 10:02',
        body: 'I can take the Tuesday slot. One ask: can we record which artifacts the harness actually read, so the verdict is reproducible from the trail rather than from memory?' },
      { id: 5118, thread_id: 41, thread_slug: 'who-changed-what', thread_title: 'Who changed what \u2014 and can you prove the rollback?', author_username: 'haldir', board_slug: 'audit-trails', created_at: '2026-04-18 09:58',
        body: 'New account, first post \u2014 held for review. The precedence log answers this cleanly; I attached the drill we ran last cycle so the wardens have it on record.' },
    ],
  };

  // Appeals review (mod/appeals): the staff side.
  const appeals = [
    {
      id: 77, status: 'open', appellant_username: 'mellon', created_at: '1 hour ago',
      target_type: 'post', target_id: 5099, original_action: 'post removed',
      target_summary: 'Reply removed from \u201cOn the naming of wards\u201d for personal attack.',
      reason: 'I was heated but I never threatened anyone. The line about shipping was about the process, not the person. Asking for the removal to be reconsidered.',
    },
    {
      id: 75, status: 'open', appellant_username: 'haldir', created_at: 'Yesterday',
      target_type: 'moderation_log', target_id: 318, original_action: 'topic locked',
      target_summary: 'Topic \u201cForge fires of the eastern hall\u201d locked as off-topic for #audit-trails.',
      reason: 'Fair that it was off-topic there. Could it be moved to #commons instead of locked? People were enjoying it.',
    },
  ];

  const outcomes = ['upheld', 'modified', 'reversed', 'dismissed'];

  // Member's own appeals view (appeals/index) — Glorfindel is not the subject here;
  // this is shown as "what a member sees". Uses the council member Erestor.
  const member = { name: 'Erestor', username: 'erestor' };
  const myAppeals = {
    eligible: {
      posts: [
        { id: 5099, thread_id: 12, thread_slug: 'on-the-naming-of-wards', thread_title: 'On the naming of wards', deleted_at: '2 days ago',
          body: 'The charter should say plainly that testimony never outranks the work. I will not soften that for anyone who finds it inconvenient.' },
      ],
      logs: [
        { id: 318, action: 'warning issued', created_at: '3 days ago', reason: 'Repeated sharp tone toward another member in #governance.' },
      ],
    },
    submitted: [
      { id: 71, status: 'reversed', target_type: 'post', target_id: 4980, created_at: 'Last week',
        target_summary: 'Reply removed from \u201cEval harness v2\u201d.', reason: 'It was on-topic \u2014 I was answering the tester call directly.',
        resolution_note: 'Agreed on review. Post restored; removal was in error.' },
      { id: 64, status: 'upheld', target_type: 'moderation_log', target_id: 290, created_at: '3 weeks ago',
        target_summary: 'Warning for editing a shared setting without recording precedence.', reason: 'I thought the change was uncontested.',
        resolution_note: 'Warning stands \u2014 the warden log is the record of precedence; edits there must be noted.' },
    ],
  };

  const reasonLabels = { harassment: 'harassment', spam: 'spam', off_topic: 'off topic', other: 'other' };

  window.RBMod = { moderator, reports, approvals, appeals, outcomes, member, myAppeals, reasonLabels };
})();
