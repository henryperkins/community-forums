import * as React from 'react';

export interface PresencePerson {
  /** Display name — the row's link text. */
  name?: string;
  /** Handle; also the colour seed for the monogram. */
  username?: string;
  /** Leaf (online), gold (away), grey (offline). The dot is decorative: the
   *  state is always spelled out on the sub-line. */
  presence?: 'online' | 'away' | 'offline';
  /** Cosmetic title, appended to the sub-line when `where` is absent. */
  title?: string;
  /** Where they are right now, e.g. "#lore" — appended to the sub-line, and it wins over `title`. */
  where?: string;
  href?: string;
  /** Real avatar image instead of the monogram. */
  src?: string;
  /** Gold ring on the avatar — staff, high regard, or the viewer themself. */
  gilt?: boolean;
  /** Renders a small "Staff" mark beside the name. */
  staff?: boolean;
  /** The viewer's own row — renders the "you" chip. The viewer counts themselves,
   *  so a member and a guest read the same number for the same moment. */
  self?: boolean;
  /** False renders the bare dot with no monogram — the reader's "show avatars" preference. */
  avatar?: boolean;
}

export interface PresenceRowProps extends PresencePerson, Omit<React.LiHTMLAttributes<HTMLLIElement>, 'title'> {}

export interface PresenceListProps extends Omit<React.HTMLAttributes<HTMLElement>, 'title'> {
  /** Heading text. Default "Online". */
  title?: React.ReactNode;
  people?: PresencePerson[];
  /** Members here *now* — the badge. Defaults to the `online` rows in `people`. */
  here?: number;
  /** Deprecated alias for `here`, kept so existing callers keep working. */
  count?: number;
  /** The whole roll (here + away). Decides "+n more" and emptiness — never `here`:
   *  a rail holding five away members and nobody here is not empty. */
  total?: number;
  /** Rows to render before folding into "+n more". Default 5; 0 renders all. */
  max?: number;
  showCount?: boolean;
  /** Skeleton rows while the roster is being fetched. */
  loading?: boolean;
  /** `list` is the sidebar column; `grid` lays the same rows wide for a directory. */
  layout?: 'list' | 'grid';
  /** False renders bare dots with no monograms — the reader's "show avatars" preference. */
  avatars?: boolean;
  emptyLabel?: string;
  /** Slot under the list — a "See everyone" link, a note about presence privacy. */
  footer?: React.ReactNode;
}

/** One member row. Use directly to build a custom roster with the same treatment. */
export function PresenceRow(props: PresenceRowProps): JSX.Element;

/**
 * The "Online" widget — who is at the council right now. Sits in the board
 * sidebar, but works anywhere a roster belongs.
 */
export function PresenceList(props: PresenceListProps): JSX.Element;
