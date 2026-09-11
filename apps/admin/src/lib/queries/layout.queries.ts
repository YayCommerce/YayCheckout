import { queryOptions, useQuery } from '@tanstack/react-query';

import { getLayoutsPage } from '@/lib/api/layout.api';

/** ─── Query Options ─────────────────────────────────── */

export const LAYOUT_QUERIES = {
  /**
   * Keyed by case so switching the dropdown in LayoutsPage is a plain
   * refetch through TanStack Query's cache, not manual state juggling.
   */
  page: (caseKey: string) =>
    queryOptions({
      queryKey: ['layouts', 'page', caseKey],
      queryFn: () => getLayoutsPage(caseKey),
    }),
};

/** ─── Hooks ─────────────────────────────────────────── */

export function useLayoutsPageQuery(caseKey: string) {
  return useQuery(LAYOUT_QUERIES.page(caseKey));
}
