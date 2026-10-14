import { z } from 'zod';

/** Mirrors LayoutTemplateCatalogue::templates(). */
export const templateWireSchema = z
  .object({
    slug: z.string(),
    name: z.string(),
    category: z.string(),
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
  category: string;
  locked: boolean;
  available: boolean;
  /** '' when the template has no preview file. */
  previewUrl: string;
};

export type TemplateLibrary = { templates: Template[] };
