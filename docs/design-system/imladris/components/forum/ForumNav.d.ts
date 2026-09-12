import * as React from 'react';

export interface ForumSurface {
  /** Stable key, e.g. 'boards'. Matched against `surface` to mark the active pill. */
  key: string;
  label: string;
  /** Template folder under templates/, e.g. 'board-index'. Omit both `dir` and
   *  `file` for a surface that has no template yet — the pill renders inert
   *  rather than pointing at some other screen. */
  dir?: string;
  /** Entry file inside that folder, e.g. 'BoardIndex.dc.html'. */
  file?: string;
}

/** The member-facing surfaces in topbar order. Messages is inert until it has a template. */
export const FORUM_SURFACES: ForumSurface[];

export interface ForumNavViewer {
  /** Display name — drives the monogram initials and the seat label. */
  name: string;
  /** Colour seed for the monogram (defaults to name). */
  username?: string;
  /** Where the seat links; defaults to the User profile template. */
  href?: string;
  /** The viewer's own presence, as the roll would show it. 'online' | 'away'
   *  draws the leaf on the seat; anything else (or omitted) draws none — a
   *  member who switched presence off must not see a leaf beside their name. */
  presence?: 'online' | 'away' | 'offline';
}

export interface ForumNavProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Key of the active surface — the only prop a template must set. */
  surface: string;
  /** Override the surface list (defaults to FORUM_SURFACES). */
  surfaces?: ForumSurface[];
  /** Unread count on the Inbox pill. 0 renders no pill. */
  inboxCount?: number;
  /** Where the search control goes when `onSearch` is not supplied. */
  searchHref?: string;
  searchLabel?: string;
  /** false drops the search control — for the Search surface itself, which
   *  carries a real query field in the page body. */
  showSearch?: boolean;
  /** Supply to render search as a button and own the palette yourself; also
   *  rebinds ⌘K to it instead of navigating. */
  onSearch?: () => void;
  /** Reflected as aria-expanded on the search button. */
  searchOpen?: boolean;
  /** Your ⌘K palette/dialog, passed as children — it renders inside the search
   *  control's positioning wrapper so it anchors to the field. */
  children?: React.ReactNode;
  /** Rail state for the toggle's pressed styling. */
  railOpen?: boolean;
  /** Supply to render the rail toggle and bind ⌘B; omitted, neither exists —
   *  for surfaces with no rail. */
  onToggleRail?: () => void;
  /** Full control of the toggle group, for a surface with more than one pane
   *  (the inbox has a rail AND a reading pane). Overrides `onToggleRail`'s
   *  single button; ⌘B still fires `onToggleRail` if you pass it too. */
  toggles?: Array<{
    key: string;
    /** Which band the glyph fills: the rail (left) or a reading pane (right). */
    icon?: 'rail' | 'pane';
    on?: boolean;
    onClick?: () => void;
    title?: string;
    /** Accessible name; falls back to `title`. */
    label?: string;
  }>;
  composeHref?: string;
  composeLabel?: string;
  /** Supply to handle compose in-page instead of navigating. */
  onCompose?: () => void;
  showCompose?: boolean;
  /** Signed-in member; omitted, the guest sign-in link renders instead. */
  viewer?: ForumNavViewer | null;
  signInHref?: string;
}

/** The unifying member chrome: house lockup, surface pills, search, rail toggle, compose, seat. */
export function ForumNav(props: ForumNavProps): JSX.Element;
