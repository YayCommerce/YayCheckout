import { z } from 'zod';

// Wire schema: the PHP shape of GET /layouts/{id}/export, exactly. Only lib/api/tools.api.ts reads it.

/** Mirrors Layout::EXPORT_FORMAT in PHP. */
export const LAYOUT_EXPORT_FORMAT = 'yaycheckout-layout';

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
        })
        .strict(),
    ),
  })
  .strict();

export type LayoutExportWire = z.infer<typeof layoutExportWireSchema>;

/** A downloadable export: the envelope as the merchant receives it, plus the name the browser saves it under. */
export interface LayoutExportFile {
  filename: string;
  /** The wire envelope verbatim, so the file re-imports without a camelCase round trip. */
  json: string;
}
