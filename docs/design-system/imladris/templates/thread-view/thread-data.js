/* ═══════════════════════════════════════════════════════════════════════════
   Thread-view exploration — shared content
   One Imladris-flavored topic that exercises every control surface at once:
   workflow status + history, assignment, snooze, tags, a poll, a living
   brief, an accepted answer, a grouped reply, an anonymous post, reactions.
   Consumed by the three direction DCs via dynamic import.
   ═══════════════════════════════════════════════════════════════════════════ */

export const BOARD = { slug: 'the-archive', name: 'The Archive' };

export const THREAD = {
  id: 214,
  slug: 'ratified-decisions',
  title: 'Where should ratified decisions live once the council has spoken?',
  openedBy: 'Erestor',
  replies: 5,
  opened: 'Jul 10',
};

export const STATUSES = [
  { value: 'open',         label: 'Open',          ink: 'var(--on-pending)', bg: 'var(--surface-pending)', border: 'var(--border-hair)' },
  { value: 'needs_answer', label: 'Needs answer',  ink: 'var(--on-review)',  bg: 'var(--surface-review)',  border: 'var(--gold-200)' },
  { value: 'solved',       label: 'Solved',        ink: 'var(--on-done)',    bg: 'var(--surface-done)',    border: 'var(--green-200)' },
  { value: 'decision',     label: 'Decision made', ink: 'var(--green-800)',  bg: 'var(--brand-subtle)',    border: 'var(--green-200)' },
  { value: 'archived',     label: 'Archived',      ink: 'var(--text-muted)', bg: 'var(--surface-sunken)',  border: 'var(--border-hair)' },
];

export const HISTORY = [
  { to: 'Solved', from: 'Needs answer', actor: 'Elrond', at: 'Jul 12 at 10:12', reason: 'Accepted Arwen’s proposal' },
  { to: 'Needs answer', from: 'Open', actor: 'Glorfindel', at: 'Jul 10 at 11:04', reason: '' },
];

export const TAGS = ['governance', 'records'];
export const TAGS_ALL = ['governance', 'records', 'precedent', 'ritual', 'lore-keeping'];

export const ROSTER = [
  { name: 'Elrond', seed: 'elrond' },
  { name: 'Glorfindel', seed: 'glorfindel' },
  { name: 'Arwen', seed: 'arwen' },
];

export const POLL = {
  question: 'Where should ratified decisions live?',
  mode: 'Choose one',
  options: [
    { id: 1, body: 'A pinned Decisions topic per board', votes: 14 },
    { id: 2, body: 'The board wiki, one page per season', votes: 9 },
    { id: 3, body: 'A quarterly ledger post', votes: 4 },
  ],
};

export const BRIEF = {
  summary: 'The council is converging on treating each verdict as a standalone artifact — a short written decision with its precedence rule attached — kept in a pinned Decisions topic per board. The wiki would hold only the index.',
  sources: [102, 106],
  refreshed: 'Refreshed Jul 12 · 6 posts weighed',
};

export const REACTIONS = {
  commend: { glyph: '✦', label: 'Commend' },
  seconded: { glyph: '✓', label: 'Seconded' },
  illuminating: { glyph: '❋', label: 'Illuminating' },
};

export const PARTICIPANTS = [
  { name: 'Erestor', seed: 'erestor' },
  { name: 'Glorfindel', seed: 'glorfindel' },
  { name: 'Arwen', seed: 'arwen' },
  { name: 'Elladan', seed: 'elladan' },
  { name: 'Lindir', seed: 'lindir' },
];
export const PARTICIPANTS_MORE = 2;

export const VIEWERS = {
  staff: { name: 'Elrond', seed: 'elrond' },
  member: { name: 'Elladan', seed: 'elladan' },
  guest: { name: 'Guest', seed: '' },
};

export const POSTS = [
  {
    id: 101, author: 'Erestor', seed: 'erestor', tier: 'Loremaster', op: true,
    rep: '3,940', time: 'Jul 10 at 09:14', day: 'The tenth of July',
    paras: [
      'Every council here ends the same way: a verdict is spoken, heads nod, and the topic scrolls on. A season later somebody asks what we decided about lantern-oil rationing, and we spend an evening excavating.',
      'Three failures, plainly:',
    ],
    list: [
      'Verdicts live in whichever topic hosted the argument — findable only by those who were there.',
      'The wiki holds three of our last eleven decisions, each written in a different form.',
      'Nothing records which decision supersedes which.',
    ],
    after: 'Before I propose ritual, I would hear the keep: where should a ratified decision live, and who tends it?',
    reactions: { commend: 4, illuminating: 2 }, mine: [],
  },
  {
    id: 102, author: 'Glorfindel', seed: 'glorfindel', tier: 'Veteran', staff: true,
    rep: '2,140', time: 'Jul 10 at 10:58',
    quote: 'Nothing records which decision supersedes which.',
    paras: [
      'This is the sharp end. The guard solved it years ago for watch-orders: every standing order carries the name of the order it replaces, and the replaced one is struck through within the hour. Two rules, kept forever.',
      'I would copy that discipline before we argue about rooms and shelves.',
    ],
    reactions: { seconded: 3 }, mine: [],
  },
  {
    id: 103, author: 'A quiet voice', seed: 'anon-103', anon: true, realAuthor: 'Lindir', realSeed: 'lindir',
    time: 'Jul 11 at 08:41',
    paras: [
      'As one who missed two verdicts last season while away at the fords: whatever we choose, let it be one place. I do not care which. I care that returning after a month does not require an archaeology of six topics.',
    ],
    reactions: { seconded: 2 }, mine: [],
  },
  {
    id: 104, author: 'Elladan', seed: 'elladan', tier: 'Member',
    rep: '310', time: 'Jul 11 at 17:20',
    paras: [
      'Seconding the single-place rule. Could the board index itself carry the latest verdicts? The rail already shows unread counts — a small ledger line under each board name would do.',
    ],
    reactions: {}, mine: [],
  },
  {
    id: 105, author: 'Elladan', seed: 'elladan', tier: 'Member', grouped: true,
    rep: '310', time: 'Jul 11 at 17:26',
    paras: [
      '(And if the ledger line linked straight to the verdict post, not the topic head, better still.)',
    ],
    reactions: {}, mine: [],
  },
  {
    id: 106, author: 'Arwen', seed: 'arwen', tier: 'Legend', accepted: true,
    rep: '5,210', time: 'Jul 12 at 10:03', day: 'The twelfth of July',
    paras: [
      'Let the decision be an artifact, not a memory. When a council concludes, the closer writes a verdict post in a fixed form and pins it to a Decisions topic — one per board, tended by the wardens:',
    ],
    list: [
      'The verdict itself, one paragraph, dated and signed.',
      'What it replaces, struck through and linked — Glorfindel’s discipline.',
      'Where the argument lived, so the reasoning is never lost.',
    ],
    after: 'The wiki then holds only the index of verdicts. One place to look, one form to trust, and the reasoning a link away.',
    reactions: { commend: 12, illuminating: 5 }, mine: ['commend'],
  },
];

const MONO = [
  ['var(--green-100)', 'var(--green-800)'],
  ['var(--river-100)', 'var(--river-700)'],
  ['var(--gold-100)', 'var(--gold-700)'],
  ['var(--mist-200)', 'var(--ink-700)'],
  ['var(--green-200)', 'var(--green-900)'],
  ['var(--river-200)', 'var(--river-900)'],
  ['var(--gold-200)', 'var(--gold-700)'],
  ['var(--parchment-200)', 'var(--ink-700)'],
  ['var(--green-050)', 'var(--green-700)'],
  ['var(--river-100)', 'var(--river-700)'],
];

export function mono(seed) {
  const s = String(seed || '');
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h + s.charCodeAt(i)) % 10;
  return { bg: MONO[h][0], ink: MONO[h][1] };
}

export function initials(name) {
  const p = String(name || '').trim().split(/\s+/).filter(Boolean);
  if (!p.length) return '?';
  return (p.length === 1 ? p[0].slice(0, 2) : p[0][0] + p[1][0]).toUpperCase();
}

export const TIERS = {
  Legend:     { ink: 'var(--gold-700)',  bg: 'var(--gold-100)',      border: 'var(--gold-200)' },
  Loremaster: { ink: 'var(--green-800)', bg: 'var(--brand-subtle)',  border: 'var(--green-200)' },
  Veteran:    { ink: 'var(--river-700)', bg: 'var(--river-100)',     border: 'var(--river-200)' },
  Member:     { ink: 'var(--text-muted)', bg: 'var(--surface-sunken)', border: 'var(--border-hair)' },
};

export const DIRECTIONS = [
  { key: 'console', letter: 'A', label: 'The Console', file: 'ConsoleThread.dc.html' },
  { key: 'ledger',  letter: 'B', label: 'The Ledger',  file: 'LedgerThread.dc.html' },
  { key: 'study',   letter: 'C', label: 'The Study',   file: 'StudyThread.dc.html' },
];
