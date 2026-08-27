import * as React from 'react';

/**
 * @startingPoint section="Forum" subtitle="Conversation message (OP / accepted / grouped)" viewport="760x260"
 */
export interface PostProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Author display name. */
  author: string;
  authorSeed?: string;
  authorHref?: string;
  /**
   * **DS extension** — renders a coloured tier pill beside the name. Production
   * has no tier enum: `partials/post.php` prints one cosmetic
   * `author_title_label` string in a single neutral chip. Use `authorTitle` for
   * a production-faithful post and reserve `authorTier` for surfaces where the
   * coloured ladder is the point (the board index, the leaderboard).
   */
  authorTier?: 'Member' | 'Veteran' | 'Loremaster' | 'Legend';
  /** @handle for the signature line under the name. */
  handle?: string;
  /** The member's title / signature (e.g. "Lady of the Wood"). */
  authorTitle?: string;
  /** Presence dot on the avatar. */
  presence?: boolean | 'online' | 'away' | 'offline';
  /** Relative or absolute time string. */
  time?: string;
  edited?: boolean;
  /** Original poster — gilds the avatar and shows the OP badge. */
  op?: boolean;
  /** Staff author — gold Staff badge. */
  staff?: boolean;
  /** Wiki post — Wiki badge. */
  wiki?: boolean;
  /** Accepted answer — gilds the avatar + green "Marked as the answer" plate + done surface. */
  accepted?: boolean;
  /** Consecutive same-author reply — drops the repeated avatar + name. */
  grouped?: boolean;
  /** Commend count shown under the avatar (the author's Regard). */
  rep?: number | string;
  /** Reaction nodes (one or more <Reaction>). */
  reactions?: React.ReactNode;
  /** The post body. */
  children?: React.ReactNode;
}

/**
 * One message in a conversation — the compact post row: identity column, head
 * row, body, reactions.
 *
 * **Scope.** This is the row for surfaces that quote or list a post: DM
 * threads, search results, moderation queues, the design-system card. The
 * thread-view template deliberately composes its own row from primitives
 * instead, because a full reading surface needs things a post row should not
 * grow: two byline placements (above the body / stacked in the gutter), an
 * inline edit textarea, report and warden-removal panels docked under the
 * body, per-post link and reference cards ordered after the prose, and a
 * hover/focus action toolbar. Adding all of that here would make one component
 * the whole page. Use `Post` for a post; compose for a thread.
 */
export function Post(props: PostProps): JSX.Element;
