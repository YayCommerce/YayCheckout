import { api } from '@/lib/api/api';
import { Layout, layoutListSchema, layoutSchema } from '@/lib/schema/layout.schema';

export async function getLayouts() {
  return layoutListSchema.parse(await api.get('layouts').json());
}

/**
 * Copies a layout's draft to its published snapshot. Returns the updated
 * layout, not the list.
 */
export async function publishLayout(id: Layout['id']) {
  return layoutSchema.parse(await api.post(`layouts/${id}/publish`).json());
}
