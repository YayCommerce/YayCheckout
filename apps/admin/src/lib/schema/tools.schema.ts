import { z } from 'zod';

import { layoutRulesWireSchema } from '@/lib/schema/layout.schema';

// Wire schema: the PHP shape of GET /layouts/{id}/export, exactly. Only lib/api/tools.api.ts reads it.

/** Mirrors Layout::EXPORT_FORMAT in PHP. */
const LAYOUT_EXPORT_FORMAT = 'yaycheckout-layout';

export const layoutExportWireSchema = z
  .object({
    format: z.literal(LAYOUT_EXPORT_FORMAT),
    plugin_version: z.string(),
    exported_at: z.string(),
    layouts: z.array(
      z
        .object({
          title: z.string(),
          content: z.string(),
          rules: layoutRulesWireSchema.shape.rules.optional(),
        })
        .strict(),
    ),
  })
  .strict();

/** A downloadable export: the envelope as the merchant receives it, plus the name the browser saves it under. */
export interface LayoutExportFile {
  filename: string;
  /** The wire envelope verbatim, so the file re-imports without a camelCase round trip. */
  json: string;
}

/** The parsed export file. Sent back to PHP verbatim, so it keeps its wire spelling. */
export type LayoutExportEnvelope = z.infer<typeof layoutExportWireSchema>;

/** What the Source step shows about a picked file. Read from the envelope itself: only the import call reaches PHP. */
export interface ImportReview {
  title: string;
  blocks: number;
}
