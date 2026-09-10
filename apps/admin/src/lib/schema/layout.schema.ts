import { z } from 'zod';

/**
 * PHP encodes an empty associative array as `[]`, not `{}`, so any map that
 * happens to be empty arrives as an empty JSON array. Normalise that before
 * validating rather than loosening the map type.
 */
const phpMap = z.preprocess(
  (value) => (Array.isArray(value) && value.length === 0 ? {} : value),
  z.record(z.string(), z.unknown()),
);

/**
 * Derived caches, regenerated from the published snapshot at publish time.
 * Keys mirror DerivedCacheBuilder's output and are stable. A layout that has
 * never been published has no derived caches at all.
 */
export const layoutDerivedSchema = z.preprocess(
  (value) => (Array.isArray(value) && value.length === 0 ? {} : value),
  z.object({
    fields: phpMap.optional(),
    extra_fields: phpMap.optional(),
    slots: z.array(z.string()).optional(),
    order_modes: z.array(z.string()).optional(),
  }),
);

/**
 * Mirrors Layout::to_array() in PHP. This is the only place the server shape is
 * asserted on the client, so it is where a drifted contract shows up.
 *
 * .strict() is deliberate: without it a renamed key parses to undefined instead
 * of failing, and a rename on the PHP side would surface as a blank field in
 * the UI rather than an error.
 */
export const layoutSchema = z
  .object({
    id: z.number(),
    title: z.string(),
    /** Working draft. Never rendered to customers. */
    draft: z.string(),
    /** Published snapshot. Empty until the layout has been published. */
    snapshot: z.string(),
    publishedAt: z.string().nullable(),
    publishedRevision: z.number().nullable(),
    isActive: z.boolean(),
    derived: layoutDerivedSchema,
  })
  .strict();

export const layoutListSchema = z.array(layoutSchema);

export type Layout = z.infer<typeof layoutSchema>;
