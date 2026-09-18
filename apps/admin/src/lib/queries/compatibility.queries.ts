import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { getCompatibility } from '@/lib/api/compatibility.api';

export const COMPATIBILITY_QUERIES = {
  report: queryOptions({
    queryKey: ['compatibility', 'report'],
    queryFn: () => getCompatibility(false),
    // Detection is a snapshot; Re-scan is the only way to take a new one.
    staleTime: Infinity,
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
