import { api } from '@/lib/api/api';
import { plan } from '@/lib/plan';
import {
  templateLibraryWireSchema,
  type Template,
  type TemplateLibrary,
  type TemplateWire,
} from '@/lib/schema/template.schema';

function templateFromWire(wire: TemplateWire): Template {
  return {
    slug: wire.slug,
    name: wire.name,
    categories: wire.categories,
    locked: wire.locked,
    available: wire.available,
    previewUrl: wire.preview_url,
  };
}

export async function getTemplateLibrary(): Promise<TemplateLibrary> {
  const wire = templateLibraryWireSchema.parse(await api.get('layouts/templates', { searchParams: { plan } }).json());
  return { templates: wire.templates.map(templateFromWire) };
}
