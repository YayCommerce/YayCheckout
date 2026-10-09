import { z } from 'zod';

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

/** Mirrors the int bounds in Design::schema() in PHP. */
export const RADIUS_RANGE = { min: 0, max: 24 } as const;
export const BASE_SIZE_RANGE = { min: 10, max: 24 } as const;

/** Mirrors the hex_color rule in Design::is_valid() in PHP. */
const hexColorSchema = z.string().regex(/^#[0-9A-Fa-f]{6}$/);
const radiusSchema = z.number().int().min(RADIUS_RANGE.min).max(RADIUS_RANGE.max);
const baseSizeSchema = z.number().int().min(BASE_SIZE_RANGE.min).max(BASE_SIZE_RANGE.max);
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
    inputs: z
      .object({ enabled: z.boolean(), style: inputStyleSchema, size: inputSizeSchema, radius: radiusSchema })
      .strict(),
  })
  .strict();

export const designResponseWireSchema = z
  .object({
    tokens: designTokensWireSchema,
    custom_css: z.string(),
    updated_at: z.string().nullable(),
    defaults: designTokensWireSchema,
  })
  .strict();

export type DesignTokensWire = z.infer<typeof designTokensWireSchema>;
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
  inputs: designTokensWireSchema.shape.inputs,
});

/** What the editor edits. */
export const designFormSchema = z.object({
  tokens: designTokensSchema,
  customCss: z.string().max(CUSTOM_CSS_MAX_BYTES),
});

export type DesignTokens = z.infer<typeof designTokensSchema>;
export type TokenSection = keyof DesignTokens;

export type DesignForm = z.infer<typeof designFormSchema>;

/** The saved design plus what the editor edits against. */
export type DesignResponse = {
  tokens: DesignTokens;
  customCss: string;
  updatedAt: string | null;
  /** Schema defaults from the server: what Reset restores. */
  defaultTokens: DesignTokens;
};
