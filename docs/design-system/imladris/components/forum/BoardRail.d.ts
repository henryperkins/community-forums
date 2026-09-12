import * as React from 'react';

export interface RailBoard {
  /** Stable key — matched against `activeBoard` to mark the current board. */
  key?: string;
  name: string;
  /** Per-board destination. Defaults to the Board page template. */
  href?: string;
  /** Unread topic count. 0 or absent renders no pill. */
  unread?: number;
  /** Accessible label for the pill, e.g. "3 unread topics". */
  unreadLabel?: string;
  /** Shown but not pickable — Compose uses this for boards the viewer may not
   *  post to, so a denied board is visible rather than quietly missing. */
  locked?: boolean;
  lockedTitle?: string;
  /** Small outlined chip after the name — Leaderboard shows board visibility
   *  ("private", "restricted") this way. */
  tag?: string;
}

export interface RailCategory {
  key?: string;
  /** Tracked-caps group heading, e.g. "Council". */
  name: string;
  boards: RailBoard[];
}

export interface RailRoute {
  key?: string;
  label: string;
  /** Second line under the label, e.g. "Your personal queue". */
  sub?: string;
  /** One of the named glyphs the rail draws. */
  icon?: 'home' | 'inbox' | 'messages' | 'drafts' | 'following' | 'trophy';
  href?: string;
  active?: boolean;
}

export interface BoardRailProps extends React.HTMLAttributes<HTMLElement> {
  categories?: RailCategory[];
  /** Key of the board the viewer is on. */
  activeBoard?: string;
  /** Intercept navigation and route in-page; omitted, real hrefs are used. */
  onNavigate?: (key: string | undefined, board: RailBoard) => void;
  /** Fallback destination for boards with no own `href` — a string or a mapper. */
  boardHref?: string | ((board: RailBoard) => string);
  /** false renders nothing at all (the ⌘B collapsed state). */
  open?: boolean;
  /** Destination rows above the categories (partials/sidebar.php's "routes
   *  above boards"). Most surfaces pass none — the topbar carries cross-surface
   *  travel — so use this only where the rail genuinely owns a route list. */
  routes?: RailRoute[];
  /** Trailing note on the active row — Compose sets "posting here", so the
   *  rail can say what picking a board means on that surface. */
  activeNote?: string;
  ariaLabel?: string;
  /** Hung beneath the boards, above the rail's bottom padding — the roster on
   *  every surface that has a rail. This is rail furniture, not surface
   *  content: pass the same roster + "See everyone" everywhere, or a block
   *  appears and disappears as the member clicks through. */
  children?: React.ReactNode;
}

/** The shared board rail: categories, boards, unread pills, and a footer slot. */
export function BoardRail(props: BoardRailProps): JSX.Element | null;
