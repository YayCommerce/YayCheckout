import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { getLayouts, publishLayout } from '@/lib/api/layout.api';
import type { Layout } from '@/lib/schema/layout.schema';

/** ─── Query Options ─────────────────────────────────── */

export const LAYOUT_QUERIES = {
  /**
   * Unlike SETTINGS_QUERIES.main this is a collection, not bootstrapped
   * singleton state: there is no initialData on the page and no staleTime of
   * Infinity, because layouts change from the editor as well as from here.
   */
  list: queryOptions({
    queryKey: ['layouts', 'list'],
    queryFn: getLayouts,
  }),
};

/** ─── Hooks ─────────────────────────────────────────── */

export function useLayoutsQuery() {
  return useQuery(LAYOUT_QUERIES.list);
}

export function usePublishLayoutMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['layouts', 'publish'],
    mutationFn: (id: Layout['id']) => publishLayout(id),
    /*
     * Invalidate rather than setQueryData: the response is a single layout
     * while the cache holds the list, and publishing can change another
     * layout's active flag.
     */
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LAYOUT_QUERIES.list.queryKey });
    },
  });
}
