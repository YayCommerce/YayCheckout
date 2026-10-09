import { api } from '@/lib/api/api';
import { designResponseFromWire, designToWire } from '@/lib/api/design.api';
import { parseLayout } from '@/lib/api/layout.api';
import type { DesignForm } from '@/lib/schema/design.schema';
import { setupResponseWireSchema, type SetupResult } from '@/lib/schema/setup.schema';

export type CompleteSetupPayload = {
  templateSlug: string;
  form: DesignForm;
  /** A re-run from Settings: always adds a draft, though setup is done. */
  rerun?: boolean;
};

export async function completeSetup({ templateSlug, form, rerun = false }: CompleteSetupPayload): Promise<SetupResult> {
  const { layout, design } = setupResponseWireSchema.parse(
    await api.post('setup', { json: { template_slug: templateSlug, design: designToWire(form), rerun } }).json(),
  );

  return { layout: parseLayout(layout), design: designResponseFromWire(design) };
}
