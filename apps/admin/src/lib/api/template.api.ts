import { api } from '@/lib/api/api';
import { plan } from '@/lib/plan';
import { templateLibrarySchema } from '@/lib/schema/template.schema';

export async function getTemplateLibrary() {
  return templateLibrarySchema.parse(await api.get('layouts/templates', { searchParams: { plan } }).json());
}
