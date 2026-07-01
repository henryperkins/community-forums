import * as React from 'react';

export interface TagProps extends React.HTMLAttributes<HTMLSpanElement> {
  children?: React.ReactNode;
}

/** The smallest meta token — board visibility, topic tag, quiet micro-cap label. */
export function Tag(props: TagProps): JSX.Element;
