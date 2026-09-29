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

// Export writes the wire envelope verbatim, so there is no camelCase projection: the file must re-import as-is.

export async function exportLayout(id: number): Promise<LayoutExportFile> {
  const response = await api.get(`layouts/${id}/export`);
  const wire = layoutExportWireSchema.parse(await response.json());

  return {
    filename: filenameFromHeaders(response.headers),
    json: JSON.stringify(wire, null, 2),
  };
}

/**
 * What the Source step shows. PHP makes the final call on import (a missing Checkout block);
 * this is only what can be read from the file itself.
 */
export function reviewExportFile(envelope: LayoutExportEnvelope): ImportReview {
  const layout = envelope.layouts[0];

  const rules = layout.rules ?? [];

  return {
    title: layout.title,
    // Mirrors Layout::block_count(): our blocks only, one `<!-- wp:yaycheckout/` each.
    blocks: layout.content.split('<!-- wp:yaycheckout/').length - 1,
    // Shape only, mirroring LayoutImportReview::rules_from_file(): PHP makes the final call on import.
    ruleCounts:
      Array.isArray(rules) && rules.every((group) => Array.isArray(group) && group.length > 0)
        ? { groups: rules.length, conditions: rules.flat().length }
        : null,
  };
}

/** Client-side gate before any request, mirroring LayoutImportReview::from_envelope(): not JSON, or JSON that is not an export envelope, never reaches PHP. */
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
