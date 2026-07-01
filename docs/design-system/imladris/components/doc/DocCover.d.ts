import * as React from 'react';

export interface DocMetaItem {
  /** Tracked-caps gold label, e.g. "Source of truth". */
  label: React.ReactNode;
  /** Mono value, e.g. "DESIGN.md · v0.11". */
  value: React.ReactNode;
}

export interface DocCoverProps extends React.HTMLAttributes<HTMLElement> {
  /** Tracked-caps kicker beside the mark, e.g. "Engineering Handoff". */
  kicker?: React.ReactNode;
  /** The large display title. */
  title?: React.ReactNode;
  /** Italic display dek under the title. */
  subtitle?: React.ReactNode;
  /** The opening paragraph. */
  lede?: React.ReactNode;
  /** Meta-grid cells (Source of truth, Stack, Build status, …). */
  meta?: DocMetaItem[];
  /** Section labels rendered as a contents rail of pills. */
  contents?: React.ReactNode[];
  /** Override the eight-point mark; pass null to hide it. */
  mark?: React.ReactNode;
  children?: React.ReactNode;
}

/** The title page of a long-form Imladris document. */
export function DocCover(props: DocCoverProps): JSX.Element;
