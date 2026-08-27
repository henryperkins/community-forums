/* ═══════════════════════════════════════════════════════════════════════════
   Thread view — shared content
   One Imladris-flavored topic that exercises every control surface at once:
   workflow status + history, assignment, snooze, tags, a poll, a living
   brief, an accepted answer, a grouped reply, an anonymous post, reactions,
   a referenced post and a link preview.
   Imported by ThreadView.dc.html at mount.
   ═══════════════════════════════════════════════════════════════════════════ */

export const BOARD = { slug: 'the-archive', name: 'The Archive' };

export const THREAD = {
  id: 214,
  slug: 'ratified-decisions',
  title: 'Where should ratified decisions live once the council has spoken?',
  openedBy: 'Erestor',
  // The reply count is derived from POSTS, never stated twice: post a reply in
  // the prototype and a literal would have stayed at 5.
  opened: 'Jul 10',
};

export const STATUSES = [
  { value: 'open',         label: 'Open',          ink: 'var(--on-pending)', bg: 'var(--surface-pending)', border: 'var(--border-hair)' },
  { value: 'needs_answer', label: 'Needs answer',  ink: 'var(--on-review)',  bg: 'var(--surface-review)',  border: 'var(--gold-200)' },
  { value: 'solved',       label: 'Solved',        ink: 'var(--on-done)',    bg: 'var(--surface-done)',    border: 'var(--green-200)' },
  { value: 'decision_made', label: 'Decision made', ink: 'var(--green-800)',  bg: 'var(--brand-subtle)',    border: 'var(--green-200)' },
  { value: 'archived',     label: 'Archived',      ink: 'var(--text-muted)', bg: 'var(--surface-sunken)',  border: 'var(--border-hair)' },
];

export const HISTORY = [
  { to: 'Solved', from: 'Needs answer', actor: 'Elrond', at: 'Jul 12 at 10:12', reason: 'Accepted Arwen’s proposal' },
  { to: 'Needs answer', from: 'Open', actor: 'Glorfindel', at: 'Jul 10 at 11:04', reason: '' },
];

export const TAGS = ['governance', 'records'];
export const TAGS_ALL = ['governance', 'records', 'precedent', 'ritual', 'lore-keeping'];

// The wardens a topic can be tended by. Names + seeds only: every avatar in the
// system hashes its swatch from the seed via <Monogram>, so a hardcoded chip
// colour here would put the same warden on two different swatches.
export const WARDENS = [
  { name: 'Elrond', seed: 'elrond', self: true },
  { name: 'Glorfindel', seed: 'glorfindel' },
  { name: 'Arwen', seed: 'arwen' },
];

// Boards this topic can be moved into (ThreadRepository::movableBoards).
export const BOARDS_MOVABLE = [
  { slug: 'the-hall-of-fire', name: 'The Hall of Fire' },
  { slug: 'the-healing-halls', name: 'The Healing Halls' },
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
  // Provenance label — always one of ThreadIntelligenceViewService's three:
  // 'AI-generated living brief' | 'AI-generated · curator edited' | 'Curated summary'.
  label: 'AI-generated living brief',
  summary: 'The council is converging on treating each verdict as a standalone artifact — a short written decision with its precedence rule attached — kept in a pinned Decisions topic per board. The wiki would hold only the index.',
  // The posts the summary was drawn from. Rendered from here, not written into
  // the markup: a hardcoded "#p102 by @glorfindel" dangles the moment that post
  // is deleted, split out or merged away.
  sources: [102, 106],
  // Member-facing meta (living_brief.php): metadata line · Version N · published.
  meta: 'Updated automatically · Version 3 · Jul 12 at 10:20',
};

// The brief's published versions, newest first. The curator panel appends to
// this when an amendment is published, so a version a curator wrote is
// restorable from the same list as the ones the archive drew.
export const BRIEF_VERSIONS = [
  { v: 3, lineage: 'AI-generated', at: '2d ago', summary: 'The council is converging on treating each verdict as a standalone artifact — a short written decision with its precedence rule attached — kept in a pinned Decisions topic per board. The wiki would hold only the index.', label: 'AI-generated living brief' },
  { v: 2, lineage: 'Curator edited', at: '5d ago', summary: 'Two shapes are on the table: a pinned Decisions topic per board, or one wiki page per season. The council has not chosen, but agrees a verdict must name what it replaces.', label: 'AI-generated · curator edited' },
  { v: 1, lineage: 'AI-generated', at: '11d ago', summary: 'Erestor asks where a ratified decision should live. Early replies favour a single place over the topic that hosted the argument.', label: 'AI-generated living brief' },
];

// Raw emoji, keyed by the emoji itself — mirrors ReactionService::ALLOWED.
// Production has no named reaction set; "Commend" is the reputation unit only.
export const REACTIONS = ['👍', '❤️', '😂', '🎉', '🔥', '💯', '😮', '😢', '👀'];

// Distinct non-anonymous authors only — PostRepository::participantsForThread
// filters is_anonymous = 0, so the anonymous author can never appear here.
// Ordered by first contribution (MIN(created_at) ASC), matching the query.
export const PARTICIPANTS = [
  { name: 'Erestor', seed: 'erestor' },
  { name: 'Glorfindel', seed: 'glorfindel' },
  { name: 'Elladan', seed: 'elladan' },
  { name: 'Arwen', seed: 'arwen' },
];
export const PARTICIPANTS_MORE = 0;

// Titles and reputation are total for signed-in members — TitleService floors
// at a default rung and users.reputation is NOT NULL — so every non-anonymous
// post carries a .post-title-chip and the Commends plinth.
export const VIEWERS = {
  staff: { name: 'Elrond', seed: 'elrond', title: 'Loremaster', rep: '4,820' },
  member: { name: 'Elladan', seed: 'elladan', title: 'Member', rep: '310' },
  guest: { name: 'Guest', seed: '' },
};

export const POSTS = [
  {
    id: 101, author: 'Erestor', seed: 'erestor', title: 'Loremaster', op: true,
    rep: '3,940', time: 'Jul 10 at 09:14',
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
    // Server-fetched, allowlisted, per-board opt-in — and the captured image is
    // deliberately never painted, because a remote asset would make every
    // reader's browser announce this page to the URL's operator.
    linkCard: {
      host: 'imladris.council',
      source: 'imladris.council · the-charter',
      title: 'A charter for keeping counsel',
      desc: 'Status is verified, not asserted. Outcomes resolve into artifacts. Testimony never outranks the work.',
    },
    reactions: { '👍': 4, '🔥': 2 }, mine: [],
  },
  {
    id: 102, author: 'Glorfindel', seed: 'glorfindel', title: 'Veteran', staff: true,
    rep: '2,140', time: 'Jul 10 at 10:58',
    quote: 'Nothing records which decision supersedes which.',
    paras: [
      'This is the sharp end. The guard solved it years ago for watch-orders: every standing order carries the name of the order it replaces, and the replaced one is struck through within the hour. Two rules, kept forever.',
      'I would copy that discipline before we argue about rooms and shelves.',
    ],
    refCard: {
      board: 'interpretability',
      title: 'Reading attention as a map, not a verdict',
      snippet: 'Attention tells you where the model looked, not what it concluded.',
      by: 'Arwen · 17 replies',
    },
    reactions: { '💯': 3 }, mine: [],
    // The catch-up line lives on the post, not in a lookup keyed by id: a map
    // beside the data goes stale the moment a reply is added, and the strip
    // then promises a count its own list contradicts.
    digest: 'reframed the scattering as a missing rule, not a filing problem.',
  },
  {
    // Anonymous post: the render-facing identity is mask_author()'s constant —
    // label/monogram "Anonymous", empty seed. revealUsername feeds only the
    // audited reveal flash; it must never reach a byline or monogram.
    id: 103, author: 'Anonymous', seed: '', anon: true, revealUsername: 'lindir',
    time: 'Jul 11 at 08:41', day: 'The eleventh of July',
    paras: [
      'As one who missed two verdicts last season while away at the fords: whatever we choose, let it be one place. I do not care which. I care that returning after a month does not require an archaeology of six topics.',
    ],
    reactions: { '👍': 2 }, mine: [],
    digest: 'asked only that it be one place, having missed two verdicts while away.',
  },
  {
    id: 104, author: 'Elladan', seed: 'elladan', title: 'Member',
    rep: '310', time: 'Jul 11 at 17:20',
    paras: [
      'Seconding the single-place rule. Could the board index itself carry the latest verdicts? The rail already shows unread counts — a small ledger line under each board name would do.',
    ],
    reactions: {}, mine: [],
    digest: 'seconded the single-place rule, and asked the board index to carry it.',
  },
  {
    id: 105, author: 'Elladan', seed: 'elladan', title: 'Member', grouped: true,
    rep: '310', time: 'Jul 11 at 17:26',
    paras: [
      '(And if the ledger line linked straight to the verdict post, not the topic head, better still.)',
    ],
    reactions: {}, mine: [],
    digest: 'added that the ledger line should point at the verdict, not the topic head.',
  },
  {
    id: 106, author: 'Arwen', seed: 'arwen', title: 'Legend', accepted: true,
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
    reactions: { '👍': 12, '🎉': 5 }, mine: ['👍'],
    digest: 'proposed a warden marks the decision, pins the brief, and locks the topic.',
  },
];

// (No initials() helper here any more: every avatar on the page is a
// <Monogram>, which derives its own initials and hashes its swatch from the
// seed. A second implementation beside it is how the top bar ended up on a
// different colour from the same member's avatar in the stream.)

// (No tier enum here: production renders one cosmetic author_title_label
// string — see partials/post.php `.post-title-chip` — in a single neutral chip
// style. The design system's <Post authorTier> is a DS extension, and its
// .d.ts says so; this template is the production-faithful reading.)
