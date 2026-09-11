import { api } from '@/lib/api/api';
import { layoutsPageDataSchema } from '@/lib/schema/layout.schema';

export async function getLayoutsPage(caseKey: string) {
  return layoutsPageDataSchema.parse(await api.get('layouts', { searchParams: { case: caseKey } }).json());
}
