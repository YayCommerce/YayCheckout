import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { getCheckoutProblems, getCompatibility } from '@/lib/api/compatibility.api';

/** Prefix of every compatibility query: invalidate it when a layout, its content or safe mode changes. */
export const COMPATIBILITY_KEY = ['compatibility'] as const;

export const COMPATIBILITY_QUERIES = {
  report: queryOptions({
    queryKey: [...COMPATIBILITY_KEY, 'report'],
    queryFn: () => getCompatibility(false),
    // Detection is a snapshot; Re-scan is the only way to take a new one.
    staleTime: Infinity,
  }),
  // A layout may have changed since the last read, so every visit asks again.
  problems: queryOptions({
    queryKey: [...COMPATIBILITY_KEY, 'problems'],
    queryFn: getCheckoutProblems,
    staleTime: 0,
  }),
};

export function useCompatibilityQuery() {
  return useQuery(COMPATIBILITY_QUERIES.report);
}

/** Re-scan: refetches the catalogue and replaces the report in place, so the page never flashes empty. */
export function useRescanMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationKey: ['compatibility', 'rescan'],
    mutationFn: () => getCompatibility(true),
    onSuccess: (report) => {
      queryClient.setQueryData(COMPATIBILITY_QUERIES.report.queryKey, report);
    },
  });
}
