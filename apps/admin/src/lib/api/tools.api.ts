import { filenameFromHeaders } from '@blocks-shared/export-file';
import { __ } from '@wordpress/i18n';

import { api } from '@/lib/api/api';
import { parseLayout } from '@/lib/api/layout.api';
import {
  importResponseWireSchema,
  layoutExportWireSchema,
  type ImportResult,
  type ImportReview,
  type LayoutExportEnvelope,
  type LayoutExportFile,
} from '@/lib/schema/tools.schema';

// No camelCase projection: the exported file must re-import as-is.

export async function exportLayout(id: number): Promise<LayoutExportFile> {
  const response = await api.get(`layouts/${id}/export`);
  const wire = layoutExportWireSchema.parse(await response.json());

  return {
    filename: filenameFromHeaders(response.headers),
    json: JSON.stringify(wire, null, 2),
  };
}

/** What the Source step shows, read from the file alone; PHP makes the final call on import. */
export function reviewExportFile(envelope: LayoutExportEnvelope): ImportReview {
  const layout = envelope.layouts[0];

  // PHP decodes `{}` to [] and reads both as no rules.
  const rules =
    layout.rules == null || (typeof layout.rules === 'object' && Object.keys(layout.rules).length === 0)
      ? []
      : layout.rules;

  return {
    title: layout.title,
    // Mirrors Layout::block_count(): our blocks only, one `<!-- wp:yaycheckout/` each.
    blocks: layout.content.split('<!-- wp:yaycheckout/').length - 1,
    // Shape only, mirroring LayoutImportReview::rules_from_file().
    ruleCounts:
      Array.isArray(rules) && rules.every((group) => Array.isArray(group) && group.length > 0)
        ? { groups: rules.length, conditions: rules.flat().length }
        : null,
  };
}

/** Refuses a file that is not one export envelope before any request. Mirrors the envelope checks in LayoutImportReview::from_envelope(). */
export function parseExportFile(text: string): LayoutExportEnvelope {
  let json: unknown;
  try {
    json = JSON.parse(text);
  } catch {
    throw new Error(__('This file is not valid JSON.', 'yaycheckout'));
  }

  const parsed = layoutExportWireSchema.safeParse(json);
  if (!parsed.success) {
    throw new Error(__('This file is not a YayCheckout layout export.', 'yaycheckout'));
  }

  if (parsed.data.layouts.length !== 1) {
    throw new Error(__('The file must contain exactly one layout.', 'yaycheckout'));
  }

  return parsed.data;
}

export async function importLayout(file: LayoutExportEnvelope): Promise<ImportResult> {
  const { rules_import, ...layout } = importResponseWireSchema.parse(
    await api.post('layouts/import', { json: { file } }).json(),
  );

  return { layout: parseLayout(layout), rulesImport: rules_import };
}
