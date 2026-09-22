import { __ } from '@wordpress/i18n';

import { api } from '@/lib/api/api';
import { parseLayout } from '@/lib/api/layout.api';
import type { Layout } from '@/lib/schema/layout.schema';
import {
  importReviewSchema,
  layoutExportWireSchema,
  type ImportReview,
  type LayoutExportEnvelope,
  type LayoutExportFile,
} from '@/lib/schema/tools.schema';

// Export writes the wire envelope verbatim, so there is no camelCase projection: the file must re-import as-is.

/** PHP names the file in Content-Disposition; a missing or malformed header falls back to a generic name rather than failing the download. */
function filenameFromHeaders(headers: Headers): string {
  return /filename="([^"]+)"/.exec(headers.get('content-disposition') ?? '')?.[1] ?? 'yaycheckout-layout.json';
}

export async function exportLayout(id: number): Promise<LayoutExportFile> {
  const response = await api.get(`layouts/${id}/export`);
  const wire = layoutExportWireSchema.parse(await response.json());

  return {
    filename: filenameFromHeaders(response.headers),
    json: JSON.stringify(wire, null, 2),
  };
}

/** Client-side gate before any request: not JSON, or JSON that is not an export envelope, never reaches PHP. */
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

  return parsed.data;
}

export async function validateImport(file: LayoutExportEnvelope): Promise<ImportReview> {
  return importReviewSchema.parse(await api.post('layouts/import/validate', { json: { file } }).json());
}

export async function importLayout(file: LayoutExportEnvelope): Promise<Layout> {
  return parseLayout(await api.post('layouts/import', { json: { file } }).json());
}
