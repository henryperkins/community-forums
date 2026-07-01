import * as React from 'react';

export interface SpecColumn {
  /** Key used to read the cell from an object row. */
  key?: string;
  /** Header label. Omit on every column to render a headerless table. */
  label?: React.ReactNode;
  /** Cell + header alignment. */
  align?: 'center' | 'right';
}

export interface SpecTableProps extends React.TableHTMLAttributes<HTMLTableElement> {
  columns: SpecColumn[];
  /** Row objects keyed by column.key, or arrays in column order. Cells may be strings or nodes. */
  rows: Array<Record<string, React.ReactNode> | React.ReactNode[]>;
  caption?: React.ReactNode;
  /** Zebra-stripe rows (default true). */
  zebra?: boolean;
  /** Applied to the wrapping element. */
  className?: string;
}

/** A bordered reference table with a sunken caps header and node-capable cells. */
export function SpecTable(props: SpecTableProps): JSX.Element;
