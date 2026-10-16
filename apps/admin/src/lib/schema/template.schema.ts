import { z } from 'zod';

/** Mirrors LayoutTemplateCatalogue::templates(), as LocalizeHelper::get_admin_data() prints it. */
export const templateWireSchema = z
  .object({
    slug: z.string(),
    name: z.string(),
    categories: z.array(z.string()),
    available: z.boolean(),
    preview_url: z.string(),
  })
  .strict();

export const templateListWireSchema = z.array(templateWireSchema);

export type TemplateWire = z.infer<typeof templateWireSchema>;

export type Template = {
  slug: string;
  name: string;
  /** One or more filter chips the template shows under. */
  categories: string[];
  available: boolean;
  /** '' when the template has no preview file. */
  previewUrl: string;
};
