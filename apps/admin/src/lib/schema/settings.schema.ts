import { z } from 'zod';

/** Backbone settings shape — extend per feature (builder, fields, design). */
export const settingsSchema = z.object({
  general: z.object({
    enabled: z.boolean(),
  }),
});

export type Settings = z.infer<typeof settingsSchema>;
