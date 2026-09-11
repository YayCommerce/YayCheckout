import { z } from 'zod';

export const templateSchema = z.object({
  slug: z.string(),
  name: z.string(),
  category: z.string(),
  locked: z.boolean(),
});

export const templateLibrarySchema = z.object({
  plan: z.enum(['free', 'pro']),
  templates: z.array(templateSchema),
});

export type Template = z.infer<typeof templateSchema>;
export type TemplateLibrary = z.infer<typeof templateLibrarySchema>;
