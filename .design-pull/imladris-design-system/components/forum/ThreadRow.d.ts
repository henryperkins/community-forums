import * as React from 'react';

/**
 * @startingPoint section="Forum" subtitle="Inbox topic row with status rule & chips" viewport="700x150"
 */
export interface ThreadRowProps extends React.LiHTMLAttributes<HTMLLIElement> {
  /** Topic title (the link text). */
  title: string;
  href?: string;
  /** Author display name — the prominent byline. */
  author?: string;
  /** Colour seed (defaults to author). */
  authorSeed?: string;
  /** Link the author name to their profile. */
  authorHref?: string;
  /** Author tier — renders a coloured tier pill in the byline. */
  authorTier?: 'Member' | 'Veteran' | 'Loremaster' | 'Legend';
  /** Author regard (commends earned) — the gold ✦ stat in the byline. */
  authorRep?: number | string;
  /** Presence dot on the avatar. */
  presence?: boolean | 'online' | 'away' | 'offline';
  /** Gild the avatar (gold ring) — for high-regard / staff authors. */
  giltAuthor?: boolean;
  /** Board slug + label, shown when showBoard is set (e.g. on the "All" inbox). */
  board?: string;
  boardName?: string;
  showBoard?: boolean;
  replies?: number;
  /** Relative time string, e.g. "2h". */
  time?: string;
  /** One-or-two-line preview (hidden in compact density). */
  snippet?: string;
  /** Commend count shown with the gold star. */
  commends?: number | string;
  /** Topic status — also sets the coloured left-rule. */
  status?: 'open' | 'solved' | 'needs_answer' | 'decision_made';
  pinned?: boolean;
  locked?: boolean;
  unread?: boolean;
  starred?: boolean;
  /** Highlight as the open topic in the reading pane. */
  active?: boolean;
  showAvatar?: boolean;
}

/**
 * One topic row in the Council Inbox. Wrap rows in <ul className="thread-list">
 * (add "is-compact" for the one-line Watch density).
 */
export function ThreadRow(props: ThreadRowProps): JSX.Element;
