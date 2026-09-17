import { z } from 'zod';
import { __ } from '@wordpress/i18n';

// PHP encodes an empty associative array as `[]`, not `{}`.
const phpMap = z.preprocess(
  (value) => (Array.isArray(value) && value.length === 0 ? {} : value),
  z.record(z.string(), z.unknown()),
);

/** Mirrors DerivedCacheBuilder's output; empty until first published. */
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

/** Mirrors LayoutStatus::listed(). */
export const LISTED_LAYOUT_STATUSES = layoutStatusSchema.options.filter((status) => status !== 'archived');

// Wire schemas: the PHP shape, exactly. Only lib/api/layout.api.ts reads them.

const layoutSummaryWireFields = {
  id: z.number(),
  title: z.string(),
  status: layoutStatusSchema,
  has_unpublished_changes: z.boolean(),
  is_active: z.boolean(),
  /** ISO 8601 UTC; null until first published. */
  published_at: z.string().nullable(),
  /** ISO 8601 UTC while status is 'scheduled'; null otherwise. */
  scheduled_at: z.string().nullable(),
};

// .strict() so a renamed PHP key fails loudly instead of parsing to undefined.
export const layoutSummaryWireSchema = z.object(layoutSummaryWireFields).strict();

export const layoutWireSchema = z
  .object({
    ...layoutSummaryWireFields,
    draft: z.string(),
    /** Empty until first published. */
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

// App-side shapes, camelCase. Built by layout.api.ts; what pages and hooks consume.
export type LayoutSummary = {
  id: number;
  title: string;
  status: LayoutStatus;
  hasUnpublishedChanges: boolean;
  isActive: boolean;
  /** ISO 8601 UTC; null until first published. Format with lib/helpers/date.helper. */
  publishedAt: string | null;
  /** ISO 8601 UTC while status is 'scheduled'; null otherwise. */
  scheduledAt: string | null;
};

export type Layout = LayoutSummary & {
  draft: string;
  /** Empty until first published. */
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

/** Mirrors LayoutRestController's template_slug rule; catalogue membership is server-side only. */
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
