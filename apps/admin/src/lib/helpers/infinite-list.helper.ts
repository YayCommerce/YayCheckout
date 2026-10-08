import type { UIEvent } from 'react';

/** Distance from the bottom of the list at which the next page is fetched. */
const LOAD_MORE_THRESHOLD_PX = 40;

/** `onScroll` for a paged picker list: fetches the next page once the list nears its bottom. */
export function loadMoreOnScroll(
  event: UIEvent<HTMLElement>,
  list: { hasNextPage: boolean; isFetchingNextPage: boolean; fetchNextPage: () => unknown },
) {
  const listElement = event.currentTarget;
  if (!list.hasNextPage || list.isFetchingNextPage) return;

  const distanceToBottomPx = listElement.scrollHeight - listElement.scrollTop - listElement.clientHeight;
  if (distanceToBottomPx < LOAD_MORE_THRESHOLD_PX) list.fetchNextPage();
}
