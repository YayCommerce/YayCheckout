import { z } from 'zod';

// Wire schemas: the PHP shape of GET /compatibility, exactly. Only lib/api/compatibility.api.ts reads them.

/** Mirrors CompatibilityCatalogue::GATEWAY_STATUSES / PLUGIN_STATUSES in PHP. */
const gatewayStatusWireSchema = z.enum(['verified', 'community', 'unverified', 'block-only']);
const pluginStatusWireSchema = z.enum(['verified', 'community', 'unverified']);
/** Mirrors CheckoutHookScanHelper::HOOKS buckets. */
const touchWireSchema = z.enum(['fields', 'templates', 'gateways', 'order_review', 'submission']);

export const compatibilityWireSchema = z
  .object({
    store: z
      .object({
        store_type: z.enum(['classic', 'block']),
        woocommerce_version: z.string(),
        wordpress_version: z.string(),
        theme: z
          .object({
            name: z.string(),
            version: z.string(),
            is_block_theme: z.boolean(),
          })
          .strict(),
        safe_mode: z.boolean(),
      })
      .strict(),
    gateways: z.array(
      z
        .object({
          id: z.string(),
          title: z.string(),
          status: gatewayStatusWireSchema,
          express_pay: z.string().nullable(),
          notes: z.string().nullable(),
          docs_url: z.string().url().nullable(),
        })
        .strict(),
    ),
    plugins: z.array(
      z
        .object({
          slug: z.string(),
          name: z.string(),
          version: z.string(),
          kind: z.enum(['plugin', 'mu-plugin', 'theme']),
          touches: z.array(touchWireSchema),
          status: pluginStatusWireSchema,
          notes: z.string().nullable(),
          docs_url: z.string().url().nullable(),
        })
        .strict(),
    ),
    catalogue: z
      .object({
        source: z.enum(['remote', 'bundled']),
        fetched_at: z.string().nullable(),
      })
      .strict(),
  })
  .strict();

export type CompatibilityWire = z.infer<typeof compatibilityWireSchema>;

// App types, camelCase.

export type GatewayStatus = z.infer<typeof gatewayStatusWireSchema>;
export type PluginStatus = z.infer<typeof pluginStatusWireSchema>;
export type CheckoutTouch = z.infer<typeof touchWireSchema>;
export type OwnerKind = 'plugin' | 'mu-plugin' | 'theme';

export type StoreEnvironment = {
  storeType: 'classic' | 'block';
  woocommerceVersion: string;
  wordpressVersion: string;
  theme: { name: string; version: string; isBlockTheme: boolean };
  safeMode: boolean;
};

export type GatewayRow = {
  id: string;
  title: string;
  status: GatewayStatus;
  expressPay: string | null;
  notes: string | null;
  docsUrl: string | null;
};

export type PluginRow = {
  slug: string;
  name: string;
  version: string;
  kind: OwnerKind;
  touches: CheckoutTouch[];
  status: PluginStatus;
  notes: string | null;
  docsUrl: string | null;
};

export type CompatibilityReport = {
  store: StoreEnvironment;
  gateways: GatewayRow[];
  plugins: PluginRow[];
  catalogue: {
    source: 'remote' | 'bundled';
    /** ISO 8601 UTC; null on the bundled list. */
    fetchedAt: string | null;
  };
};
