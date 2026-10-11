/** The Layouts page, where the app starts and every "Back to Layouts" goes. */
export const LAYOUTS_PATH = '/layouts';

/** Search param naming the layout the embedded editor opens. */
export const EDIT_LAYOUT_PARAM = 'edit';

/** The Layouts route that opens `id` in the embedded editor. */
export function editLayoutPath(id: number): string {
  return `${LAYOUTS_PATH}?${EDIT_LAYOUT_PARAM}=${id}`;
}

/** The layout id the URL asks the embedded editor to open: a positive integer, or null. */
export function editLayoutIdFrom(searchParams: URLSearchParams): number | null {
  const id = Number(searchParams.get(EDIT_LAYOUT_PARAM));
  return Number.isInteger(id) && id > 0 ? id : null;
}

/** A copy of `searchParams` asking the editor to open `id`, or to close when null; other params are kept. */
export function withEditLayout(searchParams: URLSearchParams, id: number | null): URLSearchParams {
  const next = new URLSearchParams(searchParams);
  if (id === null) {
    next.delete(EDIT_LAYOUT_PARAM);
  } else {
    next.set(EDIT_LAYOUT_PARAM, String(id));
  }
  return next;
}
