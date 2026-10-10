import { api } from '@/lib/api/api';
import {
  DesignForm,
  DesignResponse,
  DesignResponseWire,
  designResponseWireSchema,
  DesignTokens,
  DesignTokensWire,
} from '@/lib/schema/design.schema';

export function tokensFromWire(wire: DesignTokensWire): DesignTokens {
  return {
    colours: wire.colours,
    typography: {
      enabled: wire.typography.enabled,
      fontFamily: wire.typography.font_family,
      baseSize: wire.typography.base_size,
    },
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
    inputs: tokens.inputs,
  };
}

export function designResponseFromWire(wire: DesignResponseWire): DesignResponse {
  return {
    tokens: tokensFromWire(wire.tokens),
    customCss: wire.custom_css,
    updatedAt: wire.updated_at,
    defaultTokens: tokensFromWire(wire.defaults),
  };
}

export async function getDesign(): Promise<DesignResponse> {
  return designResponseFromWire(designResponseWireSchema.parse(await api.get('design').json()));
}

/** The applied-design body POST /design sends. */
function designToWire(form: DesignForm) {
  return { tokens: tokensToWire(form.tokens), custom_css: form.customCss };
}

export async function postDesign(form: DesignForm): Promise<DesignResponse> {
  return designResponseFromWire(
    designResponseWireSchema.parse(await api.post('design', { json: designToWire(form) }).json()),
  );
}
