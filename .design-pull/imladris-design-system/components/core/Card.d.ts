import * as React from 'react';

export interface CardProps extends React.HTMLAttributes<HTMLElement> {
  /** Element tag to render. Default 'div'. */
  as?: keyof JSX.IntrinsicElements;
  children?: React.ReactNode;
}

/** The base raised parchment surface (hairline border, soft shadow, lg radius). */
export function Card(props: CardProps): JSX.Element;
