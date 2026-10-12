import { api } from '@/lib/api/api';
import { parseLayout } from '@/lib/api/layout.api';
import { setupResponseWireSchema, type SetupResult } from '@/lib/schema/setup.schema';

export type CompleteSetupPayload = {
  templateSlug: string;
  /** A re-run from Settings: always adds a draft, though setup is done. */
  rerun?: boolean;
};

export async function completeSetup({ templateSlug, rerun = false }: CompleteSetupPayload): Promise<SetupResult> {
  const { layout } = setupResponseWireSchema.parse(
    await api.post('setup', { json: { template_slug: templateSlug, rerun } }).json(),
  );

  return { layout: parseLayout(layout) };
}
