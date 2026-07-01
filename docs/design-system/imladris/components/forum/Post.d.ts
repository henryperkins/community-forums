import * as React from 'react';

/**
 * @startingPoint section="Forum" subtitle="Conversation message (OP / accepted / grouped)" viewport="760x260"
 */
export interface PostProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Author display name. */
  author: string;
  authorSeed?: string;
  authorHref?: string;
  /** Author tier — renders a coloured tier pill beside the name. */
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
 * One message in a conversation.
 */
export function Post(props: PostProps): JSX.Element;
