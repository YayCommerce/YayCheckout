/** Search param LayoutsPage reads to open a layout straight in its embedded editor. */
export const EDIT_LAYOUT_PARAM = 'edit';

/** The Layouts route that opens `id` in the embedded editor. */
export function editLayoutPath(id: number): string {
  return `/layouts?${EDIT_LAYOUT_PARAM}=${id}`;
}

/** The layout id the URL asks the embedded editor to open, or null. */
export function editLayoutIdFrom(searchParams: URLSearchParams): number | null {
  return Number(searchParams.get(EDIT_LAYOUT_PARAM)) || null;
}
