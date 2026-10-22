/** The Layouts page, where the app starts and every "Back to Layouts" goes. */
export const LAYOUTS_PATH = '/layouts';

/** Search param naming the layout the embedded editor opens. */
export const EDIT_LAYOUT_PARAM = 'edit';

/** Search param naming the view the embedded editor opens: absent for the checkout. */
export const EDIT_VIEW_PARAM = 'view';

/** The editor views the Layouts page opens. Mirrors LayoutEditorEmbed::VIEW_MAIN and VIEW_THANK_YOU. */
export type EditView = 'main' | 'thank-you';

/** The Layouts route that opens `id` in the embedded editor. Mirrors AdminUrlHelper::edit_layout() in PHP. */
export function editLayoutPath(id: number): string {
  return `${LAYOUTS_PATH}?${EDIT_LAYOUT_PARAM}=${id}`;
}

/** The layout id the URL asks the embedded editor to open: a positive integer, or null. */
export function editLayoutIdFrom(searchParams: URLSearchParams): number | null {
  const id = Number(searchParams.get(EDIT_LAYOUT_PARAM));
  return Number.isInteger(id) && id > 0 ? id : null;
}

/** The view the URL asks the embedded editor to open; anything but thank-you is the checkout. */
export function editViewFrom(searchParams: URLSearchParams): EditView {
  return searchParams.get(EDIT_VIEW_PARAM) === 'thank-you' ? 'thank-you' : 'main';
}

/**
 * A copy of `searchParams` asking the editor to open `id` in `view`, or to close when null; other params
 * are kept. The view param is set only for thank-you, and cleared with the layout.
 */
export function withEditLayout(
  searchParams: URLSearchParams,
  id: number | null,
  view: EditView = 'main',
): URLSearchParams {
  const next = new URLSearchParams(searchParams);
  next.delete(EDIT_VIEW_PARAM);
  if (id === null) {
    next.delete(EDIT_LAYOUT_PARAM);
  } else {
    next.set(EDIT_LAYOUT_PARAM, String(id));
    if (view === 'thank-you') {
      next.set(EDIT_VIEW_PARAM, view);
    }
  }
  return next;
}
