import { HTMLAttributes, ThHTMLAttributes, TdHTMLAttributes } from "react";

export interface TableProps extends HTMLAttributes<HTMLTableElement> {}

/**
 * Data table with sticky header, uppercase micro-labels, soft row hover.
 * Compose: Table > THead > Tr > Th, then TBody > Tr > Td.
 * @startingPoint section="Display" subtitle="Sticky-header data table with hover rows" viewport="700x260"
 */
export function Table(props: HTMLAttributes<HTMLTableElement>): JSX.Element;
export function THead(props: HTMLAttributes<HTMLTableSectionElement>): JSX.Element;
export function TBody(props: HTMLAttributes<HTMLTableSectionElement>): JSX.Element;
export function Tr(props: HTMLAttributes<HTMLTableRowElement>): JSX.Element;
export function Th(props: ThHTMLAttributes<HTMLTableCellElement> & { align?: string }): JSX.Element;
export function Td(props: TdHTMLAttributes<HTMLTableCellElement> & { align?: string; mono?: boolean }): JSX.Element;
