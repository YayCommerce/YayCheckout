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

export const layoutLifecycleSchema = z.enum(['draft', 'published', 'archived']);

const layoutSummaryFields = {
  id: z.number(),
  title: z.string(),
  status: layoutLifecycleSchema,
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

export type LayoutLifecycle = z.infer<typeof layoutLifecycleSchema>;
export type LayoutSummary = z.infer<typeof layoutSummarySchema>;
export type Layout = z.infer<typeof layoutSchema>;
export type LayoutListResponse = z.infer<typeof layoutListResponseSchema>;

const layoutActionButtonSchema = z.enum(['enabled', 'disabled', 'hidden']);

const layoutRowActionsSchema = z.object({
  edit: layoutActionButtonSchema,
  /** "previewDraft" labels the button "Preview draft" instead of "Preview". */
  preview: z.enum(['preview', 'previewDraft', 'disabled', 'hidden']),
  /** Only rendered when the row's `isActive` is false — an active row shows a
   * plain "active" label instead, in every case this schema covers. */
  setActive: layoutActionButtonSchema,
  publish: z.boolean(),
  /** The "⋯" menu trigger. */
  moreMenu: z.boolean(),
  restore: z.boolean().optional(),
  /** Labeled "Delete for good" — permanent delete, archive-only. */
  delete: z.boolean().optional(),
  fixInEditor: z.boolean().optional(),
  restoreLastGood: z.boolean().optional(),
});

export const layoutRowSchema = z.object({
  id: z.number(),
  title: z.string(),
  statusLabel: z.string(),
  statusTone: z.enum(['default', 'warning']),
  isActive: z.boolean(),
  lastPublishedAt: z.string().nullable(),
  archivedAt: z.string().nullable().optional(),
  /** Plain text for the Default/catch-all row (paired with `isActive`), a
   * bordered chip otherwise — see LayoutsPage.tsx. */
  assignment: z.string().nullable().optional(),
  dimmed: z.boolean().optional(),
  /** e.g. "Setting active…" — replaces the status pill while set. */
  inFlight: z.string().nullable().optional(),
  issue: z
    .object({
      message: z.string(),
      lastGoodRevision: z.string().optional(),
    })
    .nullable()
    .optional(),
  actions: layoutRowActionsSchema,
});

const healthRowSchema = z.object({ label: z.string(), value: z.string() });

const linkSchema = z.object({ label: z.string() });

export const layoutsPageDataSchema = z.object({
  case: z.string(),
  plan: z.enum(['free', 'pro']),
  viewOnly: z.boolean(),
  archivedView: z.boolean(),
  listLocked: z.boolean(),
  counts: z.object({ active: z.number(), archived: z.number() }),
  planLimit: z.object({
    limit: z.number().nullable(),
    used: z.number(),
    reached: z.boolean(),
  }),
  search: z.object({ term: z.string(), totalShown: z.number(), total: z.number() }).nullable().optional(),
  pagination: z.object({ page: z.number(), totalPages: z.number() }).nullable().optional(),
  table: z.object({
    showAssignmentColumn: z.boolean(),
    showArchivedColumns: z.boolean(),
  }),
  layouts: z.array(layoutRowSchema),
  health: z.object({
    rows: z.array(healthRowSchema),
    note: z.string().optional(),
    link: linkSchema.nullable().optional(),
  }),
  compatibility: z.object({
    summary: z.string(),
    tone: z.enum(['default', 'warning']),
    link: linkSchema.nullable().optional(),
  }),
  asideNote: z.object({ title: z.string(), message: z.string() }).nullable().optional(),
  banner: z
    .object({
      tone: z.enum(['info', 'warning']),
      title: z.string().optional(),
      message: z.string(),
      action: linkSchema.optional(),
      link: linkSchema.optional(),
    })
    .nullable()
    .optional(),
  planPromo: z.object({ message: z.string() }).nullable().optional(),
  emptyState: z.object({ title: z.string(), message: z.string() }).nullable().optional(),
  tableFooterNote: z.string().nullable().optional(),
});

export type LayoutRow = z.infer<typeof layoutRowSchema>;
export type LayoutsPageData = z.infer<typeof layoutsPageDataSchema>;

export const createLayoutDraftResponseSchema = z.object({
  id: z.number(),
  name: z.string(),
  source: z.enum(['blank', 'library']),
  templateSlug: z.string().nullable(),
});

export type CreateLayoutDraftResponse = z.infer<typeof createLayoutDraftResponseSchema>;

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
