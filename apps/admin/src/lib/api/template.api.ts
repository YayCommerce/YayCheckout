import { api } from '@/lib/api/api';
import { templateLibrarySchema } from '@/lib/schema/template.schema';

export async function getTemplateLibrary(plan: 'free' | 'pro') {
  return templateLibrarySchema.parse(await api.get('layouts/templates', { searchParams: { plan } }).json());
}
