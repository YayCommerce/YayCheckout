import { api } from '@/lib/api/api';
import { createLayoutDraftResponseSchema, layoutsPageDataSchema } from '@/lib/schema/layout.schema';

export async function getLayoutsPage(caseKey: string) {
  return layoutsPageDataSchema.parse(await api.get('layouts', { searchParams: { case: caseKey } }).json());
}

export type CreateLayoutDraftPayload =
  { name: string; source: 'blank' } | { name: string; source: 'library'; templateSlug: string };

export async function createLayoutDraft(payload: CreateLayoutDraftPayload) {
  const json =
    payload.source === 'library'
      ? { name: payload.name, source: payload.source, template_slug: payload.templateSlug }
      : payload;

  return createLayoutDraftResponseSchema.parse(await api.post('layouts', { json }).json());
}
