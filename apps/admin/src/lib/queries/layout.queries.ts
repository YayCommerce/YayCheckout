import {
  keepPreviousData,
  queryOptions,
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';

import {
  activateLayout,
  archiveLayout,
  createLayoutDraft,
  createPreviewLink,
  deactivateLayout,
  deleteLayout,
  duplicateLayout,
  getLayout,
  getLayoutCounts,
  getLayoutRules,
  getLayouts,
  LayoutListParams,
  publishLayout,
  resetLayout,
  restoreLayout,
  saveLayoutRules,
} from '@/lib/api/layout.api';
import { LISTED_LAYOUT_STATUSES, type LayoutListResponse } from '@/lib/schema/layout.schema';

export const LAYOUT_KEYS = {
  all: ['layouts'] as const,
  list: (params: LayoutListParams = {}) => ['layouts', 'list', params] as const,
  counts: ['layouts', 'counts'] as const,
  detail: (id: number) => ['layouts', 'detail', id] as const,
  rules: (id: number) => ['layouts', 'rules', id] as const,
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

/** The picker's list: pages pile up as it is scrolled, so every layout is reachable without page controls. */
export function useInfiniteLayoutsQuery(params: LayoutListParams = {}) {
  return useInfiniteQuery({
    queryKey: [...LAYOUT_KEYS.list(params), 'infinite'],
    queryFn: ({ pageParam }) => getLayouts({ ...params, page: pageParam }),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.page < last.totalPages ? last.page + 1 : undefined),
    // A new search is a new key; without this the open list empties while it loads.
    placeholderData: keepPreviousData,
  });
}

export function useLayoutCountsQuery() {
  return useQuery(LAYOUT_QUERIES.counts);
}

export function useLayoutQuery(id: number) {
  return useQuery(LAYOUT_QUERIES.detail(id));
}

export const CREATE_LAYOUT_DRAFT_MUTATION_KEY = ['layouts', 'create-draft'];

export function useCreateLayoutDraftMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: CREATE_LAYOUT_DRAFT_MUTATION_KEY,
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

/** The row leaves the archived tab and appears in the listed one, so both
 * list caches move — hence `all`, not one status filter. */
export function useRestoreLayoutMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['layouts', 'restore'],
    mutationFn: restoreLayout,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.all });
    },
  });
}

/**
 * Every list and the counts, but not `all`: the modal's detail query for
 * the deleted id is still mounted here, and refetching (or removing) it
 * would fire a 404. Nothing reads that id again; it ages out of the cache.
 */
export function useDeleteLayoutMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['layouts', 'delete'],
    mutationFn: deleteLayout,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.counts });
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.list() });
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

/** Rows carry blocks and size, which reset changes; counts stay valid. */
export function useResetLayoutMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['layouts', 'reset'],
    mutationFn: resetLayout,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.list() });
    },
  });
}

/** Nothing to invalidate: a link is minted, not stored. */
export function useCreatePreviewLinkMutation() {
  return useMutation({
    mutationKey: ['layouts', 'preview-link'],
    mutationFn: createPreviewLink,
  });
}

/** Pro only: the Free build has no rules route. */
export function useLayoutRulesQuery(id: number) {
  return useQuery({
    queryKey: LAYOUT_KEYS.rules(id),
    queryFn: () => getLayoutRules(id),
  });
}

/** The reply is the stored rules as the server keeps them, so they go into the cached rules and every cached list: no refetch. */
export function useSaveLayoutRulesMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['layouts', 'save-rules'],
    mutationFn: saveLayoutRules,
    onSuccess: (reply, { id }) => {
      queryClient.setQueryData(LAYOUT_KEYS.rules(id), reply);
      // The picker's infinite lists share this key prefix but hold pages, not items: no `items`, left as is.
      queryClient.setQueriesData<LayoutListResponse>(
        { queryKey: LAYOUT_KEYS.list() },
        (list) =>
          list?.items && {
            ...list,
            items: list.items.map((item) => (item.id === id ? { ...item, rules: reply.rules } : item)),
          },
      );
    },
  });
}
