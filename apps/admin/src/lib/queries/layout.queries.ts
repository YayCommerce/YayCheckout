import { keepPreviousData, queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  activateLayout,
  createLayoutDraft,
  deactivateLayout,
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

/** ─── Query Options ─────────────────────────────────── */

export const LAYOUT_QUERIES = {
  /**
   * Not a bootstrapped singleton state like SETTINGS_QUERIES.main: there is
   * no initialData and no staleTime of Infinity, because layouts change from
   * the editor as well as from here.
   */
  list: (params: LayoutListParams = {}) =>
    queryOptions({
      queryKey: LAYOUT_KEYS.list(params),
      queryFn: () => getLayouts(params),
      /*
       * Switching Active/Archive is a different queryKey (different status
       * filter), so without this the whole page would drop back to its
       * first-load skeleton on every tab switch. Keeps the previous tab's
       * data on screen — isFetching (not isPending) is what flips true.
       */
      placeholderData: keepPreviousData,
    }),
  /**
   * No status param and no placeholderData: this key never changes when the
   * tab switches, so it simply doesn't refetch on tab switch at all — and
   * with only one cache entry ever, there's no stale/fresh snapshot to
   * diverge between tabs.
   */
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

/** ─── Hooks ─────────────────────────────────────────── */

export function useLayoutsQuery(params: LayoutListParams = {}) {
  return useQuery(LAYOUT_QUERIES.list(params));
}

export function useLayoutCountsQuery() {
  return useQuery(LAYOUT_QUERIES.counts);
}

export function useLayoutQuery(id: number) {
  return useQuery(LAYOUT_QUERIES.detail(id));
}

/**
 * skipGlobalError: the New layout modal renders its own inline error on
 * failure (form.setError('root', ...)) — a global toast on top would say
 * the same thing twice.
 */
export function useCreateLayoutDraftMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['layouts', 'create-draft'],
    mutationFn: createLayoutDraft,
    meta: { skipGlobalError: true },
    onSuccess: () => {
      // A new draft always lands in the listed tab — narrower than LAYOUT_KEYS.all
      // so this doesn't also refetch the archived list.
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
      // Publishing never moves a layout between tabs (draft and published are
      // both "listed"), so counts don't change — only the row's status badge does.
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.list({ status: LISTED_LAYOUT_STATUSES }) });
    },
  });
}

/**
 * Invalidates the list, not just the row: under Free, activating one layout
 * deactivates whichever was active before, so a row the user never touched
 * changes too. Counts go with it — `active` is part of them, and activating an
 * unpublished layout publishes it, changing its status badge and published-at.
 */
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

/** Writes only the active-ids option, so status and published-at are untouched —
 * but is_active and the `active` count both move. */
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
