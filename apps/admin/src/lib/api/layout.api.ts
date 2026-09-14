import { api } from '@/lib/api/api';
import {
  Layout,
  LayoutLifecycle,
  layoutListResponseSchema,
  layoutSchema,
} from '@/lib/schema/layout.schema';

export type LayoutListParams = {
  status?: LayoutLifecycle[];
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

/**
 * Copies a layout's draft to its published snapshot. Returns the updated
 * layout, not the list.
 */
export async function publishLayout(id: Layout['id']) {
  return layoutSchema.parse(await api.post(`layouts/${id}/publish`).json());
}
