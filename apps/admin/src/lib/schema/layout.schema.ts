import { z } from 'zod';
import { __ } from '@wordpress/i18n';

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

/** Mirrors LayoutStatus::all() in PHP. */
export const layoutStatusSchema = z.enum(['draft', 'pending', 'scheduled', 'published', 'archived']);

/** Mirrors LayoutStatus::listed(): the Active tab, everything not archived. */
export const LISTED_LAYOUT_STATUSES = layoutStatusSchema.options.filter((status) => status !== 'archived');

/*
 * Wire schemas below assert the PHP shape exactly — snake_case, like every
 * payload field (docs/code-standards.md, REST). The SPA never touches them
 * directly: lib/api/layout.api.ts parses with these and converts to the
 * camelCase app types at the bottom of this file, so the conversion lives in
 * one place and no component knows how PHP spells a key.
 */

const layoutSummaryWireFields = {
  id: z.number(),
  title: z.string(),
  status: layoutStatusSchema,
  has_unpublished_changes: z.boolean(),
  is_active: z.boolean(),
  published_at: z.string().nullable(),
  /** GMT publish date while status is 'scheduled'; null otherwise. */
  scheduled_at: z.string().nullable(),
};

/**
 * Collection row. Mirrors Layout::to_summary_array() — no markup.
 *
 * .strict() is deliberate: without it a renamed key parses to undefined instead
 * of failing, and a rename on the PHP side would surface as a blank field in
 * the UI rather than an error.
 */
export const layoutSummaryWireSchema = z.object(layoutSummaryWireFields).strict();

/**
 * Full layout. Mirrors Layout::to_array().
 */
export const layoutWireSchema = z
  .object({
    ...layoutSummaryWireFields,
    /** Working draft. Never rendered to customers. */
    draft: z.string(),
    /** Published snapshot. Empty until the layout has been published. */
    snapshot: z.string(),
    published_revision: z.number().nullable(),
    derived: layoutDerivedSchema,
  })
  .strict();

export const layoutListResponseWireSchema = z
  .object({
    items: z.array(layoutSummaryWireSchema),
    total: z.number(),
    page: z.number(),
    total_pages: z.number(),
  })
  .strict();

export const layoutCountsSchema = z
  .object({
    listed: z.number(),
    archived: z.number(),
    active: z.number(),
  })
  .strict();

/** GET /layouts/counts — its own endpoint so tab totals have one cache key,
 * independent of whichever status filter the list query is on. */
export const layoutCountsResponseSchema = z
  .object({
    counts: layoutCountsSchema,
  })
  .strict();

export type LayoutStatus = z.infer<typeof layoutStatusSchema>;
export type LayoutCounts = z.infer<typeof layoutCountsSchema>;

export type LayoutSummaryWire = z.infer<typeof layoutSummaryWireSchema>;
export type LayoutWire = z.infer<typeof layoutWireSchema>;
export type LayoutListResponseWire = z.infer<typeof layoutListResponseWireSchema>;

/** App-side shapes — camelCase. Built from the wire types by `layout.api.ts`; this is what pages and hooks consume. */
export type LayoutSummary = {
  id: number;
  title: string;
  status: LayoutStatus;
  hasUnpublishedChanges: boolean;
  isActive: boolean;
  publishedAt: string | null;
  /** GMT publish date while status is 'scheduled'; null otherwise. */
  scheduledAt: string | null;
};

export type Layout = LayoutSummary & {
  /** Working draft. Never rendered to customers. */
  draft: string;
  /** Published snapshot. Empty until the layout has been published. */
  snapshot: string;
  publishedRevision: number | null;
  derived: z.infer<typeof layoutDerivedSchema>;
};

export type LayoutListResponse = {
  items: LayoutSummary[];
  total: number;
  page: number;
  totalPages: number;
};

/**
 * Client-side validation for the New layout form. Mirrors the `template_slug`
 * arg's validate_callback in LayoutRestController (templateSlug required
 * precisely when source is 'library') so a bad submission never reaches the
 * network; catalogue membership is still checked server-side only.
 */
export const newLayoutFormSchema = z
  .object({
    name: z.string().trim().min(1, __('Name is required', 'yaycheckout')),
    source: z.enum(['blank', 'library']),
    templateSlug: z.string().nullable(),
  })
  .refine((data) => data.source === 'blank' || !!data.templateSlug, {
    message: __('Pick a template', 'yaycheckout'),
    path: ['templateSlug'],
  });

export type NewLayoutFormValues = z.infer<typeof newLayoutFormSchema>;
