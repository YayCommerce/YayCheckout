import { templateListWireSchema, type Template, type TemplateWire } from '@/lib/schema/template.schema';

function templateFromWire(wire: TemplateWire): Template {
  return {
    slug: wire.slug,
    name: wire.name,
    categories: wire.categories,
    available: wire.available,
    previewUrl: wire.preview_url,
  };
}

let templates: Template[] | undefined;

/**
 * The template catalogue PHP prints into the page, for the installed plan. It can't change while the
 * page is open, so it is parsed once and never fetched.
 */
export function getTemplates(): Template[] {
  templates ??= templateListWireSchema.parse(window.yayCheckoutAdmin.templates).map(templateFromWire);
  return templates;
}
