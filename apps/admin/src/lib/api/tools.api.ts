import { __ } from '@wordpress/i18n';

import { api } from '@/lib/api/api';
import { parseLayout } from '@/lib/api/layout.api';
import type { Layout } from '@/lib/schema/layout.schema';
import {
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

/**
 * What the Source step shows. PHP makes the final call on import (a missing Checkout block);
 * this is only what can be read from the file itself.
 */
export function reviewExportFile(envelope: LayoutExportEnvelope): ImportReview {
  const layout = envelope.layouts[0];

  // Same count as the `blocks` field of Layout::to_summary_array(): our blocks only, one `<!-- wp:yaycheckout/` each.
  return { title: layout.title, blocks: layout.content.split('<!-- wp:yaycheckout/').length - 1 };
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

  if (parsed.data.layouts.length !== 1) {
    throw new Error(__('The file must contain exactly one layout.', 'yaycheckout'));
  }

  return parsed.data;
}

export async function importLayout(file: LayoutExportEnvelope): Promise<Layout> {
  return parseLayout(await api.post('layouts/import', { json: { file } }).json());
}
