import { getTemplates } from '@/lib/api/template.api';
import type { Template } from '@/lib/schema/template.schema';

/** Every template in catalogue order. */
export function useTemplates(): Template[] {
  return getTemplates();
}

/** Mirrors LayoutTemplateCatalogue::assert_available(): unlocked on this plan and not Coming soon. */
export function isPickable(template: Template): boolean {
  return !template.locked && template.available;
}

/** The catalogue row for `slug`; undefined when no template has that slug. */
export function useTemplate(slug: string | null | undefined): Template | undefined {
  return useTemplates().find((template) => template.slug === slug);
}
