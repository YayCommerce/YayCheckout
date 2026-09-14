import { z } from 'zod';

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
    name: z.string().trim().min(1, 'Name is required'),
    source: z.enum(['blank', 'library']),
    templateSlug: z.string().nullable(),
  })
  .refine((data) => data.source === 'blank' || !!data.templateSlug, {
    message: 'Pick a template',
    path: ['templateSlug'],
  });

export type NewLayoutFormValues = z.infer<typeof newLayoutFormSchema>;
