import { keepPreviousData, queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  activateLayout,
  archiveLayout,
  createLayoutDraft,
  deactivateLayout,
  duplicateLayout,
  getLayout,
  getLayoutCounts,
  getLayouts,
  LayoutListParams,
  publishLayout,
} from '@/lib/api/layout.api';
import { LISTED_LAYOUT_STATUSES } from '@/lib/schema/layout.schema';

export const LAYOUT_KEYS = {
  all: ['layouts'] as const,
  list: (params: LayoutListParams = {}) => ['layouts', 'list', params] as const,
  counts: ['layouts', 'counts'] as const,
  detail: (id: number) => ['layouts', 'detail', id] as const,
};

export const LAYOUT_QUERIES = {
  // No initialData or staleTime: Infinity — layouts also change from the editor.
  list: (params: LayoutListParams = {}) =>
    queryOptions({
      queryKey: LAYOUT_KEYS.list(params),
      queryFn: () => getLayouts(params),
      // Tab switch is a new key; without this the page drops back to its first-load skeleton.
      placeholderData: keepPreviousData,
    }),
  counts: queryOptions({
    queryKey: LAYOUT_KEYS.counts,
    queryFn: getLayoutCounts,
  }),
  detail: (id: number) =>
    queryOptions({
      queryKey: LAYOUT_KEYS.detail(id),
      queryFn: () => getLayout(id),
      enabled: id > 0,
    }),
};

export function useLayoutsQuery(params: LayoutListParams = {}) {
  return useQuery(LAYOUT_QUERIES.list(params));
}

export function useLayoutCountsQuery() {
  return useQuery(LAYOUT_QUERIES.counts);
}

export function useLayoutQuery(id: number) {
  return useQuery(LAYOUT_QUERIES.detail(id));
}

export function useCreateLayoutDraftMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['layouts', 'create-draft'],
    mutationFn: createLayoutDraft,
    // The modal renders its own inline error.
    meta: { skipGlobalError: true },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.counts });
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.list({ status: LISTED_LAYOUT_STATUSES }) });
    },
  });
}

export function usePublishLayoutMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['layouts', 'publish'],
    mutationFn: publishLayout,
    onSuccess: () => {
      // Counts are untouched: draft and published are both "listed".
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.list({ status: LISTED_LAYOUT_STATUSES }) });
    },
  });
}

// Whole list, not the row: on Free, activating one layout deactivates another.
export function useActivateLayoutMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['layouts', 'activate'],
    mutationFn: activateLayout,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.counts });
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.list({ status: LISTED_LAYOUT_STATUSES }) });
    },
  });
}

export function useDeactivateLayoutMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['layouts', 'deactivate'],
    mutationFn: deactivateLayout,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.counts });
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.list({ status: LISTED_LAYOUT_STATUSES }) });
    },
  });
}

/** The row leaves the listed tab and appears in the archived one, so both
 * list caches move — hence `all`, not one status filter. */
export function useArchiveLayoutMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['layouts', 'archive'],
    mutationFn: archiveLayout,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.all });
    },
  });
}

/** Adds a new draft row, so both the list and the listed count change. */
export function useDuplicateLayoutMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['layouts', 'duplicate'],
    mutationFn: duplicateLayout,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.counts });
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.list({ status: LISTED_LAYOUT_STATUSES }) });
    },
  });
}
