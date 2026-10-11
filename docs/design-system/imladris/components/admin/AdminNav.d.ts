import * as React from 'react';

export interface AdminArea {
  /** Stable key, e.g. 'content'. Matched against `area` to mark the active tab. */
  key: string;
  label: string;
  /** Template folder name under templates/, e.g. 'admin-content'. */
  dir: string;
  /** Entry file inside that folder, e.g. 'AdminContent.dc.html'. */
  file: string;
  /** Openable by a board moderator, not only an admin. Only Moderation is. */
  moderatorOk?: boolean;
}

/** The eleven admin areas in console order, Moderation at index 1. */
export const ADMIN_AREAS: AdminArea[];

export interface AdminNavViewer {
  /** Display name — the seat label and the monogram's initials. */
  name: string;
  /** Colour seed for the monogram (defaults to name). Pass the handle the
   *  member seat uses, or the operator changes colour on entering the console. */
  username?: string;
  /** Where the seat links; defaults to the User profile template. */
  href?: string;
}

export interface AdminNavProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Key of the active area — the only prop a template must set. */
  area: string;
  /** Override the area list (defaults to ADMIN_AREAS). */
  areas?: AdminArea[];
  /** Where "Back to the council" goes. Defaults to the Board index template. */
  backHref?: string;
  backLabel?: string;
  /** 'moderator' reduces the tier to the areas that role can actually open. */
  role?: 'admin' | 'moderator';
  /** Signed-in operator; omitted, the bell, seat and sign-out are not rendered.
   *  Every admin template passes one: production always has a viewer on these
   *  routes, so a console page without the cluster is a page production never shows. */
  viewer?: AdminNavViewer | null;
  /** Unread count on the bell. 0 renders no count; above 99 renders 99+. */
  notificationCount?: number;
  /** Where the bell goes. Defaults to the Board index template (its Notifications pane). */
  notificationsHref?: string;
  showSearch?: boolean;
  /** Right-hand mode pill. Defaults to the role's own name; '' or null hides it. */
  modeLabel?: string | null;
  /** Supply to render tabs as buttons and handle routing yourself; omitted, the
   *  tier renders real relative <a> hrefs to the sibling admin templates. */
  onNavigate?: (key: string) => void;
}

/** The unifying admin chrome: identity row, operator cluster, area tier. */
export function AdminNav(props: AdminNavProps): JSX.Element;
