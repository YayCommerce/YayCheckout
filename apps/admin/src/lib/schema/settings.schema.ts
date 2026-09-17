import { z } from 'zod';

/** Mirrors Models\Settings::PREVIEW_LINK_LIFETIMES in PHP — minutes a shared preview URL stays valid. */
export const PREVIEW_LINK_LIFETIME_OPTIONS = [15, 30, 60, 120, 1440] as const;
export type PreviewLinkLifetime = (typeof PREVIEW_LINK_LIFETIME_OPTIONS)[number];

/** z.enum is string-only, so the numeric enum checks membership in the list. Shared by the wire and app schemas. */
const previewLinkLifetimeSchema = z.custom<PreviewLinkLifetime>(
  (value) => typeof value === 'number' && (PREVIEW_LINK_LIFETIME_OPTIONS as readonly number[]).includes(value),
  { message: 'Not an allowed preview link lifetime' },
);

/*
 * Wire schemas assert the PHP shape exactly — snake_case, strict, mirroring
 * Models\Settings::schema() and to_array(). Only
 * lib/api/settings.api.ts parses with these; everything else uses the
 * camelCase app types below. A renamed PHP key fails here, loudly.
 */

export const settingsWireSchema = z
  .object({
    general: z
      .object({
        safe_mode: z.boolean(),
        share_usage_data: z.boolean(),
        delete_data_on_uninstall: z.boolean(),
      })
      .strict(),
    advanced: z
      .object({
        preview_link_lifetime: previewLinkLifetimeSchema,
        force_enqueue_checkout_scripts: z.boolean(),
      })
      .strict(),
  })
  .strict();

export const settingsResponseWireSchema = z
  .object({
    settings: settingsWireSchema,
    updated_at: z.string().nullable(),
  })
  .strict();

/**
 * Mirrors IntegrationsHelper::describe() in PHP. Not strict: the list passes
 * through the yaycheckout_integrations filter, so a companion may add fields
 * of its own — unknown keys are stripped, not rejected.
 */
export const integrationWireSchema = z.object({
  slug: z.string(),
  name: z.string(),
  description: z.string(),
  detected: z.boolean(),
  version: z.string().nullable(),
  active: z.boolean(),
  learn_more_url: z.string().url().nullable(),
});

export type SettingsWire = z.infer<typeof settingsWireSchema>;
export type SettingsResponseWire = z.infer<typeof settingsResponseWireSchema>;
export type IntegrationWire = z.infer<typeof integrationWireSchema>;

/* App types — camelCase. settingsSchema doubles as the react-hook-form resolver schema. */

export const settingsSchema = z.object({
  general: z.object({
    safeMode: z.boolean(),
    shareUsageData: z.boolean(),
    deleteDataOnUninstall: z.boolean(),
  }),
  advanced: z.object({
    previewLinkLifetime: previewLinkLifetimeSchema,
    forceEnqueueCheckoutScripts: z.boolean(),
  }),
});

export type Settings = z.infer<typeof settingsSchema>;

export type SettingsResponse = {
  settings: Settings;
  /** ISO 8601 of the last save, or null when settings were never saved. */
  updatedAt: string | null;
};

export type Integration = {
  slug: string;
  name: string;
  description: string;
  detected: boolean;
  version: string | null;
  active: boolean;
  learnMoreUrl: string | null;
};
