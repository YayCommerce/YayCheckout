import { z } from 'zod';
import { __ } from '@wordpress/i18n';

/** Mirrors LayoutStatus::all() in PHP: WordPress post_status values, not friendlier names. */
export const layoutStatusSchema = z.enum(['draft', 'pending', 'future', 'publish', 'trash']);

/** Mirrors LayoutStatus::listed(). */
export const LISTED_LAYOUT_STATUSES = layoutStatusSchema.options.filter((status) => status !== 'trash');

// Wire schemas: the PHP shape, exactly. .strict() makes a renamed PHP key fail loudly instead of parsing to undefined.

/**
 * Assignment rules as PHP sends them: groups ORed, conditions ANDed.
 * Loose on type and operator: the server validated them on save, and the drawer owns their vocabulary.
 */
const rulesWireSchema = z.array(
  z.array(
    z.object({
      type: z.string(),
      operator: z.string(),
      value: z.union([z.array(z.union([z.string(), z.number()])), z.string(), z.number()]),
    }),
  ),
);

const layoutSummaryWireFields = {
  id: z.number(),
  title: z.string(),
  status: layoutStatusSchema,
  is_active: z.boolean(),
  blocks: z.number(),
  size: z.number(),
  /** ISO 8601 UTC while status is 'publish'; null otherwise. */
  published_at: z.string().nullable(),
  /** ISO 8601 UTC while status is 'future'; null otherwise. */
  scheduled_at: z.string().nullable(),
  /** [] when the layout has none. */
  rules: rulesWireSchema,
};

/** Collection row. Mirrors Layout::to_summary_array(): no markup. */
export const layoutSummaryWireSchema = z.object(layoutSummaryWireFields).strict();

/** Full layout. Mirrors Layout::to_array(). */
export const layoutWireSchema = z
  .object({
    ...layoutSummaryWireFields,
    content: z.string(),
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

/** GET /layouts/counts. */
export const layoutCountsResponseSchema = z
  .object({
    counts: layoutCountsSchema,
  })
  .strict();

/** POST /layouts/{id}/preview-link. */
export const previewLinkWireSchema = z
  .object({
    url: z.string(),
    /** ISO 8601 UTC. */
    expires_at: z.string(),
  })
  .strict();

export type LayoutStatus = z.infer<typeof layoutStatusSchema>;
export type LayoutCounts = z.infer<typeof layoutCountsSchema>;

export type LayoutSummaryWire = z.infer<typeof layoutSummaryWireSchema>;
export type LayoutWire = z.infer<typeof layoutWireSchema>;
export type LayoutListResponseWire = z.infer<typeof layoutListResponseWireSchema>;

/** App-side shapes, camelCase. Built from the wire types by layout.api.ts. */
export type LayoutSummary = {
  id: number;
  title: string;
  status: LayoutStatus;
  isActive: boolean;
  blocks: number;
  size: number;
  /** ISO 8601 UTC while status is 'publish'; null otherwise. */
  publishedAt: string | null;
  /** ISO 8601 UTC while status is 'future'; null otherwise. */
  scheduledAt: string | null;
  /** Groups ORed, conditions ANDed; [] means the layout applies to everyone. */
  rules: LayoutRulesWire;
};

export type Layout = LayoutSummary & {
  content: string;
};

export type LayoutListResponse = {
  items: LayoutSummary[];
  total: number;
  page: number;
  totalPages: number;
};

export type PreviewLink = {
  url: string;
  /** ISO 8601 UTC. */
  expiresAt: string;
};

/** New layout form. Mirrors LayoutRestController's template_slug rule; only the server checks the slug is in the catalogue. */
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

/** Rename form. Mirrors LayoutRestController::name_arg(). */
export const renameLayoutFormSchema = z.object({
  name: z.string().trim().min(1, __('Name is required', 'yaycheckout')),
});

export type RenameLayoutForm = z.infer<typeof renameLayoutFormSchema>;

export const layoutRulesWireSchema = z.object({
  rules: rulesWireSchema,
});

export type LayoutRulesResponse = z.infer<typeof layoutRulesWireSchema>;
export type LayoutRulesWire = LayoutRulesResponse['rules'];
