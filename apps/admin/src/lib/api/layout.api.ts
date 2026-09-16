import { api } from '@/lib/api/api';
import {
  LayoutStatus,
  layoutCountsResponseSchema,
  layoutListResponseSchema,
  layoutSchema,
} from '@/lib/schema/layout.schema';

export type LayoutListParams = {
  status?: LayoutStatus[];
  search?: string;
  page?: number;
};

export async function getLayouts(params: LayoutListParams = {}) {
  const searchParams: Record<string, string> = {};
  if (params.status?.length) searchParams.status = params.status.join(',');
  if (params.search) searchParams.search = params.search;
  if (params.page != null) searchParams.page = String(params.page);

  return layoutListResponseSchema.parse(
    await api.get('layouts', Object.keys(searchParams).length ? { searchParams } : undefined).json(),
  );
}

export async function getLayoutCounts() {
  return layoutCountsResponseSchema.parse(await api.get('layouts/counts').json()).counts;
}

export async function getLayout(id: number) {
  return layoutSchema.parse(await api.get(`layouts/${id}`).json());
}

export async function publishLayout(id: number) {
  return layoutSchema.parse(await api.post(`layouts/${id}/publish`).json());
}

export type CreateLayoutDraftPayload =
  { name: string; source: 'blank' } | { name: string; source: 'library'; templateSlug: string };

export async function createLayoutDraft(payload: CreateLayoutDraftPayload) {
  return layoutSchema.parse(await api.post('layouts', { json: payload }).json());
}
