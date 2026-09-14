import { api } from '@/lib/api/api';
import {
  createLayoutDraftResponseSchema,
  LayoutStatus,
  layoutListResponseSchema,
  layoutSchema,
  layoutsPageDataSchema,
} from '@/lib/schema/layout.schema';

export async function getLayoutsPage(caseKey: string) {
  return layoutsPageDataSchema.parse(await api.get('layouts', { searchParams: { case: caseKey } }).json());
}

export type LayoutListParams = {
  status?: LayoutStatus[];
};

export async function getLayouts(params: LayoutListParams = {}) {
  const searchParams = params.status?.length ? { status: params.status.join(',') } : undefined;

  return layoutListResponseSchema.parse(
    await api.get('layouts', searchParams ? { searchParams } : undefined).json(),
  );
}

export async function getLayout(id: number) {
  return layoutSchema.parse(await api.get(`layouts/${id}`).json());
}

export type CreateLayoutDraftPayload =
  { name: string; source: 'blank' } | { name: string; source: 'library'; templateSlug: string };

export async function createLayoutDraft(payload: CreateLayoutDraftPayload) {
  return createLayoutDraftResponseSchema.parse(await api.post('layouts', { json: payload }).json());
}
