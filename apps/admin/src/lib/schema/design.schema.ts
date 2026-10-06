import { z } from 'zod';
import { __ } from '@wordpress/i18n';

/** Mirrors Design::FONT_FAMILIES in PHP: slug stored in tokens → CSS font-family name; '' is the theme's own font. */
export const FONT_FAMILIES = {
  '': '',
  inter: 'Inter',
  roboto: 'Roboto',
  'open-sans': 'Open Sans',
  lato: 'Lato',
  montserrat: 'Montserrat',
  poppins: 'Poppins',
  nunito: 'Nunito',
  'source-sans-3': 'Source Sans 3',
  manrope: 'Manrope',
  merriweather: 'Merriweather',
  'playfair-display': 'Playfair Display',
  'roboto-slab': 'Roboto Slab',
} as const;
export type FontFamily = keyof typeof FONT_FAMILIES;

export const FONT_FAMILY_KEYS = Object.keys(FONT_FAMILIES) as [FontFamily, ...FontFamily[]];

/** Mirrors Design::SPACING_SCALES in PHP. */
export const SPACING_SCALE_OPTIONS = [1, 1.5, 2, 2.5, 3, 3.5, 4] as const;
export type SpacingScale = (typeof SPACING_SCALE_OPTIONS)[number];

// z.enum is string-only.
const spacingScaleSchema = z.custom<SpacingScale>(
  (value) => typeof value === 'number' && (SPACING_SCALE_OPTIONS as readonly number[]).includes(value),
  { message: 'Not an allowed spacing scale' },
);

/** Mirrors the int bounds in Design::schema() in PHP. */
export const RADIUS_RANGE = { min: 0, max: 24 } as const;
export const BASE_SIZE_RANGE = { min: 10, max: 24 } as const;

/** Mirrors Design::presets()[0]['id'] in PHP: the built-in preset every payload carries. */
export const DEFAULT_PRESET_ID = 'default';

const hexColorSchema = z.string().regex(/^#[0-9A-Fa-f]{6}$/);
const radiusSchema = z.number().int().min(RADIUS_RANGE.min).max(RADIUS_RANGE.max);
const baseSizeSchema = z.number().int().min(BASE_SIZE_RANGE.min).max(BASE_SIZE_RANGE.max);
const buttonStyleSchema = z.enum(['fill', 'outline']);
const inputStyleSchema = z.enum(['boxed', 'underline']);
const inputSizeSchema = z.enum(['small', 'medium', 'large']);

/** Mirrors Design::CUSTOM_CSS_MAX_BYTES in PHP. */
export const CUSTOM_CSS_MAX_BYTES = 50000;

// Wire schemas: the PHP shape, exactly.

export const designTokensWireSchema = z
  .object({
    colours: z
      .object({
        enabled: z.boolean(),
        primary: hexColorSchema,
        text: hexColorSchema,
        background: hexColorSchema,
        border: hexColorSchema,
      })
      .strict(),
    typography: z
      .object({
        enabled: z.boolean(),
        font_family: z.enum(FONT_FAMILY_KEYS),
        base_size: baseSizeSchema,
      })
      .strict(),
    spacing: z.object({ enabled: z.boolean(), scale: spacingScaleSchema }).strict(),
    buttons: z.object({ enabled: z.boolean(), style: buttonStyleSchema, radius: radiusSchema }).strict(),
    inputs: z
      .object({ enabled: z.boolean(), style: inputStyleSchema, size: inputSizeSchema, radius: radiusSchema })
      .strict(),
  })
  .strict();

export const designPresetWireSchema = z
  .object({ id: z.string(), name: z.string(), builtin: z.boolean(), tokens: designTokensWireSchema })
  .strict();

export const designResponseWireSchema = z
  .object({
    tokens: designTokensWireSchema,
    preset_id: z.string().nullable(),
    custom_css: z.string(),
    updated_at: z.string().nullable(),
    presets: z.array(designPresetWireSchema),
  })
  .strict();

export type DesignTokensWire = z.infer<typeof designTokensWireSchema>;
export type DesignPresetWire = z.infer<typeof designPresetWireSchema>;
export type DesignResponseWire = z.infer<typeof designResponseWireSchema>;

// App types, camelCase. Only typography differs from the wire shape.
// designFormSchema doubles as the react-hook-form resolver.

export const designTokensSchema = z.object({
  colours: designTokensWireSchema.shape.colours,
  typography: z.object({
    enabled: z.boolean(),
    fontFamily: z.enum(FONT_FAMILY_KEYS),
    baseSize: baseSizeSchema,
  }),
  spacing: designTokensWireSchema.shape.spacing,
  buttons: designTokensWireSchema.shape.buttons,
  inputs: designTokensWireSchema.shape.inputs,
});

/** What the editor edits; which preset the tokens came from is page state, not a field. */
export const designFormSchema = z.object({
  tokens: designTokensSchema,
  customCss: z.string().max(CUSTOM_CSS_MAX_BYTES),
});

export type DesignTokens = z.infer<typeof designTokensSchema>;
export type TokenSection = keyof DesignTokens;

export const BUTTON_STYLE_LABELS: Record<DesignTokens['buttons']['style'], string> = {
  fill: __('Fill', 'yaycheckout'),
  outline: __('Outline', 'yaycheckout'),
};
export type DesignForm = z.infer<typeof designFormSchema>;

export type DesignPreset = { id: string; name: string; builtin: boolean; tokens: DesignTokens };

/** The saved design plus what the editor edits against. */
export type DesignResponse = {
  tokens: DesignTokens;
  presetId: string | null;
  customCss: string;
  updatedAt: string | null;
  presets: DesignPreset[];
  /** Tokens of the built-in Default preset: what "Reset to theme" restores. */
  defaultTokens: DesignTokens;
};
