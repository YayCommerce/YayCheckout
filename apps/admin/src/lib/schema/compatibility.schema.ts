import { z } from 'zod';

// Wire schemas: the PHP shape of GET /compatibility, exactly.

/** Mirrors CompatibilityCatalogue::GATEWAY_STATUSES / PLUGIN_STATUSES in PHP. */
const gatewayStatusWireSchema = z.enum(['verified', 'community', 'unverified', 'block-only', 'conflict']);
const pluginStatusWireSchema = z.enum(['verified', 'community', 'unverified', 'conflict']);
/** Mirrors CheckoutHookScanHelper::HOOKS buckets. */
const touchWireSchema = z.enum(['fields', 'templates', 'gateways', 'order_review', 'submission']);
const storeTypeWireSchema = z.enum(['classic', 'block']);
const ownerKindWireSchema = z.enum(['plugin', 'mu-plugin', 'theme']);
const catalogueSourceWireSchema = z.enum(['remote', 'bundled']);
/** Mirrors one entry of CheckoutHealth::reported_problems(). */
const checkoutProblemWireSchema = z
  .object({
    reason: z.string(),
    detail: z.string().nullable(),
    layout_id: z.number().int().nullable(),
    layout_title: z.string().nullable(),
  })
  .strict();

export const compatibilityWireSchema = z
  .object({
    store: z
      .object({
        store_type: storeTypeWireSchema,
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
        checkout_problems: z.array(checkoutProblemWireSchema),
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
          kind: ownerKindWireSchema,
          touches: z.array(touchWireSchema),
          status: pluginStatusWireSchema,
          notes: z.string().nullable(),
          docs_url: z.string().url().nullable(),
        })
        .strict(),
    ),
    catalogue: z
      .object({
        source: catalogueSourceWireSchema,
        fetched_at: z.string().nullable(),
      })
      .strict(),
  })
  .strict();

export type CompatibilityWire = z.infer<typeof compatibilityWireSchema>;

/** GET /compatibility/problems: the store's safe mode and checkout problems, without the rest of the report. */
export const checkoutProblemsWireSchema = z
  .object({
    safe_mode: z.boolean(),
    checkout_problems: z.array(checkoutProblemWireSchema),
  })
  .strict();

export type CheckoutProblemWire = z.infer<typeof checkoutProblemWireSchema>;

// App types, camelCase.

export type GatewayStatus = z.infer<typeof gatewayStatusWireSchema>;
export type PluginStatus = z.infer<typeof pluginStatusWireSchema>;
export type CheckoutTouch = z.infer<typeof touchWireSchema>;
export type OwnerKind = z.infer<typeof ownerKindWireSchema>;

export type StoreEnvironment = {
  storeType: z.infer<typeof storeTypeWireSchema>;
  woocommerceVersion: string;
  wordpressVersion: string;
  theme: { name: string; version: string; isBlockTheme: boolean };
  safeMode: boolean;
  /** Why layouts are unsafe to serve: one per broken active layout, then a store-level one with no layout; empty when there is none. */
  checkoutProblems: CheckoutProblem[];
};

export type CheckoutProblem = {
  reason: string;
  detail: string | null;
  layoutId: number | null;
  layoutTitle: string | null;
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
    source: z.infer<typeof catalogueSourceWireSchema>;
    /** ISO 8601 UTC; null on the bundled list. */
    fetchedAt: string | null;
  };
};
