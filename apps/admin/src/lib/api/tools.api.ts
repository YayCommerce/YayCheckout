import { api } from '@/lib/api/api';
import { layoutExportWireSchema, type LayoutExportFile } from '@/lib/schema/tools.schema';

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
