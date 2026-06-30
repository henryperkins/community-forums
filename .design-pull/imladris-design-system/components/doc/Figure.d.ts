import * as React from 'react';

export interface FigureProps extends React.HTMLAttributes<HTMLElement> {
  /** Image URL. Omit to render a drop-in slot, or pass children instead. */
  src?: string;
  /** Alt text (also labels the empty slot). */
  alt?: string;
  /** Mono caption label, e.g. "FIG 3". */
  label?: React.ReactNode;
  /** Caption text after the label. */
  caption?: React.ReactNode;
  /** Drop the frame for edge-to-edge art. */
  plain?: boolean;
  /** Hint shown inside the empty slot. */
  slotHint?: React.ReactNode;
  /** Custom media (e.g. an <image-slot> or <img>) placed inside the frame. */
  children?: React.ReactNode;
}

/** A framed figure with a mono caption; renders a fill-in slot when empty. */
export function Figure(props: FigureProps): JSX.Element;
