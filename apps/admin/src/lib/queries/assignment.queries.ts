import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { getAssignmentOptionLabels, searchAssignmentOptions } from '@/lib/api/assignment.api';
import type { AssignmentOptionKind } from '@/lib/schema/assignment.schema';

/** Pro only, as the routes are. */
export function useAssignmentOptionSearch(kind: AssignmentOptionKind, search: string, enabled: boolean) {
  return useQuery({
    queryKey: ['assignment', kind, 'search', search],
    queryFn: () => searchAssignmentOptions(kind, search),
    enabled,
    placeholderData: keepPreviousData,
  });
}

/**
 * Id => label for picked ids, so a rule names its products and categories. Ids sorted in the key, so the
 * same set shares one cache entry; disabled while there are none.
 */
export function useAssignmentOptionLabels(kind: AssignmentOptionKind, ids: number[]) {
  const sorted = [...new Set(ids)].sort((a, b) => a - b);

  return useQuery({
    queryKey: ['assignment', kind, 'labels', sorted],
    queryFn: async () =>
      Object.fromEntries((await getAssignmentOptionLabels(kind, sorted)).map((option) => [option.id, option.label])),
    enabled: sorted.length > 0,
    staleTime: Infinity,
    placeholderData: keepPreviousData,
  });
}
