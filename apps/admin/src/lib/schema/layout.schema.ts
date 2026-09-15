import { __ } from '@wordpress/i18n';
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

export const layoutStatusSchema = z.enum(['draft', 'published', 'archived']);

const layoutSummaryFields = {
  id: z.number(),
  title: z.string(),
  status: layoutStatusSchema,
  hasUnpublishedChanges: z.boolean(),
  isActive: z.boolean(),
  publishedAt: z.string().nullable(),
};

/**
 * Collection row. Mirrors Layout::to_summary_array() — no markup.
 *
 * .strict() is deliberate: without it a renamed key parses to undefined instead
 * of failing, and a rename on the PHP side would surface as a blank field in
 * the UI rather than an error.
 */
export const layoutSummarySchema = z.object(layoutSummaryFields).strict();

/**
 * Full layout. Mirrors Layout::to_array().
 */
export const layoutSchema = z
  .object({
    ...layoutSummaryFields,
    /** Working draft. Never rendered to customers. */
    draft: z.string(),
    /** Published snapshot. Empty until the layout has been published. */
    snapshot: z.string(),
    publishedRevision: z.number().nullable(),
    derived: layoutDerivedSchema,
  })
  .strict();

export const layoutListResponseSchema = z
  .object({
    items: z.array(layoutSummarySchema),
    counts: z
      .object({
        listed: z.number(),
        archived: z.number(),
      })
      .strict(),
  })
  .strict();

export type LayoutStatus = z.infer<typeof layoutStatusSchema>;
export type LayoutSummary = z.infer<typeof layoutSummarySchema>;
export type Layout = z.infer<typeof layoutSchema>;
export type LayoutListResponse = z.infer<typeof layoutListResponseSchema>;

/**
 * Client-side validation for the New layout form. Mirrors the server rule in
 * LayoutRestController::create_draft() (templateSlug required precisely when
 * source is 'library') so a bad submission never reaches the network.
 */
export const newLayoutFormSchema = z
  .object({
    name: z
      .string()
      .trim()
      .min(1, __('Name is required', 'yaycheckout')),
    source: z.enum(['blank', 'library']),
    templateSlug: z.string().nullable(),
  })
  .refine((data) => data.source === 'blank' || !!data.templateSlug, {
    message: __('Pick a template', 'yaycheckout'),
    path: ['templateSlug'],
  });

export type NewLayoutFormValues = z.infer<typeof newLayoutFormSchema>;
