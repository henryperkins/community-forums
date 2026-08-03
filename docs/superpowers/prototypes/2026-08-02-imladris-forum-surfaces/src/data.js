export const categories = [
  {
    name: "The Commons",
    boards: [
      {
        slug: "announcements",
        name: "announcements",
        description: "Community news, release notes, and notices from the wardens.",
        threads: 12,
        posts: 94,
      },
      {
        slug: "introductions",
        name: "introductions",
        description: "New here? Introduce yourself and find your way around.",
        threads: 68,
        posts: 341,
      },
      {
        slug: "the-valley",
        name: "the-valley",
        description: "Open conversation for everything that does not need a specialist room.",
        threads: 84,
        posts: 592,
      },
    ],
  },
  {
    name: "Vilya · Exposé",
    boards: [
      {
        slug: "interpretability",
        name: "interpretability",
        description: "Methods for making systems legible, inspectable, and accountable.",
        threads: 126,
        posts: 924,
      },
      {
        slug: "evaluations",
        name: "evaluations",
        description: "Rubrics, test suites, and evidence for deciding what works.",
        threads: 98,
        posts: 711,
      },
      {
        slug: "audit-trails",
        name: "audit-trails",
        description: "Change records, rollback drills, and proof that survives the moment.",
        threads: 64,
        posts: 482,
      },
      {
        slug: "the-archive",
        name: "the-archive",
        description: "Durable decisions, ratified guidance, and the history behind both.",
        threads: 142,
        posts: 1144,
      },
    ],
  },
];

export const archiveThreads = [
  {
    id: 184,
    slug: "where-should-ratified-decisions-live",
    title: "Where should ratified decisions live once the council has spoken?",
    author: "Erestor",
    initials: "ER",
    replies: 42,
    activity: "2 hours ago",
    status: "Solved",
    pinned: true,
    unread: true,
  },
  {
    id: 179,
    slug: "versioning-the-living-brief",
    title: "Versioning the living brief without losing the last good reading",
    author: "Glorfindel",
    initials: "GL",
    replies: 18,
    activity: "5 hours ago",
    status: "Decision made",
    unread: true,
  },
  {
    id: 166,
    slug: "keeping-rollback-evidence-attached",
    title: "How do we keep rollback evidence attached to a decision?",
    author: "Arwen",
    initials: "AR",
    replies: 27,
    activity: "yesterday",
    status: "Needs answer",
  },
  {
    id: 151,
    slug: "archive-naming",
    title: "Archive naming: decision record or council note?",
    author: "Lindir",
    initials: "LI",
    replies: 9,
    activity: "3 days ago",
    status: "Open",
  },
  {
    id: 138,
    slug: "quarterly-housekeeping",
    title: "Quarterly housekeeping for stale topics",
    author: "Celebrían",
    initials: "CE",
    replies: 31,
    activity: "1 week ago",
    status: "Open",
    locked: true,
  },
];

export const threadPosts = [
  {
    id: 441,
    author: "Erestor",
    initials: "ER",
    role: "Original poster",
    time: "2 hours ago",
    body: [
      "We keep re-litigating settled questions because the record scatters. A proposal becomes a thread, the thread becomes a decision, and then the useful answer disappears into the chronology.",
      "I would like every ratified decision to have one durable home, while keeping the conversation that produced it close enough to inspect.",
    ],
  },
  {
    id: 447,
    author: "Glorfindel",
    initials: "GL",
    role: "Accepted answer",
    time: "1 hour ago",
    accepted: true,
    body: [
      "The scattering is the symptom, not the disease. We have no rule about when a topic stops being a discussion and starts being a record.",
      "Keep the canonical decision in the archive, pin a short living brief to the topic, and let the brief link back to the exact replies that support it. The discussion stays readable; the decision stays findable.",
    ],
  },
  {
    id: 452,
    author: "Arwen",
    initials: "AR",
    time: "34 minutes ago",
    body: [
      "That also gives revisions a clean shape: update the archived decision, keep the prior version, and add a note to the original topic. Readers can see both the current counsel and how it changed.",
    ],
  },
];
