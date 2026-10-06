import { z } from 'zod';

/** Minutes. Mirrors Settings::PREVIEW_LINK_LIFETIMES in PHP. */
export const PREVIEW_LINK_LIFETIME_OPTIONS = [15, 30, 60, 120, 1440] as const;
export type PreviewLinkLifetime = (typeof PREVIEW_LINK_LIFETIME_OPTIONS)[number];

// z.enum is string-only.
const previewLinkLifetimeSchema = z.custom<PreviewLinkLifetime>(
  (value) => typeof value === 'number' && (PREVIEW_LINK_LIFETIME_OPTIONS as readonly number[]).includes(value),
  { message: 'Not an allowed preview link lifetime' },
);

// Wire schemas: the PHP shape, exactly.

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

/** Mirrors IntegrationsHelper::describe() in PHP. Not strict: the yaycheckout_integrations filter may add fields. */
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

// App types, camelCase. settingsSchema doubles as the react-hook-form resolver.

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
  /** ISO 8601 UTC; null when never saved. */
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
