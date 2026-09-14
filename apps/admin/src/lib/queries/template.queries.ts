import { queryOptions, useQuery } from '@tanstack/react-query';

import { getTemplateLibrary } from '@/lib/api/template.api';
import { isPro } from '@/lib/plan';

export const TEMPLATE_QUERIES = {
  library: () =>
    queryOptions({
      queryKey: ['templates', 'library', isPro],
      queryFn: getTemplateLibrary,
    }),
};

export function useTemplateLibraryQuery() {
  return useQuery(TEMPLATE_QUERIES.library());
}
