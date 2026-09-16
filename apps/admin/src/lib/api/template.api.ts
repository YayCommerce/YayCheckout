import { api } from '@/lib/api/api';
import { isPro } from '@/lib/plan';
import { templateLibrarySchema } from '@/lib/schema/template.schema';

export async function getTemplateLibrary() {
  const plan = isPro ? 'pro' : 'free';
  return templateLibrarySchema.parse(await api.get('layouts/templates', { searchParams: { plan } }).json());
}
