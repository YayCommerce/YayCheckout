import { getTemplates } from '@/lib/api/template.api';
import { isPro } from '@/lib/plan';
import type { Template } from '@/lib/schema/template.schema';

/** Every template in catalogue order. */
export function useTemplates(): Template[] {
  return getTemplates();
}

/** Mirrors LayoutTemplateCatalogue::assert_available(): not Coming soon, and not a Pro template on Free. */
export function isPickable(template: Template): boolean {
  return template.available;
}

/** A Pro template on Free: PHP lists it unavailable, and the picker shows it behind a Pro badge. */
export function isLocked(template: Template): boolean {
  return template.pro && !isPro;
}

/** The catalogue row for `slug`; undefined when no template has that slug. */
export function useTemplate(slug: string | null | undefined): Template | undefined {
  return useTemplates().find((template) => template.slug === slug);
}
