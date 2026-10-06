import { z } from 'zod';

import { layoutWireSchema, type Layout } from '@/lib/schema/layout.schema';

// Wire schema: the PHP shape of GET /layouts/{id}/export, exactly.

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
          // Unchecked: PHP drops rules it cannot read and still imports the layout.
          rules: z.unknown().optional(),
        })
        .strict(),
    ),
  })
  .strict();

/** A downloadable export and the name the browser saves it under. */
export interface LayoutExportFile {
  filename: string;
  /** The wire envelope verbatim, so the file re-imports without a camelCase round trip. */
  json: string;
}

/** The parsed export file. Sent back to PHP verbatim, so it keeps its wire spelling. */
export type LayoutExportEnvelope = z.infer<typeof layoutExportWireSchema>;

/** What the Source step shows about a picked file, read from the file without a request. */
export interface ImportReview {
  title: string;
  blocks: number;
  /** The file's assignment rules; groups 0 when it has none, null when they are not groups of conditions. */
  ruleCounts: { groups: number; conditions: number } | null;
}

/** POST /layouts/import: the new draft (Layout::to_array()) plus what became of the file's rules. */
export const importResponseWireSchema = layoutWireSchema.extend({
  rules_import: z
    .object({
      /** 'dropped': this version could not read them, so the draft has none. */
      status: z.enum(['imported', 'dropped', 'none']),
    })
    .strict(),
});

export type RulesImport = z.infer<typeof importResponseWireSchema>['rules_import'];

export interface ImportResult {
  layout: Layout;
  rulesImport: RulesImport;
}
