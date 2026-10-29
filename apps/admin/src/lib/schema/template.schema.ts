import { z } from 'zod';

/** Mirrors LayoutTemplateCatalogue::templates(), as LocalizeHelper::get_admin_data() prints it. */
export const templateWireSchema = z
  .object({
    slug: z.string(),
    name: z.string(),
    categories: z.array(z.string()),
    pro: z.boolean(),
    available: z.boolean(),
    preview_url: z.string(),
    thank_you_preview_url: z.string(),
  })
  .strict();

export const templateListWireSchema = z.array(templateWireSchema);

export type TemplateWire = z.infer<typeof templateWireSchema>;

export type Template = {
  slug: string;
  name: string;
  /** One or more filter chips the template shows under. */
  categories: string[];
  /** Built from Pro blocks. On Free it is listed locked: previewed, never pickable. */
  pro: boolean;
  available: boolean;
  /** '' when the template has no preview file. */
  previewUrl: string;
  /** The Thank you card on the New layout modal's preview step; '' until the template ships thank-you-preview.html. */
  thankYouPreviewUrl: string;
};
