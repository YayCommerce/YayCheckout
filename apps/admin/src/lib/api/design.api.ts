import { api } from '@/lib/api/api';
import {
  DEFAULT_PRESET_ID,
  DesignForm,
  DesignPreset,
  DesignPresetWire,
  DesignResponse,
  DesignResponseWire,
  designResponseWireSchema,
  DesignTokens,
  DesignTokensWire,
} from '@/lib/schema/design.schema';

// The only place wire snake_case meets app camelCase.

export function tokensFromWire(wire: DesignTokensWire): DesignTokens {
  return {
    colours: wire.colours,
    typography: {
      enabled: wire.typography.enabled,
      fontFamily: wire.typography.font_family,
      baseSize: wire.typography.base_size,
    },
    spacing: wire.spacing,
    buttons: wire.buttons,
    inputs: wire.inputs,
  };
}

export function tokensToWire(tokens: DesignTokens): DesignTokensWire {
  return {
    colours: tokens.colours,
    typography: {
      enabled: tokens.typography.enabled,
      font_family: tokens.typography.fontFamily,
      base_size: tokens.typography.baseSize,
    },
    spacing: tokens.spacing,
    buttons: tokens.buttons,
    inputs: tokens.inputs,
  };
}

function presetFromWire(wire: DesignPresetWire): DesignPreset {
  return { id: wire.id, name: wire.name, builtin: wire.builtin, tokens: tokensFromWire(wire.tokens) };
}

export function designResponseFromWire(wire: DesignResponseWire): DesignResponse {
  const presets = wire.presets.map(presetFromWire);
  const defaultPreset = presets.find((preset) => preset.id === DEFAULT_PRESET_ID);
  if (!defaultPreset) throw new Error('Design payload has no Default preset');

  return {
    tokens: tokensFromWire(wire.tokens),
    presetId: wire.preset_id,
    customCss: wire.custom_css,
    updatedAt: wire.updated_at,
    presets,
    defaultTokens: defaultPreset.tokens,
  };
}

export async function getDesign(): Promise<DesignResponse> {
  return designResponseFromWire(designResponseWireSchema.parse(await api.get('design').json()));
}

export async function postDesign(form: DesignForm, presetId: string | null): Promise<DesignResponse> {
  return designResponseFromWire(
    designResponseWireSchema.parse(
      await api
        .post('design', {
          json: { tokens: tokensToWire(form.tokens), preset_id: presetId, custom_css: form.customCss },
        })
        .json(),
    ),
  );
}

export async function postDesignPreset(name: string, tokens: DesignTokens): Promise<DesignResponse> {
  return designResponseFromWire(
    designResponseWireSchema.parse(
      await api.post('design/presets', { json: { name, tokens: tokensToWire(tokens) } }).json(),
    ),
  );
}

export async function deleteDesignPreset(id: string): Promise<DesignResponse> {
  return designResponseFromWire(designResponseWireSchema.parse(await api.delete(`design/presets/${id}`).json()));
}
