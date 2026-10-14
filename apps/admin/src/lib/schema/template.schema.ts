import { z } from 'zod';

/** Mirrors LayoutTemplateCatalogue::templates(). */
export const templateWireSchema = z
  .object({
    slug: z.string(),
    name: z.string(),
    categories: z.array(z.string()),
    locked: z.boolean(),
    available: z.boolean(),
    preview_url: z.string(),
  })
  .strict();

export const templateLibraryWireSchema = z
  .object({
    templates: z.array(templateWireSchema),
  })
  .strict();

export type TemplateWire = z.infer<typeof templateWireSchema>;

export type Template = {
  slug: string;
  name: string;
  /** One or more filter chips the template shows under. */
  categories: string[];
  locked: boolean;
  available: boolean;
  /** '' when the template has no preview file. */
  previewUrl: string;
};

export type TemplateLibrary = { templates: Template[] };
