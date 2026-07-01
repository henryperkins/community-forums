/* Admin Console kit — seed data for the operator's desk. */
(function () {
  window.RBAdmin = {
    admin: { name: 'Elrond', username: 'elrond' },
    siteName: 'RetroBoards',
    audit: [
      { when: '2h ago', actor: 'elrond', action: 'post.lock', target: 'thread #1042', reason: 'Resolved; locking to preserve the answer.' },
      { when: '5h ago', actor: 'galadriel', action: 'post.accept_answer', target: 'post #7731', reason: '' },
      { when: 'yesterday', actor: 'system', action: 'badge.award', target: 'user #88', reason: 'Rule: solved_count ≥ 10' },
      { when: '2 days ago', actor: 'elrond', action: 'user.role_change', target: 'user #51', reason: 'Promoted to moderator.' },
      { when: '3 days ago', actor: 'erestor', action: 'board.archive', target: 'board #14', reason: 'Inactive since Second Age.' },
    ],
    categories: [
      { id: 1, name: 'The Commons', boards: [
        { id: 11, name: 'announcements', slug: 'announcements', visibility: 'public', threads: 12, archived: false },
        { id: 12, name: 'introductions', slug: 'introductions', visibility: 'public', threads: 31, archived: false },
        { id: 13, name: 'the-valley', slug: 'the-valley', visibility: 'public', threads: 88, archived: false },
      ] },
      { id: 2, name: 'Vilya · Expose', boards: [
        { id: 21, name: 'interpretability', slug: 'interpretability', visibility: 'public', threads: 47, archived: false },
        { id: 22, name: 'evaluations', slug: 'evaluations', visibility: 'members', threads: 63, archived: false },
        { id: 23, name: 'audit-trails', slug: 'audit-trails', visibility: 'private', threads: 39, archived: false },
        { id: 24, name: 'old-council', slug: 'old-council', visibility: 'public', threads: 5, archived: true },
      ] },
    ],
    users: [
      { id: 88, username: 'galadriel', display: 'Galadriel', role: 'moderator', state: 'active', rep: 5120, joined: 'T.A. 2019' },
      { id: 12, username: 'elrond', display: 'Elrond', role: 'admin', state: 'active', rep: 8740, joined: 'T.A. 2018' },
      { id: 51, username: 'erestor', display: 'Erestor', role: 'member', state: 'active', rep: 3985, joined: 'T.A. 2021' },
      { id: 64, username: 'glorfindel', display: 'Glorfindel', role: 'member', state: 'active', rep: 2140, joined: 'T.A. 2022' },
      { id: 77, username: 'arwen', display: 'Arwen', role: 'member', state: 'active', rep: 1760, joined: 'T.A. 2023' },
      { id: 90, username: 'saruman', display: 'Saruman', role: 'member', state: 'deactivated', rep: 12, joined: 'T.A. 2024' },
    ],
    badgeRules: [
      { id: 1, badge: 'Welcome', rule: 'post_count', threshold: 1, board: null, enabled: true },
      { id: 2, badge: 'Trusted Answerer', rule: 'solved_count', threshold: 10, board: null, enabled: true },
      { id: 3, badge: 'Loremaster of Evals', rule: 'reputation', threshold: 5000, board: 'evaluations', enabled: false },
    ],
    emailQueue: { queued: 3, sent: 1284, failed: 2, suppressed: 6, bounced: 1, complained: 0 },
    deliveries: [
      { when: '2h ago', to: 'arwen@imladris.council', kind: 'instant', status: 'sent', attempts: '1 / 3', subject: 'New counsel on your topic', detail: 'msg_8821' },
      { when: '6h ago', to: 'lindir@imladris.council', kind: 'digest', status: 'sent', attempts: '1 / 3', subject: 'Your daily digest', detail: 'msg_8790' },
      { when: 'yesterday', to: 'bounce@nowhere.test', kind: 'instant', status: 'failed', attempts: '3 / 3', subject: 'You were mentioned', detail: '550 mailbox unavailable' },
    ],
    suppressions: [
      { email: 'bounce@nowhere.test', reason: 'hard_bounce', since: 'yesterday' },
    ],
    webhooks: [
      { id: 1, name: 'Ops bridge', url: 'https://ops.imladris.council/hooks/forum', active: true, last: '200' },
      { id: 2, name: 'Archive mirror', url: 'https://mirror.example/ingest', active: false, last: '— ' },
    ],
    webhookEvents: {
      'post.created': 'A new post or reply is published',
      'thread.created': 'A new topic is opened',
      'thread.solved': 'A topic is marked solved',
      'user.registered': 'A new member joins',
      'ping': 'Test event (admin-only)',
    },
    tokens: [
      { id: 1, name: 'Read-only mirror', scopes: 'read:threads, read:posts', created: 'T.A. 2024', last: '2h ago', revoked: false },
      { id: 2, name: 'Legacy importer', scopes: 'write:posts', created: 'T.A. 2023', last: '—', revoked: true },
    ],
    tokenScopes: {
      'read:threads': 'Read topics and boards',
      'read:posts': 'Read posts and reactions',
      'write:posts': 'Create posts on behalf of a member',
      'admin:users': 'Read and modify user records',
    },
    tags: [
      { id: 1, name: 'how-to', slug: 'how-to', desc: 'Practical guides', visibility: 'public', enabled: true },
      { id: 2, name: 'rfc', slug: 'rfc', desc: 'Proposals for council', visibility: 'public', enabled: true },
      { id: 3, name: 'archived-lore', slug: 'archived-lore', desc: 'Older reference', visibility: 'hidden', enabled: false },
    ],
    handlers: [
      { pkg: 'imladris/anti-abuse', handler: 'post.scan', status: 'enabled', entrypoint: 'handlers/scan.php' },
      { pkg: 'imladris/digest', handler: 'cron.digest', status: 'enabled', entrypoint: 'handlers/digest.php' },
    ],
    runs: [
      { when: '1h ago', handler: 'post.scan', status: 'ok', detail: '' },
      { when: '8h ago', handler: 'cron.digest', status: 'ok', detail: '' },
      { when: 'yesterday', handler: 'post.scan', status: 'error', detail: 'sandbox timeout (5s)' },
    ],
    badgeCatalogue: ['Welcome', 'First Thread', 'Trusted Answerer', 'Problem Solver', 'Loremaster of Evals'],
  };
})();
