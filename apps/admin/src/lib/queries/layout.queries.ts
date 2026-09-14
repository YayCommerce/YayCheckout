import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createLayoutDraft,
  getLayout,
  getLayouts,
  getLayoutsPage,
  LayoutListParams
} from '@/lib/api/layout.api';
import type { Layout } from '@/lib/schema/layout.schema';

export const LAYOUT_KEYS = {
  all: ['layouts'] as const,
  list: (params: LayoutListParams = {}) => ['layouts', 'list', params] as const,
  detail: (id: number) => ['layouts', 'detail', id] as const,
};

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
  /**
   * Unlike SETTINGS_QUERIES.main this is a collection, not bootstrapped
   * singleton state: there is no initialData on the page and no staleTime of
   * Infinity, because layouts change from the editor as well as from here.
   */
  list: (params: LayoutListParams = {}) =>
    queryOptions({
      queryKey: LAYOUT_KEYS.list(params),
      queryFn: () => getLayouts(params),
    }),
  detail: (id: number) =>
    queryOptions({
      queryKey: LAYOUT_KEYS.detail(id),
      queryFn: () => getLayout(id),
      enabled: id > 0,
    }),
};

/** ─── Hooks ─────────────────────────────────────────── */

export function useLayoutsPageQuery(caseKey: string) {
  return useQuery(LAYOUT_QUERIES.page(caseKey));
}

export function useLayoutsQuery(params: LayoutListParams = {}) {
  return useQuery(LAYOUT_QUERIES.list(params));
}

export function useLayoutQuery(id: number) {
  return useQuery(LAYOUT_QUERIES.detail(id));
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
