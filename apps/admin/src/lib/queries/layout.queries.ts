import { queryOptions, useMutation, useQuery } from '@tanstack/react-query';

import { createLayoutDraft, getLayoutsPage } from '@/lib/api/layout.api';

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

/**
 * skipGlobalError: the New layout modal renders every failure itself
 * (inline duplicate-name message, or a generic inline error) — a global
 * toast on top would say the same thing twice.
 */
export function useCreateLayoutDraftMutation() {
  return useMutation({
    mutationKey: ['layouts', 'create-draft'],
    mutationFn: createLayoutDraft,
    meta: { skipGlobalError: true },
  });
}
