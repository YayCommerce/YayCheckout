import { queryOptions, useQuery } from '@tanstack/react-query';

import { getTemplateLibrary } from '@/lib/api/template.api';
import { isPro } from '@/lib/plan';
import type { Template } from '@/lib/schema/template.schema';

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

/** Mirrors LayoutTemplateCatalogue::assert_available(): unlocked on this plan and not Coming soon. */
export function isPickable(template: Template): boolean {
  return !template.locked && template.available;
}

/** The catalogue row for `slug`; undefined while the library loads or when no template has that slug. */
export function useTemplate(slug: string | null | undefined): Template | undefined {
  const { data } = useTemplateLibraryQuery();
  return data?.templates.find((template) => template.slug === slug);
}
