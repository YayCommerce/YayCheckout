import { queryOptions, useQuery } from '@tanstack/react-query';

import { getTemplateLibrary } from '@/lib/api/template.api';

export const TEMPLATE_QUERIES = {
  library: (plan: 'free' | 'pro') =>
    queryOptions({
      queryKey: ['templates', 'library', plan],
      queryFn: () => getTemplateLibrary(plan),
    }),
};

export function useTemplateLibraryQuery(plan: 'free' | 'pro') {
  return useQuery(TEMPLATE_QUERIES.library(plan));
}
