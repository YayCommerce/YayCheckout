import { api } from '@/lib/api/api';
import { designResponseFromWire, tokensToWire } from '@/lib/api/design.api';
import { parseLayout } from '@/lib/api/layout.api';
import type { DesignForm } from '@/lib/schema/design.schema';
import { setupResponseWireSchema, type SetupResult } from '@/lib/schema/setup.schema';

export type CompleteSetupPayload = {
  templateSlug: string;
  form: DesignForm;
  presetId: string | null;
};

export async function completeSetup({ templateSlug, form, presetId }: CompleteSetupPayload): Promise<SetupResult> {
  const { layout, design } = setupResponseWireSchema.parse(
    await api
      .post('setup', {
        json: {
          template_slug: templateSlug,
          design: { tokens: tokensToWire(form.tokens), preset_id: presetId, custom_css: form.customCss },
        },
      })
      .json(),
  );

  return { layout: parseLayout(layout), design: designResponseFromWire(design) };
}
