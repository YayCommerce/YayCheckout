import { z } from 'zod';

/** Mirrors Design::FONT_FAMILIES in PHP; '' is the theme's own font. */
export const FONT_FAMILY_OPTIONS = [
  '',
  'Inter',
  'Roboto',
  'Open Sans',
  'Lato',
  'Montserrat',
  'Poppins',
  'Nunito',
  'Source Sans 3',
  'Manrope',
  'Merriweather',
  'Playfair Display',
  'Roboto Slab',
] as const;

/** Mirrors Design::SPACING_SCALES in PHP. */
export const SPACING_SCALE_OPTIONS = [1, 1.5, 2, 2.5, 3, 3.5, 4] as const;
export type SpacingScale = (typeof SPACING_SCALE_OPTIONS)[number];

// z.enum is string-only.
const spacingScaleSchema = z.custom<SpacingScale>(
  (value) => typeof value === 'number' && (SPACING_SCALE_OPTIONS as readonly number[]).includes(value),
  { message: 'Not an allowed spacing scale' },
);

const hexColorSchema = z.string().regex(/^#[0-9A-Fa-f]{6}$/);
const radiusSchema = z.number().int().min(0).max(24);
const buttonStyleSchema = z.enum(['fill', 'outline']);
const inputStyleSchema = z.enum(['boxed', 'underline']);
const inputSizeSchema = z.enum(['small', 'medium', 'large']);

export const CUSTOM_CSS_MAX_BYTES = 50000;

// Wire schemas: the PHP shape, exactly. Only lib/api/design.api.ts reads them.

export const designTokensWireSchema = z
  .object({
    colours: z
      .object({ primary: hexColorSchema, text: hexColorSchema, background: hexColorSchema, border: hexColorSchema })
      .strict(),
    typography: z
      .object({
        inherit: z.boolean(),
        font_family: z.enum(FONT_FAMILY_OPTIONS),
        base_size: z.number().int().min(10).max(24),
      })
      .strict(),
    spacing: z.object({ scale: spacingScaleSchema, radius: radiusSchema }).strict(),
    buttons: z.object({ style: buttonStyleSchema, radius: radiusSchema }).strict(),
    inputs: z.object({ style: inputStyleSchema, size: inputSizeSchema, radius: radiusSchema }).strict(),
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

// App types, camelCase. designFormSchema doubles as the react-hook-form resolver.

export const designTokensSchema = z.object({
  colours: z.object({
    primary: hexColorSchema,
    text: hexColorSchema,
    background: hexColorSchema,
    border: hexColorSchema,
  }),
  typography: z.object({
    inherit: z.boolean(),
    fontFamily: z.enum(FONT_FAMILY_OPTIONS),
    baseSize: z.number().int().min(10).max(24),
  }),
  spacing: z.object({ scale: spacingScaleSchema, radius: radiusSchema }),
  buttons: z.object({ style: buttonStyleSchema, radius: radiusSchema }),
  inputs: z.object({ style: inputStyleSchema, size: inputSizeSchema, radius: radiusSchema }),
});

export const designFormSchema = z.object({
  tokens: designTokensSchema,
  presetId: z.string().nullable(),
  customCss: z.string().max(CUSTOM_CSS_MAX_BYTES),
});

export type DesignTokens = z.infer<typeof designTokensSchema>;
export type TokenSection = keyof DesignTokens;
export type DesignForm = z.infer<typeof designFormSchema>;

export type DesignPreset = { id: string; name: string; builtin: boolean; tokens: DesignTokens };

/** The saved design plus what the editor edits against. */
export type DesignResponse = {
  tokens: DesignTokens;
  presetId: string | null;
  customCss: string;
  updatedAt: string | null;
  presets: DesignPreset[];
  /** Tokens of the built-in Default preset: what "Reset to theme" restores and "(from theme)" compares to. */
  defaultTokens: DesignTokens;
};
